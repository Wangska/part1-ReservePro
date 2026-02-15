<?php
// Test upload functionality

echo "<h2>Upload Directory Test</h2>";

$upload_dir = __DIR__ . '/uploads/properties/';

// Check if directory exists
if (file_exists($upload_dir)) {
    echo "✅ Directory exists: $upload_dir<br>";
    
    // Check if writable
    if (is_writable($upload_dir)) {
        echo "✅ Directory is writable<br>";
    } else {
        echo "❌ Directory is NOT writable<br>";
    }
    
    // List files
    $files = scandir($upload_dir);
    echo "<h3>Files in directory:</h3>";
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
} else {
    echo "❌ Directory does NOT exist: $upload_dir<br>";
    echo "Attempting to create directory...<br>";
    
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Directory created successfully!<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

// Check database for photos
require_once __DIR__ . '/config/database.php';
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM property_photos ORDER BY id DESC LIMIT 10");

echo "<h3>Recent photos in database:</h3>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Property ID</th><th>Photo URL</th><th>Is Primary</th><th>Preview</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['property_id']}</td>";
        echo "<td>{$row['photo_url']}</td>";
        echo "<td>" . ($row['is_primary'] ? 'Yes' : 'No') . "</td>";
        echo "<td><img src='{$row['photo_url']}' style='width: 100px; height: 75px; object-fit: cover;' onerror='this.src=\"https://via.placeholder.com/100x75?text=Error\"'></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No photos found in database.</p>";
}

$conn->close();
?>
