<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/config/session.php';
    require_once __DIR__ . '/config/database.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server configuration error. Please try again.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Please sign in to reply.']);
    exit();
}

$user = getCurrentUser();
if ($user['role'] !== 'host') {
    echo json_encode(['success' => false, 'error' => 'Only hosts can reply to messages.']);
    exit();
}

$property_id = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$receiver_id = isset($_POST['receiver_id']) ? (int) $_POST['receiver_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$property_id || !$receiver_id || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Property, recipient and message are required.']);
    exit();
}

if (strlen($message) > 2000) {
    echo json_encode(['success' => false, 'error' => 'Message is too long.']);
    exit();
}

function messageContainsForbiddenContact($text) {
    $text = ' ' . $text . ' ';
    if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', $text)) {
        return 'email';
    }
    if (preg_match('/\+?[\d\s.\-()]{10,}/', $text) && preg_match('/\d{7,}/', $text)) {
        return 'phone';
    }
    if (preg_match('/\b(whatsapp|viber|telegram|wechat|my number|call me|text me|email me at|reach me at|contact me at)\b/i', $text)) {
        return 'contact';
    }
    return null;
}

$forbidden = messageContainsForbiddenContact($message);
if ($forbidden !== null) {
    echo json_encode([
        'success' => false,
        'error' => 'Please do not share contact details (phone numbers, emails, or messaging apps) in messages. Use this chat to communicate.'
    ]);
    exit();
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT id, host_id FROM properties WHERE id = ? AND host_id = ? AND status = 'approved'");
    if (!$stmt) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
        exit();
    }
    $stmt->bind_param("ii", $property_id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $property = $result->fetch_assoc();
    $stmt->close();

    if (!$property) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Property not found or you are not the host.']);
        exit();
    }

    if ($receiver_id === (int) $user['id']) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'You cannot message yourself.']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Failed to send reply. Please try again.']);
        exit();
    }
    $stmt->bind_param("iiis", $property_id, $user['id'], $receiver_id, $message);
    if ($stmt->execute()) {
        $new_id = (int) $conn->insert_id;
        $stmt->close();
        $conn->close();
        echo json_encode([
            'success' => true,
            'message' => 'Reply sent.',
            'id' => $new_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'error' => 'Failed to send reply. Please try again.']);
    }
} catch (Throwable $e) {
    if (isset($conn) && $conn) $conn->close();
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
}
