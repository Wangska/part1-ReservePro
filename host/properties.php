<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Hosts must complete verification before managing properties
if ($user && $user['role'] === 'host' && empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

$conn = getDBConnection();

// Handle host actions: availability, auto-accept, delete
$action_message = null;
$action_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = intval($_POST['property_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($property_id > 0 && $action && $user) {
        // Ensure property belongs to current host
        $stmt = $conn->prepare("SELECT id, status FROM properties WHERE id = ? AND host_id = ?");
        $stmt->bind_param("ii", $property_id, $user['id']);
        $stmt->execute();
        $propResult = $stmt->get_result();
        $propertyRow = $propResult->fetch_assoc();
        $stmt->close();

        if (!$propertyRow) {
            $action_error = "You are not allowed to modify this property.";
        } else {
            if ($action === 'update_availability') {
                $new_status = $_POST['new_status'] ?? '';
                if (in_array($new_status, ['approved', 'out_of_order'], true)) {
                    // Only allow toggling between approved and out_of_order
                    $stmt = $conn->prepare("UPDATE properties SET status = ? WHERE id = ? AND host_id = ?");
                    $stmt->bind_param("sii", $new_status, $property_id, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $action_message = $new_status === 'out_of_order'
                        ? "Property marked as Out of Order. Guests will no longer see it."
                        : "Property marked as Available. Guests can see and book it again.";
                }
            } elseif ($action === 'toggle_auto_accept') {
                $new_value = intval($_POST['new_value'] ?? 0) ? 1 : 0;
                $stmt = $conn->prepare("UPDATE properties SET auto_accept_bookings = ? WHERE id = ? AND host_id = ?");
                $stmt->bind_param("iii", $new_value, $property_id, $user['id']);
                $stmt->execute();
                $stmt->close();
                $action_message = $new_value
                    ? "Auto-accept enabled. New bookings for this property will be auto-confirmed."
                    : "Auto-accept disabled. You will manually review bookings.";
            } elseif ($action === 'delete_property') {
                // Deletion rules:
                // - Only owner can delete (already enforced above)
                // - Cannot delete if there are any bookings for this property
                $stmt = $conn->prepare("SELECT COUNT(*) AS booking_count FROM bookings WHERE property_id = ?");
                $stmt->bind_param("i", $property_id);
                $stmt->execute();
                $countResult = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($countResult && $countResult['booking_count'] > 0) {
                    $action_error = "This property has bookings and cannot be deleted. Settle/cancel bookings instead.";
                } else {
                    // Safe to delete property (related rows cascade via FK)
                    $stmt = $conn->prepare("DELETE FROM properties WHERE id = ? AND host_id = ?");
                    $stmt->bind_param("ii", $property_id, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $action_message = "Property deleted successfully.";
                }
            }
        }
    }
}

// Get all host properties with photos (after any updates)
$stmt = $conn->prepare("
    SELECT p.*,
    COALESCE(
        (SELECT photo_url FROM property_photos WHERE property_id = p.id AND is_primary = 1 LIMIT 1),
        (SELECT photo_url FROM property_photos WHERE property_id = p.id LIMIT 1)
    ) as primary_photo
    FROM properties p
    WHERE p.host_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$property_stats = [
    'total' => count($properties),
    'approved' => 0,
    'pending' => 0,
    'out_of_order' => 0,
    'auto_accept' => 0,
];

foreach ($properties as $property_item) {
    $status_key = $property_item['status'] ?? '';
    if (isset($property_stats[$status_key])) {
        $property_stats[$status_key]++;
    }
    if (!empty($property_item['auto_accept_bookings'])) {
        $property_stats['auto_accept']++;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>My Properties - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=13.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <link rel="stylesheet" href="../assets/css/landing.css?v=25.1">
    <link rel="stylesheet" href="../assets/css/animations.css?v=1.0">
    <link rel="stylesheet" href="../assets/css/home-modern.css?v=4.5">
    <style>
        .host-congrats {
            margin: 0 0 18px;
            border-radius: 22px;
            padding: 20px 20px;
            border: 1px solid rgba(34,197,94,0.28);
            background: radial-gradient(1200px 400px at 30% 0%, rgba(34,197,94,0.14), transparent 60%),
                        linear-gradient(135deg, rgba(17,24,39,0.86), rgba(30,41,59,0.80));
            box-shadow: 0 18px 50px rgba(0,0,0,0.22);
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .host-congrats-icon {
            width: 48px; height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            background: rgba(34,197,94,0.14);
            border: 1px solid rgba(34,197,94,0.28);
            color: #86efac;
        }
        .host-congrats h2 {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 900;
            color: #ffffff !important;
            letter-spacing: -0.02em;
        }
        .host-congrats p {
            margin: 0;
            color: #CBD5E1 !important;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.55;
        }
        .host-congrats-actions {
            margin-left: auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }
        .host-congrats-actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color: #E2E8F0;
            text-decoration: none;
            font-weight: 900;
            font-size: 13px;
            white-space: nowrap;
        }
        .host-congrats-actions a:hover { background: rgba(255,255,255,0.10); }
        .host-congrats-actions a.primary {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            border-color: transparent;
            color: #0f172a;
        }
        body.light-mode .host-congrats {
            border-color: rgba(22,163,74,0.22);
            background: linear-gradient(135deg, rgba(240,253,244,0.95), rgba(255,255,255,0.95));
            box-shadow: 0 14px 35px rgba(15,23,42,0.10);
        }
        body.light-mode .host-congrats h2 { color: #0f172a !important; }
        body.light-mode .host-congrats p { color: #334155 !important; }
        body.light-mode .host-congrats-actions a { background: #fff; border-color: rgba(15,23,42,0.10); color: #0f172a; }

        .host-prop-card { cursor: pointer !important; }
        .hpc-status-badge {
            position: absolute; top: 10px; right: 10px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
        }
        .hpc-status-badge.status-approved  { background: rgba(34,197,94,0.85);  color: #fff; }
        .hpc-status-badge.status-pending   { background: rgba(234,179,8,0.85);  color: #fff; }
        .hpc-status-badge.status-rejected  { background: rgba(239,68,68,0.85);  color: #fff; }
        .hpc-status-badge.status-out_of_order { background: rgba(239,68,68,0.85); color: #fff; }
        .card-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
        .hpc-actions { display: flex; gap: 6px; align-items: center; }
        .hpc-toggles {
            display: flex; flex-direction: column; gap: 6px;
            margin-top: 10px; padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .hpc-toggle-form { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .hpc-toggle-label { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .hpc-toggle-on      { color: #86efac; }
        .hpc-toggle-off     { color: #fca5a5; }
        .hpc-toggle-neutral { color: #94a3b8; }
        .hpc-toggle-btn {
            padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
            cursor: pointer; border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.08); color: #E0E0E0;
            transition: background 0.2s; white-space: nowrap; flex-shrink: 0;
        }
        .hpc-toggle-btn:hover { background: rgba(255,255,255,0.15); }
        .hpc-toggle-on-btn  { background: rgba(34,197,94,0.18); border-color: rgba(34,197,94,0.4); color: #86efac; }
        .hpc-toggle-on-btn:hover { background: rgba(34,197,94,0.3); }
        /* Light mode */
        body.light-mode .hpc-toggles { border-top-color: rgba(0,0,0,0.08); }
        body.light-mode .hpc-toggle-on      { color: #16a34a; }
        body.light-mode .hpc-toggle-off     { color: #dc2626; }
        body.light-mode .hpc-toggle-neutral { color: #64748b; }
        body.light-mode .hpc-toggle-btn { background: rgba(0,0,0,0.05); border-color: rgba(0,0,0,0.12); color: #334155; }
        body.light-mode .hpc-toggle-btn:hover { background: rgba(0,0,0,0.10); }
        body.light-mode .hpc-toggle-on-btn  { background: rgba(34,197,94,0.12); border-color: rgba(22,163,74,0.35); color: #15803d; }

        /* Make .host-action-btn.is-info identical to .sub-action-btn */
        .host-action-btn.is-info {
            padding: 9px 14px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
            white-space: nowrap;
            min-height: 40px;
        }
        .host-action-btn.is-info:hover {
            transform: translateY(-1px);
            border-color: rgba(212, 165, 116, 0.35);
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            box-shadow: 0 8px 20px rgba(212,165,116,0.22);
        }


        .status-approved {
            color: #fff !important;
            background: #22c55e !important;
        }
        .status-out_of_order {
            color: #fff !important;
            background: #ef4444 !important;
        }
        .hpc-status-badge.status-approved {
            background: linear-gradient(90deg, #e6faed 60%, #c6f6d5 100%);
            color: #fff !important;
            box-shadow: 0 2px 8px rgba(34,197,94,0.07);
            letter-spacing: 0.03em;
            font-weight: 500;
            font-family: inherit;
            text-shadow: none;
            border-radius: 14px;
        }
        .hpc-status-badge.status-out_of_order {
            background: linear-gradient(90deg, #fee2e2 60%, #fecaca 100%);
            color: #b91c1c;

            box-shadow: 0 2px 8px rgba(239,68,68,0.07);
            letter-spacing: 0.03em;
            font-weight: 500;
            font-family: inherit;
            text-shadow: none;
            border-radius: 14px;
        }
/* Make .host-action-btn.is-primary identical to .host-action-btn.is-info */
        .host-action-btn.is-primary {
            padding: 9px 14px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #212121 !important;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
            white-space: nowrap;
            min-height: 40px;
        }
        .host-action-btn.is-primary:hover {
            transform: translateY(-1px);
            border-color: rgba(212, 165, 116, 0.35);
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            box-shadow: 0 8px 20px rgba(212,165,116,0.22);
        }

        .property-image .status-badge.status-approved,
        .property-image .status-badge.status-out_of_order {
            border: none;
        }

@keyframes hpc-btn-pop {
    0% { transform: scale(1); }
    60% { transform: scale(1.08); }
    100% { transform: scale(1); }
}
    </style>
    <!-- Notification bell styles (from refunds.php) -->
    <style>
        .admin-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }
        .adm-notif-wrap {
            position: relative;
        }
        .adm-notif-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(255, 255, 255, 0.06);
            color: #A3A3A3;
            font-size: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s;
        }
        .adm-notif-btn:hover {
            background: rgba(255, 255, 255, 0.11);
            border-color: rgba(212, 165, 116, 0.4);
        }
        .adm-notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: #EF4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            pointer-events: none;
        }
        .adm-notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 340px;
            max-width: calc(100vw - 32px);
            border-radius: 18px;
            background: rgba(17, 24, 39, 0.97);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.42);
            z-index: 9999;
            overflow: hidden;
        }
        .adm-notif-dropdown-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 14px 11px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }
        .adm-notif-dropdown-title {
            font-size: 13px;
            font-weight: 900;
            color: #F1F5F9;
            letter-spacing: -0.01em;
        }
        .adm-notif-markall {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 10px;
            cursor: pointer;
        }
        .adm-notif-markall:hover {
            background: rgba(255, 255, 255, 0.11);
        }
        .adm-notif-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px;
            max-height: 340px;
            overflow-y: auto;
        }
        .adm-notif-item {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            padding: 9px 10px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.12);
            background: rgba(255, 255, 255, 0.03);
        }
        .adm-notif-item.unread {
            border-color: rgba(212, 165, 116, 0.32);
            background: rgba(212, 165, 116, 0.07);
        }
        .adm-notif-item-body {
            flex: 1;
            min-width: 0;
        }
        .adm-notif-item strong {
            display: block;
            color: #F1F5F9;
            font-weight: 800;
            font-size: 12px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .adm-notif-item small {
            display: block;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
        }
        .adm-notif-item-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
            flex-shrink: 0;
        }
        .adm-notif-link {
            color: #FDE68A;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
        }
        .adm-notif-mark {
            border: 0;
            background: transparent;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }
        .adm-notif-empty {
            padding: 14px 10px;
            color: #94A3B8;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        body.light-mode .adm-notif-btn {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.10);
            color: #6B7280;
        }
        body.light-mode .adm-notif-btn:hover {
            background: #F1F5F9;
        }
        body.light-mode .adm-notif-dropdown {
            background: #FFFFFF;
            border-color: rgba(15, 23, 42, 0.10);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
        }
        body.light-mode .adm-notif-dropdown-head {
            border-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .adm-notif-dropdown-title {
            color: #0F172A;
        }
        body.light-mode .adm-notif-markall {
            background: #F8FAFC;
            color: #0F172A;
            border-color: rgba(15, 23, 42, 0.10);
        }
        body.light-mode .adm-notif-item {
            background: #F8FAFC;
            border-color: #E2E8F0;
        }
        body.light-mode .adm-notif-item.unread {
            background: rgba(212, 165, 116, 0.10);
            border-color: rgba(212, 165, 116, 0.40);
        }
        body.light-mode .adm-notif-item strong {
            color: #0F172A;
        }
        body.light-mode .adm-notif-item small {
            color: #475569;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-properties-page">
    <div class="host-layout">
        <!-- Sidebar (same as dashboard) -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                    <span>Profile</span>
                </a>
                <a href="properties.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                    <span>Add Property</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>Bookings</span>
                </a>
                <a href="refund-requests.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                    <span>Refund Requests</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="overflow:hidden;">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img
                                src="<?php echo htmlspecialchars('../' . ltrim((string)$user['profile_photo'], '/')); ?>"
                                alt="Profile photo"
                                style="width:100%;height:100%;object-fit:cover;display:block;"
                                onerror="this.style.display='none'"
                            >
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Host</div>
                    </div>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="host-header host-page-hero">
                <div class="host-page-hero-content">
                    <h1 style="margin-top: 20px;">My Properties</h1>
                    <!-- subtitle removed -->
                </div>
                <div class="admin-hero-actions">
                    <div class="adm-notif-wrap" id="admNotifWrap">
                        <button class="adm-notif-btn" id="admNotifBtn" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="admNotifDropdown">
                            <i class="fa-solid fa-bell" aria-hidden="true" style="font-size: 17px;"></i>
                            <span class="adm-notif-badge" id="admNotifBadge" hidden></span>
                        </button>
                        <div class="adm-notif-dropdown" id="admNotifDropdown" hidden>
                            <div class="adm-notif-dropdown-head">
                                <span class="adm-notif-dropdown-title">Notifications</span>
                                <button class="adm-notif-markall" id="admNotifMarkAll" type="button">Mark all read</button>
                            </div>
                            <div class="adm-notif-list" id="admNotifList">
                                <div class="adm-notif-empty">Loading&hellip;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'property_added'): ?>
                <div class="host-congrats">
                    <div class="host-congrats-icon" aria-hidden="true">
                        <i class="fa-solid fa-circle-check" style="font-size:18px;"></i>
                    </div>
                    <div>
                        <h2>Congratulations! You listed a property.</h2>
                        <p>Please wait for the admin to approve it. Your listing will appear to guests once it’s approved.</p>
                    </div>
                    <div class="host-congrats-actions">
                        <a href="add-property.php">Add another</a>
                        <a class="primary" href="properties.php">Got it</a>
                    </div>
                </div>
            <?php endif; ?>


            <?php if ($action_error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($action_error); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'notfound'): ?>
                <div class="alert alert-error">Property not found or you do not have permission to view it.</div>
            <?php endif; ?>

            <div class="host-metric-grid">
                <div class="host-metric-card">
                    <div class="host-metric-icon is-sky"><i class="fa-solid fa-building" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Total Listings</p>
                        <h3><?php echo $property_stats['total']; ?></h3>
                        <!-- host-metric-note removed -->
                    </div>
                </div>
                <div class="host-metric-card">
                    <div class="host-metric-icon is-emerald"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Approved</p>
                        <h3><?php echo $property_stats['approved']; ?></h3>
                        <!-- host-metric-note removed -->
                    </div>
                </div>
                <div class="host-metric-card">
                    <div class="host-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Pending Review</p>
                        <h3><?php echo $property_stats['pending']; ?></h3>
                        <!-- host-metric-note removed -->
                    </div>
                </div>
                <div class="host-metric-card">
                    <div class="host-metric-icon is-gold"><i class="fa-solid fa-bolt" aria-hidden="true"></i></div>
                    <div class="host-metric-copy">
                        <p>Auto-Accept Enabled</p>
                        <h3><?php echo $property_stats['auto_accept']; ?></h3>
                        <!-- host-metric-note removed -->
                    </div>
                </div>
            </div>

            <?php if (empty($properties)): ?>
                <div class="empty-state host-empty-state host-surface">
                    <span class="empty-icon host-empty-icon"><i class="fa-solid fa-house-circle-xmark" aria-hidden="true"></i></span>
                    <h3>No properties yet</h3>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($properties as $property):
                        $raw_photo = $property['primary_photo'] ?? '';
                        if (!empty($raw_photo) && strpos($raw_photo, 'http') !== 0) {
                            $photo_url = htmlspecialchars('../' . ltrim($raw_photo, '/'));
                        } else {
                            $photo_url = !empty($raw_photo) ? htmlspecialchars($raw_photo) : 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400';
                        }
                        $display_title    = htmlspecialchars($property['title'] ?? '');
                        $display_city     = htmlspecialchars($property['city'] ?? '');
                        $display_country  = htmlspecialchars($property['country'] ?? '');
                        $display_location = trim($display_city . ($display_city && $display_country ? ', ' : '') . $display_country);
                    ?>
                        <div class="service-card host-prop-card" data-host-prop-card data-property-id="<?php echo (int)$property['id']; ?>">
                            <div class="card-image">
                                <img src="<?php echo $photo_url; ?>" alt="<?php echo $display_title; ?>" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400'">
                                <span class="card-badge"><?php echo ucfirst(htmlspecialchars($property['property_type'] ?? 'property')); ?></span>
                                <span class="hpc-status-badge status-<?php echo htmlspecialchars($property['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $property['status'])); ?>
                                </span>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title"><?php echo $display_title; ?></h3>
                                <div class="card-location">
                                    <span class="card-location-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10Z"></path>
                                            <circle cx="12" cy="11" r="2.5"></circle>
                                        </svg>
                                    </span>
                                    <span><?php echo $display_location; ?></span>
                                </div>
                                <div class="card-details">
                                    <span class="card-meta-item">
                                        <span class="card-meta-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 11V7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5V11"></path>
                                                <path d="M3 13h18"></path><path d="M5 19v-6"></path><path d="M19 19v-6"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo $property['bedrooms']; ?> bed<?php echo $property['bedrooms'] > 1 ? 's' : ''; ?></span>
                                    </span>
                                    <span class="card-meta-item">
                                        <span class="card-meta-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M7 21h10"></path><path d="M9 17h6"></path>
                                                <path d="M8 3h8l1 9a5 5 0 0 1-10 0l1-9Z"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo $property['bathrooms']; ?> bath<?php echo $property['bathrooms'] > 1 ? 's' : ''; ?></span>
                                    </span>
                                    <span class="card-meta-item">
                                        <span class="card-meta-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9.5" cy="7" r="4"></circle>
                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                        </span>
                                        <span><?php echo $property['max_guests']; ?> guest<?php echo $property['max_guests'] > 1 ? 's' : ''; ?></span>
                                    </span>
                                </div>
                                <div class="card-footer">
                                    <div class="card-price">
                                        <div class="price-wrapper">
                                            <span class="price-current price-amount">₱<?php echo number_format($property['price_per_night'], 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="host-stack-actions">
                                        <a href="view-property.php?id=<?php echo (int)$property['id']; ?>" class="host-action-btn is-info">View</a>
                                        <a href="edit-property.php?id=<?php echo (int)$property['id']; ?>" class="host-action-btn is-primary">Edit</a>
                                        <form method="POST" action="properties.php" style="display:contents;" onsubmit="return confirm('Delete this property? This cannot be undone.');">
                                            <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                                            <input type="hidden" name="action" value="delete_property">
                                            <button type="submit" class="host-action-btn is-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="hpc-toggles">
                                    <?php if (in_array($property['status'], ['approved', 'out_of_order'])): ?>
                                    <div class="hpc-toggle-row">
                                        <form method="POST" action="properties.php" class="hpc-toggle-form">
                                            <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                                            <input type="hidden" name="action" value="update_availability">
                                            <?php if ($property['status'] === 'out_of_order'): ?>
                                                <input type="hidden" name="new_status" value="approved">
                                                <span class="hpc-toggle-label hpc-toggle-off"> Out of Order</span>
                                                <button type="submit" class="hpc-toggle-btn hpc-toggle-on-btn">Mark Available</button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status" value="out_of_order">
                                                <span class="hpc-toggle-label hpc-toggle-on">Available</span>
                                                <button type="submit" class="hpc-toggle-btn">Mark Out of Order</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                    <div class="hpc-toggle-row">
                                        <span class="hpc-toggle-label hpc-toggle-neutral"><?php echo ucfirst($property['status']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="hpc-toggle-row">
                                        <form method="POST" action="properties.php" class="hpc-toggle-form">
                                            <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_auto_accept">
                                            <input type="hidden" name="new_value" value="<?php echo $property['auto_accept_bookings'] ? 0 : 1; ?>">
                                            <span class="hpc-toggle-label <?php echo $property['auto_accept_bookings'] ? 'hpc-toggle-on' : 'hpc-toggle-neutral'; ?>">
                                                Auto-accept: <?php echo $property['auto_accept_bookings'] ? 'On' : 'Off'; ?>
                                            </span>
                                            <button type="submit" class="hpc-toggle-btn <?php echo $property['auto_accept_bookings'] ? '' : 'hpc-toggle-on-btn'; ?>">
                                                <?php echo $property['auto_accept_bookings'] ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
    <script>
        // Host → My Properties: disable any modal-style card view.
        // Clicking the card navigates to the full host view page instead.
        (function () {
            document.addEventListener('click', function (e) {
                var card = e.target && e.target.closest ? e.target.closest('[data-host-prop-card]') : null;
                if (!card) return;

                // Let explicit actions behave normally.
                if (e.target && e.target.closest && e.target.closest('a, button, form, input, textarea, select, label')) return;

                var id = card.getAttribute('data-property-id');
                if (!id) return;
                window.location.href = 'view-property.php?id=' + encodeURIComponent(id);
            });
        })();
    </script>
    <script>
        (function(){
            var btn = document.getElementById('admNotifBtn');
            var dropdown = document.getElementById('admNotifDropdown');
            var badge = document.getElementById('admNotifBadge');
            var list = document.getElementById('admNotifList');
            var markAllBtn = document.getElementById('admNotifMarkAll');
            if (!btn || !dropdown) return;

            function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

            function render(items){
                if (!items || !items.length){
                    list.innerHTML = '<div class="adm-notif-empty">No notifications yet.</div>';
                    return;
                }
                list.innerHTML = items.map(function(n){
                    var unread = String(n.is_read)==='0';
                    var link = n.link ? String(n.link) : '';
                    var body = n.body ? String(n.body) : '';
                    var attrs = ' style="cursor:pointer"';
                    if (link) attrs += ' data-link="'+esc(link)+'"';
                    if (unread) attrs += ' data-mark="'+esc(n.id)+'"';
                    return '<div class="adm-notif-item'+(unread?' unread':'')+'"'+attrs+'>'+ 
                        '<div class="adm-notif-item-body"><strong>'+esc(n.title)+'</strong>'+ (body?'<small>'+esc(n.body)+'</small>':'')+'</div>'+ '<div class="adm-notif-item-actions"></div></div>';
                }).join('');
            }

            function load(){
                fetch('../api/notifications-list.php?limit=8', {credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data||!data.ok) return;
                        var unread = parseInt(data.unread||0, 10);
                        var items = data.items||[];
                        if (items.length > 0) {
                            if (unread > 0) {
                                badge.textContent = unread > 99 ? '99+' : String(unread);
                                badge.hidden = false;
                            } else {
                                badge.hidden = true;
                            }
                        } else {
                            badge.hidden = true;
                        }
                        render(items);
                    })
                    .catch(function(){ list.innerHTML='<div class="adm-notif-empty">Failed to load.</div>'; badge.hidden = true; });
            }

            function mark(id){
                var fd = new FormData();
                if (id) fd.append('id', String(id));
                fetch('../api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){ if(data&&data.ok) load(); })
                    .catch(function(){});
            }

            list.addEventListener('click', function(e){
                var item = e.target && e.target.closest && e.target.closest('.adm-notif-item');
                if (!item) return;
                
                var hasMarkAttr = item.hasAttribute('data-mark');
                var hasLinkAttr = item.hasAttribute('data-link');
                
                if (hasMarkAttr) {
                    var id = parseInt(item.getAttribute('data-mark'), 10);
                    if (id) {
                        var url = hasLinkAttr ? item.getAttribute('data-link') : null;
                        var fd = new FormData();
                        fd.append('id', String(id));
                        fetch('../api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                            .then(function(r){ return r.json(); })
                            .then(function(data){ 
                                if(data&&data.ok) {
                                    if (url) window.location.href = url;
                                    else load();
                                }
                            })
                            .catch(function(){});
                        return;
                    }
                }
                
                if (hasLinkAttr) {
                    var url = item.getAttribute('data-link');
                    if (url) window.location.href = url;
                }
            });

            markAllBtn.addEventListener('click', function(){ mark(0); });

            btn.addEventListener('click', function(e){
                e.stopPropagation();
                var open = !dropdown.hidden;
                dropdown.hidden = open;
                btn.setAttribute('aria-expanded', String(!open));
                if (!open) load();
            });

            document.addEventListener('click', function(e){
                if (!document.getElementById('admNotifWrap').contains(e.target)){
                    dropdown.hidden = true;
                    btn.setAttribute('aria-expanded','false');
                }
            });

            load();
        })();
    </script>
</body>
</html>
