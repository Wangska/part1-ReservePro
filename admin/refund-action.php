<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';
require_once __DIR__ . '/../config/booking_money.php';

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    header('Location: ../home.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: refunds.php');
    exit();
}

$id = isset($_POST['refund_request_id']) ? (int)$_POST['refund_request_id'] : 0;
$action = strtolower(trim((string)($_POST['action'] ?? '')));
$overridePercent = isset($_POST['override_percent']) ? (int)$_POST['override_percent'] : -1;
$note = trim((string)($_POST['note'] ?? ''));
if (strlen($note) > 1000) $note = substr($note, 0, 1000);

if ($id <= 0) {
    header('Location: refunds.php?error=invalid');
    exit();
}

$allowed = ['approve','reject','processing','completed','override'];
if (!in_array($action, $allowed, true)) {
    header('Location: refund.php?id=' . $id . '&error=bad_action');
    exit();
}

if ($action === 'override') {
    if ($overridePercent < 0 || $overridePercent > 100) {
        header('Location: refund.php?id=' . $id . '&error=bad_override');
        exit();
    }
    if ($note === '') {
        header('Location: refund.php?id=' . $id . '&error=note_required');
        exit();
    }
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare("
    SELECT rr.id, rr.status, rr.refund_percent, rr.refund_amount, rr.booking_id, rr.property_id,
           b.total_price,
           p.host_id
    FROM refund_requests rr
    JOIN bookings b ON b.id = rr.booking_id
    JOIN properties p ON p.id = rr.property_id
    WHERE rr.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) {
    $conn->close();
    header('Location: refunds.php?error=notfound');
    exit();
}

$fromStatus = (string)($r['status'] ?? '');
$toStatus = $fromStatus;

$conn->begin_transaction();
try {
    $meta = [];

    if ($action === 'approve') {
        $toStatus = 'approved';
        $up = $conn->prepare("UPDATE refund_requests SET status = 'approved' WHERE id = ?");
        $up->bind_param('i', $id);
        $up->execute();
        $up->close();
    } elseif ($action === 'reject') {
        $toStatus = 'rejected';
        $up = $conn->prepare("UPDATE refund_requests SET status = 'rejected' WHERE id = ?");
        $up->bind_param('i', $id);
        $up->execute();
        $up->close();
    } elseif ($action === 'processing') {
        $toStatus = 'processing';
        $up = $conn->prepare("UPDATE refund_requests SET status = 'processing' WHERE id = ?");
        $up->bind_param('i', $id);
        $up->execute();
        $up->close();
    } elseif ($action === 'completed') {
        $toStatus = 'completed';
        $up = $conn->prepare("UPDATE refund_requests SET status = 'completed' WHERE id = ?");
        $up->bind_param('i', $id);
        $up->execute();
        $up->close();

        // Deduct host money when refund is completed (host share only).
        $hostId = (int)($r['host_id'] ?? 0);
        $total = (float)($r['total_price'] ?? 0);
        $pct = (int)($r['refund_percent'] ?? 0);
        $hostShare = reservepro_host_share_from_total($total);
        $deduct = round(max(0, $hostShare) * (max(0, min(100, $pct)) / 100), 2);

        if ($hostId > 0 && $deduct > 0) {
            // host_ledger has unique key on (refund_request_id, entry_type) to prevent duplicates
            $note2 = 'Refund completed: deduct host share (' . $pct . '% of host share)';
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
    } else { // override
        $total = (float)($r['total_price'] ?? 0);
        $amt = round(max(0, $total) * ($overridePercent / 100), 2);
        $toStatus = 'pending'; // stays pending for processing/approval sequence
        $up = $conn->prepare("
            UPDATE refund_requests
            SET admin_override_percent = ?,
                admin_override_note = ?,
                refund_percent = ?,
                refund_amount = ?,
                status = 'pending'
            WHERE id = ?
        ");
        $up->bind_param('isidi', $overridePercent, $note, $overridePercent, $amt, $id);
        $up->execute();
        $up->close();
        $meta = ['override_percent' => $overridePercent, 'override_amount' => $amt];
    }

    $metaJson = $meta ? json_encode($meta) : null;
    $log = $conn->prepare("
        INSERT INTO refund_logs (refund_request_id, actor_user_id, actor_role, action, from_status, to_status, note, meta_json)
        VALUES (?, ?, 'admin', ?, ?, ?, ?, ?)
    ");
    $act = 'admin_' . $action;
    $log->bind_param('iisssss', $id, $user['id'], $act, $fromStatus, $toStatus, $note, $metaJson);
    $log->execute();
    $log->close();

    $conn->commit();
    $conn->close();
    header('Location: refund.php?id=' . $id . '&saved=1');
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header('Location: refund.php?id=' . $id . '&error=save_failed');
    exit();
}

