<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in as a guest to leave a review.']);
    exit();
}

$user = getCurrentUser();
if (!isset($user['role']) || $user['role'] !== 'guest') {
    http_response_code(403);
    echo json_encode(['error' => 'Only guest accounts can leave reviews.']);
    exit();
}

$property_id = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$comment = trim($_POST['comment'] ?? '');

if ($property_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid property ID.']);
    exit();
}
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Rating must be between 1 and 5 stars.']);
    exit();
}
if ($comment === '' || mb_strlen($comment) < 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a short comment (at least 10 characters).']);
    exit();
}

$conn = getDBConnection();
// Ensure reviews table/columns exist
initializeHostTables();

// Ensure property exists and is approved
$stmt = $conn->prepare("SELECT id, host_id, status FROM properties WHERE id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property || $property['status'] !== 'approved') {
    http_response_code(404);
    echo json_encode(['error' => 'Property not found or not available.']);
    $conn->close();
    exit();
}

// Prevent reviewing own property as host
if ((int) $property['host_id'] === (int) $user['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'You cannot review your own property.']);
    $conn->close();
    exit();
}

// Ensure guest has at least one confirmed/completed booking for this property
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM bookings 
    WHERE property_id = ? 
      AND guest_id = ? 
      AND status IN ('confirmed', 'completed')
");
$stmt->bind_param("ii", $property_id, $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || (int) $row['cnt'] === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only review properties you have booked.']);
    $conn->close();
    exit();
}

// Insert or update review (one review per guest per property)
$stmt = $conn->prepare("SELECT id FROM property_reviews WHERE property_id = ? AND guest_id = ?");
$stmt->bind_param("ii", $property_id, $user['id']);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $review_id = (int) $existing['id'];
    $stmt = $conn->prepare("UPDATE property_reviews SET rating = ?, comment = ? WHERE id = ?");
    $stmt->bind_param("isi", $rating, $comment, $review_id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO property_reviews (property_id, guest_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $property_id, $user['id'], $rating, $comment);
    $stmt->execute();
    $review_id = $stmt->insert_id;
    $stmt->close();
}

// Recalculate summary rating for the property
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating FROM property_reviews WHERE property_id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$review_count = (int) ($summary['cnt'] ?? 0);
$average_rating = $summary['avg_rating'] !== null ? round((float) $summary['avg_rating'], 2) : null;

$stmt = $conn->prepare("UPDATE properties SET review_count = ?, average_rating = ? WHERE id = ?");
$stmt->bind_param("idi", $review_count, $average_rating, $property_id);
$stmt->execute();
$stmt->close();

// Return the newly saved/updated review (for optimistic UI update)
$response_review = [
    'id' => $review_id,
    'rating' => $rating,
    'comment' => $comment,
    'created_at' => date('Y-m-d H:i:s'),
    'first_name' => $user['first_name'] ?? '',
    'last_name' => $user['last_name'] ?? '',
];

$conn->close();

echo json_encode([
    'success' => true,
    'review' => $response_review,
    'average_rating' => $average_rating,
    'review_count' => $review_count,
]);

