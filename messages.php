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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - ReservePro</title>
    <link rel="stylesheet" href="assets/css/style.css?v=12.0">
    <link rel="stylesheet" href="assets/css/theme-toggle.css?v=2.0">
    <style>
        .messages-page { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .messages-header {
            /* Trendy gray header instead of brown */
            background: linear-gradient(135deg, #111827 0%, #1F2933 45%, #020617 100%);
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .messages-header h1 { font-size: 28px; margin: 0 0 4px 0; color: #fff !important; }
        .messages-header p { margin: 0; opacity: 0.9; font-size: 14px; color: #E0E0E0 !important; }
        .messages-layout { display: grid; grid-template-columns: 320px 1fr; gap: 20px; height: calc(100vh - 220px); min-height: 400px; }
        .conversations-list {
            background: var(--card-bg, #1F1F1F);
            border-radius: 12px;
            border: 1px solid var(--border-color, #3A3A3A);
            overflow-y: auto;
        }
        .conversations-header { padding: 16px; border-bottom: 1px solid var(--border-color, #3A3A3A); }
        .search-box {
            width: 100%;
            padding: 10px 14px;
            background: var(--input-bg, #2C2C2C);
            border: 1px solid var(--border-color, #3A3A3A);
            border-radius: 8px;
            color: var(--text-color, #fff);
            font-size: 14px;
        }
        .conversation-item {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color, #2C2C2C);
            cursor: pointer;
            transition: background 0.2s;
        }
        .conversation-item:hover { background: var(--hover-bg, #2C2C2C); }
        .conversation-item.active { background: rgba(212, 165, 116, 0.15); border-left: 3px solid #D4A574; }
        .conv-name { font-weight: 600; color: var(--text-color, #fff); margin-bottom: 2px; }
        .conv-meta { font-size: 12px; color: #888; margin-bottom: 4px; }
        .conv-preview { font-size: 13px; color: #B8B8B8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .chat-container {
            background: var(--card-bg, #1F1F1F);
            border-radius: 12px;
            border: 1px solid var(--border-color, #3A3A3A);
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .chat-header { padding: 16px 20px; border-bottom: 1px solid var(--border-color, #3A3A3A); }
        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
        .message { display: flex; gap: 10px; max-width: 75%; }
        .message.sent { margin-left: auto; flex-direction: row-reverse; }
        .message-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #D4A574, #B8935F);
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 12px; color: #0F0F0F; flex-shrink: 0;
        }
        .message-content { flex: 1; }
        .message-bubble { padding: 10px 14px; border-radius: 12px; background: #2C2C2C; color: #E0E0E0; line-height: 1.5; }
        .message.sent .message-bubble { background: linear-gradient(135deg, #D4A574, #B8935F); color: #0F0F0F; }
        .message-time { font-size: 11px; color: #6B6B6B; margin-top: 4px; }
        .chat-input-area { padding: 16px 20px; border-top: 1px solid var(--border-color, #3A3A3A); display: flex; gap: 10px; }
        .chat-input {
            flex: 1; padding: 10px 14px; background: var(--input-bg, #2C2C2C);
            border: 1px solid var(--border-color, #3A3A3A); border-radius: 8px;
            color: var(--text-color, #fff); font-size: 14px; resize: none; min-height: 44px; max-height: 120px;
        }
        .btn-send {
            padding: 10px 20px; background: linear-gradient(135deg, #D4A574, #B8935F); color: #0F0F0F;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
        }
        .btn-send:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212, 165, 116, 0.3); }
        .btn-send:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .empty-state { text-align: center; padding: 48px 24px; color: #888; }
        .empty-state h3 { font-size: 20px; margin-bottom: 8px; color: var(--text-color, #fff) !important; }
        .nav-links-messages { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .nav-links-messages a {
            color: #E0E0E0; text-decoration: none; font-size: 14px; padding: 6px 12px; border-radius: 6px;
        }
        .nav-links-messages a:hover { background: rgba(255,255,255,0.1); color: #fff; }
        @media (max-width: 768px) {
            .messages-layout { grid-template-columns: 1fr; height: auto; }
            .conversations-list { max-height: 300px; }
        }
    </style>
</head>
<body class="dashboard-page">
    <div class="messages-page">
        <div class="messages-header">
            <div>
                <h1>💬 Messages</h1>
                <p>Your conversations with hosts</p>
            </div>
            <div class="nav-links-messages">
                <a href="home.php">Home</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
                <div class="theme-toggle">
                    <span class="theme-toggle-icon">☀️</span>
                    <span class="theme-toggle-text">Light</span>
                </div>
            </div>
        </div>

        <div class="messages-layout">
            <div class="conversations-list">
                <div class="conversations-header">
                    <input type="text" class="search-box" placeholder="Search conversations..." id="messagesSearch">
                </div>
                <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <p>No messages yet. Contact a host from a property listing to start a conversation.</p>
                </div>
                <?php else: ?>
                <div id="conversationItems">
                    <?php foreach ($messages as $conv):
                        $last = end($conv['messages']);
                        $preview = $last ? mb_substr($last['message'], 0, 60) . (mb_strlen($last['message']) > 60 ? '…' : '') : '';
                        $lastTime = $last ? $last['created_at'] : '';
                    ?>
                    <div class="conversation-item" data-property-id="<?php echo (int)$conv['property_id']; ?>" data-host-id="<?php echo (int)$conv['host_id']; ?>" data-host-name="<?php echo htmlspecialchars($conv['host_name']); ?>" data-property-title="<?php echo htmlspecialchars($conv['property_title']); ?>">
                        <div class="conv-name"><?php echo htmlspecialchars($conv['host_name']); ?></div>
                        <div class="conv-meta"><?php echo htmlspecialchars($conv['property_title']); ?> · <?php echo $lastTime ? date('M j, g:i A', strtotime($lastTime)) : ''; ?></div>
                        <div class="conv-preview"><?php echo htmlspecialchars($preview); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="chat-container" id="chatContainer">
                <div class="empty-state" id="chatPlaceholder">
                    <h3>Select a conversation</h3>
                    <p>Choose a conversation from the list to view messages and reply.</p>
                </div>
                <div id="chatDetail" style="display: none; flex: 1; flex-direction: column; min-height: 0;">
                    <div id="chatHeader" class="chat-header"></div>
                    <div id="chatMessages" class="chat-messages"></div>
                    <div class="chat-input-area" id="replyArea">
                        <textarea class="chat-input" id="replyInput" placeholder="Type your reply..." rows="2"></textarea>
                        <button type="button" class="btn-send" id="replyBtn">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    window.conversationsData = <?php echo json_encode($messages); ?>;
    </script>
    <script>
    (function() {
        var items = document.querySelectorAll('.conversation-item');
        var placeholder = document.getElementById('chatPlaceholder');
        var detail = document.getElementById('chatDetail');
        var headerEl = document.getElementById('chatHeader');
        var messagesEl = document.getElementById('chatMessages');
        var replyInput = document.getElementById('replyInput');
        var replyBtn = document.getElementById('replyBtn');
        var searchInput = document.getElementById('messagesSearch');
        var currentPropertyId = null, currentHostId = null;

        function getConv(propertyId, hostId) {
            var key = propertyId + '-' + hostId;
            return (window.conversationsData || []).find(function(c) { return (c.property_id + '-' + c.host_id) === key; });
        }
        function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        function formatTime(iso) {
            if (!iso) return '';
            var d = new Date(iso);
            var sameDay = d.toDateString() === new Date().toDateString();
            return sameDay ? d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }
        function renderThread(conv) {
            if (!messagesEl || !conv) return;
            messagesEl.innerHTML = '';
            (conv.messages || []).forEach(function(msg) {
                var div = document.createElement('div');
                div.className = 'message' + (msg.is_sent ? ' sent' : '');
                var initial = msg.is_sent ? 'You' : (conv.host_name || 'H').charAt(0);
                div.innerHTML = '<div class="message-avatar">' + initial + '</div><div class="message-content"><div class="message-bubble">' + escapeHtml(msg.message) + '</div><div class="message-time">' + formatTime(msg.created_at) + '</div></div>';
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
            replyInput.addEventListener('keydown', function(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); } });
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
                if (detail) { detail.style.display = 'flex'; }
                if (headerEl) headerEl.innerHTML = '<strong style="color: #D4A574;">' + escapeHtml(hostName) + '</strong><br><span style="font-size: 13px; color: #888;">' + escapeHtml(propertyTitle) + '</span>';
                var conv = getConv(propertyId, hostId);
                renderThread(conv || { messages: [], host_name: hostName });
                items.forEach(function(i) { i.classList.remove('active'); });
                this.classList.add('active');
            });
        });
        if (searchInput && document.getElementById('conversationItems')) {
            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                document.querySelectorAll('#conversationItems .conversation-item').forEach(function(item) {
                    item.style.display = !q || (item.textContent || '').toLowerCase().indexOf(q) !== -1 ? 'block' : 'none';
                });
            });
        }
    })();
    </script>
    <script src="assets/js/theme-toggle.js"></script>
</body>
</html>
