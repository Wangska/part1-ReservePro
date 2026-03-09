<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
$user = getCurrentUser();

// Only verified hosts can update bookings
if (!$user || ($user['role'] ?? null) !== 'host') {
    header('Location: ../home.php');
    exit();
}
if (empty($user['host_verified'])) {
    header('Location: verify-account.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php');
    exit();
}

$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
$new_status = $_POST['new_status'] ?? '';

// For now we only allow hosts to move a booking from pending -> confirmed
if ($booking_id <= 0 || $new_status !== 'confirmed') {
    header('Location: bookings.php?error=invalid');
    exit();
}

$conn = getDBConnection();

// Update booking status, ensuring the booking belongs to one of this host's properties
$stmt = $conn->prepare("
    UPDATE bookings b
    JOIN properties p ON b.property_id = p.id
    SET b.status = 'confirmed'
    WHERE b.id = ? 
      AND p.host_id = ?
      AND b.status = 'pending'
");
$stmt->bind_param('ii', $booking_id, $user['id']);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

// Redirect back to booking details view
$target = 'view-booking.php?id=' . $booking_id;
if ($affected === 0) {
    // nothing changed (maybe already confirmed/cancelled or not this host's booking)
    $target .= '&error=update_failed';
}
header('Location: ' . $target);
exit();

