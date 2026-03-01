<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Hosts must complete verification before accessing messages
if ($user && $user['role'] === 'host' && empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

// Ensure user is a host
if ($user['role'] !== 'host') {
    header('Location: ../home.php');
    exit();
}

$conn = getDBConnection();

// Ensure messages table exists (schema may not have been run yet)
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

// Get messages for this host (receiver_id = current user), with sender and property info
$stmt = $conn->prepare("
    SELECT m.id, m.property_id, m.sender_id, m.receiver_id, m.message, m.created_at, m.read_at,
           u.first_name AS sender_first_name, u.last_name AS sender_last_name, u.email AS sender_email,
           p.title AS property_title
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    JOIN properties p ON m.property_id = p.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=11.0">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=11.0">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=11.0">
    <style>
        .messages-header {
            background: linear-gradient(135deg, #2C1810 0%, #3E2723 50%, #0F0F0F 100%);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 32px;
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

        .messages-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            height: calc(100vh - 280px);
        }

        .conversations-list {
            background: #1F1F1F;
            border-radius: 12px;
            border: 1px solid #3A3A3A;
            overflow-y: auto;
        }

        .conversations-header {
            padding: 20px;
            border-bottom: 1px solid #3A3A3A;
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

        .conversation-item {
            padding: 16px 20px;
            border-bottom: 1px solid #2C2C2C;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .conversation-item:hover {
            background: #2C2C2C;
        }

        .conversation-item.active {
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

        .chat-container {
            background: #1F1F1F;
            border-radius: 12px;
            border: 1px solid #3A3A3A;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
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

        .message {
            display: flex;
            gap: 12px;
            max-width: 70%;
        }

        .message.sent {
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

        .message-content {
            flex: 1;
        }

        .message-bubble {
            padding: 12px 16px;
            border-radius: 12px;
            background: #2C2C2C;
            color: #E0E0E0;
            line-height: 1.5;
        }

        .message.sent .message-bubble {
            background: linear-gradient(135deg, #D4A574, #B8935F);
            color: #0F0F0F;
        }

        .message-time {
            font-size: 11px;
            color: #6B6B6B;
            margin-top: 4px;
        }

        .chat-input-area {
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

        @media (max-width: 968px) {
            .messages-layout {
                grid-template-columns: 1fr;
            }
            
            .conversations-list {
                max-height: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="host-layout">
        <!-- Sidebar -->
        <aside class="host-sidebar">
            <div class="sidebar-header">
                <a href="../home.php" class="sidebar-brand">
                    <?php require __DIR__ . '/../includes/brand-icon-svg.php'; ?>
                    <span>ReservePro</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item">
                    <span class="nav-icon">➕</span>
                    <span>Add Property</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon">📅</span>
                    <span>Bookings</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon">💰</span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item active">
                    <span class="nav-icon">💬</span>
                    <span>Messages</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon">🌐</span>
                    <span>View Site</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="user-role">Host</div>
                    </div>
                </div>
                
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="host-main">
            <div class="messages-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>💬 Messages</h1>
                    <p>Communicate with your guests</p>
                </div>
                <!-- Theme Toggle -->
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>

            <div class="messages-layout">
                <!-- Conversations List -->
                <div class="conversations-list">
                    <div class="conversations-header">
                        <input type="text" class="search-box" placeholder="Search messages..." id="messagesSearch">
                    </div>

                    <?php if (empty($messages)): ?>
                    <div class="empty-messages">
                        <div class="empty-messages-icon">📭</div>
                        <h3>No Messages Yet</h3>
                        <p>When guests contact you about your properties (via "Contact Host" on a listing), messages will appear here.</p>
                    </div>
                    <?php else: ?>
                    <div class="conversation-items" id="conversationItems">
                        <?php foreach ($messages as $msg): ?>
                        <div class="conversation-item" data-message-id="<?php echo (int)$msg['id']; ?>" style="padding: 16px; border-bottom: 1px solid #3A3A3A; cursor: pointer; transition: background 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                <strong style="color: #FFFFFF;"><?php echo htmlspecialchars($msg['sender_first_name'] . ' ' . $msg['sender_last_name']); ?></strong>
                                <span style="font-size: 12px; color: #888;"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
                            </div>
                            <div class="conversation-property" style="font-size: 12px; color: #B8B8B8; margin-bottom: 4px;"><?php echo htmlspecialchars($msg['property_title']); ?></div>
                            <div class="message-snippet" style="font-size: 13px; color: #E0E0E0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars(mb_substr($msg['message'], 0, 80)); ?><?php echo mb_strlen($msg['message']) > 80 ? '…' : ''; ?></div>
                            <div class="message-full" style="display: none;"><?php echo htmlspecialchars($msg['message']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Container - selected message detail -->
                <div class="chat-container" id="chatContainer">
                    <div class="empty-messages" id="chatPlaceholder">
                        <div class="empty-messages-icon">💬</div>
                        <h3>Select a message</h3>
                        <p>Click a message from the list to read it.</p>
                    </div>
                    <div id="chatMessageDetail" style="display: none; padding: 24px; color: #E0E0E0;">
                        <div id="chatMessageHeader" style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #3A3A3A;"></div>
                        <div id="chatMessageBody" style="white-space: pre-wrap; line-height: 1.6;"></div>
                    </div>
                </div>
            </div>
            <script>
            (function() {
                var items = document.querySelectorAll('.conversation-item');
                var placeholder = document.getElementById('chatPlaceholder');
                var detail = document.getElementById('chatMessageDetail');
                var headerEl = document.getElementById('chatMessageHeader');
                var bodyEl = document.getElementById('chatMessageBody');
                var searchInput = document.getElementById('messagesSearch');
                items.forEach(function(el) {
                    el.addEventListener('click', function() {
                        var snippet = this.querySelector('.message-snippet');
                        var full = this.querySelector('.message-full');
                        var name = this.querySelector('strong').textContent;
                        var date = this.querySelector('span').textContent;
                        var prop = this.querySelector('.conversation-property');
                        var propTitle = prop ? prop.textContent : '';
                        if (placeholder) placeholder.style.display = 'none';
                        if (detail) detail.style.display = 'block';
                        if (headerEl) headerEl.innerHTML = '<strong style="color: #D4A574;">' + name + '</strong><br><span style="font-size: 13px; color: #888;">' + propTitle + ' · ' + date + '</span>';
                        if (bodyEl && full) bodyEl.textContent = full.textContent;
                        items.forEach(function(i) { i.style.background = ''; });
                        this.style.background = 'rgba(212, 165, 116, 0.1)';
                    });
                });
                if (searchInput && document.getElementById('conversationItems')) {
                    searchInput.addEventListener('input', function() {
                        var q = this.value.toLowerCase().trim();
                        var list = document.getElementById('conversationItems');
                        if (!list) return;
                        list.querySelectorAll('.conversation-item').forEach(function(item) {
                            var text = (item.textContent || '').toLowerCase();
                            item.style.display = q === '' || text.indexOf(q) !== -1 ? 'block' : 'none';
                        });
                    });
                }
            })();
            </script>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>
