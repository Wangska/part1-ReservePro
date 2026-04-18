<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';
require_once __DIR__ . '/config/refunds.php';

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

$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$issueType = strtolower(trim((string)($_POST['issue_type'] ?? '')));
$desc = trim((string)($_POST['description'] ?? ''));
if ($bookingId <= 0 || $issueType === '' || $desc === '') {
    header('Location: request-refund-issue.php?booking_id=' . $bookingId . '&error=missing');
    exit();
}
if (strlen($desc) > 2000) {
    $desc = substr($desc, 0, 2000);
}

$allowedTypes = ['dirty_room','wrong_listing','safety_issue','missing_amenities','host_no_show','other'];
if (!in_array($issueType, $allowedTypes, true)) {
    header('Location: request-refund-issue.php?booking_id=' . $bookingId . '&error=bad_type');
    exit();
}

function save_refund_evidence(array $file, int $refundRequestId, int $userId, int $idx, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Failed to upload evidence photo.'; return null; }

    $maxSize = 6 * 1024 * 1024;
    $minSize = 40 * 1024;
    if (($file['size'] ?? 0) > $maxSize) { $errors[] = 'Evidence photo is too large (max 6MB).'; return null; }
    if (($file['size'] ?? 0) < $minSize) { $errors[] = 'Evidence photo looks too small. Please upload a clearer photo.'; return null; }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
    if (!in_array($ext, $allowed, true)) { $errors[] = 'Evidence must be an image (JPG, PNG, WEBP, or AVIF).'; return null; }

    $tmp = $file['tmp_name'] ?? '';
    $img = @getimagesize($tmp);
    if ($img && !empty($img[0]) && !empty($img[1])) {
        $w = (int)$img[0];
        $h = (int)$img[1];
        if ($w < 900 || $h < 600) { $errors[] = 'Evidence image resolution is too low (min 900×600).'; return null; }
    } elseif ($ext === 'avif') {
        $mime = function_exists('mime_content_type') ? (string)@mime_content_type($tmp) : '';
        if ($mime !== '' && stripos($mime, 'image/') !== 0) { $errors[] = 'Evidence must be a valid image.'; return null; }
    } else {
        $errors[] = 'Evidence must be a valid image.';
        return null;
    }

    $baseDir = __DIR__ . '/uploads/refund-evidence/' . (int)$refundRequestId . '/';
    if (!file_exists($baseDir)) {
        @mkdir($baseDir, 0777, true);
        @chmod($baseDir, 0777);
    }
    if (!is_dir($baseDir) || !is_writable($baseDir)) {
        $errors[] = 'Upload directory is not writable. Please contact support.';
        return null;
    }
    $filename = 'evidence_' . (int)$userId . '_' . (int)$refundRequestId . '_' . $idx . '_' . time() . '.' . $ext;
    $dest = $baseDir . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = 'Failed to save evidence photo.';
        return null;
    }
    return 'uploads/refund-evidence/' . (int)$refundRequestId . '/' . $filename;
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare("
    SELECT b.*, p.cancellation_policy, p.host_id
    FROM bookings b
    JOIN properties p ON p.id = b.property_id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$b || (int)$b['guest_id'] !== (int)$user['id']) {
    $conn->close();
    header('Location: my-bookings.php?error=not_found');
    exit();
}

$elig = reservepro_issue_eligibility((string)$b['check_in']);
if (!$elig['eligible']) {
    $conn->close();
    header('Location: request-refund-issue.php?booking_id=' . $bookingId . '&error=not_eligible');
    exit();
}

// Prevent duplicates
$activeStmt = $conn->prepare("
    SELECT id
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
    header('Location: request-refund-issue.php?booking_id=' . $bookingId . '&error=already_requested');
    exit();
}

$total = (float)($b['total_price'] ?? 0);
$issuePct = reservepro_issue_refund_percent($issueType);
$refundPercent = (int)$issuePct['percent'];
$refundAmount = round(max(0, $total) * ($refundPercent / 100), 2);

$policy = (string)($b['cancellation_policy'] ?? 'moderate');

$conn->begin_transaction();
try {
    $ins = $conn->prepare("
        INSERT INTO refund_requests (
            booking_id, requester_user_id, property_id, request_type,
            issue_type, description, evidence_json,
            policy, refund_percent, refund_amount, currency,
            status
        ) VALUES (?, ?, ?, 'issue', ?, ?, NULL, ?, ?, ?, 'PHP', 'pending_review')
    ");
    $propertyId = (int)$b['property_id'];
    $ins->bind_param('iiisssidd', $bookingId, $user['id'], $propertyId, $issueType, $desc, $policy, $refundPercent, $refundAmount);
    $ins->execute();
    $refundRequestId = (int)$conn->insert_id;
    $ins->close();

    $errors = [];
    $paths = [];
    $files = $_FILES['evidence'] ?? null;
    if ($files && isset($files['name']) && is_array($files['name'])) {
        $count = min(3, count($files['name']));
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
            $p = save_refund_evidence($file, $refundRequestId, (int)$user['id'], $i + 1, $errors);
            if ($p) $paths[] = $p;
        }
    }
    if (!empty($errors)) {
        throw new Exception(implode(' ', $errors));
    }

    $evidenceJson = json_encode(['photos' => $paths], JSON_UNESCAPED_SLASHES);
    $up = $conn->prepare("UPDATE refund_requests SET evidence_json = ? WHERE id = ?");
    $up->bind_param('si', $evidenceJson, $refundRequestId);
    $up->execute();
    $up->close();

    $meta = json_encode([
        'eligible_deadline' => $elig['deadline'] ?? '',
        'issue_rule' => $issuePct['rule'] ?? '',
        'refund_percent' => $refundPercent,
        'refund_amount' => $refundAmount,
    ]);
    $log = $conn->prepare("
        INSERT INTO refund_logs (refund_request_id, actor_user_id, actor_role, action, from_status, to_status, note, meta_json)
        VALUES (?, ?, 'guest', 'create_issue_refund_request', NULL, 'pending_review', NULL, ?)
    ");
    $log->bind_param('iis', $refundRequestId, $user['id'], $meta);
    $log->execute();
    $log->close();

    $conn->commit();
    $conn->close();
    header('Location: my-bookings.php?issue_submitted=1');
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header('Location: request-refund-issue.php?booking_id=' . $bookingId . '&error=submit_failed');
    exit();
}

