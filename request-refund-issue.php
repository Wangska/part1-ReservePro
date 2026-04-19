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
    <title>Report an issue - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=27.5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .ri-page { max-width: 900px; margin: 0 auto; padding: 24px; }
        .ri-hero {
            background: linear-gradient(135deg, #111827 0%, #1F2933 45%, #020617 100%);
            border-radius: 18px;
            padding: 24px 24px;
            display:flex;
            justify-content: space-between;
            align-items:flex-start;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 16px;
        }
        .ri-hero h1 { margin:0 0 6px; color:#fff !important; font-size: 24px; }
        .ri-hero p { margin:0; color:#E5E7EB !important; opacity:0.92; }
        .ri-card {
            background: rgba(17, 24, 39, 0.78);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.18);
        }
        .ri-row { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 720px) { .ri-row { grid-template-columns: 1fr; } }
        .ri-pill { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10); border-radius: 14px; padding: 10px 12px; }
        .ri-pill small { display:block; color:#94A3B8 !important; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; font-size: 10px; margin-bottom: 6px; }
        .ri-pill strong { color:#F1F5F9 !important; font-size: 13px; }
        label { display:block; margin-top: 12px; margin-bottom: 6px; color:#CBD5E1; font-weight: 900; font-size: 13px; }
        select, textarea, input[type="file"] {
            width:100%;
            padding: 12px 12px;
            border-radius: 12px;
            border: 1px solid rgba(148,163,184,0.18);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0;
        }
        textarea { min-height: 120px; resize: vertical; }
        .ri-alert {
            border-radius: 14px;
            padding: 12px;
            border: 1px solid rgba(239, 68, 68, 0.28);
            background: rgba(239, 68, 68, 0.10);
            color: #fecaca;
            font-weight: 800;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .ri-warn {
            border-radius: 14px;
            padding: 12px;
            border: 1px solid rgba(234,179,8,0.28);
            background: rgba(234,179,8,0.10);
            color: #FDE68A;
            font-weight: 800;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .ri-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top: 14px; justify-content:flex-end; }
        .ri-btn {
            display:inline-flex; align-items:center; gap:8px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color:#E2E8F0; text-decoration:none; font-weight: 900; font-size: 13px;
            cursor:pointer;
        }
        .ri-btn-primary { background: linear-gradient(135deg, #D4A574, #B8935F); color:#0f172a; border-color: transparent; }
        body.light-mode .ri-card { background: #fff !important; border-color:#E2E8F0 !important; }
        body.light-mode .ri-pill { background:#F8FAFC !important; border-color:#E2E8F0 !important; }
        body.light-mode .ri-pill small { color:#475569 !important; }
        body.light-mode .ri-pill strong { color:#0f172a !important; }
        body.light-mode label { color:#0f172a; }
        body.light-mode select,
        body.light-mode textarea,
        body.light-mode input[type="file"] { background:#fff; color:#0f172a; border-color:#E2E8F0; }
        body.light-mode .ri-btn { background:#fff; color:#0f172a; border-color:#E2E8F0; }
    </style>
</head>
<body class="dashboard-page">
    <div class="ri-page">
        <div class="ri-hero">
            <div>
                <h1>Report an issue</h1>
                <p>Tell us what happened. You can attach clear photos as evidence.</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a class="ri-btn" href="my-bookings.php"><i class="fa-solid fa-arrow-left"></i>Back</a>
                <div class="theme-toggle theme-toggle-home-static">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>
        </div>

        <?php if ($active): ?>
            <div class="ri-alert">
                A refund request already exists for this booking (<?php echo h($active['request_type']); ?>) and is currently <strong><?php echo h($active['status']); ?></strong>.
                Please wait for the review to finish.
            </div>
        <?php endif; ?>

        <?php if (!$elig['eligible']): ?>
            <div class="ri-alert">
                This issue-based refund request is no longer eligible. It must be submitted within <strong>24 hours after check-in</strong>.
            </div>
        <?php else: ?>
            <div class="ri-warn">
                Eligible until <strong><?php echo h($elig['deadline']); ?></strong>. Please upload clear, readable photos (not blurry/cropped).
            </div>
        <?php endif; ?>

        <div class="ri-card">
            <div class="ri-row">
                <div class="ri-pill"><small>Property</small><strong><?php echo h($b['property_title']); ?></strong></div>
                <div class="ri-pill"><small>Location</small><strong><?php echo h(($b['city'] ?? '') . ', ' . ($b['country'] ?? '')); ?></strong></div>
                <div class="ri-pill"><small>Check-in</small><strong><?php echo h((string)$b['check_in']); ?></strong></div>
                <div class="ri-pill"><small>Check-out</small><strong><?php echo h((string)$b['check_out']); ?></strong></div>
            </div>

            <form method="post" action="submit-issue-refund.php" enctype="multipart/form-data" style="margin-top: 8px;">
                <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">

                <label for="issue_type">Issue type *</label>
                <select id="issue_type" name="issue_type" required <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>>
                    <option value="">Select an issue</option>
                    <option value="dirty_room">Dirty room</option>
                    <option value="wrong_listing">Wrong listing / mismatch</option>
                    <option value="safety_issue">Safety issue</option>
                    <option value="missing_amenities">Missing amenities</option>
                    <option value="host_no_show">Host no-show / cannot access</option>
                    <option value="other">Other</option>
                </select>

                <label for="description">Describe what happened *</label>
                <textarea id="description" name="description" maxlength="2000" required <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>></textarea>

                <label for="evidence1">Evidence photo (optional)</label>
                <input type="file" id="evidence1" name="evidence[]" accept=".jpg,.jpeg,.png,.webp,.avif" <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>>

                <label for="evidence2">Evidence photo (optional)</label>
                <input type="file" id="evidence2" name="evidence[]" accept=".jpg,.jpeg,.png,.webp,.avif" <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>>

                <label for="evidence3">Evidence photo (optional)</label>
                <input type="file" id="evidence3" name="evidence[]" accept=".jpg,.jpeg,.png,.webp,.avif" <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>>

                <div class="ri-actions">
                    <a class="ri-btn" href="messages.php">Message host</a>
                    <button class="ri-btn ri-btn-primary" type="submit" <?php echo (!$elig['eligible'] || $active) ? 'disabled' : ''; ?>>
                        Submit issue report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/theme-toggle.js?v=26.0"></script>
</body>
</html>

