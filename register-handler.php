<?php
// Handler for modal registration (when coming from home.php modal)
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'guest';
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match";
        header('Location: home.php?error=password_mismatch');
        exit();
    }
    
    $result = Auth::register($first_name, $last_name, $email, $password, $role);
    
    if ($result['success']) {
        // Redirect based on selected role
        if ($role === 'host') {
            // Hosts must complete verification before accessing the dashboard
            header('Location: host/verify-account.php');
        } else {
            // Guests go to browse properties (home page)
            header('Location: home.php');
        }
        exit();
    } else {
        $_SESSION['register_errors'] = $result['errors'];
        header('Location: home.php?error=registration');
        exit();
    }
}

// If not POST, redirect to home
header('Location: home.php');
exit();
?>
