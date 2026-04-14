<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

// Latest host_documents per user (if any)
$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.role,
        u.email_verified,
        u.host_verified,
        u.host_verification_status,
        u.created_at,
        hd.id AS host_doc_id,
        hd.created_at AS host_doc_created_at,
        hd.verification_status AS host_doc_status,
        hd.id_full_name,
        hd.gov_id_type,
        hd.gov_id_number,
        hd.gov_id_photo_path,
        hd.ownership_proof_type,
        hd.ownership_reference,
        hd.ownership_doc_photo_path,
        hd.bank_name,
        hd.bank_account_name,
        hd.bank_account_number
    FROM users u
    LEFT JOIN (
        SELECT h1.*
        FROM host_documents h1
        JOIN (
            SELECT user_id, MAX(id) AS max_id
            FROM host_documents
            GROUP BY user_id
        ) latest ON latest.user_id = h1.user_id AND latest.max_id = h1.id
    ) hd ON hd.user_id = u.id
    ORDER BY u.created_at DESC
";
$result = $conn->query($sql);
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>User Submissions - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        .submissions-hero {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            border: 1px solid rgba(212, 165, 116, 0.22);
            border-radius: 22px;
            padding: 26px 28px;
            margin-bottom: 22px;
            box-shadow: 0 22px 50px rgba(0, 0, 0, 0.26);
        }
        .submissions-hero h1 { margin: 0 0 6px; color: #fff !important; }
        .submissions-hero p { margin: 0; color: #CBD5E1 !important; line-height: 1.6; }

        .submissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 18px;
        }
        .submission-card {
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(17, 24, 39, 0.86);
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
            padding: 18px;
            overflow: hidden;
        }
        .submission-top {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 12px;
        }
        .submission-avatar {
            width: 44px; height: 44px; border-radius: 14px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; color: #0F0F0F;
        }
        .submission-name { font-weight: 800; color: #fff; }
        .submission-meta { font-size: 12px; color: #94A3B8; margin-top: 2px; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            color: #E2E8F0;
            margin-left: auto;
        }
        .badge.host { color: #A7F3D0; }
        .badge.guest { color: #BAE6FD; }
        .badge.admin { color: #FCA5A5; }

        .kv {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 12px;
            margin-top: 10px;
        }
        .kv .k { color: #94A3B8; font-size: 11px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
        .kv .v { color: #F1F5F9; font-size: 13px; font-weight: 600; }
        .kv > div { min-width: 0; }
        .kv .v.truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .proofs {
            margin-top: 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .proof-thumb {
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(255,255,255,0.04);
            overflow: hidden;
            cursor: pointer;
            aspect-ratio: 16/10;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .proof-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .proof-thumb .proof-empty {
            color: #94A3B8;
            font-size: 12px;
            font-weight: 700;
            padding: 10px;
            text-align: center;
        }
        .card-actions {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-mini {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(212, 165, 116, 0.26);
            background: rgba(255,255,255,0.04);
            color: #E2E8F0;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }
        .btn-mini:hover { border-color: rgba(212,165,116,0.5); }

        /* Modal */
        .img-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.72);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 9999;
        }
        .img-modal-backdrop.open { display: flex; }
        .img-modal {
            width: min(980px, 96vw);
            max-height: 90vh;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(17,24,39,0.92);
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,0.6);
        }
        .img-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
        }
        .img-modal-title { color: #fff; font-weight: 800; font-size: 14px; }
        .img-modal-close {
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.06);
            color: #fff;
            border-radius: 10px;
            padding: 8px 10px;
            cursor: pointer;
            font-weight: 800;
        }
        .img-modal-body {
            padding: 12px;
            display: grid;
            place-items: center;
            background: rgba(0,0,0,0.25);
        }
        .img-modal-body img {
            max-width: 100%;
            max-height: calc(90vh - 120px);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(0,0,0,0.25);
        }

        body.light-mode .submission-card {
            background: #FFFFFF !important;
            border-color: #E0E0E0 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08) !important;
        }
        body.light-mode .submission-name { color: #0f172a !important; }
        body.light-mode .submission-meta { color: #475569 !important; }
        body.light-mode .kv .k { color: #64748b !important; }
        body.light-mode .kv .v { color: #0f172a !important; }
        body.light-mode .badge { background: #F8FAFC !important; color: #0f172a !important; border-color: #E2E8F0 !important; }
        body.light-mode .proof-thumb { background: #F8FAFC !important; border-color: #E2E8F0 !important; }
        body.light-mode .proof-thumb .proof-empty { color: #475569 !important; }
    </style>
</head>
<body class="dashboard-page admin-page">
<div class="host-layout">
    <aside class="host-sidebar">
        <div class="sidebar-header">
            <a href="../home.php" class="sidebar-brand">
                <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                <span>ReservePro</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span><span>Admin Panel</span></a>
            <a href="host-verifications.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-user-check"></i></span><span>Host Verifications</span></a>
            <a href="submissions.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-file-lines"></i></span><span>Submissions</span></a>
            <a href="properties.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house"></i></span><span>All Properties</span></a>
            <a href="users.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-users"></i></span><span>Users</span></a>
            <a href="bookings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span><span>All Bookings</span></a>
            <a href="earnings.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-wallet"></i></span><span>Earnings</span></a>
            <a href="commission.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-coins"></i></span><span>Commission</span></a>
            <a href="../home.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-globe"></i></span><span>View Site</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></div>
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
        <div class="submissions-hero">
            <h1><i class="fa-solid fa-file-lines" style="margin-right:10px;"></i>User submissions</h1>
            <p>Review user-submitted profile information and host verification proofs. Sensitive credentials like passwords are never fetched or displayed.</p>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty-state admin-empty-state admin-surface">
                <span class="empty-icon admin-empty-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                <h3>No users found</h3>
                <p>Once users register and hosts submit verification, they will appear here.</p>
            </div>
        <?php else: ?>
            <div class="submissions-grid">
                <?php foreach ($rows as $r):
                    $role = $r['role'] ?? 'guest';
                    $badgeClass = $role === 'host' ? 'host' : ($role === 'admin' ? 'admin' : 'guest');
                    $initials = strtoupper(substr($r['first_name'] ?? 'U', 0, 1) . substr($r['last_name'] ?? 'U', 0, 1));
                    $govImg = !empty($r['gov_id_photo_path']) ? '../' . ltrim($r['gov_id_photo_path'], '/') : '';
                    $ownImg = !empty($r['ownership_doc_photo_path']) ? '../' . ltrim($r['ownership_doc_photo_path'], '/') : '';
                ?>
                <section class="submission-card">
                    <div class="submission-top">
                        <div class="submission-avatar"><?php echo h($initials); ?></div>
                        <div style="min-width:0;">
                            <div class="submission-name truncate"><?php echo h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></div>
                            <div class="submission-meta truncate"><?php echo h($r['email'] ?? ''); ?> · ID #<?php echo (int)$r['id']; ?></div>
                        </div>
                        <div class="badge <?php echo h($badgeClass); ?>"><?php echo h($role); ?></div>
                    </div>

                    <div class="kv">
                        <div>
                            <div class="k">Email verified</div>
                            <div class="v"><?php echo !empty($r['email_verified']) ? 'Yes' : 'No'; ?></div>
                        </div>
                        <div>
                            <div class="k">Joined</div>
                            <div class="v"><?php echo !empty($r['created_at']) ? date('M j, Y', strtotime($r['created_at'])) : ''; ?></div>
                        </div>
                        <div>
                            <div class="k">Host verified</div>
                            <div class="v"><?php echo !empty($r['host_verified']) ? 'Yes' : 'No'; ?></div>
                        </div>
                        <div>
                            <div class="k">Host status</div>
                            <div class="v"><?php echo h($r['host_verification_status'] ?? 'none'); ?></div>
                        </div>
                    </div>

                    <?php if ($role === 'host'): ?>
                        <div class="kv" style="margin-top:14px;">
                            <div>
                                <div class="k">Name on ID</div>
                                <div class="v truncate"><?php echo h($r['id_full_name'] ?? ''); ?></div>
                            </div>
                            <div>
                                <div class="k">Gov ID</div>
                                <div class="v truncate"><?php echo h(($r['gov_id_type'] ?? '') . ' · ' . ($r['gov_id_number'] ?? '')); ?></div>
                            </div>
                            <div>
                                <div class="k">Supporting doc</div>
                                <div class="v truncate"><?php echo h(($r['ownership_proof_type'] ?? '') . ' · ' . ($r['ownership_reference'] ?? '')); ?></div>
                            </div>
                            <div>
                                <div class="k">Contact / payout</div>
                                <div class="v truncate"><?php echo h(($r['bank_name'] ?? '') . ' · ' . ($r['bank_account_name'] ?? '')); ?></div>
                            </div>
                        </div>

                        <div class="proofs">
                            <div class="proof-thumb" data-img="<?php echo h($govImg); ?>" data-title="Government ID">
                                <?php if ($govImg): ?>
                                    <img src="<?php echo h($govImg); ?>" alt="Government ID proof">
                                <?php else: ?>
                                    <div class="proof-empty">No ID image</div>
                                <?php endif; ?>
                            </div>
                            <div class="proof-thumb" data-img="<?php echo h($ownImg); ?>" data-title="Supporting document">
                                <?php if ($ownImg): ?>
                                    <img src="<?php echo h($ownImg); ?>" alt="Supporting document proof">
                                <?php else: ?>
                                    <div class="proof-empty">No supporting doc</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card-actions">
                        <a class="btn-mini" href="view-user.php?id=<?php echo (int)$r['id']; ?>"><i class="fa-solid fa-eye"></i> View user</a>
                        <?php if (!empty($r['host_doc_id'])): ?>
                            <span class="btn-mini" style="opacity:0.9;"><i class="fa-solid fa-clock"></i> Host doc: <?php echo h($r['host_doc_status'] ?? ''); ?></span>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<div class="img-modal-backdrop" id="imgModal">
    <div class="img-modal" role="dialog" aria-modal="true" aria-labelledby="imgModalTitle">
        <div class="img-modal-header">
            <div class="img-modal-title" id="imgModalTitle">Proof</div>
            <button type="button" class="img-modal-close" id="imgModalClose">Close</button>
        </div>
        <div class="img-modal-body">
            <img id="imgModalImg" alt="Proof image">
        </div>
    </div>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
<script>
    (function () {
        var modal = document.getElementById('imgModal');
        var modalImg = document.getElementById('imgModalImg');
        var modalTitle = document.getElementById('imgModalTitle');
        var closeBtn = document.getElementById('imgModalClose');
        if (!modal || !modalImg || !modalTitle || !closeBtn) return;

        function close() { modal.classList.remove('open'); }
        function open(src, title) {
            if (!src) return;
            modalTitle.textContent = title || 'Proof';
            modalImg.src = src;
            modal.classList.add('open');
        }
        document.querySelectorAll('.proof-thumb').forEach(function (el) {
            el.addEventListener('click', function () {
                var src = el.getAttribute('data-img') || '';
                var title = el.getAttribute('data-title') || 'Proof';
                open(src, title);
            });
        });
        closeBtn.addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    })();
</script>
</body>
</html>

