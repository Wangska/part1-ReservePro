<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

requireLogin();
$user = getCurrentUser();

// Simple form to test photo upload
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Photo Upload Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background: #D4A574;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #B8935F;
        }
        .preview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .preview img {
            width: 150px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Quick Property Test</h1>
        <p>Test adding a property with photos</p>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $conn = getDBConnection();
            
            // Insert minimal property
            $title = $_POST['title'] ?? 'Test Property';
            $description = $_POST['description'] ?? 'Test Description';
            $price = 1000;
            
            $stmt = $conn->prepare("INSERT INTO properties (host_id, title, description, property_type, address, city, country, price_per_night, max_guests, bedrooms, bathrooms, status) VALUES (?, ?, ?, 'house', '123 Test St', 'Manila', 'Philippines', ?, 2, 1, 1, 'pending')");
            $stmt->bind_param("issd", $user['id'], $title, $description, $price);
            
            if ($stmt->execute()) {
                $property_id = $stmt->insert_id;
                echo "<div class='success'>✅ Property created! ID: $property_id</div>";
                
                // Handle photos
                if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/part1/uploads/properties/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $photo_count = 0;
                    $is_primary = 1;
                    
                    for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
                        if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_tmp = $_FILES['photos']['tmp_name'][$i];
                            $file_name = $_FILES['photos']['name'][$i];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            
                            $new_filename = 'property_' . $property_id . '_' . time() . '_' . $i . '.' . $file_ext;
                            $upload_path = $upload_dir . $new_filename;
                            $photo_url = 'uploads/properties/' . $new_filename;
                            
                            if (move_uploaded_file($file_tmp, $upload_path)) {
                                $stmt = $conn->prepare("INSERT INTO property_photos (property_id, photo_url, is_primary) VALUES (?, ?, ?)");
                                $stmt->bind_param("isi", $property_id, $photo_url, $is_primary);
                                $stmt->execute();
                                
                                echo "<div class='success'>✅ Photo uploaded: $new_filename (Primary: " . ($is_primary ? 'Yes' : 'No') . ")</div>";
                                
                                $is_primary = 0;
                                $photo_count++;
                            } else {
                                echo "<div class='error'>❌ Failed to move file: $file_name</div>";
                            }
                        }
                    }
                    
                    echo "<div class='success'>✅ Total photos uploaded: $photo_count</div>";
                } else {
                    echo "<div class='error'>⚠️ No photos uploaded</div>";
                }
                
                echo "<p><a href='home.php'>View on Home Page</a> | <a href='test-upload.php'>Check Upload Test</a></p>";
            } else {
                echo "<div class='error'>❌ Failed to create property</div>";
            }
            
            $conn->close();
        }
        ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Property Title:</label>
                <input type="text" name="title" value="Beautiful Beach House" required>
            </div>

            <div class="form-group">
                <label>Description:</label>
                <textarea name="description" rows="3" required>Amazing property with ocean view</textarea>
            </div>

            <div class="form-group">
                <label>Upload Photos (1-5 images):</label>
                <input type="file" name="photos[]" accept="image/*" multiple required id="photoInput">
                <div class="preview" id="preview"></div>
            </div>

            <button type="submit">🚀 Create Test Property</button>
        </form>

        <hr style="margin: 30px 0;">
        <p><a href="host/add-property.php">← Back to Full Add Property Form</a></p>
    </div>

    <script>
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            preview.innerHTML = '';
            
            for (let i = 0; i < e.target.files.length && i < 5; i++) {
                const file = e.target.files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
