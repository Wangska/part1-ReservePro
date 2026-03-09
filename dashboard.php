<?php
require_once __DIR__ . '/config/session.php';

$user = isLoggedIn() ? getCurrentUser() : null;

// Hosts and admins go to their own dashboards
if ($user && isset($user['role'])) {
    if ($user['role'] === 'host') {
        header('Location: host/dashboard.php');
        exit();
    }
    if ($user['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    }
}

// Guests: redirect to home page
header('Location: home.php');
exit();
