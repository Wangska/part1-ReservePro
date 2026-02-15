<?php
// Quick test to verify the API endpoint works

// Test 1: Check if database.php exists
if (file_exists(__DIR__ . '/config/database.php')) {
    echo "✓ database.php exists<br>";
} else {
    echo "✗ database.php NOT found<br>";
    exit;
}

// Test 2: Include database and get connection
require_once __DIR__ . '/config/database.php';
echo "✓ database.php included successfully<br>";

$conn = getDBConnection();
echo "✓ Database connection established<br>";

// Test 3: Get a property ID
$result = $conn->query("SELECT id FROM properties WHERE status = 'approved' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $property_id = $row['id'];
    echo "✓ Found property ID: " . $property_id . "<br>";
    
    // Test 4: Try to fetch property details
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
    
    if ($property) {
        echo "✓ Property found: " . $property['title'] . "<br>";
        echo "<br><strong>Test the API directly:</strong><br>";
        echo "<a href='get-property-details.php?id=" . $property_id . "' target='_blank'>Click here to test API</a><br>";
        echo "<br><strong>Property Data Preview:</strong><br>";
        echo "<pre>" . print_r($property, true) . "</pre>";
    } else {
        echo "✗ Property not found<br>";
    }
    
    $stmt->close();
} else {
    echo "✗ No approved properties found in database<br>";
}

$conn->close();
?>
