<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';
require_once __DIR__ . '/config/refunds.php';
require_once __DIR__ . '/config/notifications.php';

requireLogin();
$user = getCurrentUser();

if (!$user || ($user['role'] ?? '') !== 'guest') {
    header('Location: home.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my-bookings.php');
    exit();
}

$bookingId = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$reason = trim((string)($_POST['reason'] ?? ''));
$refundAck = isset($_POST['refund_ack']) ? trim((string)$_POST['refund_ack']) : '0';
if ($bookingId <= 0) {
    header('Location: my-bookings.php?error=invalid_booking');
    exit();
}
if ($refundAck !== '1') {
    header('Location: my-bookings.php?error=refund_ack_required');
    exit();
}
if (strlen($reason) > 255) {
    $reason = substr($reason, 0, 255);
}

$conn = getDBConnection();
initializeHostTables();

// Load booking + policy + property host
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
        p.host_id
    FROM bookings b
    JOIN properties p ON p.id = b.property_id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking || (int)$booking['guest_id'] !== (int)$user['id']) {
    $conn->close();
    header('Location: my-bookings.php?error=not_found');
    exit();
}

// Only allow cancellation if confirmed
if ((string)$booking['status'] !== 'confirmed') {
    $conn->close();
    header('Location: my-bookings.php?error=not_confirmed');
    exit();
}

// Prevent cancellation if already has an active refund request
$activeStmt = $conn->prepare("
    SELECT id, status
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
if ($active) {
    $conn->close();
    header('Location: my-bookings.php?error=already_requested');
    exit();
}

$policy = (string)($booking['cancellation_policy'] ?? 'moderate');
$total = (float)($booking['total_price'] ?? 0);

$preview = reservepro_refund_preview_cancellation(
    $policy,
    (string)$booking['booking_date'],
    (string)$booking['check_in'],
    $total
);

$refundPercent = (int)$preview['percent'];
$refundAmount = (float)$preview['amount'];

// Strict policy: do not auto-approve; keep at 0% and route to review.
$refundStatus = 'pending';
if (strtolower($policy) === 'strict') {
    $refundPercent = 0;
    $refundAmount = 0.0;
    $refundStatus = 'pending_review';
} elseif ($refundPercent >= 99) {
    // Optional enhancement: auto-approve highest cancellation refund tier when eligible
    $refundStatus = 'approved';
}

$conn->begin_transaction();
try {
    // Update booking status to cancelled
    $up = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND guest_id = ? AND status = 'confirmed'");
    $up->bind_param('ii', $bookingId, $user['id']);
    $up->execute();
    if ($up->affected_rows !== 1) {
        $up->close();
        throw new Exception('Booking update failed');
    }
    $up->close();

    // Record cancellation audit
    $insC = $conn->prepare("
        INSERT INTO booking_cancellations (booking_id, user_id, policy, refund_percent_preview, refund_amount_preview, reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insC->bind_param('iisids', $bookingId, $user['id'], $policy, $refundPercent, $refundAmount, $reason);
    $insC->execute();
    $insC->close();

    // Create refund request (cancellation)
    $rr = $conn->prepare("
        INSERT INTO refund_requests (
            booking_id, requester_user_id, property_id, request_type,
            policy, refund_percent, refund_amount, currency,
            status
        ) VALUES (?, ?, ?, 'cancellation', ?, ?, ?, 'PHP', ?)
    ");
    $propertyId = (int)$booking['property_id'];
    $rr->bind_param('iiisids', $bookingId, $user['id'], $propertyId, $policy, $refundPercent, $refundAmount, $refundStatus);
    $rr->execute();
    $refundRequestId = (int)$conn->insert_id;
    $rr->close();

    // Log action
    $meta = json_encode([
        'policy' => $policy,
        'preview_rule' => $preview['rule'] ?? '',
        'warning' => $preview['warning'] ?? '',
        'refund_percent' => $refundPercent,
        'refund_amount' => $refundAmount,
    ]);
    $log = $conn->prepare("
        INSERT INTO refund_logs (refund_request_id, actor_user_id, actor_role, action, from_status, to_status, note, meta_json)
        VALUES (?, ?, 'guest', 'create_cancellation_refund_request', NULL, ?, ?, ?)
    ");
    $note = $reason !== '' ? ('Cancellation reason: ' . $reason) : null;
    $log->bind_param('iisss', $refundRequestId, $user['id'], $refundStatus, $note, $meta);
    $log->execute();
    $log->close();

    // Optional: mark pending payment as cancelled (simulation)
    $pay = $conn->prepare("UPDATE payments SET status = 'cancelled' WHERE booking_id = ? AND status = 'pending'");
    $pay->bind_param('i', $bookingId);
    $pay->execute();
    $pay->close();

    $conn->commit();
    $conn->close();

    // Notifications: host + admins (sent after successful commit)
    $hostId = (int)($booking['host_id'] ?? 0);
    $bookingLabel = '#' . (int)$bookingId;
    if ($hostId > 0) {
        reservepro_notification_create(
            $hostId,
            'booking_cancelled',
            'Booking cancelled',
            'A guest cancelled booking ' . $bookingLabel . '.',
            '../host/bookings.php'
        );
    }
    reservepro_notification_notify_admins(
        'booking_cancelled',
        'Booking cancelled',
        'A guest cancelled booking ' . $bookingLabel . '.',
        '../admin/bookings.php'
    );

    header('Location: my-bookings.php?cancelled=1');
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header('Location: my-bookings.php?error=cancel_failed');
    exit();
}

