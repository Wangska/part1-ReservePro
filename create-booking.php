<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';
require_once __DIR__ . '/config/paymongo.php';

header('Content-Type: application/json');

// Must be logged in as a guest
requireLogin();
$user = getCurrentUser();
if (!$user || ($user['role'] ?? 'guest') !== 'guest') {
    echo json_encode(['error' => 'Only guest accounts can make bookings.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit();
}

$property_id = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$check_in    = trim($_POST['check_in'] ?? '');
$check_out   = trim($_POST['check_out'] ?? '');
$guests      = isset($_POST['guests']) ? (int) $_POST['guests'] : 0;

if ($property_id <= 0 || !$check_in || !$check_out || $guests <= 0) {
    echo json_encode(['error' => 'Missing or invalid booking details.']);
    exit();
}

// Basic date validation
try {
    $checkInDate  = new DateTime($check_in);
    $checkOutDate = new DateTime($check_out);
} catch (Exception $e) {
    echo json_encode(['error' => 'Invalid date format.']);
    exit();
}

if ($checkOutDate <= $checkInDate) {
    echo json_encode(['error' => 'Check-out date must be after check-in date.']);
    exit();
}

$today = new DateTime('today');
if ($checkInDate < $today) {
    echo json_encode(['error' => 'Check-in date must be today or later.']);
    exit();
}

// Compute nights
$interval = $checkInDate->diff($checkOutDate);
$nights   = (int) $interval->days;
if ($nights <= 0) {
    echo json_encode(['error' => 'Stay must be at least 1 night.']);
    exit();
}

$conn = getDBConnection();

// Load property info
$stmt = $conn->prepare("
    SELECT id, host_id, title, price_per_night, max_guests, status, auto_accept_bookings
    FROM properties
    WHERE id = ? AND status = 'approved'
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property) {
    $conn->close();
    echo json_encode(['error' => 'Property not available for booking.']);
    exit();
}

if ($guests > (int)$property['max_guests']) {
    $conn->close();
    echo json_encode(['error' => 'Number of guests exceeds the maximum allowed for this property.']);
    exit();
}

// Check for overlapping bookings (pending or confirmed)
$stmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM bookings
    WHERE property_id = ?
      AND status IN ('pending', 'confirmed')
      AND (check_in < ? AND check_out > ?)
");
$checkInStr  = $checkInDate->format('Y-m-d');
$checkOutStr = $checkOutDate->format('Y-m-d');
$stmt->bind_param("iss", $property_id, $checkOutStr, $checkInStr);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row && (int)$row['cnt'] > 0) {
    $conn->close();
    echo json_encode(['error' => 'Selected dates are no longer available. Please choose different dates.']);
    exit();
}

if (!paymongo_is_configured()) {
    $conn->close();
    echo json_encode(['error' => 'Online payment is not configured. Bookings are unavailable until PayMongo is set up.']);
    exit();
}

// Calculate total price (nights * price_per_night + 10% service fee)
$pricePerNight = (float) $property['price_per_night'];
$subtotal      = $nights * $pricePerNight;
$serviceFee    = $subtotal * 0.10;
$total         = $subtotal + $serviceFee;

$status = ((int)$property['auto_accept_bookings'] === 1) ? 'confirmed' : 'pending';

$stmt = $conn->prepare("
    INSERT INTO bookings (property_id, guest_id, check_in, check_out, guests, total_price, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$guest_id = (int)$user['id'];
$stmt->bind_param(
    "iissids",
    $property_id,
    $guest_id,
    $checkInStr,
    $checkOutStr,
    $guests,
    $total,
    $status
);

if ($stmt->execute()) {
    $booking_id = $stmt->insert_id;
    $stmt->close();

    $paymentUrl = null;
    $payment_checkout_failed = false;

    $payStmt = $conn->prepare("
        INSERT INTO payments (booking_id, provider, method, amount, status)
        VALUES (?, 'paymongo', 'checkout_session', ?, 'pending')
    ");
    $payStmt->bind_param("id", $booking_id, $total);
    $payStmt->execute();
    $payment_id = (int) $conn->insert_id;
    $payStmt->close();

    $amountCentavos = (int) round($total * 100);
    $base = paymongo_app_base_url();
    $successUrl = $base . '/home.php?payment=success&booking_id=' . $booking_id;
    $cancelUrl = $base . '/home.php?payment=cancel&booking_id=' . $booking_id;
    $propTitle = trim((string) ($property['title'] ?? 'Property'));
    $lineName = 'Stay: ' . $propTitle;
    $desc = sprintf('Booking #%d · %d night(s)', $booking_id, $nights);
    $session = paymongo_create_checkout_session(
        $amountCentavos,
        (string) $booking_id,
        ['booking_id' => (string) $booking_id],
        $successUrl,
        $cancelUrl,
        substr($lineName, 0, 255),
        $desc
    );
    if ($session !== null) {
        $paymentUrl = $session['checkout_url'];
        $csId = $session['session_id'];
        $up = $conn->prepare('UPDATE payments SET external_reference = ? WHERE id = ?');
        $up->bind_param('si', $csId, $payment_id);
        $up->execute();
        $up->close();
    } else {
        $payment_checkout_failed = true;
    }

    $conn->close();

    echo json_encode([
        'success'                 => true,
        'booking_id'              => $booking_id,
        'status'                  => $status,
        'nights'                  => $nights,
        'subtotal'                => $subtotal,
        'service_fee'             => $serviceFee,
        'total'                   => $total,
        'payment_url'             => $paymentUrl,
        'payment_checkout_failed' => $payment_checkout_failed,
        'message'                 => $status === 'confirmed'
            ? 'Your booking is confirmed!'
            : 'Your booking request has been sent to the host.'
    ]);
    exit();
}

$stmt->close();
$conn->close();

echo json_encode(['error' => 'Failed to create booking. Please try again.']);
