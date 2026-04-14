<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';
require_once __DIR__ . '/../config/refunds.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

requireLogin();
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
$type = strtolower(trim((string)($_GET['type'] ?? 'cancellation')));
if ($bookingId <= 0 || !in_array($type, ['cancellation', 'issue'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
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
        p.cancellation_policy,
        p.title AS property_title
    FROM bookings b
    JOIN properties p ON p.id = b.property_id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Booking not found']);
    exit();
}

// Ownership: guest can only preview their own bookings; admin can preview any.
$isAdmin = (($user['role'] ?? '') === 'admin');
if (!$isAdmin && (int)$booking['guest_id'] !== (int)$user['id']) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit();
}

// Prevent duplicates: if there is an active refund request, return it.
$activeStmt = $conn->prepare("
    SELECT id, status, request_type, refund_percent, refund_amount
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

$policy = (string)($booking['cancellation_policy'] ?? 'moderate');
$total = (float)($booking['total_price'] ?? 0);

$resp = [
    'ok' => true,
    'booking' => [
        'booking_id' => (int)$booking['id'],
        'user_id' => (int)$booking['guest_id'],
        'property_id' => (int)$booking['property_id'],
        'check_in_date' => (string)$booking['check_in'],
        'check_out_date' => (string)$booking['check_out'],
        'total_amount' => $total,
        'booking_date' => (string)$booking['booking_date'],
        'status' => (string)$booking['status'],
        'property_title' => (string)($booking['property_title'] ?? ''),
        'policy' => $policy,
    ],
    'active_request' => $active ? [
        'id' => (int)$active['id'],
        'status' => (string)$active['status'],
        'request_type' => (string)$active['request_type'],
        'refund_percent' => (int)$active['refund_percent'],
        'refund_amount' => (float)$active['refund_amount'],
    ] : null,
];

if ($type === 'cancellation') {
    $preview = reservepro_refund_preview_cancellation(
        $policy,
        (string)$booking['booking_date'],
        (string)$booking['check_in'],
        $total
    );
    $resp['preview'] = [
        'refund_percent' => (int)$preview['percent'],
        'refund_amount' => (float)$preview['amount'],
        'warning' => (string)$preview['warning'],
        'rule' => (string)$preview['rule'],
    ];
} else {
    $elig = reservepro_issue_eligibility((string)$booking['check_in']);
    $resp['issue'] = [
        'eligible' => (bool)$elig['eligible'],
        'deadline' => (string)$elig['deadline'],
        'rule' => (string)$elig['rule'],
    ];
}

$conn->close();
echo json_encode($resp);

