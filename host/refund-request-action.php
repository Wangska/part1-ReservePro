<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';
require_once __DIR__ . '/../config/booking_money.php';

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
$note = trim((string)($_POST['note'] ?? ''));
if (strlen($note) > 1000) $note = substr($note, 0, 1000);

if ($id <= 0 || !in_array($decision, ['approve','reject','complete'], true)) {
    header('Location: refund-requests.php?error=invalid');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

// Ensure this refund request belongs to one of host's properties and is still reviewable
$stmt = $conn->prepare("
    SELECT rr.id, rr.status, rr.host_decision, rr.host_decision_percent, rr.refund_percent, rr.refund_amount, rr.booking_id, rr.property_id,
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

$total = (float)($r['total_price'] ?? 0);
$hostDecision = 'none';
$hostPct = null;

if ($decision === 'complete') {
    // Idempotent completion: use the already decided percent on the request.
    $hostDecision = (string)($r['host_decision'] ?? 'none');
    $hostPct = (isset($r['host_decision_percent']) && $r['host_decision_percent'] !== null)
        ? (int)$r['host_decision_percent']
        : (int)($r['refund_percent'] ?? 0);
    if ($hostPct < 0) $hostPct = 0;
    if ($hostPct > 100) $hostPct = 100;
} elseif ($decision === 'approve') {
    // Approve the current suggested percent on the request.
    $hostDecision = 'approve_partial';
    $hostPct = (int)($r['refund_percent'] ?? 0);
    if ($hostPct < 0) $hostPct = 0;
    if ($hostPct > 100) $hostPct = 100;
} else {
    $hostDecision = 'reject';
    $hostPct = 0;
}

$hostAmount = round(max(0, $total) * ((int)$hostPct / 100), 2);

$conn->begin_transaction();
try {
    if ($decision !== 'complete') {
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
    }

    // Host-authority model:
    // - Reject => rejected
    // - Approve => completed immediately (host transacts the refund)
    $toStatus = ($hostDecision === 'reject') ? 'rejected' : 'completed';
    $st = $conn->prepare("UPDATE refund_requests SET status = ? WHERE id = ?");
    $st->bind_param('si', $toStatus, $id);
    $st->execute();
    $st->close();

    $metaArr = [
        'host_decision' => $hostDecision,
        'host_percent' => $hostPct,
        'host_amount' => $hostAmount,
    ];

    // Deduct host money when refund is completed (host share only).
    if ($toStatus === 'completed') {
        $hostId = (int)($r['host_id'] ?? 0);
        $pct = (int)$hostPct;
        $hostShare = reservepro_host_share_from_total((float)$total);
        $deduct = round(max(0, $hostShare) * (max(0, min(100, $pct)) / 100), 2);

        if ($hostId > 0 && $deduct > 0) {
            $note2 = 'Refund completed by host: deduct host share (' . $pct . '% of host share)';
            $ins = $conn->prepare("
                INSERT IGNORE INTO host_ledger (host_id, booking_id, refund_request_id, entry_type, amount, note)
                VALUES (?, ?, ?, 'refund_debit', ?, ?)
            ");
            $bookingId = (int)($r['booking_id'] ?? 0);
            $neg = -1 * $deduct;
            $ins->bind_param('iiids', $hostId, $bookingId, $id, $neg, $note2);
            $ins->execute();
            $ins->close();
        }

        // Platform commission refund record (informational): if refund is 99%+, platform returns its 9% commission.
        if ($pct >= 99) {
            $metaArr['platform_commission_refund'] = reservepro_platform_commission_from_total((float)$total);
            $metaArr['platform_commission_refund_rule'] = 'pct>=99 => refund 9% commission';
        }
    }

    $meta = json_encode($metaArr);
    $log = $conn->prepare("
        INSERT INTO refund_logs (refund_request_id, actor_user_id, actor_role, action, from_status, to_status, note, meta_json)
        VALUES (?, ?, 'host', ?, ?, ?, ?, ?)
    ");
    $act = ($decision === 'complete') ? 'host_complete_refund' : 'host_decision';
    $note2 = $note;
    if ($decision === 'complete' && $note2 === '') {
        $note2 = 'Host marked refund as completed.';
    }
    $log->bind_param('iisssss', $id, $user['id'], $act, $currentStatus, $toStatus, $note2, $meta);
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

