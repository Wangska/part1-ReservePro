<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/database_schema.php';
    // Ensure any newly added user columns exist (idempotent)
    if (function_exists('initializeHostTables')) {
        initializeHostTables();
    }
    $conn = getDBConnection();
    
    $user_id = $_SESSION['user_id'];
    // Include common profile fields (host_verified when present)
    $stmt = $conn->prepare("SELECT id, first_name, last_name, date_of_birth, profile_photo, email, role, IFNULL(host_verified, 0) AS host_verified FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $user;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        // Always redirect to the main login page at the project root
        header('Location: /part1-ReservePro/login.php');
        exit();
    }
}

// Redirect if already logged in (based on role)
function requireGuest() {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        
        if ($user && isset($user['role'])) {
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'host':
                    header('Location: host/dashboard.php');
                    break;
                default:
                    // Guests go to browse properties
                    header('Location: home.php');
                    break;
            }
        } else {
            // Default to browse properties
            header('Location: home.php');
        }
        exit();
    }
}
?>
