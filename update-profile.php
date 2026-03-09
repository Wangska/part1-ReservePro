<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
// Only guests can update profile via this page
if ($user['role'] !== 'guest') {
    header('Location: ' . ($user['role'] === 'host' ? 'host/dashboard.php' : ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php')));
    exit();
}
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

    if (empty($errors)) {
        $conn = getDBConnection();
        // If email changed, check it's not taken by another user
        if (strcasecmp($email, $user['email']) !== 0) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user['id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'That email is already in use.';
            }
            $stmt->close();
        }
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssi", $first_name, $last_name, $email, $user['id']);
            if ($stmt->execute()) {
                $success = true;
                $_SESSION['profile_updated'] = true;
            } else {
                $errors[] = 'Could not update profile. Please try again.';
            }
            $stmt->close();
            $conn->close();
        }
    }
}

if ($success) {
    header('Location: profile.php?updated=1');
    exit();
}

// If we have errors, redirect back with them
if (!empty($errors)) {
    $_SESSION['profile_errors'] = $errors;
    $_SESSION['profile_old'] = [
        'first_name' => $first_name ?? '',
        'last_name' => $last_name ?? '',
        'email' => $email ?? ''
    ];
    header('Location: profile.php');
    exit();
}

header('Location: profile.php');
exit();
