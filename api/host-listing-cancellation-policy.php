<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';
require_once __DIR__ . '/../config/refunds.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? '') !== 'host') {
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit();
}

$propertyId = isset($_GET['property_id']) ? (int) $_GET['property_id'] : 0;
if ($propertyId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid property']);
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare('
    SELECT cancellation_policy
    FROM properties
    WHERE id = ? AND host_id = ?
    LIMIT 1
');
$stmt->bind_param('ii', $propertyId, $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Not found']);
    exit();
}

$policy = (string)($row['cancellation_policy'] ?? 'moderate');
echo json_encode([
    'ok' => true,
    'policy' => $policy,
    'summary' => reservepro_cancellation_policy_human_summary($policy),
]);
