<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

requireLogin();
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
$data = reservepro_notification_list((int)$user['id'], $limit);
echo json_encode([
    'ok' => true,
    'unread' => (int)$data['unread'],
    'items' => $data['items'],
]);

