<?php
/**
 * Reverse geocode proxy (Leaflet pin -> address fields).
 * GET lat=..., lng=...
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$latRaw = isset($_GET['lat']) ? trim((string) $_GET['lat']) : '';
$lngRaw = isset($_GET['lng']) ? trim((string) $_GET['lng']) : '';

if ($latRaw === '' || $lngRaw === '') {
    echo json_encode(['ok' => false]);
    exit;
}

$lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
$lng = filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false) {
    echo json_encode(['ok' => false]);
    exit;
}

// Basic sanity: keep within valid lat/lng ranges
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['ok' => false]);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1'
    . '&lat=' . rawurlencode((string) $lat)
    . '&lon=' . rawurlencode((string) $lng);

$ctx = stream_context_create([
    'http' => [
        'header' => "User-Agent: ReserveProHostPropertyMap/1.0\r\nAccept: application/json\r\n",
        'timeout' => 12,
        'ignore_errors' => true,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false || $raw === '') {
    echo json_encode(['ok' => false]);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true, 'data' => $data]);

