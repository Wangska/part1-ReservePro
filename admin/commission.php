<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/booking_money.php';

requireLogin();
$user = getCurrentUser();

if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();

$result = $conn->query("
    SELECT 
        b.id,
        b.booking_date,
        b.check_in,
        b.check_out,
        b.total_price,
        b.status,
        b.guests,
        p.title AS property_name,
        h.first_name AS host_first_name,
        h.last_name AS host_last_name,
        g.first_name AS guest_first_name,
        g.last_name AS guest_last_name,
        g.email AS guest_email,
        DATEDIFF(b.check_out, b.check_in) AS nights
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users h ON p.host_id = h.id
    JOIN users g ON b.guest_id = g.id
    ORDER BY b.booking_date DESC
");
$bookings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$commission_earned   = 0.0;
$commission_pending  = 0.0;
$commission_cancelled = 0.0;
$gross_earned        = 0.0;
$gross_pending       = 0.0;

foreach ($bookings as $b) {
    $total = (float) $b['total_price'];
    $fee   = reservepro_platform_commission_from_total($total);
    $st    = $b['status'];

    if ($st === 'pending') {
        $commission_pending += $fee;
        $gross_pending += $total;
        continue;
    }
    if ($st === 'confirmed' || $st === 'completed') {
        $commission_earned += $fee;
        $gross_earned += $total;
        continue;
    }
    if ($st === 'cancelled') {
        $commission_cancelled += $fee;
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
    <title>Platform Commission - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        .filter-buttons { display: flex; gap: 8px; flex-wrap: wrap; width: 100%; }
        .booking-id { font-family: 'Courier New', monospace; color: #D4A574; font-weight: 600; }
        .col-commission { color: #38bdf8; font-weight: 700; }
        .col-host { color: #A7F3D0; }

        .admin-commission-page .earnings-table th,
        .admin-commission-page .earnings-table td {
            text-align: center;
            vertical-align: middle;
            border-left: none !important;
            border-right: none !important;
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
<body class="dashboard-page admin-page admin-clean-page admin-commission-page">
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
                <a href="commission.php" class="nav-item active">
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

                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <main class="host-main">
            <div class="properties-header admin-page-hero">
                <div class="admin-page-hero-content">
                    <h1>Platform Commission</h1>
                    <p class="subtitle"></p>
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
        <div>

            <div class="commission-stats admin-metric-grid">
                <div class="commission-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-emerald"><i class="fa-solid fa-coins" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Commission Earned</div>
                        <div class="stat-value">₱<?php echo number_format($commission_earned, 2); ?></div>
                    </div>
                </div>
                <div class="commission-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-amber"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Commission Pending</div>
                        <div class="stat-value">₱<?php echo number_format($commission_pending, 2); ?></div>
                    </div>
                </div>
                <div class="commission-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-sky"><i class="fa-solid fa-receipt" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Gross Paid</div>
                        <div class="stat-value">₱<?php echo number_format($gross_earned, 2); ?></div>
                    </div>
                </div>
                <div class="commission-stat-card admin-metric-card">
                    <div class="admin-metric-icon is-red"><i class="fa-solid fa-ban" aria-hidden="true"></i></div>
                    <div class="admin-metric-copy">
                        <div class="stat-label">Cancelled Commission</div>
                        <div class="stat-value">₱<?php echo number_format($commission_cancelled, 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="earnings-table-container admin-surface">
                <div class="table-header admin-surface-header">
                    <div>
                        <h2>Per-Booking Breakdown</h2>
                        <p></p>
                    </div>
                    <div class="filter-buttons">
                        <button type="button" class="filter-btn active" data-filter="all">All</button>
                        <button type="button" class="filter-btn" data-filter="earned">Earned</button>
                        <button type="button" class="filter-btn" data-filter="pending">Pending</button>
                        <button type="button" class="filter-btn" data-filter="cancelled">Cancelled</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="empty-earnings admin-empty-state">
                        <span class="admin-empty-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                        <h3>No bookings</h3>
                        <p>Commission will appear when there are reservations.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-scroll-x">
                        <table class="earnings-table">
                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Property</th>
                                    <th>Host</th>
                                    <th>Guest paid</th>
                                    <th>Host share</th>
                                    <th>Commission (10%)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b):
                                    $total = (float) $b['total_price'];
                                    $comm  = reservepro_platform_commission_from_total($total);
                                    $host  = reservepro_host_share_from_total($total);
                                    ?>
                                    <tr data-status="<?php echo htmlspecialchars($b['status']); ?>">
                                        <td class="booking-id">BK-<?php echo str_pad((string) $b['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($b['property_name']); ?></td>
                                        <td><?php echo htmlspecialchars($b['host_first_name'] . ' ' . $b['host_last_name']); ?></td>
                                        <td>₱<?php echo number_format($total, 2); ?></td>
                                        <td class="col-host">₱<?php echo number_format($host, 2); ?></td>
                                        <td class="col-commission">₱<?php echo number_format($comm, 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($b['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($b['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=27.0"></script>
    <script src="../assets/js/admin-view-site-confirm.js?v=1.0"></script>
    <script>
        (function () {
            var buttons = document.querySelectorAll('.earnings-table-container .filter-btn');
            var rows = document.querySelectorAll('.earnings-table tbody tr');
            if (!buttons.length || !rows.length) return;
            function applyFilter(mode) {
                rows.forEach(function (row) {
                    var s = row.getAttribute('data-status') || '';
                    if (mode === 'all') { row.style.display = ''; return; }
                    if (mode === 'earned') {
                        row.style.display = (s === 'confirmed' || s === 'completed') ? '' : 'none';
                        return;
                    }
                    if (mode === 'pending') { row.style.display = s === 'pending' ? '' : 'none'; return; }
                    if (mode === 'cancelled') { row.style.display = s === 'cancelled' ? '' : 'none'; }
                });
            }
            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    buttons.forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    applyFilter(btn.getAttribute('data-filter') || 'all');
                });
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
</body>
</html>
