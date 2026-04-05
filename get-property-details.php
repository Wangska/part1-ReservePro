<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paymongo.php';

header('Content-Type: application/json');

$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$property_id) {
    echo json_encode(['error' => 'Invalid property ID']);
    exit();
}

$conn = getDBConnection();

// Get property details
$stmt = $conn->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email
    FROM properties p
    JOIN users u ON p.host_id = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();
$property = $result->fetch_assoc();
$stmt->close();

if (!$property) {
    echo json_encode(['error' => 'Property not found']);
    $conn->close();
    exit();
}

// Get property photos
$stmt = $conn->prepare("
    SELECT photo_url, is_primary 
    FROM property_photos 
    WHERE property_id = ? 
    ORDER BY is_primary DESC, id ASC
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$photos_result = $stmt->get_result();
$property['photos'] = $photos_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get property amenities
$stmt = $conn->prepare("
    SELECT a.name, a.icon 
    FROM amenities a
    JOIN property_amenities pa ON a.id = pa.amenity_id
    WHERE pa.property_id = ?
    ORDER BY a.name
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$amenities_result = $stmt->get_result();
$property['amenities'] = $amenities_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get latest reviews for this property (if reviews table exists)
$property['reviews'] = [];
$checkReviewsTable = $conn->query("SHOW TABLES LIKE 'property_reviews'");
if ($checkReviewsTable && $checkReviewsTable->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at,
               u.first_name, u.last_name
        FROM property_reviews r
        JOIN users u ON r.guest_id = u.id
        WHERE r.property_id = ?
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    if ($stmt) {
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $reviews_result = $stmt->get_result();
        if ($reviews_result) {
            $property['reviews'] = $reviews_result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }
}

$conn->close();

$property['paymongo_available'] = paymongo_is_configured();

echo json_encode($property);
?>
