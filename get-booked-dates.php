<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;

if (!$property_id) {
    echo json_encode(['error' => 'Invalid property ID', 'dates' => []]);
    exit();
}

$conn = getDBConnection();

// Get all booked date ranges (pending + confirmed) for this property
$stmt = $conn->prepare("
    SELECT check_in, check_out
    FROM bookings
    WHERE property_id = ? AND status IN ('pending', 'confirmed')
    ORDER BY check_in
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();
$booked_dates = [];
while ($row = $result->fetch_assoc()) {
    $start = new DateTime($row['check_in']);
    $end = new DateTime($row['check_out']);
    while ($start < $end) {
        $booked_dates[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }
}
$stmt->close();
$conn->close();

$booked_dates = array_values(array_unique($booked_dates));
sort($booked_dates);

echo json_encode(['dates' => $booked_dates]);
