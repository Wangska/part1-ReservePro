<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$role = (string)($user['role'] ?? 'guest');
if ($role === 'admin') {
    header('Location: admin/dashboard.php');
    exit();
}
$errors = [];
$success = false;

function rp_save_profile_photo(array $file, int $userId, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Failed to upload profile photo.'; return null; }

    $maxSize = 3 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) { $errors[] = 'Profile photo is too large (max 3MB).'; return null; }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
    if (!in_array($ext, $allowed, true)) { $errors[] = 'Profile photo must be an image (JPG, PNG, WEBP, or AVIF).'; return null; }

    $tmp = $file['tmp_name'] ?? '';
    $img = @getimagesize($tmp);
    if ($img && !empty($img[0]) && !empty($img[1])) {
        $w = (int)$img[0];
        $h = (int)$img[1];
        if ($w < 200 || $h < 200) { $errors[] = 'Profile photo is too small (min 200×200).'; return null; }
    } elseif ($ext === 'avif') {
        $mime = function_exists('mime_content_type') ? (string)@mime_content_type($tmp) : '';
        if ($mime !== '' && stripos($mime, 'image/') !== 0) { $errors[] = 'Profile photo must be a valid image.'; return null; }
    } else {
        $errors[] = 'Profile photo must be a valid image.';
        return null;
    }

    $baseDir = __DIR__ . '/uploads/profile-photos/' . (int)$userId . '/';
    if (!file_exists($baseDir)) {
        @mkdir($baseDir, 0777, true);
        @chmod($baseDir, 0777);
    }
    if (!is_dir($baseDir) || !is_writable($baseDir)) {
        $errors[] = 'Upload directory is not writable. Please contact support.';
        return null;
    }
    $filename = 'avatar_' . (int)$userId . '_' . time() . '.' . $ext;
    $dest = $baseDir . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = 'Failed to save profile photo.';
        return null;
    }
    return 'uploads/profile-photos/' . (int)$userId . '/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';

    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

    $dob = null;
    if ($date_of_birth === '') {
        $errors[] = 'Date of birth is required.';
    } else {
        try {
            $dob = (new DateTimeImmutable($date_of_birth))->format('Y-m-d');
        } catch (Exception $e) {
            $errors[] = 'Invalid date of birth.';
        }
    }

    if (empty($errors)) {
        initializeHostTables();
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
            $photoPath = rp_save_profile_photo($_FILES['profile_photo'] ?? [], (int)$user['id'], $errors);
            if (empty($errors)) {
                if ($photoPath !== null) {
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, date_of_birth = ?, email = ?, profile_photo = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $first_name, $last_name, $dob, $email, $photoPath, $user['id']);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, date_of_birth = ?, email = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $first_name, $last_name, $dob, $email, $user['id']);
                }
            }
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
    header('Location: ' . ($role === 'host' ? 'host/profile.php?updated=1' : 'profile.php?updated=1'));
    exit();
}

// If we have errors, redirect back with them
if (!empty($errors)) {
    $_SESSION['profile_errors'] = $errors;
    $_SESSION['profile_old'] = [
        'first_name' => $first_name ?? '',
        'last_name' => $last_name ?? '',
        'email' => $email ?? '',
        'date_of_birth' => $date_of_birth ?? '',
    ];
    header('Location: ' . ($role === 'host' ? 'host/profile.php' : 'profile.php'));
    exit();
}

header('Location: ' . ($role === 'host' ? 'host/profile.php' : 'profile.php'));
exit();
