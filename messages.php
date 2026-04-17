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

        .search-box {
            width: 100%;
            padding: 12px 16px;
            background: #2C2C2C;
            border: 1px solid #3A3A3A;
            border-radius: 8px;
            color: #FFFFFF;
            font-size: 14px;
            box-sizing: border-box;
        }
        .search-box::placeholder { color: #6B6B6B; }
        .search-box:focus { outline: none; border-color: #D4A574; }

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
        .message.sent .message-avatar {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: #fff;
        }
        .message-content { flex: 1; }
        .message-time { font-size: 11px; color: #6B6B6B; margin-top: 4px; }

        .chat-input-area {
            padding: 20px;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
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
        .chat-input::placeholder { color: #6B6B6B; }
        .chat-input:focus { outline: none; border-color: #D4A574; }
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
        .btn-send:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(212, 165, 116, 0.3); }
        .btn-send:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        @media (max-width: 968px) {
            .host-messages-layout { grid-template-columns: 1fr; }
            .host-conversations-list { max-height: 400px; }
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
            <div class="host-page-hero messages-header">
                <div class="host-page-hero-content">

                    <h1>Messages</h1>
                </div>

            </div>

            <div class="host-messages-layout">
                <!-- Conversations List -->
                <div class="host-conversations-list">
                    <div class="host-surface-header" style="border-bottom: 1px solid rgba(148, 163, 184, 0.1); padding-bottom: 20px; align-items: stretch;">
                        <div><h2>Conversations</h2></div>
                        <input type="text" class="search-box" placeholder="Search messages..." id="messagesSearch">
                    </div>

                    <?php if (empty($messages)): ?>
                    <div class="host-empty-state">
                        <div class="host-empty-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></div>
                        <h3>No Messages Yet</h3>

                    </div>
                    <?php else: ?>
                    <div class="conversation-items" id="conversationItems">
                        <?php foreach ($messages as $conv):
                            $last = end($conv['messages']);
                            $preview = $last ? mb_substr($last['message'], 0, 80) . (mb_strlen($last['message']) > 80 ? '…' : '') : '';
                            $lastTime = $last ? $last['created_at'] : '';
                        ?>
                        <div class="host-conversation-item"
                            data-property-id="<?php echo (int)$conv['property_id']; ?>"
                            data-host-id="<?php echo (int)$conv['host_id']; ?>"
                            data-host-name="<?php echo htmlspecialchars($conv['host_name']); ?>"
                            data-property-title="<?php echo htmlspecialchars($conv['property_title']); ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                <strong style="color: #FFFFFF;"><?php echo htmlspecialchars($conv['host_name']); ?></strong>
                                <span style="font-size: 12px; color: #888;"><?php echo $lastTime ? date('M j, g:i A', strtotime($lastTime)) : ''; ?></span>
                            </div>
                            <div style="font-size: 12px; color: #B8B8B8; margin-bottom: 4px;"><?php echo htmlspecialchars($conv['property_title']); ?></div>
                            <div style="font-size: 13px; color: #E0E0E0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($preview); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Container -->
                <div class="host-chat-shell" id="chatContainer">
                    <div class="host-empty-state" id="chatPlaceholder">
                        <div class="host-empty-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></div>
                        <h3>Select a conversation</h3>

                    </div>
                    <div id="chatDetail" style="display: none; flex: 1; flex-direction: column; min-height: 0;">
                        <div id="chatHeader" class="host-chat-header" style="padding: 20px;"></div>
                        <div id="chatMessages" class="chat-messages"></div>
                        <div class="chat-input-area host-chat-input" id="chatReplyArea">
                            <textarea class="chat-input" id="replyInput" placeholder="Type your message..." rows="2"></textarea>
                            <button type="button" class="btn-send" id="replyBtn">Send</button>
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
                var detail = document.getElementById('chatDetail');
                var headerEl = document.getElementById('chatHeader');
                var messagesEl = document.getElementById('chatMessages');
                var replyInput = document.getElementById('replyInput');
                var replyBtn = document.getElementById('replyBtn');
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
                    var sameDay = d.toDateString() === new Date().toDateString();
                    return sameDay ? d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                }
                function renderThread(conv) {
                    if (!messagesEl || !conv) return;
                    messagesEl.innerHTML = '';
                    (conv.messages || []).forEach(function(msg) {
                        var div = document.createElement('div');
                        div.className = 'message host-message' + (msg.is_sent ? ' sent is-sent' : '');
                        var initial = msg.is_sent ? 'You' : (conv.host_name || 'H').charAt(0);
                        div.innerHTML = '<div class="message-avatar">' + initial + '</div>' +
                            '<div class="message-content">' +
                            '<div class="message-bubble host-message-bubble">' + escapeHtml(msg.message) + '</div>' +
                            '<div class="message-time">' + formatTime(msg.created_at) + '</div>' +
                            '</div>';
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
                        if (headerEl) headerEl.innerHTML =
                            '<strong style="color: #D4A574;">' + escapeHtml(hostName) + '</strong><br>' +
                            '<span style="font-size: 13px; color: #888;">' + escapeHtml(propertyTitle) + '</span>';
                        var conv = getConv(propertyId, hostId);
                        renderThread(conv || { messages: [], host_name: hostName });
                        items.forEach(function(i) { i.classList.remove('is-active'); });
                        this.classList.add('is-active');
                    });
                });

                if (searchInput && document.getElementById('conversationItems')) {
                    searchInput.addEventListener('input', function() {
                        var q = this.value.toLowerCase().trim();
                        document.querySelectorAll('#conversationItems .host-conversation-item').forEach(function(item) {
                            item.style.display = !q || (item.textContent || '').toLowerCase().indexOf(q) !== -1 ? 'block' : 'none';
                        });
                    });
                }
            })();
            </script>
        </main>
    </div>

    <script src="assets/js/theme-toggle.js?v=27.5"></script>
</body>
</html>
