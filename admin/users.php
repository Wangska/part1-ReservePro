<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Ensure user is an admin
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();

// Get all users
$query = "
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
        (SELECT COUNT(*) FROM properties WHERE host_id = u.id) as total_properties,
        (SELECT COUNT(*) FROM bookings WHERE guest_id = u.id) as total_bookings
    FROM users u
    ORDER BY u.created_at DESC
";
$result = $conn->query($query);
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [
    'total' => count($users),
    'guests' => 0,
    'hosts' => 0,
    'admins' => 0
];

foreach ($users as $u) {
    $stats[$u['role'] . 's']++;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Users - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=10.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        .user-avatar-large {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0F0F0F;
            font-size: 18px;
        }

        .user-cell {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .user-details {
            text-align: left;
        }

        .user-details h3 {
            font-size: 16px;
            font-weight: 600;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .user-details p {
            font-size: 13px;
            color: #B8B8B8;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: 1px solid transparent;
        }

        .role-guest {
            background: rgba(186,230,253,0.1);
            color: #BAE6FD;
            border: 1px solid rgba(186,230,253,0.2);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .role-host {
            background: rgba(212, 165, 116, 0.2);
            color: #D4A574;
            border: 1px solid rgba(212, 165, 116, 0.3);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .role-admin {
            background: rgba(239, 68, 68, 0.2);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .admin-users-page .btn-action.btn-view {
            background: transparent !important;
            color: #D4A574 !important;
            border: none !important;
            border-radius: 10px !important;
            min-height: 32px !important;
            min-width: 70px !important;
            padding: 0 18px !important;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: background 0.18s, border-color 0.18s, color 0.18s, box-shadow 0.18s;
        }
        .admin-users-page .btn-action.btn-view:hover {
            background: linear-gradient(135deg, #D4A574, #B8935F) !important;
            color: #0F0F0F !important;
            border-color: transparent !important;
            box-shadow: 0 8px 20px rgba(212,165,116,0.22) !important;
        }

        .admin-users-page .properties-table td {
            text-align: center;
            vertical-align: middle;
        }

        .admin-users-page .properties-table {
            border-collapse: collapse;
            border-spacing: 0;
        }

        .admin-users-page .properties-table th,
        .admin-users-page .properties-table td {
            border-left: none !important;
            border-right: none !important;
        }
        /* ── Notification bell dropdown (copied from refunds.php for pixel-perfect match) ── */
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
        /* Light mode overrides */
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
<body class="dashboard-page admin-page admin-clean-page admin-users-page">
    <div class="host-layout">
        <!-- Sidebar -->
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
                    <span>Dashboard</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></span>
                    <span>Analytics</span>
                </a>
                <a href="refunds.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                    <span>Refunds</span>
                </a>
                <a href="host-verifications.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                    <span>Host Verifications</span>
                </a>
                <a href="submissions.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                    <span>Submissions</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>All Properties</span>
                </a>
                <a href="users.php" class="nav-item active">
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
                
                <!-- Theme Toggle -->

                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="users-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <h1>Users</h1>
                    <p></p>
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

            <!-- Statistics -->
            <div class="properties-stats admin-metric-grid">
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-sky"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Total Users</p>
                        <h3><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-emerald"><i class="fa-solid fa-user" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Guests</p>
                        <h3><?php echo $stats['guests']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-gold"><i class="fa-solid fa-house-user" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Hosts</p>
                        <h3><?php echo $stats['hosts']; ?></h3>
                    </div>
                </div>
                <div class="stat-card admin-metric-card">
                    <div class="stat-icon admin-metric-icon is-red"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
                    <div class="stat-content admin-metric-copy">
                        <p>Administrators</p>
                        <h3><?php echo $stats['admins']; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="properties-table-container admin-surface">
                <div class="table-header admin-surface-header">
                    <div>
                        <h2>All Users</h2>
                        <p></p>
                    </div>
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab active" onclick="filterUsers('all', this)">All</button>
                        <button type="button" class="filter-tab" onclick="filterUsers('guest', this)">Guests</button>
                        <button type="button" class="filter-tab" onclick="filterUsers('host', this)">Hosts</button>
                        <button type="button" class="filter-tab" onclick="filterUsers('admin', this)">Admins</button>
                    </div>
                </div>

                <table class="properties-table">
                    <thead>
                        <tr>
                            <th style="text-align:center;">User</th>
                            <th style="text-align:center;">Email</th>
                            <th style="text-align:center;">Role</th>
                            <th style="text-align:center;">Properties</th>
                            <th style="text-align:center;">Bookings</th>
                            <th style="text-align:center;">Joined</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr data-role="<?php echo $u['role']; ?>">
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-large">
                                            <?php echo strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <h3><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></h3>
                                            <p>ID: <?php echo $u['id']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="user-table-email"><?php echo htmlspecialchars($u['email']); ?></span></td>
                                <td>
                                    <span class="role-badge role-<?php echo $u['role']; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $u['total_properties']; ?></td>
                                <td><?php echo $u['total_bookings']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons" style="justify-content:center;">
                                        <button class="btn-action btn-view" onclick="viewUser(<?php echo $u['id']; ?>)">View</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=26.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
        function filterUsers(role, el) {
            const rows = document.querySelectorAll('.properties-table tbody tr');
            const buttons = document.querySelectorAll('.properties-table-container .filter-tab');
            buttons.forEach(btn => btn.classList.remove('active'));
            if (el) el.classList.add('active');
            
            rows.forEach(row => {
                if (role === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.role === role ? '' : 'none';
                }
            });
        }

        function viewUser(id) {
            if (!id) return;
            window.location.href = 'view-user.php?id=' + encodeURIComponent(id);
        }
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
                    var attrs = '';
                    if (link) attrs = ' data-link="'+esc(link)+'" style="cursor:pointer"';
                    return '<div class="adm-notif-item'+(unread?' unread':'')+'"'+attrs+'>'+ 
                        '<div class="adm-notif-item-body"><strong>'+esc(n.title)+'</strong>'+ (body?'<small>'+esc(n.body)+'</small>':'')+'</div>'+ '<div class="adm-notif-item-actions">'+ (unread?'<button class="adm-notif-mark" data-mark="'+esc(n.id)+'">Mark read</button>':'')+'</div></div>';
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
