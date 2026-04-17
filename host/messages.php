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

// Get all messages where host is sender or receiver, for their properties (full threads)
$stmt = $conn->prepare("
    SELECT m.id, m.property_id, m.sender_id, m.receiver_id, m.message, m.created_at, m.read_at,
           sender.first_name AS sender_first_name, sender.last_name AS sender_last_name,
           receiver.first_name AS receiver_first_name, receiver.last_name AS receiver_last_name,
           p.title AS property_title
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    JOIN properties p ON m.property_id = p.id AND p.host_id = ?
    WHERE (m.receiver_id = ? OR m.sender_id = ?)
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iii", $user['id'], $user['id'], $user['id']);
$stmt->execute();
$all_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group into conversations: (property_id, guest_id) -> { property_id, property_title, guest_id, guest_name, messages[] }
$conversations = [];
foreach ($all_messages as $m) {
    $guest_id = (int)$m['receiver_id'] === (int)$user['id'] ? (int)$m['sender_id'] : (int)$m['receiver_id'];
    $guest_name = (int)$m['receiver_id'] === (int)$user['id']
        ? trim($m['sender_first_name'] . ' ' . $m['sender_last_name'])
        : trim($m['receiver_first_name'] . ' ' . $m['receiver_last_name']);
    $key = $m['property_id'] . '-' . $guest_id;
    if (!isset($conversations[$key])) {
        $conversations[$key] = [
            'property_id' => (int)$m['property_id'],
            'property_title' => $m['property_title'],
            'guest_id' => $guest_id,
            'guest_name' => $guest_name,
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
// Sort each conversation's messages by time (already ASC from query); sort conversation list by latest message
$messages = array_values($conversations);
usort($messages, function ($a, $b) {
    $aLast = end($a['messages'])['created_at'] ?? '';
    $bLast = end($b['messages'])['created_at'] ?? '';
    return strcmp($bLast, $aLast);
});

$conversation_count = count($messages);
$total_message_count = count($all_messages);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../background%20image/newicon.png" type="image/png">
    <title>Messages - ReservePro</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=11.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/host-dashboard.css?v=27.3">
    <link rel="stylesheet" href="../assets/css/admin.css?v=25.4">
    <link rel="stylesheet" href="../assets/css/theme-toggle.css?v=27.5">
    <style>
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
<body class="dashboard-page admin-page admin-clean-page host-clean-page host-messages-page">
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
                <a href="properties.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <span>My Properties</span>
                </a>
                <a href="add-property.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
                    <span>Add Property</span>
                </a>
                <a href="bookings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span>Bookings</span>
                </a>
                <a href="refund-requests.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></span>
                    <span>Refund Requests</span>
                </a>
                <a href="earnings.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                    <span>Earnings</span>
                </a>
                <a href="messages.php" class="nav-item active">
                    <span class="nav-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                    <span>Messages</span>
                </a>
                <a href="../home.php" class="nav-item">
                    <span class="nav-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>
                    <span>Home</span>
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
            <div class="messages-header host-page-hero">
                <div class="host-page-hero-content">
                    <h1 style="margin-top: 20px;">Messages</h1>
                </div>
                <!-- host-page-summary removed -->
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
                        <div class="host-conversation-item" data-property-id="<?php echo (int)$conv['property_id']; ?>" data-guest-id="<?php echo (int)$conv['guest_id']; ?>" data-guest-name="<?php echo htmlspecialchars($conv['guest_name']); ?>" data-property-title="<?php echo htmlspecialchars($conv['property_title']); ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                <strong style="color: #FFFFFF;"><?php echo htmlspecialchars($conv['guest_name']); ?></strong>
                                <span style="font-size: 12px; color: #888;"><?php echo $lastTime ? date('M j, g:i A', strtotime($lastTime)) : ''; ?></span>
                            </div>
                            <div class="conversation-property" style="font-size: 12px; color: #B8B8B8; margin-bottom: 4px;"><?php echo htmlspecialchars($conv['property_title']); ?></div>
                            <div class="message-snippet" style="font-size: 13px; color: #E0E0E0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($preview); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Container - selected conversation with thread and reply -->
                <div class="host-chat-shell" id="chatContainer">
                    <div class="empty-messages host-empty-state" id="chatPlaceholder">
                        <div class="empty-messages-icon host-empty-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></div>
                        <h3>Select a conversation</h3>
                    </div>
                    <div id="chatMessageDetail" style="display: none; flex: 1; flex-direction: column; min-height: 0;">
                        <div id="chatMessageHeader" class="host-chat-header" style="padding: 20px;"></div>
                        <div id="chatMessages" class="chat-messages" style="flex: 1; overflow-y: auto; padding: 24px;"></div>
                        <div class="chat-input-area host-chat-input" id="chatReplyArea">
                            <textarea class="chat-input" id="replyInput" placeholder="Type your reply..." rows="2"></textarea>
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
                var replyArea = document.getElementById('chatReplyArea');
                var replyInput = document.getElementById('replyInput');
                var replyBtn = document.getElementById('replySendBtn');
                var searchInput = document.getElementById('messagesSearch');
                var currentPropertyId = null;
                var currentGuestId = null;

                function getConversation(propertyId, guestId) {
                    var key = propertyId + '-' + guestId;
                    return (window.conversationsData || []).find(function(c) {
                        return (c.property_id + '-' + c.guest_id) === key;
                    });
                }

                function renderThread(conv) {
                    if (!messagesEl || !conv) return;
                    messagesEl.innerHTML = '';
                    (conv.messages || []).forEach(function(msg) {
                        var div = document.createElement('div');
                        div.className = 'message host-message' + (msg.is_sent ? ' sent is-sent' : '');
                        var initial = msg.is_sent ? 'You' : (conv.guest_name || 'G').charAt(0);
                        div.innerHTML = '<div class="message-avatar">' + initial + '</div><div class="message-content"><div class="message-bubble host-message-bubble">' + escapeHtml(msg.message) + '</div><div class="message-time">' + formatTime(msg.created_at) + '</div></div>';
                        messagesEl.appendChild(div);
                    });
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }

                function escapeHtml(s) {
                    var div = document.createElement('div');
                    div.textContent = s;
                    return div.innerHTML;
                }

                function formatTime(iso) {
                    if (!iso) return '';
                    var d = new Date(iso);
                    var now = new Date();
                    var sameDay = d.toDateString() === now.toDateString();
                    return sameDay ? d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                }

                function sendReply() {
                    var text = (replyInput && replyInput.value || '').trim();
                    if (!text || !currentPropertyId || !currentGuestId) return;
                    replyBtn.disabled = true;
                    var formData = new FormData();
                    formData.append('property_id', currentPropertyId);
                    formData.append('receiver_id', currentGuestId);
                    formData.append('message', text);
                    fetch('../reply-message.php', { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            replyBtn.disabled = false;
                            if (data.success) {
                                replyInput.value = '';
                                var conv = getConversation(currentPropertyId, currentGuestId);
                                if (conv) {
                                    conv.messages = conv.messages || [];
                                    conv.messages.push({ id: data.id, sender_id: 0, message: text, created_at: data.created_at, is_sent: true });
                                    renderThread(conv);
                                }
                            } else {
                                alert(data.error || 'Failed to send reply.');
                            }
                        })
                        .catch(function() {
                            replyBtn.disabled = false;
                            alert('Failed to send reply. Please try again.');
                        });
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
                        var guestId = parseInt(this.getAttribute('data-guest-id'), 10);
                        var guestName = this.getAttribute('data-guest-name') || '';
                        var propertyTitle = this.getAttribute('data-property-title') || '';
                        currentPropertyId = propertyId;
                        currentGuestId = guestId;
                        if (placeholder) placeholder.style.display = 'none';
                        if (detail) detail.style.display = 'flex';
                        if (headerEl) headerEl.innerHTML = '<strong style="color: #D4A574;">' + escapeHtml(guestName) + '</strong><br><span style="font-size: 13px; color: #888;">' + escapeHtml(propertyTitle) + '</span>';
                        var conv = getConversation(propertyId, guestId);
                        renderThread(conv || { messages: [], guest_name: guestName });
                        if (replyArea) replyArea.style.display = 'flex';
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
                            var text = (item.textContent || '').toLowerCase();
                            item.style.display = q === '' || text.indexOf(q) !== -1 ? 'block' : 'none';
                        });
                    });
                }
            })();
            </script>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js?v=27.5"></script>
    <script src="../assets/js/host-view-site-confirm.js?v=1.0"></script>
</body>
</html>
