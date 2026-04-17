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

$days = isset($_GET['days']) ? (int) $_GET['days'] : 90;
if (!in_array($days, [30, 90, 180, 365], true)) {
    $days = 90;
}

$conn = getDBConnection();
initializeHostTables();

// Note: bookings table uses booking_date as created timestamp.
// Requirement says created_at — we treat booking_date as booking creation time.

// Daily bookings (exclude cancelled)
$daily = [];
$stmt = $conn->prepare("
    SELECT DATE(booking_date) AS d, COUNT(*) AS c
    FROM bookings
    WHERE status <> 'cancelled'
      AND booking_date >= (NOW() - INTERVAL ? DAY)
    GROUP BY DATE(booking_date)
    ORDER BY d ASC
");
$stmt->bind_param('i', $days);
$stmt->execute();
$daily = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Weekly bookings (ISO week, exclude cancelled)
$weekly = [];
$stmt = $conn->prepare("
    SELECT YEARWEEK(booking_date, 3) AS yw, MIN(DATE(booking_date)) AS week_start, COUNT(*) AS c
    FROM bookings
    WHERE status <> 'cancelled'
      AND booking_date >= (NOW() - INTERVAL ? DAY)
    GROUP BY YEARWEEK(booking_date, 3)
    ORDER BY yw ASC
");
$stmt->bind_param('i', $days);
$stmt->execute();
$weekly = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Monthly bookings (exclude cancelled)
$monthly = [];
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(booking_date, '%Y-%m') AS ym, COUNT(*) AS c
    FROM bookings
    WHERE status <> 'cancelled'
      AND booking_date >= (NOW() - INTERVAL ? DAY)
    GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
    ORDER BY ym ASC
");
$stmt->bind_param('i', $days);
$stmt->execute();
$monthly = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Occupancy (stay-based): active bookings per day in range (exclude cancelled)
$occ = [];
$occStmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM bookings
    WHERE status <> 'cancelled'
      AND check_in <= ?
      AND check_out > ?
");

$start = new DateTimeImmutable('today');
$start = $start->sub(new DateInterval('P' . ($days - 1) . 'D'));
$end = new DateTimeImmutable('today');

for ($d = $start; $d <= $end; $d = $d->add(new DateInterval('P1D'))) {
    $day = $d->format('Y-m-d');
    $occStmt->bind_param('ss', $day, $day);
    $occStmt->execute();
    $row = $occStmt->get_result()->fetch_assoc();
    $occ[] = ['d' => $day, 'c' => (int)($row['c'] ?? 0)];
}
$occStmt->close();

$conn->close();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function toChart(array $rows, string $xKey, string $yKey): array {
    $labels = [];
    $values = [];
    foreach ($rows as $r) {
        $labels[] = (string)($r[$xKey] ?? '');
        $values[] = (int)($r[$yKey] ?? 0);
    }
    return [$labels, $values];
}

[$dailyLabels, $dailyValues] = toChart($daily, 'd', 'c');
[$weeklyLabels, $weeklyValues] = toChart($weekly, 'week_start', 'c');
[$monthlyLabels, $monthlyValues] = toChart($monthly, 'ym', 'c');
[$occLabels, $occValues] = toChart($occ, 'd', 'c');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Analytics - Admin - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=25.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
        body.admin-page:not(.light-mode) {
            background: #06090F !important;
        }
        body.admin-page::before,
        body.admin-page::after {
            display: none !important;
        }
        .analytics-hero {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            border: 1px solid rgba(212, 165, 116, 0.22);
            border-radius: 22px;
            padding: 24px 26px;
            margin-bottom: 18px;
            box-shadow: 0 22px 50px rgba(0,0,0,0.26);
            display:flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
        }
        .analytics-hero h1 { margin: 0 0 6px; color:#fff !important; }
        .analytics-hero p { margin: 0; color:#CBD5E1 !important; line-height:1.6; }
        .range-pills { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .range-pill {
            display: inline-flex;
            align-items: center;
            padding: 9px 14px;
            min-height: 40px;
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.16);
            background: rgba(255,255,255,0.04);
            color: #CBD5E1;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        .range-pill:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(212,165,116,0.38);
            color: #FFFFFF;
            transform: translateY(-1px);
        }
        .range-pill.active {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border-color: transparent;
            box-shadow: 0 10px 24px rgba(212,165,116,0.22);
        }
        .charts-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 16px;
        }
        .chart-card {
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(17, 24, 39, 0.86);
            box-shadow: 0 18px 36px rgba(0,0,0,0.18);
            padding: 16px;
        }
        .chart-card h2 { margin:0 0 10px; color:#fff !important; font-size:16px; }
        .chart-wrap { height: 260px; }
        .chart-wrap canvas { width:100% !important; height:100% !important; }

        .table-card {
            margin-top: 16px;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(17, 24, 39, 0.86);
            box-shadow: 0 18px 36px rgba(0,0,0,0.18);
            overflow:hidden;
        }
        .table-card .table-header {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(148,163,184,0.14);
            display:flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }
        .table-card .table-header h2 { margin:0; color:#fff !important; font-size:16px; }
        .table-card .table-header p { margin:0; color:#94A3B8 !important; font-size:13px; }
        .mini {
            width:100%;
            border-collapse: collapse;
        }
        .mini th, .mini td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(148,163,184,0.12);
            font-size: 13px;
        }
        .mini th { text-align:center; color:#CBD5E1 !important; font-weight:800; letter-spacing:0.04em; text-transform:uppercase; font-size:11px; }
        .mini td { color:#F1F5F9 !important; font-weight:600; text-align:center; }

        body.light-mode .chart-card,
        body.light-mode .table-card {
            background: #FFFFFF !important;
            border-color: #E0E0E0 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08) !important;
        }
        body.light-mode .chart-card h2,
        body.light-mode .table-card .table-header h2 { color:#0f172a !important; }
        body.light-mode .table-card .table-header p { color:#475569 !important; }
        body.light-mode .mini th { color:#334155 !important; border-bottom-color:#E2E8F0 !important; text-align:center; }
        body.light-mode .mini td { color:#0f172a !important; border-bottom-color:#F1F5F9 !important; text-align:center; }
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
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                <span>Dashboard</span>
            </a>
            <a href="analytics.php" class="nav-item active">
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
            <a href="commission.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
                <span>Commission</span>
            </a>
            <a href="geocode-all-properties.php" class="nav-item">
                <span class="nav-icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                <span>Geocode Properties</span>
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
                    <div class="user-name"><?php echo h($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>

            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </aside>

    <main class="host-main">
        <div class="analytics-hero">
            <div>
                <h1><i class="fa-solid fa-chart-simple" style="margin-right:10px;"></i>Booking analytics</h1>
            </div>
            <div class="range-pills">
                <?php foreach ([30, 90, 180, 365] as $opt): ?>
                    <a class="range-pill <?php echo $opt === $days ? 'active' : ''; ?>" href="analytics.php?days=<?php echo $opt; ?>">
                        <?php echo $opt; ?> days
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h2>Daily bookings</h2>
                <div class="chart-wrap"><canvas id="dailyChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Weekly bookings</h2>
                <div class="chart-wrap"><canvas id="weeklyChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Monthly bookings</h2>
                <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Occupancy (active stays per day)</h2>
                <div class="chart-wrap"><canvas id="occChart"></canvas></div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div>
                    <h2>Raw daily counts</h2>
                </div>
                <p><?php echo count($dailyLabels); ?> day(s) in range</p>
            </div>
            <div style="overflow-x:auto;">
                <table class="mini">
                    <thead><tr><th>Date</th><th>Bookings</th><th>Occupancy</th></tr></thead>
                    <tbody>
                    <?php
                        $dailyMap = [];
                        foreach ($daily as $r) $dailyMap[$r['d']] = (int)$r['c'];
                        $occMap = [];
                        foreach ($occ as $r) $occMap[$r['d']] = (int)$r['c'];
                        foreach ($occ as $r):
                            $d = $r['d'];
                    ?>
                        <tr>
                            <td><?php echo h($d); ?></td>
                            <td><?php echo (int)($dailyMap[$d] ?? 0); ?></td>
                            <td><?php echo (int)($occMap[$d] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="../assets/js/theme-toggle.js?v=26.0"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const dailyLabels = <?php echo json_encode($dailyLabels); ?>;
    const dailyValues = <?php echo json_encode($dailyValues); ?>;
    const weeklyLabels = <?php echo json_encode($weeklyLabels); ?>;
    const weeklyValues = <?php echo json_encode($weeklyValues); ?>;
    const monthlyLabels = <?php echo json_encode($monthlyLabels); ?>;
    const monthlyValues = <?php echo json_encode($monthlyValues); ?>;
    const occLabels = <?php echo json_encode($occLabels); ?>;
    const occValues = <?php echo json_encode($occValues); ?>;

    function gridColor() {
        return document.body.classList.contains('light-mode') ? 'rgba(15, 23, 42, 0.08)' : 'rgba(148, 163, 184, 0.12)';
    }
    function tickColor() {
        return document.body.classList.contains('light-mode') ? '#334155' : '#CBD5E1';
    }
    function buildLine(ctx, labels, data, label, color) {
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label,
                    data,
                    borderColor: color,
                    backgroundColor: color.replace('1)', '0.14)'),
                    fill: true,
                    tension: 0.25,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: {
                        grid: { color: gridColor() },
                        ticks: { color: tickColor(), maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor() },
                        ticks: { color: tickColor(), precision: 0 }
                    }
                }
            }
        });
    }

    const charts = [];
    charts.push(buildLine(document.getElementById('dailyChart'), dailyLabels, dailyValues, 'Daily', 'rgba(56, 189, 248, 1)'));
    charts.push(buildLine(document.getElementById('weeklyChart'), weeklyLabels, weeklyValues, 'Weekly', 'rgba(212, 165, 116, 1)'));
    charts.push(buildLine(document.getElementById('monthlyChart'), monthlyLabels, monthlyValues, 'Monthly', 'rgba(34, 197, 94, 1)'));
    charts.push(buildLine(document.getElementById('occChart'), occLabels, occValues, 'Occupancy', 'rgba(99, 102, 241, 1)'));

    // Repaint chart colors when theme toggles
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.theme-toggle')) return;
        setTimeout(function () {
            charts.forEach(function (c) {
                c.options.scales.x.grid.color = gridColor();
                c.options.scales.y.grid.color = gridColor();
                c.options.scales.x.ticks.color = tickColor();
                c.options.scales.y.ticks.color = tickColor();
                c.update();
            });
        }, 80);
    });
</script>
</body>
</html>

