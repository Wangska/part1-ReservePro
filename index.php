<?php
require_once __DIR__ . '/config/session.php';

// Redirect based on login status and role
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
} else {
    header('Location: home.php');
}
exit();
?>
