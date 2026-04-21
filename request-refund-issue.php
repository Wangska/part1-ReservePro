<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';
require_once __DIR__ . '/config/refunds.php';

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'guest') {
    header('Location: home.php');
    exit();
}

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    header('Location: my-bookings.php?error=invalid_booking');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare("
    SELECT
        b.id,
        b.guest_id,
        b.property_id,
        b.check_in,
        b.check_out,
        b.total_price,
        b.booking_date,
        b.status,
        p.title AS property_title,
        p.city,
        p.country,
        p.cancellation_policy,
        p.host_id
    FROM bookings b
    JOIN properties p ON p.id = b.property_id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$b || (int)$b['guest_id'] !== (int)$user['id']) {
    $conn->close();
    header('Location: my-bookings.php?error=not_found');
    exit();
}

// Active request guard
$activeStmt = $conn->prepare("
    SELECT id, status, request_type
    FROM refund_requests
    WHERE booking_id = ?
      AND status IN ('pending_review','pending','approved','processing','completed')
    ORDER BY id DESC
    LIMIT 1
");
$activeStmt->bind_param('i', $bookingId);
$activeStmt->execute();
$active = $activeStmt->get_result()->fetch_assoc();
$activeStmt->close();

$elig = reservepro_issue_eligibility((string)$b['check_in']);

$conn->close();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Report an Issue - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=27.5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body.ri-page-body { background: #06090F !important; }
        body.ri-page-body::before, body.ri-page-body::after { display: none !important; }

        .ri-main { max-width: 100%; }

        /* Booking summary pills */
        .ri-summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        @media (max-width: 600px) { .ri-summary-grid { grid-template-columns: 1fr; } }
        .ri-pill {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 14px;
            padding: 12px 14px;
        }
        .ri-pill small {
            display: block;
            color: #94A3B8;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-size: 10px;
            margin-bottom: 5px;
        }
        .ri-pill strong { color: #F1F5F9; font-size: 13px; font-weight: 700; }

        /* Form card */
        .ri-card {
            background: rgba(17, 24, 39, 0.82);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.18);
        }

        /* Section divider inside card */
        .ri-section-label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748B;
            margin: 0 0 14px;
        }

        /* Alerts */
        .ri-alert, .ri-warn, .ri-success {
            border-radius: 14px;
            padding: 13px 16px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.6;
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .ri-alert { border: 1px solid rgba(239,68,68,0.28); background: rgba(239,68,68,0.09); color: #fecaca; }
        .ri-warn  { border: 1px solid rgba(234,179,8,0.28);  background: rgba(234,179,8,0.09);  color: #FDE68A; }
        .ri-alert-icon, .ri-warn-icon { flex-shrink: 0; margin-top: 2px; }

        /* Form elements */
        .ri-field { margin-bottom: 16px; }
        .ri-label {
            display: block;
            margin-bottom: 7px;
            color: #CBD5E1;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 0.01em;
        }
        .ri-label span { color: #F87171; margin-left: 2px; }
        .ri-select, .ri-textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 14px;
            border-radius: 12px;
            border: 1px solid rgba(148,163,184,0.18);
            background: rgba(255,255,255,0.05);
            color: #E2E8F0;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s ease;
            outline: none;
        }
        .ri-select:focus, .ri-textarea:focus {
            border-color: rgba(212,165,116,0.5);
            background: rgba(255,255,255,0.07);
        }
        .ri-textarea { min-height: 130px; resize: vertical; line-height: 1.6; }

        /* Photo upload grid */
        .ri-upload-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        @media (max-width: 540px) { .ri-upload-grid { grid-template-columns: 1fr; } }
        .ri-upload-slot {
            border: 1.5px dashed rgba(148,163,184,0.22);
            border-radius: 14px;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
            position: relative;
        }
        .ri-upload-slot:hover { border-color: rgba(212,165,116,0.5); background: rgba(212,165,116,0.04); }
        .ri-upload-slot input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .ri-upload-slot input[type="file"]:disabled { cursor: not-allowed; }
        .ri-upload-icon { font-size: 22px; color: #64748B; }
        .ri-upload-label { font-size: 11px; color: #94A3B8; font-weight: 700; text-align: center; }

        /* Divider */
        .ri-divider { border: none; border-top: 1px solid rgba(148,163,184,0.12); margin: 20px 0; }

        /* Actions */
        .ri-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .ri-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 18px;
            border-radius: 11px;
            border: 1px solid rgba(255,255,255,0.13);
            background: rgba(255,255,255,0.05);
            color: #E2E8F0; text-decoration: none; font-weight: 800; font-size: 13px;
            cursor: pointer; transition: background 0.2s ease, border-color 0.2s ease;
        }
        .ri-btn:hover { background: rgba(255,255,255,0.09); }
        .ri-btn-primary {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0f172a;
            border-color: transparent;
            font-weight: 900;
        }
        .ri-btn-primary:hover { background: linear-gradient(135deg, #ddb887, #c9a06e); }
        .ri-btn:disabled { opacity: 0.45; cursor: not-allowed; pointer-events: none; }

        /* Light mode */
        body.light-mode.ri-page-body { background: #F8FAFC !important; }
        body.light-mode .ri-card { background: #fff !important; border-color: #E2E8F0 !important; box-shadow: 0 8px 24px rgba(15,23,42,0.07) !important; }
        body.light-mode .ri-pill { background: #F8FAFC !important; border-color: #E2E8F0 !important; }
        body.light-mode .ri-pill small { color: #64748B !important; }
        body.light-mode .ri-pill strong { color: #0f172a !important; }
        body.light-mode .ri-label { color: #1e293b; }
        body.light-mode .ri-section-label { color: #94A3B8; }
        body.light-mode .ri-select, body.light-mode .ri-textarea { background: #F8FAFC; color: #0f172a; border-color: #E2E8F0; }
        body.light-mode .ri-select:focus, body.light-mode .ri-textarea:focus { border-color: #B8935F; background: #fff; }
        body.light-mode .ri-upload-slot { border-color: #CBD5E1; }
        body.light-mode .ri-upload-slot:hover { border-color: #B8935F; background: rgba(184,147,95,0.04); }
        body.light-mode .ri-upload-icon { color: #94A3B8; }
        body.light-mode .ri-upload-label { color: #64748B; }
        body.light-mode .ri-btn { background: #fff; color: #0f172a; border-color: #E2E8F0; }
        body.light-mode .ri-divider { border-color: #E2E8F0; }
        body.light-mode .ri-alert { background: rgba(239,68,68,0.08) !important; color: #991b1b !important; border-color: rgba(239,68,68,0.25) !important; }
        body.light-mode .ri-warn  { background: rgba(234,179,8,0.09) !important; color: #854d0e !important; border-color: rgba(234,179,8,0.3) !important; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page ri-page-body">
    <div class="host-layout">
        <!-- Sidebar -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                    <span>Profile</span>
                </a>
                <a href="my-bookings.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>My Bookings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
                <a href="home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                    <span>Home</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Guest</div>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="ri-main">

                <!-- Page Hero -->
                <div class="admin-page-hero" style="margin-bottom: 20px;">
                    <div class="admin-page-hero-content" style="flex: 1;">
                        <h1>Report an Issue</h1>
                    </div>
                    <a class="ri-btn" href="my-bookings.php" style="flex-shrink:0; align-self:center;">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                    </a>
                </div>

                <!-- Alerts -->
                <?php if ($active): ?>
                    <div class="ri-alert">
                        <i class="fa-solid fa-circle-exclamation ri-alert-icon" aria-hidden="true"></i>
                        <span>A refund request already exists for this booking (<strong><?php echo h($active['request_type']); ?></strong>) and is currently <strong><?php echo h($active['status']); ?></strong>. Please wait for the review to finish.</span>
                    </div>
                <?php endif; ?>

                <?php if (!$elig['eligible']): ?>
                    <div class="ri-alert">
                        <i class="fa-solid fa-circle-exclamation ri-alert-icon" aria-hidden="true"></i>
                        <span>This issue-based refund request is no longer eligible. It must be submitted within <strong>24 hours after check-in</strong>.</span>
                    </div>
                <?php else: ?>
                    <div class="ri-warn">
                        <i class="fa-solid fa-triangle-exclamation ri-warn-icon" aria-hidden="true"></i>
                        <span>Eligible until <strong><?php echo h($elig['deadline']); ?></strong>. Please upload clear, readable photos (not blurry or cropped).</span>
                    </div>
                <?php endif; ?>

                <!-- Booking Summary -->
                <div class="ri-summary-grid">
                    <div class="ri-pill"><small>Property</small><strong><?php echo h($b['property_title']); ?></strong></div>
                    <div class="ri-pill"><small>Location</small><strong><?php echo h(trim(($b['city'] ?? '') . ', ' . ($b['country'] ?? ''), ', ')); ?></strong></div>
                    <div class="ri-pill"><small>Check-in</small><strong><?php echo h(date('M j, Y', strtotime((string)$b['check_in']))); ?></strong></div>
                    <div class="ri-pill"><small>Check-out</small><strong><?php echo h(date('M j, Y', strtotime((string)$b['check_out']))); ?></strong></div>
                </div>

                <!-- Form Card -->
                <div class="ri-card">
                    <p class="ri-section-label">Issue Details</p>
                    <form method="post" action="submit-issue-refund.php" enctype="multipart/form-data">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">

                        <div class="ri-field">
                            <label class="ri-label" for="issue_type">Issue type</label>
                            <select id="issue_type" name="issue_type" class="ri-select ri-select-large" required <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?> >
                                <option value="">Select an issue</option>
                                <option value="dirty_room">Dirty or unclean room</option>
                                <option value="wrong_listing">Wrong listing / mismatch with description</option>
                                <option value="safety_issue">Safety or security issue</option>
                                <option value="missing_amenities">Missing amenities or features</option>
                                <option value="host_no_show">Host did not show up / cannot access property</option>
                                <option value="other">Other (please describe below)</option>
                            </select>
                        </div>

                        <div class="ri-field">
                            <label class="ri-label" for="description">Describe what happened</label>
                            <textarea id="description" name="description" class="ri-textarea" maxlength="2000" placeholder="Provide as much detail as possible…" required <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>></textarea>
                        </div>

                        <hr class="ri-divider">

                        <div class="ri-field">
                            <label class="ri-label">Evidence photos <span style="color:#64748B;font-weight:600;">(optional, up to 3)</span></label>
                            <div class="ri-upload-grid">
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                <div class="ri-upload-slot">
                                    <input type="file" id="evidence<?php echo $i; ?>" name="evidence[]" accept=".jpg,.jpeg,.png,.webp,.avif" <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>>
                                    <i class="fa-solid fa-image ri-upload-icon" aria-hidden="true"></i>
                                    <span class="ri-upload-label">Photo <?php echo $i; ?></span>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <hr class="ri-divider">

                        <div class="ri-actions">
                            <a class="ri-btn" href="messages.php"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Message host</a>
                            <button class="ri-btn ri-btn-primary" type="submit" <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>>
                                Submit report
                            </button>
                        </div>
                    </form>
                </div>

            </div><!-- /.ri-main -->
        </main>
    </div>

    <script src="assets/js/theme-toggle.js?v=26.0"></script>
</body>
</html>

