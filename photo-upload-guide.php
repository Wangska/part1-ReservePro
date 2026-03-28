<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="background%20image/newicon.png" type="image/png">
    <title>Photo Upload Guide - ReservePro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .guide-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #D4A574;
            border-bottom: 3px solid #D4A574;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
        }
        .step {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #D4A574;
            border-radius: 4px;
        }
        .step h3 {
            margin-top: 0;
            color: #D4A574;
        }
        .btn {
            display: inline-block;
            background: #D4A574;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 10px 10px 0;
            font-weight: bold;
        }
        .btn:hover {
            background: #B8935F;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #28a745;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        ol {
            line-height: 2;
        }
        img {
            max-width: 100%;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="guide-box">
        <h1>📸 Photo Upload Guide</h1>
        
        <div class="success">
            ✅ <strong>Upload directory created successfully!</strong><br>
            Location: <code>C:\xampp\htdocs\part1\uploads\properties\</code>
        </div>

        <h2>🎯 How to Add Photos to Your Property</h2>

        <div class="step">
            <h3>Step 1: Go to Add Property Page</h3>
            <p>Navigate to <strong>Host Dashboard → Add Property</strong></p>
            <a href="host/add-property.php" class="btn">📝 Add Property</a>
        </div>

        <div class="step">
            <h3>Step 2: Fill Out Property Details</h3>
            <p>Fill in all required fields:</p>
            <ul>
                <li>Property Title</li>
                <li>Description</li>
                <li>Property Type</li>
                <li>Location (Address, City, Country)</li>
                <li>Price per Night</li>
                <li>Capacity (Guests, Bedrooms, Bathrooms)</li>
                <li>Select Amenities</li>
            </ul>
        </div>

        <div class="step">
            <h3>Step 3: Upload Photos (NEW SECTION!)</h3>
            <p>Scroll down to the <strong>"📸 Property Photos"</strong> section:</p>
            <ol>
                <li><strong>Click</strong> on the upload area (or drag & drop images)</li>
                <li><strong>Select</strong> 1 to 5 photos from your computer</li>
                <li><strong>Preview</strong> will appear below showing your selected photos</li>
                <li>The <strong>first photo</strong> will be marked as "PRIMARY"</li>
                <li>You'll see: <strong>"Ready to upload: X photo(s) selected"</strong></li>
            </ol>
        </div>

        <div class="step">
            <h3>Step 4: Submit the Form</h3>
            <p>Click <strong>"Submit for Review"</strong> button at the bottom</p>
            <p>The property AND photos will be uploaded together!</p>
        </div>

        <div class="step">
            <h3>Step 5: Admin Approval</h3>
            <p>Login as admin and approve the property</p>
            <ol>
                <li>Go to <strong>Admin Dashboard</strong></li>
                <li>Find your property in the pending reviews</li>
                <li>Click <strong>"Approve"</strong></li>
            </ol>
        </div>

        <div class="step">
            <h3>Step 6: View Photos on Home Page</h3>
            <p>Once approved, the property with photos will appear on:</p>
            <ul>
                <li>🏠 <strong>Home Page</strong> - in the property grid</li>
                <li>✨ <strong>Experiences Page</strong> - in featured experiences</li>
            </ul>
        </div>

        <div class="warning">
            <strong>⚠️ Important Notes:</strong>
            <ul>
                <li>Photos are <strong>uploaded when you submit the form</strong></li>
                <li>Existing properties don't have photos (they were added before this feature)</li>
                <li>To see photos, you must <strong>add a NEW property</strong> with photos</li>
                <li>Maximum 5 photos per property</li>
                <li>Supported formats: JPG, PNG, GIF, WEBP</li>
                <li>Max size: 5MB per photo</li>
            </ul>
        </div>

        <h2>🧪 Quick Test Options</h2>
        
        <a href="quick-test-property.php" class="btn">🚀 Quick Test (Simple Form)</a>
        <a href="test-upload.php" class="btn">🔍 Check Uploads</a>
        <a href="host/add-property.php" class="btn">📝 Full Add Property Form</a>

        <hr style="margin: 40px 0;">

        <h2>📊 Current Status</h2>
        <?php
        require_once __DIR__ . '/config/database.php';
        $conn = getDBConnection();
        
        // Count properties
        $result = $conn->query("SELECT COUNT(*) as total FROM properties");
        $total_properties = $result->fetch_assoc()['total'];
        
        // Count properties with photos
        $result = $conn->query("SELECT COUNT(DISTINCT property_id) as total FROM property_photos");
        $properties_with_photos = $result->fetch_assoc()['total'];
        
        // Count total photos
        $result = $conn->query("SELECT COUNT(*) as total FROM property_photos");
        $total_photos = $result->fetch_assoc()['total'];
        
        echo "<ul style='font-size: 18px; line-height: 2;'>";
        echo "<li>📊 <strong>Total Properties:</strong> $total_properties</li>";
        echo "<li>📸 <strong>Properties with Photos:</strong> $properties_with_photos</li>";
        echo "<li>🖼️ <strong>Total Photos Uploaded:</strong> $total_photos</li>";
        echo "</ul>";
        
        if ($total_photos == 0) {
            echo "<div class='warning'>";
            echo "<strong>No photos uploaded yet!</strong><br>";
            echo "Use the 'Add Property' form or 'Quick Test' button above to upload your first property with photos.";
            echo "</div>";
        }
        
        $conn->close();
        ?>
    </div>
</body>
</html>
