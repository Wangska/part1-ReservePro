<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'host') {
    header('Location: ../home.php');
    exit();
}
if (empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: refund-requests.php');
    exit();
}

$id = isset($_POST['refund_request_id']) ? (int)$_POST['refund_request_id'] : 0;
$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
$partial = isset($_POST['partial_percent']) ? (int)$_POST['partial_percent'] : 0;
$note = trim((string)($_POST['note'] ?? ''));
if (strlen($note) > 1000) $note = substr($note, 0, 1000);

if ($id <= 0 || !in_array($decision, ['approve_full','approve_partial','approve_50','reject'], true)) {
    header('Location: refund-requests.php?error=invalid');
    exit();
}
if (($decision === 'approve_partial' || $decision === 'approve_50') && ($partial < 1 || $partial > 100)) {
    header('Location: refund-request.php?id=' . $id . '&error=bad_partial');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

// Ensure this refund request belongs to one of host's properties and is still reviewable
$stmt = $conn->prepare("
    SELECT rr.id, rr.status, rr.host_decision, rr.refund_percent, rr.refund_amount, rr.booking_id, rr.property_id,
           b.total_price,
           p.host_id
    FROM refund_requests rr
    JOIN bookings b ON b.id = rr.booking_id
    JOIN properties p ON p.id = rr.property_id
    WHERE rr.id = ? AND p.host_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) {
    $conn->close();
    header('Location: refund-requests.php?error=notfound');
    exit();
}

$currentStatus = (string)($r['status'] ?? '');
if (!in_array($currentStatus, ['pending_review','pending'], true)) {
    $conn->close();
    header('Location: refund-request.php?id=' . $id . '&error=not_reviewable');
    exit();
}

$suggestedPct = (int)($r['refund_percent'] ?? 0);
if ($suggestedPct !== 50) {
    $conn->close();
    header('Location: refund-request.php?id=' . $id . '&error=not_allowed');
    exit();
}

$total = (float)($r['total_price'] ?? 0);
$hostDecision = 'none';
$hostPct = null;

if ($decision === 'approve_full') {
    $hostDecision = 'approve_full';
    $hostPct = 100;
} elseif ($decision === 'approve_partial' || $decision === 'approve_50') {
    $hostDecision = 'approve_partial';
    $hostPct = ($decision === 'approve_50') ? 50 : $partial;
} else {
    $hostDecision = 'reject';
    $hostPct = 0;
}

$hostAmount = round(max(0, $total) * ((int)$hostPct / 100), 2);

$conn->begin_transaction();
try {
    // Update refund_request host decision + suggested percent/amount from host
    $up = $conn->prepare("
        UPDATE refund_requests
        SET host_decision = ?,
            host_decision_percent = ?,
            host_decision_note = ?,
            refund_percent = ?,
            refund_amount = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $up->bind_param('sisidi', $hostDecision, $hostPct, $note, $hostPct, $hostAmount, $id);
    $up->execute();
    $up->close();

    // Move status to pending (awaiting admin finalization) unless rejected then rejected
    $toStatus = ($hostDecision === 'reject') ? 'rejected' : 'pending';
    $st = $conn->prepare("UPDATE refund_requests SET status = ? WHERE id = ?");
    $st->bind_param('si', $toStatus, $id);
    $st->execute();
    $st->close();

    $meta = json_encode([
        'host_decision' => $hostDecision,
        'host_percent' => $hostPct,
        'host_amount' => $hostAmount,
    ]);
    $log = $conn->prepare("
        INSERT INTO refund_logs (refund_request_id, actor_user_id, actor_role, action, from_status, to_status, note, meta_json)
        VALUES (?, ?, 'host', 'host_decision', ?, ?, ?, ?)
    ");
    $log->bind_param('iissss', $id, $user['id'], $currentStatus, $toStatus, $note, $meta);
    $log->execute();
    $log->close();

    $conn->commit();
    $conn->close();
    header('Location: refund-request.php?id=' . $id . '&updated=1');
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header('Location: refund-request.php?id=' . $id . '&error=save_failed');
    exit();
}

