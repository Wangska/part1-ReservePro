<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'host') {
    header('Location: ../home.php');
    exit();
}

$errors = isset($_SESSION['profile_errors']) ? $_SESSION['profile_errors'] : [];
$old = isset($_SESSION['profile_old']) ? $_SESSION['profile_old'] : null;
$updated = isset($_GET['updated']) && $_GET['updated'] == '1';
if (isset($_SESSION['profile_errors'])) unset($_SESSION['profile_errors']);
if (isset($_SESSION['profile_old'])) unset($_SESSION['profile_old']);
if (isset($_SESSION['profile_updated'])) unset($_SESSION['profile_updated']);

$first_name = $old['first_name'] ?? ($user['first_name'] ?? '');
$last_name = $old['last_name'] ?? ($user['last_name'] ?? '');
$email = $old['email'] ?? ($user['email'] ?? '');
$date_of_birth = $old['date_of_birth'] ?? ($user['date_of_birth'] ?? '');
$profile_photo = (string)($user['profile_photo'] ?? '');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Profile - Host - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.profile-page-body { background:#06090F !important; }
        body.profile-page-body::before, body.profile-page-body::after { display:none !important; }
        .pf-alert { border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-weight: 800; font-size: 13px; }
        .pf-alert-success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.28); color:#86efac; }
        .pf-alert-error { background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.28); color:#fecaca; }
        .pf-banner{background:linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));border:1px solid rgba(148,163,184,0.16);border-radius:20px;padding:22px;margin-bottom:16px}
        .pf-banner-inner{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
        .pf-banner-avatar{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#2563EB);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;color:#fff;border:3px solid rgba(59,130,246,0.4);overflow:hidden}
        .pf-banner-avatar img{width:100%;height:100%;object-fit:cover;display:block}
        .pf-banner-info{flex:1;min-width:220px}
        .pf-banner-name{margin:0 0 4px;color:#fff;font-weight:900;font-size:20px}
        .pf-banner-email{margin:0;color:#94A3B8;font-weight:700;font-size:13px}
        .pf-edit-btn{border-radius:12px;padding:10px 12px;border:1px solid rgba(255,255,255,0.14);background:rgba(255,255,255,0.06);color:#E2E8F0;font-weight:900;font-size:13px;cursor:pointer}
        .pf-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);display:none;align-items:center;justify-content:center;z-index:9999;padding:18px}
        .pf-modal-overlay.open{display:flex}
        .pf-modal{width:100%;max-width:560px;border-radius:18px;overflow:hidden;background:rgba(17,24,39,0.96);border:1px solid rgba(148,163,184,0.18)}
        .pf-modal-head{padding:14px 16px;border-bottom:1px solid rgba(148,163,184,0.14);display:flex;justify-content:space-between;align-items:center}
        .pf-modal-head h3{margin:0;color:#fff;font-size:15px}
        .pf-modal-close{border:0;background:transparent;color:#CBD5E1;font-size:18px;cursor:pointer}
        .pf-modal-body{padding:16px}
        .pf-form-group{margin-bottom:14px}
        .pf-form-group label{display:block;margin-bottom:6px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:0.06em;color:#94A3B8}
        .pf-form-group input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(148,163,184,0.18);border-radius:12px;color:#E2E8F0;font-size:14px;font-weight:700}
        .pf-form-group input[type="file"]{padding:10px 12px}
        .pf-submit{padding:10px 14px;border-radius:12px;border:0;background:linear-gradient(135deg,#D4A574,#B8935F);color:#0f172a;font-weight:900;font-size:13px;cursor:pointer}
        body.light-mode .pf-modal{background:#fff}
        body.light-mode .pf-modal-head h3{color:#0f172a}
        body.light-mode .pf-form-group input{background:#fff;color:#0f172a;border-color:#E2E8F0}
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-dashboard-page profile-page-body">
<div class="host-layout">
    <aside class="host-sidebar">
        <div class="sidebar-header">
            <a href="../home.php" class="sidebar-brand">
                <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                <span>ReservePro</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            
            <a href="profile.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-user"></i></span><span>Profile</span></a>
            <a href="properties.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house"></i></span><span>My Properties</span></a>
            <a href="add-property.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-plus"></i></span><span>Add Property</span></a>
            <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span><span>Bookings</span></a>
            <a href="refund-requests.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-rotate-left"></i></span><span>Refund Requests</span></a>
            <a href="messages.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-envelope"></i></span><span>Messages</span></a>
            <a href="earnings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet"></i></span><span>Earnings</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php if (!empty($profile_photo)): ?>
                        <img src="<?php echo h('../' . ltrim($profile_photo, '/')); ?>" alt="Profile photo" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%;" onerror="this.style.display='none'">
                    <?php else: ?>
                        <?php echo strtoupper(substr((string)$first_name, 0, 1) . substr((string)$last_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo h($first_name . ' ' . $last_name); ?></div>
                    <div class="user-role">Host</div>
                </div>
            </div>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">

        <?php if ($updated): ?>
            <div class="pf-alert pf-alert-success"><i class="fa-solid fa-circle-check"></i> Profile updated.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="pf-alert pf-alert-error">
                <ul style="margin:0; padding-left:18px;">
                    <?php foreach ($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="pf-banner">
            <div class="pf-banner-inner">
                <div class="pf-banner-avatar">
                    <?php if (!empty($profile_photo)): ?>
                        <img src="<?php echo h('../' . ltrim($profile_photo, '/')); ?>" alt="Profile photo" onerror="this.style.display='none'">
                    <?php else: ?>
                        <?php echo strtoupper(substr((string)$first_name, 0, 1) . substr((string)$last_name, 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="pf-banner-info">
                    <div class="pf-banner-name"><?php echo h($first_name . ' ' . $last_name); ?></div>
                    <div class="pf-banner-email"><?php echo h($email); ?></div>
                    <div style="margin-top:8px; color:#94A3B8; font-weight:800; font-size:12px;">DOB: <?php echo $date_of_birth ? h($date_of_birth) : '—'; ?></div>
                </div>
                <div>
                    <button class="pf-edit-btn" type="button" onclick="openEditModal()"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</button>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="pf-modal-overlay" id="editModal" onclick="handleOverlayClick(event)">
    <div class="pf-modal">
        <div class="pf-modal-head">
            <h3><i class="fa-solid fa-pen-to-square" style="color:#D4A574; margin-right:8px;"></i>Edit Profile</h3>
            <button class="pf-modal-close" type="button" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="pf-modal-body">
            <form method="post" action="../update-profile.php" enctype="multipart/form-data">
                <div class="pf-form-group">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo h($first_name); ?>" required>
                </div>
                <div class="pf-form-group">
                    <label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo h($last_name); ?>" required>
                </div>
                <div class="pf-form-group">
                    <label for="date_of_birth">Date of birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo h((string)$date_of_birth); ?>" required>
                </div>
                <div class="pf-form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required>
                </div>
                <div class="pf-form-group">
                    <label for="profile_photo">Profile photo (optional)</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
                    <button type="button" onclick="closeEditModal()" style="padding:10px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(148,163,184,0.18); border-radius:10px; color:#94A3B8; font-weight:900; font-size:13px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="pf-submit"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/theme-toggle.js?v=27.5"></script>
<script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
<script>
function openEditModal(){ document.getElementById('editModal').classList.add('open'); document.body.style.overflow='hidden'; }
function closeEditModal(){ document.getElementById('editModal').classList.remove('open'); document.body.style.overflow=''; }
function handleOverlayClick(e){ if (e.target === document.getElementById('editModal')) closeEditModal(); }
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeEditModal(); });
<?php if (!empty($errors)): ?>window.addEventListener('DOMContentLoaded', function(){ openEditModal(); });<?php endif; ?>
</script>
</body>
</html>

