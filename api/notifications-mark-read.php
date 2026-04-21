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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$ok = reservepro_notification_mark_read((int)$user['id'], $id > 0 ? $id : null);
echo json_encode(['ok' => (bool)$ok]);

