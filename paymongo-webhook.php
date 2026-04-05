<?php
/**
 * PayMongo webhook endpoint. Register this URL in the PayMongo dashboard, e.g.:
 *   https://your-domain.example/part1-ReservePro/paymongo-webhook.php
 * Subscribe at least to: checkout_session.payment.paid
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paymongo.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    http_response_code(400);
    echo json_encode(['received' => false, 'error' => 'empty body']);
    exit;
}

$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
if (!paymongo_verify_webhook_signature($payload, (string) $signatureHeader, (string) PAYMONGO_WEBHOOK_SECRET)) {
    http_response_code(401);
    echo json_encode(['received' => false, 'error' => 'invalid signature']);
    exit;
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['received' => false, 'error' => 'invalid json']);
    exit;
}

$attrs = $data['data']['attributes'] ?? null;
if (!is_array($attrs)) {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

$eventType = $attrs['type'] ?? '';
$inner = $attrs['data'] ?? null;

$checkoutSessionId = null;
if ($eventType === 'checkout_session.payment.paid' && is_array($inner) && ($inner['type'] ?? '') === 'checkout_session') {
    $checkoutSessionId = $inner['id'] ?? null;
}

if (is_string($checkoutSessionId) && strpos($checkoutSessionId, 'cs_') === 0) {
    $conn = getDBConnection();
    $stmt = $conn->prepare('
        UPDATE payments
        SET status = ?, raw_payload = ?, updated_at = NOW()
        WHERE external_reference = ? AND status = ?
    ');
    $statusPaid = 'paid';
    $pending = 'pending';
    $snippet = substr($payload, 0, 65000);
    $stmt->bind_param('ssss', $statusPaid, $snippet, $checkoutSessionId, $pending);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

http_response_code(200);
echo json_encode(['received' => true]);
