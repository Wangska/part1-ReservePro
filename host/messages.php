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

// Get messages for host (from guests who booked their properties)
// For now, we'll create a placeholder since messages table doesn't exist yet
$messages = [];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - ServePro</title>
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
                    <svg class="brand-icon" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16 1c2 0 3.46 1.63 3.46 3.41 0 1.78-1.46 3.41-3.46 3.41s-3.46-1.63-3.46-3.41C12.54 2.63 14 1 16 1zm0 6.82c2.52 0 4.61-1.84 4.61-4.41C20.61 1.84 18.52 0 16 0s-4.61 1.84-4.61 4.41c0 2.57 2.09 4.41 4.61 4.41zM13.96 28.85l6.72-11.87c-1.41-.83-3.07-1.33-4.86-1.33-1.79 0-3.45.5-4.86 1.33l6.72 11.87h.28zm-1.27-1.89l-5.12-9.04C8.47 16.02 9.99 15 11.71 15h8.58c1.72 0 3.24 1.02 4.14 2.92l-5.12 9.04h-7.62z"/>
                    </svg>
                    <span>ServePro</span>
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
                        <input type="text" class="search-box" placeholder="Search messages...">
                    </div>

                    <!-- Empty State -->
                    <div class="empty-messages">
                        <div class="empty-messages-icon">📭</div>
                        <h3>No Messages Yet</h3>
                        <p>When guests contact you about your properties, messages will appear here.</p>
                    </div>
                </div>

                <!-- Chat Container -->
                <div class="chat-container">
                    <div class="empty-messages">
                        <div class="empty-messages-icon">💬</div>
                        <h3>Select a Conversation</h3>
                        <p>Choose a conversation from the list to start chatting with your guests.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-toggle.js"></script>
</body>
</html>
