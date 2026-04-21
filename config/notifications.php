<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/database_schema.php';

function reservepro_notification_create(
    int $userId,
    string $type,
    string $title,
    ?string $body = null,
    ?string $link = null
): bool {
    if ($userId <= 0) return false;
    $type = trim($type);
    $title = trim($title);
    $body = $body !== null ? trim($body) : null;
    $link = $link !== null ? trim($link) : null;
    if ($type === '' || $title === '') return false;

    // Ensure table exists (safe, idempotent)
    initializeHostTables();

    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, body, link, is_read)
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    if (!$stmt) {
        $conn->close();
        return false;
    }
    $stmt->bind_param('issss', $userId, $type, $title, $body, $link);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return (bool)$ok;
}

function reservepro_notification_notify_admins(
    string $type,
    string $title,
    ?string $body = null,
    ?string $link = null
): int {
    initializeHostTables();
    $conn = getDBConnection();
    $res = $conn->query("SELECT id FROM users WHERE role = 'admin'");
    $count = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $uid = (int)($row['id'] ?? 0);
            if ($uid > 0 && reservepro_notification_create($uid, $type, $title, $body, $link)) {
                $count++;
            }
        }
    }
    $conn->close();
    return $count;
}

/**
 * @return array{unread:int, items:array<int, array{id:int,type:string,title:string,body:?string,link:?string,is_read:int,created_at:string}>}
 */
function reservepro_notification_list(int $userId, int $limit = 8): array
{
    $limit = max(1, min(30, (int)$limit));
    initializeHostTables();
    $conn = getDBConnection();

    $unread = 0;
    $c = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($c) {
        $c->bind_param('i', $userId);
        $c->execute();
        $row = $c->get_result()->fetch_assoc();
        $unread = (int)($row['c'] ?? 0);
        $c->close();
    }

    $items = [];
    $stmt = $conn->prepare("
        SELECT id, type, title, body, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT $limit
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $conn->close();
    return ['unread' => $unread, 'items' => $items ?: []];
}

function reservepro_notification_mark_read(int $userId, ?int $notificationId = null): bool
{
    initializeHostTables();
    $conn = getDBConnection();
    if ($notificationId !== null && $notificationId > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id = ?");
        if (!$stmt) { $conn->close(); return false; }
        $stmt->bind_param('ii', $userId, $notificationId);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return (bool)$ok;
    }
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    if (!$stmt) { $conn->close(); return false; }
    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return (bool)$ok;
}

