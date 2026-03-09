<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database_schema.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: host-verifications.php');
    exit();
}

$doc_id = (int) ($_POST['doc_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($doc_id < 1 || !in_array($action, ['approve', 'reject'])) {
    header('Location: host-verifications.php?error=invalid');
    exit();
}

$conn = getDBConnection();
initializeHostTables();

$stmt = $conn->prepare("SELECT user_id, verification_status FROM host_documents WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['verification_status'] !== 'pending') {
    $conn->close();
    header('Location: host-verifications.php?error=invalid');
    exit();
}

$status = $action === 'approve' ? 'approved' : 'rejected';
$stmt = $conn->prepare("UPDATE host_documents SET verification_status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $doc_id);
$stmt->execute();
$stmt->close();

$user_id = (int) $row['user_id'];
if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE users SET host_verified = 1, host_verification_status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("UPDATE users SET host_verification_status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header('Location: host-verifications.php?success=' . $action);
exit();
