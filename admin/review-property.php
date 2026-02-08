<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = intval($_POST['property_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($property_id > 0 && in_array($action, ['approve', 'reject'])) {
        $conn = getDBConnection();
        
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $stmt = $conn->prepare("UPDATE properties SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $admin_notes, $property_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header('Location: dashboard.php?success=' . $action);
            exit();
        }
        
        $stmt->close();
        $conn->close();
    }
}

header('Location: dashboard.php?error=invalid_request');
exit();
?>
