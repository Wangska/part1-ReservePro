<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$pending = [];
$result = $conn->query("
    SELECT h.id AS doc_id, h.user_id, h.gov_id_type, h.gov_id_number, h.ownership_proof_type,
           h.bank_name, h.bank_account_name, h.verification_status, h.created_at,
           u.first_name, u.last_name, u.email
    FROM host_documents h
    JOIN users u ON h.user_id = u.id
    WHERE h.verification_status = 'pending'
    ORDER BY h.created_at ASC
");
if ($result) {
    $pending = $result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Host Verifications - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=14.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=14.3">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .verification-card {
            background: var(--bg-secondary, #1A1A1A);
            border: 1px solid var(--border-color, #3A3A3A);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .verification-card h3 { margin: 0 0 8px 0; color: #fff !important; font-size: 18px; }
        .verification-card .meta { color: #B8B8B8; font-size: 14px; margin-bottom: 12px; }
        .verification-card .details { font-size: 13px; color: #B8B8B8; margin-bottom: 16px; }
        .verification-card .details span { display: inline-block; margin-right: 16px; }
        .verification-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .alert-success { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page admin-verifications-page">
    <div class="host-layout">
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                    <span>Admin Panel</span>
                </a>
                <a href="host-verifications.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                    <span>Host Verifications</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>All Properties</span>
                </a>
                <a href="users.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <span>Users</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                    <span>All Bookings</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="commission.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
                    <span>Commission</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                    <span>View Site</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <div class="theme-toggle" style="margin-bottom: 12px;">
                    <span class="theme-toggle-icon" aria-hidden="true"></span>
                    <span class="theme-toggle-text">Theme</span>
                </div>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">
            <div class="host-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <span class="admin-page-eyebrow">Host Review Queue</span>
                    <h1>Host Verifications</h1>
                    <p class="subtitle">Review identity, ownership, and payout details before giving hosts access to the publishing tools.</p>
                </div>
                <div class="admin-page-summary">
                    <span class="admin-page-summary-label">Pending Requests</span>
                    <strong><?php echo count($pending); ?></strong>
                    <span class="admin-page-summary-text">accounts awaiting a decision</span>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">
                    <?php echo $_GET['success'] === 'approve' ? 'Host has been approved and can now access the host dashboard.' : 'Host verification has been rejected.'; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert-error">Invalid request. Please try again.</div>
            <?php endif; ?>

            <?php if (empty($pending)): ?>
                <div class="empty-state admin-empty-state admin-surface">
                    <span class="empty-icon admin-empty-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                    <h3>No pending verifications</h3>
                    <p>All host verification requests have been reviewed.</p>
                </div>
            <?php else: ?>
                <p class="admin-inline-note"><?php echo count($pending); ?> request(s) are ready for review. Start with the newest submissions to keep host onboarding moving.</p>
                <?php foreach ($pending as $doc): ?>
                <div class="verification-card">
                    <h3><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></h3>
                    <div class="meta"><?php echo htmlspecialchars($doc['email']); ?> · Submitted <?php echo date('M j, Y \a\t g:i A', strtotime($doc['created_at'])); ?></div>
                    <div class="details admin-detail-grid">
                        <div class="admin-detail-card">
                            <span class="admin-detail-label">Government ID</span>
                            <span class="admin-detail-value"><?php echo htmlspecialchars($doc['gov_id_type']); ?></span>
                        </div>
                        <div class="admin-detail-card">
                            <span class="admin-detail-label">Ownership Proof</span>
                            <span class="admin-detail-value"><?php echo htmlspecialchars($doc['ownership_proof_type']); ?></span>
                        </div>
                        <div class="admin-detail-card">
                            <span class="admin-detail-label">Bank</span>
                            <span class="admin-detail-value"><?php echo htmlspecialchars($doc['bank_name']); ?></span>
                        </div>
                        <div class="admin-detail-card">
                            <span class="admin-detail-label">Account Name</span>
                            <span class="admin-detail-value"><?php echo htmlspecialchars($doc['bank_account_name']); ?></span>
                        </div>
                    </div>
                    <div class="verification-actions">
                        <form method="POST" action="approve-host.php" style="display: inline;">
                            <input type="hidden" name="doc_id" value="<?php echo (int)$doc['doc_id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn-approve">Approve</button>
                        </form>
                        <form method="POST" action="approve-host.php" style="display: inline;">
                            <input type="hidden" name="doc_id" value="<?php echo (int)$doc['doc_id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn-reject">Reject</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
</body>
</html>
