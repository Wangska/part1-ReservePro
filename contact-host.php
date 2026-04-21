<?php
// Ensure we always return JSON (no accidental output)
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/config/session.php';
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/notifications.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server configuration error. Please try again.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Please sign in to contact the host.']);
    exit();
}

$user = getCurrentUser();
$property_id = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$property_id || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Property and message are required.']);
    exit();
}

if (strlen($message) > 2000) {
    echo json_encode(['success' => false, 'error' => 'Message is too long.']);
    exit();
}

// Forbid sharing contact info (emails, phone numbers) in messages
function messageContainsForbiddenContact($text) {
    $text = ' ' . $text . ' ';
    // Email: local@domain (common patterns)
    if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', $text)) {
        return 'email';
    }
    // Phone: sequences of digits with optional spaces, dots, dashes, parentheses, or leading +
    // Match 7+ consecutive digits, or patterns like +1 234 567 8900, (234) 567-8900, 234.567.8900
    if (preg_match('/\+?[\d\s.\-()]{10,}/', $text) && preg_match('/\d{7,}/', $text)) {
        return 'phone';
    }
    // Explicit phrases that suggest sharing contact
    if (preg_match('/\b(whatsapp|viber|telegram|wechat|my number|call me|text me|email me at|reach me at|contact me at)\b/i', $text)) {
        return 'contact';
    }
    return null;
}

$forbidden = messageContainsForbiddenContact($message);
if ($forbidden !== null) {
    echo json_encode([
        'success' => false,
        'error' => 'Please do not share contact details (phone numbers, emails, or messaging apps) in messages. Use this chat to communicate with the host.'
    ]);
    exit();
}

try {
    $conn = getDBConnection();

    // Ensure messages table exists (in case schema wasn't run)
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

    $stmt = $conn->prepare("SELECT id, host_id, title FROM properties WHERE id = ? AND status = 'approved'");
    if (!$stmt) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
        exit();
    }
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $property = $result->fetch_assoc();
    $stmt->close();

    if (!$property) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Property not found.']);
        exit();
    }

    $host_id = (int) $property['host_id'];
    if ($host_id === (int) $user['id']) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'You cannot message yourself.']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Failed to send message. Please try again.']);
        exit();
    }
    $stmt->bind_param("iiis", $property_id, $user['id'], $host_id, $message);
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        $propTitle = trim((string)($property['title'] ?? 'Property'));
        $senderName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        reservepro_notification_create(
            $host_id,
            'new_message',
            'New message',
            ($senderName !== '' ? ($senderName . ' messaged you about ') : 'New message about ') . $propTitle . '.',
            '../host/messages.php'
        );
        echo json_encode(['success' => true, 'message' => 'Message sent to the host. They will reply from their Messages page.']);
    } else {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Failed to send message. Please try again.']);
    }
} catch (Throwable $e) {
    if (isset($conn) && $conn) $conn->close();
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
}
