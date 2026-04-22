<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$user = getCurrentUser();

// Hosts go to their host messages page
if ($user['role'] === 'host') {
    header('Location: host/messages.php');
    exit();
}

$conn = getDBConnection();

$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    property_id INT(11) NOT NULL,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
)");

// All messages where guest is sender or receiver; get host and property info
$stmt = $conn->prepare("
    SELECT m.id, m.property_id, m.sender_id, m.receiver_id, m.message, m.created_at,
           sender.first_name AS sender_first_name, sender.last_name AS sender_last_name,
           receiver.first_name AS receiver_first_name, receiver.last_name AS receiver_last_name,
           p.title AS property_title
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    JOIN properties p ON m.property_id = p.id
    WHERE (m.sender_id = ? OR m.receiver_id = ?)
    ORDER BY m.created_at ASC
");
$stmt->bind_param("ii", $user['id'], $user['id']);
$stmt->execute();
$all_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group by conversation: (property_id, host_id). Host is the other party.
$conversations = [];
foreach ($all_messages as $m) {
    $host_id = (int)$m['receiver_id'] === (int)$user['id'] ? (int)$m['sender_id'] : (int)$m['receiver_id'];
    $host_name = (int)$m['receiver_id'] === (int)$user['id']
        ? trim($m['sender_first_name'] . ' ' . $m['sender_last_name'])
        : trim($m['receiver_first_name'] . ' ' . $m['receiver_last_name']);
    $key = $m['property_id'] . '-' . $host_id;
    if (!isset($conversations[$key])) {
        $conversations[$key] = [
            'property_id' => (int)$m['property_id'],
            'property_title' => $m['property_title'],
            'host_id' => $host_id,
            'host_name' => $host_name,
            'messages' => []
        ];
    }
    $conversations[$key]['messages'][] = [
        'id' => (int)$m['id'],
        'sender_id' => (int)$m['sender_id'],
        'message' => $m['message'],
        'created_at' => $m['created_at'],
        'is_sent' => (int)$m['sender_id'] === (int)$user['id']
    ];
}
$messages = array_values($conversations);
usort($messages, function ($a, $b) {
    $aLast = end($a['messages'])['created_at'] ?? '';
    $bLast = end($b['messages'])['created_at'] ?? '';
    return strcmp($bLast, $aLast);
});

$conversation_count = count($messages);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Messages - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=12.0">
    <link rel="stylesheet" href="assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=27.5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body.msg-page-body { background: #06090F !important; }
        body.msg-page-body::before, body.msg-page-body::after { display: none !important; }

        .messages-header {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            gap: 20px;
            padding: 28px 30px;
            margin-bottom: 28px;
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.88));
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.24);
            color: white;
        }

        .messages-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            color: #FFFFFF !important;
        }

        .messages-header p {
            opacity: 0.9;
            font-size: 16px;
            color: #E0E0E0 !important;
        }

        .admin-hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .host-messages-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            height: calc(100vh - 280px);
        }

        .host-conversations-list {
            background: #1F1F1F;
            border-radius: 12px;
            border: 1px solid #3A3A3A;
            overflow-y: auto;
        }

        .search-box {
            width: 100%;
            padding: 12px 16px;
            background: #2C2C2C;
            border: 1px solid #3A3A3A;
            border-radius: 8px;
            color: #FFFFFF;
            font-size: 14px;
        }

        .search-box::placeholder {
            color: #6B6B6B;
        }

        .search-box:focus {
            outline: none;
            border-color: #D4A574;
        }

        .host-conversation-item {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            font-size: 15px;
            letter-spacing: 0.01em;
            padding: 14px 16px;
            border-bottom: 1px solid #2C2C2C;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            position: relative;
            gap: 0;
        }

        .host-conversation-item:hover {
            background: #2C2C2C;
        }

        .host-conversation-item.active {
            background: #2C2C2C;
            border-left: 3px solid #D4A574;
        }

        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0F0F0F;
            flex-shrink: 0;
        }

        .conversation-details {
            flex: 1;
            min-width: 0;
            padding-right: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .conversation-name {
            font-size: 16px;
            font-weight: 700;
            color: #F1F5F9;
            margin-bottom: 0;
            line-height: 1.4;
        }

        .conversation-preview {
            font-size: 13px;
            color: #B8B8B8;
            margin-bottom: 0;
            line-height: 1.5;
        }
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0F0F0F;
            flex-shrink: 0;
        }

        .conversation-details {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 600;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .conversation-preview {
            font-size: 14px;
            color: #B8B8B8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 12px;
            color: #6B6B6B;
            margin-top: 4px;
        }

        .unread-badge {
            background: #D4A574;
            color: #0F0F0F;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: auto;
        }

        .host-chat-shell {
            background: #1F1F1F;
            border-radius: 12px;
            border: 1px solid #3A3A3A;
            display: flex;
            flex-direction: column;
        }

        .host-chat-header {
            padding: 20px;
            border-bottom: 1px solid #3A3A3A;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0F0F0F;
        }

        .chat-header-info h3 {
            font-size: 16px;
            font-weight: 600;
            color: #FFFFFF !important;
            margin-bottom: 2px;
        }

        .chat-header-info p {
            font-size: 13px;
            color: #B8B8B8 !important;
        }

        .chat-messages {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .host-message {
            display: flex;
            gap: 12px;
            max-width: 70%;
        }

        .host-message.sent {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            color: #0F0F0F;
            flex-shrink: 0;
        }

        .host-message.sent .message-avatar {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: #fff;
        }

        .message-content {
            flex: 1;
        }

        .host-message-bubble {
            padding: 12px 16px;
            border-radius: 12px;
            background: #2C2C2C;
            color: #E0E0E0;
            line-height: 1.5;
        }

        .host-message.sent .host-message-bubble {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
        }

        .message-time {
            font-size: 11px;
            color: #6B6B6B;
            margin-top: 4px;
        }

        .host-chat-input {
            padding: 20px;
            border-top: 1px solid #3A3A3A;
            display: flex;
            gap: 12px;
        }

        .chat-input {
            flex: 1;
            padding: 12px 16px;
            background: #2C2C2C;
            border: 1px solid #3A3A3A;
            border-radius: 8px;
            color: #FFFFFF;
            font-size: 14px;
            resize: none;
            min-height: 48px;
            max-height: 120px;
            font-family: inherit;
        }

        .chat-input::placeholder {
            color: #6B6B6B;
        }

        .chat-input:focus {
            outline: none;
            border-color: #D4A574;
        }

        .btn-send {
            padding: 12px 24px;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 165, 116, 0.3);
        }

        .btn-send:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .empty-messages {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 40px;
        }

        .empty-messages-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-messages h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: #FFFFFF !important;
        }

        .empty-messages p {
            font-size: 16px;
            color: #B8B8B8 !important;
            margin-bottom: 24px;
        }

        .conv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .conv-guest-name {
            font-size: 15px;
            font-weight: 700;
            color: #F1F5F9;
            white-space: nowrap;
        }

        .conversation-property {
            font-size: 12px;
            color: #A3A3A3 !important;
            margin-bottom: 4px !important;
            font-weight: 500;
            line-height: 1.3;
        }

        .message-snippet {
            font-size: 13px !important;
            color: #C0C0C0 !important;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conversation-time {
            position: static;
            margin-left: auto;
            align-self: flex-end;
            background: none;
            padding: 0 0 0 12px;
            border-radius: 0;
            font-size: 11px;
            color: #8B8B8B;
            white-space: nowrap;
            line-height: 1.4;
            font-weight: 500;
        }

        @media (max-width: 968px) {
            .host-messages-layout { grid-template-columns: 1fr; }
            .host-conversations-list { max-height: 400px; }
        }

        /* Notification Button Styles (from my-bookings.php) */
        .adm-notif-wrap {
            position: relative;
        }
        .adm-notif-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(255, 255, 255, 0.06);
            color: #A3A3A3;
            font-size: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.18s, border-color 0.18s;
        }
        .adm-notif-btn:hover {
            background: rgba(255, 255, 255, 0.11);
            border-color: rgba(212, 165, 116, 0.4);
        }
        .adm-notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: #EF4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            pointer-events: none;
        }
        .adm-notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 340px;
            max-width: calc(100vw - 32px);
            border-radius: 18px;
            background: rgba(17, 24, 39, 0.97);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.42);
            z-index: 9999;
            overflow: hidden;
        }
        .adm-notif-dropdown-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 14px 11px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }
        .adm-notif-dropdown-title {
            font-size: 13px;
            font-weight: 900;
            color: #F1F5F9;
            letter-spacing: -0.01em;
        }
        .adm-notif-markall {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
            font-size: 11px;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 10px;
            cursor: pointer;
        }
        .adm-notif-markall:hover {
            background: rgba(255, 255, 255, 0.11);
        }
        .adm-notif-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px;
            max-height: 340px;
            overflow-y: auto;
        }
        .adm-notif-item {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            padding: 9px 10px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.12);
            background: rgba(255, 255, 255, 0.03);
        }
        .adm-notif-item.unread {
            border-color: rgba(212, 165, 116, 0.32);
            background: rgba(212, 165, 116, 0.07);
        }
        .adm-notif-item-body {
            flex: 1;
            min-width: 0;
        }
        .adm-notif-item strong {
            font-size: 12px;
            font-weight: 700;
            color: #E2E8F0;
            display: block;
        }
        .adm-notif-item small {
            display: block;
            font-size: 11px;
            color: #94A3B8;
            margin-top: 2px;
            line-height: 1.4;
        }
        .adm-notif-item-actions {
            display: flex;
            gap: 4px;
        }
        .adm-notif-mark {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #CBD5E1;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 8px;
            cursor: pointer;
        }
        .adm-notif-mark:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .adm-notif-empty {
            padding: 14px 10px;
            color: #94A3B8;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        /* Light mode notification overrides */
        body.light-mode .adm-notif-btn {
            background: #F8FAFC;
            border-color: rgba(15, 23, 42, 0.10);
            color: #6B7280;
        }
        body.light-mode .adm-notif-btn:hover {
            background: #F1F5F9;
        }
        body.light-mode .adm-notif-dropdown {
            background: #FFFFFF;
            border-color: rgba(15, 23, 42, 0.10);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
        }
        body.light-mode .adm-notif-dropdown-head {
            border-bottom-color: rgba(15, 23, 42, 0.08);
        }
        body.light-mode .adm-notif-dropdown-title {
            color: #0f172a;
        }
        body.light-mode .adm-notif-item {
            border-color: rgba(15, 23, 42, 0.08);
            background: rgba(15, 23, 42, 0.02);
        }
        body.light-mode .adm-notif-item.unread {
            border-color: rgba(212, 165, 116, 0.22);
            background: rgba(212, 165, 116, 0.04);
        }
        body.light-mode .adm-notif-item strong {
            color: #0f172a;
        }
        body.light-mode .adm-notif-item small {
            color: #64748B;
        }
    </style>
</head>
<body class="dashboard-page admin-page admin-clean-page host-clean-page msg-page-body">
    <div class="host-layout">
        <!-- Sidebar -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="profile.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                    <span>Profile</span>
                </a>
                <a href="my-bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>My Bookings</span>
                </a>
                <a href="messages.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
                <a href="home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                    <span>Home</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Guest</div>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="messages-header host-page-hero">
                <div class="host-page-hero-content">
                    <h1 style="margin-top: 20px;">Messages</h1>
                </div>
                <div class="admin-hero-actions">
                    <div class="adm-notif-wrap" id="admNotifWrap">
                        <button class="adm-notif-btn" id="admNotifBtn" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="admNotifDropdown">
                            <i class="fa-solid fa-bell" aria-hidden="true" style="font-size: 17px;"></i>
                            <span class="adm-notif-badge" id="admNotifBadge" hidden></span>
                        </button>
                        <div class="adm-notif-dropdown" id="admNotifDropdown" hidden>
                            <div class="adm-notif-dropdown-head">
                                <span class="adm-notif-dropdown-title">Notifications</span>
                                <button class="adm-notif-markall" id="admNotifMarkAll" type="button">Mark all read</button>
                            </div>
                            <div class="adm-notif-list" id="admNotifList">
                                <div class="adm-notif-empty">Loading&hellip;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="host-messages-layout">
                <!-- Conversations List -->
                <div class="host-conversations-list">
                    <div class="host-surface-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.1); padding-bottom: 20px; align-items: stretch;">
                        <div>
                            <h2>Conversations</h2>
                        </div>
                        <input type="text" class="search-box" placeholder="Search messages..." id="messagesSearch">
                    </div>

                    <?php if (empty($messages)): ?>
                    <div class="empty-messages host-empty-state">
                        <div class="empty-messages-icon host-empty-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></div>
                        <h3>No Messages Yet</h3>
                    </div>
                    <?php else: ?>
                    <div class="conversation-items" id="conversationItems">
                        <?php foreach ($messages as $conv):
                            $last = end($conv['messages']);
                            $preview = $last ? mb_substr($last['message'], 0, 80) . (mb_strlen($last['message']) > 80 ? '…' : '') : '';
                            $lastTime = $last ? $last['created_at'] : '';
                        ?>
                        <div class="host-conversation-item" data-property-id="<?php echo (int)$conv['property_id']; ?>" data-host-id="<?php echo (int)$conv['host_id']; ?>" data-host-name="<?php echo htmlspecialchars($conv['host_name']); ?>" data-property-title="<?php echo htmlspecialchars($conv['property_title']); ?>">
                            <div class="conv-header">
                                <div class="conv-guest-name"><?php echo htmlspecialchars($conv['host_name']); ?></div>
                                <div class="conversation-time"><?php echo $lastTime ? date('M j, g:i A', strtotime($lastTime)) : ''; ?></div>
                            </div>
                            <div class="conversation-property"><?php echo htmlspecialchars($conv['property_title']); ?></div>
                            <div class="message-snippet"><?php echo htmlspecialchars($preview); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Container -->
                <div class="host-chat-shell" id="chatContainer">
                    <div class="empty-messages host-empty-state" id="chatPlaceholder">
                        <div class="empty-messages-icon host-empty-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></div>
                        <h3>Select a conversation</h3>
                    </div>
                    <div id="chatMessageDetail" style="display: none; flex: 1; flex-direction: column; min-height: 0;">
                        <div id="chatMessageHeader" class="host-chat-header" style="padding: 20px;"></div>
                        <div id="chatMessages" class="chat-messages" style="flex: 1; overflow-y: auto; padding: 24px;"></div>
                        <div class="chat-input-area host-chat-input" id="chatReplyArea">
                            <textarea class="chat-input" id="replyInput" placeholder="Type your message..." rows="2"></textarea>
                            <button type="button" class="btn-send" id="replySendBtn">Send</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            window.conversationsData = <?php echo json_encode($messages); ?>;
            </script>
            <script>
            (function() {
                var items = document.querySelectorAll('.host-conversation-item');
                var placeholder = document.getElementById('chatPlaceholder');
                var detail = document.getElementById('chatMessageDetail');
                var headerEl = document.getElementById('chatMessageHeader');
                var messagesEl = document.getElementById('chatMessages');
                var replyInput = document.getElementById('replyInput');
                var replyBtn = document.getElementById('replySendBtn');
                var searchInput = document.getElementById('messagesSearch');
                var currentPropertyId = null, currentHostId = null;

                function getConv(propertyId, hostId) {
                    var key = propertyId + '-' + hostId;
                    return (window.conversationsData || []).find(function(c) {
                        return (c.property_id + '-' + c.host_id) === key;
                    });
                }
                function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
                function formatTime(iso) {
                    if (!iso) return '';
                    var d = new Date(iso);
                    var now = new Date();
                    var sameDay = d.toDateString() === now.toDateString();
                    return sameDay ? d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                }
                function renderThread(conv) {
                    if (!messagesEl || !conv) return;
                    messagesEl.innerHTML = '';
                    (conv.messages || []).forEach(function(msg) {
                        var div = document.createElement('div');
                        div.className = 'message host-message' + (msg.is_sent ? ' sent is-sent' : '');
                        var initial = msg.is_sent ? 'You' : (conv.host_name || 'H').charAt(0);
                        div.innerHTML = '<div class="message-avatar">' + initial + '</div><div class="message-content"><div class="host-message-bubble">' + escapeHtml(msg.message) + '</div><div class="message-time">' + formatTime(msg.created_at) + '</div></div>';
                        messagesEl.appendChild(div);
                    });
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }
                function sendReply() {
                    var text = (replyInput && replyInput.value || '').trim();
                    if (!text || !currentPropertyId || !currentHostId) return;
                    replyBtn.disabled = true;
                    var fd = new FormData();
                    fd.append('property_id', currentPropertyId);
                    fd.append('message', text);
                    fetch('contact-host.php', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            replyBtn.disabled = false;
                            if (data.success) {
                                replyInput.value = '';
                                var conv = getConv(currentPropertyId, currentHostId);
                                if (conv) {
                                    conv.messages = conv.messages || [];
                                    conv.messages.push({ id: 0, message: text, created_at: new Date().toISOString().slice(0, 19).replace('T', ' '), is_sent: true });
                                    renderThread(conv);
                                }
                            } else { alert(data.error || 'Failed to send.'); }
                        })
                        .catch(function() { replyBtn.disabled = false; alert('Failed to send. Try again.'); });
                }

                if (replyBtn && replyInput) {
                    replyBtn.addEventListener('click', sendReply);
                    replyInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
                    });
                }

                items.forEach(function(el) {
                    el.addEventListener('click', function() {
                        var propertyId = parseInt(this.getAttribute('data-property-id'), 10);
                        var hostId = parseInt(this.getAttribute('data-host-id'), 10);
                        var hostName = this.getAttribute('data-host-name') || '';
                        var propertyTitle = this.getAttribute('data-property-title') || '';
                        currentPropertyId = propertyId;
                        currentHostId = hostId;
                        if (placeholder) placeholder.style.display = 'none';
                        if (detail) detail.style.display = 'flex';
                        if (headerEl) headerEl.innerHTML = '<strong style="color: #D4A574;">' + escapeHtml(hostName) + '</strong><br><span style="font-size: 13px; color: #888;">' + escapeHtml(propertyTitle) + '</span>';
                        var conv = getConv(propertyId, hostId);
                        renderThread(conv || { messages: [], host_name: hostName });
                        items.forEach(function(i) { i.style.background = ''; });
                        this.style.background = 'rgba(212, 165, 116, 0.1)';
                    });
                });

                if (searchInput && document.getElementById('conversationItems')) {
                    searchInput.addEventListener('input', function() {
                        var q = this.value.toLowerCase().trim();
                        var list = document.getElementById('conversationItems');
                        if (!list) return;
                        list.querySelectorAll('.host-conversation-item').forEach(function(item) {
                            item.style.display = q === '' || (item.textContent || '').toLowerCase().indexOf(q) !== -1 ? 'block' : 'none';
                        });
                    });
                }
            })();
            </script>

            <!-- Notification system (from my-bookings.php) -->
            <script>
            (function(){
                var btn = document.getElementById('admNotifBtn');
                var dropdown = document.getElementById('admNotifDropdown');
                var badge = document.getElementById('admNotifBadge');
                var list = document.getElementById('admNotifList');
                var markAllBtn = document.getElementById('admNotifMarkAll');
                if (!btn || !dropdown) return;

                function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

                function render(items){
                    if (!items || !items.length){
                        list.innerHTML = '<div class="adm-notif-empty">No notifications yet.</div>';
                        return;
                    }
                    list.innerHTML = items.map(function(n){
                        var unread = String(n.is_read)==='0';
                        var link = n.link ? String(n.link) : '';
                        var body = n.body ? String(n.body) : '';
                        var attrs = '';
                        if (link) attrs = ' data-link="'+esc(link)+'" style="cursor:pointer"';
                        return '<div class="adm-notif-item'+(unread?' unread':'')+'""+attrs+'>'+ 
                            '<div class="adm-notif-item-body"><strong>'+esc(n.title)+'</strong>'+ (body?'<small>'+esc(n.body)+'</small>':'')+'</div>'+ '<div class="adm-notif-item-actions">'+ (unread?'<button class="adm-notif-mark" data-mark="'+esc(n.id)+'">Mark read</button>':'')+'</div></div>';
                    }).join('');}}

                function load(){
                    fetch('api/notifications-list.php?limit=8', {credentials:'same-origin'})
                        .then(function(r){ return r.json(); })
                        .then(function(data){
                            if (!data||!data.ok) return;
                            var unread = parseInt(data.unread||0, 10);
                            var items = data.items||[];
                            if (items.length > 0) {
                                if (unread > 0) {
                                    badge.textContent = unread > 99 ? '99+' : String(unread);
                                    badge.hidden = false;
                                } else {
                                    badge.hidden = true;
                                }
                            } else {
                                badge.hidden = true;
                            }
                            render(items);
                        })
                        .catch(function(){ list.innerHTML='<div class="adm-notif-empty">Failed to load.</div>'; badge.hidden = true; });
                }

                function mark(id){
                    var fd = new FormData();
                    if (id) fd.append('id', String(id));
                    fetch('api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                        .then(function(r){ return r.json(); })
                        .then(function(data){ if(data&&data.ok) load(); })
                        .catch(function(){});
                }

                list.addEventListener('click', function(e){
                    var item = e.target && e.target.closest && e.target.closest('.adm-notif-item');
                    if (!item) return;
                    
                    var hasMarkAttr = item.hasAttribute('data-mark');
                    var hasLinkAttr = item.hasAttribute('data-link');
                    
                    if (hasMarkAttr) {
                        var id = parseInt(item.getAttribute('data-mark'), 10);
                        if (id) {
                            var url = hasLinkAttr ? item.getAttribute('data-link') : null;
                            var fd = new FormData();
                            fd.append('id', String(id));
                            fetch('api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                                .then(function(r){ return r.json(); })
                                .then(function(data){ 
                                    if(data&&data.ok) {
                                        if (url) window.location.href = url;
                                        else load();
                                    }
                                })
                                .catch(function(){});
                            return;
                        }
                    }
                    
                    if (hasLinkAttr) {
                        var url = item.getAttribute('data-link');
                        if (url) window.location.href = url;
                    }
                });

                markAllBtn.addEventListener('click', function(){ mark(0); });

                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var open = !dropdown.hidden;
                    dropdown.hidden = open;
                    btn.setAttribute('aria-expanded', String(!open));
                    if (!open) load();
                });

                document.addEventListener('click', function(e){
                    if (!document.getElementById('admNotifWrap').contains(e.target)){
                        dropdown.hidden = true;
                        btn.setAttribute('aria-expanded','false');
                    }
                });

                load();
            })();
            </script>
        </main>
    </div>

    <script src="assets/js/theme-toggle.js?v=27.5"></script>
</body>
</html>
