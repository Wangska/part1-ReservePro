<?php
/**
 * Thin proxy for OpenStreetMap Nominatim (usage policy–friendly User-Agent).
 * GET q= full search string (address, city, country, etc.)
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($q === '' || strlen($q) > 400) {
    echo json_encode([]);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode($q);
$ctx = stream_context_create([
    'http' => [
        'header' => "User-Agent: ReserveProHostPropertyMap/1.0\r\nAccept: application/json\r\n",
        'timeout' => 12,
        'ignore_errors' => true,
    ],
]);
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false || $raw === '') {
    echo json_encode([]);
    exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode([]);
    exit;
}
echo json_encode($data);
