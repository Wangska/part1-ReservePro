<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$user = getCurrentUser();

// Profile page is for guest accounts only; hosts and admins are redirected
if ($user['role'] !== 'guest') {
    if ($user['role'] === 'host') {
        header('Location: host/dashboard.php');
        exit();
    }
    if ($user['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    }
    header('Location: dashboard.php');
    exit();
}

$errors = isset($_SESSION['profile_errors']) ? $_SESSION['profile_errors'] : [];
$old = isset($_SESSION['profile_old']) ? $_SESSION['profile_old'] : null;
$updated = isset($_GET['updated']) && $_GET['updated'] == '1';
if (isset($_SESSION['profile_errors'])) unset($_SESSION['profile_errors']);
if (isset($_SESSION['profile_old'])) unset($_SESSION['profile_old']);
if (isset($_SESSION['profile_updated'])) unset($_SESSION['profile_updated']);

$first_name = $old['first_name'] ?? $user['first_name'];
$last_name = $old['last_name'] ?? $user['last_name'];
$email = $old['email'] ?? $user['email'];

$role_label = isset($user['role']) ? ucfirst($user['role']) : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Profile - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=12.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=27.2">
    <style>
        .profile-page { max-width: 640px; margin: 0 auto; padding: 24px; }
        .profile-header {
            /* Trendy gray header instead of brown */
            background: linear-gradient(135deg, #111827 0%, #1F2933 45%, #020617 100%);
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .profile-header h1 { font-size: 26px; margin: 0 0 4px 0; color: #fff !important; }
        .profile-header p { margin: 0; font-size: 14px; color: #E0E0E0 !important; opacity: 0.9; }
        .profile-nav { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .profile-nav a {
            color: #E0E0E0; text-decoration: none; font-size: 14px; padding: 6px 12px; border-radius: 6px;
        }
        .profile-nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .profile-card {
            background: var(--bg-secondary, #1A1A1A);
            border: 1px solid var(--border-color, #3A3A3A);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 20px;
        }
        .profile-card h2 { font-size: 18px; margin-bottom: 20px; color: #D4A574 !important; }
        .profile-avatar {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 700; color: #0F0F0F;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 14px; color: #B8B8B8 !important; }
        .form-group input {
            width: 100%; padding: 12px 14px;
            background: var(--bg-tertiary, #2C2C2C); border: 1px solid var(--border-color, #3A3A3A);
            border-radius: 8px; color: #fff; font-size: 15px;
        }
        .form-group input:focus { outline: none; border-color: #D4A574; }
        .form-group .readonly { opacity: 0.85; cursor: default; }
        .btn-primary {
            display: inline-block; padding: 12px 24px;
            background: linear-gradient(135deg, #D4A574, #B8935F); color: #0F0F0F;
            border: none; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212, 165, 116, 0.3); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }
        .alert-success { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; }
        .alert ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body class="dashboard-page">
    <div class="profile-page">
        <div class="profile-header">
            <div>
                <h1>👤 Profile</h1>
                <p>Manage your account details</p>
            </div>
            <div class="profile-nav">
                <a href="home.php">Home</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="messages.php">Messages</a>
                <a href="logout.php">Logout</a>
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>
        </div>

        <?php if ($updated): ?>
        <div class="alert alert-success">Your profile has been updated.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?php echo htmlspecialchars($role_label); ?>" readonly class="readonly" disabled>
            </div>
        </div>

        <div class="profile-card">
            <h2>Edit profile</h2>
            <form method="post" action="update-profile.php">
                <div class="form-group">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <button type="submit" class="btn-primary">Save changes</button>
            </form>
        </div>
    </div>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>
