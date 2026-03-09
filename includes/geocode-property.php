<?php
/**
 * Region-aware geocoding for properties (Lapu-Lapu/Cebu vs Manila, etc.).
 * Used by admin batch update and can be reused elsewhere.
 */

define('NOMINATIM_USER_AGENT', 'ReserveProPropertyMap/1.0 (Admin Geocode)');

function property_suggests_manila(array $property): bool {
    $combined = strtolower(trim($property['city'] ?? '') . ' ' . trim($property['address'] ?? ''));
    return (bool) preg_match('/manila|quezon city|makati|caloocan|pasig|mandaluyong|marikina|las piñas|taguig|parañaque|paranaque|valenzuela|malabon|navotas|san juan|pateros|muntinlupa|metro manila|ncr/i', $combined);
}

function property_needs_geocode(array $property): bool {
    $lat = isset($property['latitude']) && $property['latitude'] !== '' && $property['latitude'] !== null ? floatval($property['latitude']) : null;
    $lng = isset($property['longitude']) && $property['longitude'] !== '' && $property['longitude'] !== null ? floatval($property['longitude']) : null;
    if ($lat === null || $lng === null || (abs($lat) < 1e-6 && abs($lng) < 1e-6)) {
        return true;
    }
    $manilaBounds = ['latMin' => 14.4, 'latMax' => 14.8, 'lngMin' => 120.9, 'lngMax' => 121.1];
    $inManila = ($lat >= $manilaBounds['latMin'] && $lat <= $manilaBounds['latMax'] && $lng >= $manilaBounds['lngMin'] && $lng <= $manilaBounds['lngMax']);
    if ($inManila && !property_suggests_manila($property)) {
        return true;
    }
    $cityLower = strtolower(trim($property['city'] ?? ''));
    $addressLower = strtolower(trim($property['address'] ?? ''));
    $isLapuLapuOrCebu = (strpos($cityLower, 'lapu-lapu') !== false || strpos($cityLower, 'lapu lapu') !== false || strpos($cityLower, 'cebu') !== false || strpos($cityLower, 'talamban') !== false || strpos($addressLower, 'cebu city') !== false || strpos($addressLower, 'cebu') !== false || strpos($addressLower, 'talamban') !== false);
    if ($isLapuLapuOrCebu && $inManila) {
        return true;
    }
    return false;
}

function build_geocode_query(array $property): string {
    $address = trim($property['address'] ?? '');
    $city = trim($property['city'] ?? '');
    $country = trim($property['country'] ?? 'Philippines');
    $searchQuery = implode(', ', array_filter([$address, $city, $country]));
    if ($searchQuery === '') {
        return $city . ', ' . $country;
    }
    $cityLower = strtolower($city);
    $addressLower = strtolower($address);
    if (stripos($country, 'philippines') !== false) {
        if (strpos($cityLower, 'lapu-lapu') !== false || strpos($cityLower, 'lapu lapu') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Cebu, ' . $country;
        } elseif (strpos($cityLower, 'cebu city') !== false || $cityLower === 'cebu' || strpos($cityLower, 'talamban') !== false || strpos($addressLower, 'cebu city') !== false || strpos($addressLower, 'talamban') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Cebu, ' . $country;
        } elseif (strpos($cityLower, 'davao') !== false || strpos($addressLower, 'davao') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Davao del Sur, ' . $country;
        } elseif (strpos($cityLower, 'iloilo') !== false || strpos($addressLower, 'iloilo') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Iloilo, ' . $country;
        } elseif (strpos($cityLower, 'baguio') !== false || strpos($addressLower, 'baguio') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Benguet, ' . $country;
        } elseif (strpos($cityLower, 'bacolod') !== false || strpos($addressLower, 'bacolod') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Negros Occidental, ' . $country;
        } elseif (strpos($cityLower, 'cagayan de oro') !== false || strpos($cityLower, 'cdo') !== false || strpos($addressLower, 'cagayan de oro') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Misamis Oriental, ' . $country;
        } elseif (strpos($cityLower, 'zamboanga') !== false || strpos($addressLower, 'zamboanga') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', Zamboanga del Sur, ' . $country;
        } elseif (strpos($cityLower, 'general santos') !== false || strpos($cityLower, 'gensan') !== false || strpos($addressLower, 'general santos') !== false) {
            $searchQuery = ($address ? $address . ', ' : '') . $city . ', South Cotabato, ' . $country;
        }
    }
    return $searchQuery;
}

function geocode_property(array $property): ?array {
    $cityLower = strtolower(trim($property['city'] ?? ''));
    $addressLower = strtolower(trim($property['address'] ?? ''));
    $isLapuLapuOrCebu = (strpos($cityLower, 'lapu-lapu') !== false || strpos($cityLower, 'lapu lapu') !== false || strpos($cityLower, 'cebu') !== false || strpos($cityLower, 'talamban') !== false || strpos($addressLower, 'cebu city') !== false || strpos($addressLower, 'cebu') !== false || strpos($addressLower, 'talamban') !== false);
    $cebuBounds = ['latMin' => 10.0, 'latMax' => 11.0, 'lngMin' => 123.5, 'lngMax' => 124.2];
    $phBounds = ['latMin' => 4.5, 'latMax' => 21, 'lngMin' => 116, 'lngMax' => 127];
    $defaultLapuLapu = ['lat' => 10.3119, 'lng' => 123.9494];
    $defaultManila = ['lat' => 14.5995, 'lng' => 120.9842];

    $searchQuery = build_geocode_query($property);
    $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . rawurlencode($searchQuery) . '&limit=5';
    $ctx = stream_context_create([
        'http' => [
            'header' => "Accept: application/json\r\nUser-Agent: " . NOMINATIM_USER_AGENT . "\r\n",
            'timeout' => 10,
        ]
    ]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        return $isLapuLapuOrCebu ? $defaultLapuLapu : $defaultManila;
    }
    $results = json_decode($json, true);
    if (!is_array($results) || count($results) === 0) {
        if ($isLapuLapuOrCebu) {
            $addr = strtolower($property['address'] ?? '');
            $fallbackQuery = 'Cebu City, Cebu, Philippines';
            if (strpos($addr, 'maribago') !== false) $fallbackQuery = 'Maribago, Lapu-Lapu City, Cebu, Philippines';
            elseif (strpos($addr, 'talamban') !== false) $fallbackQuery = 'Talamban, Cebu City, Cebu, Philippines';
            elseif (strpos($addr, 'lapu-lapu') !== false) $fallbackQuery = 'Lapu-Lapu City, Cebu, Philippines';
            $url2 = 'https://nominatim.openstreetmap.org/search?format=json&q=' . rawurlencode($fallbackQuery) . '&limit=1';
            $json2 = @file_get_contents($url2, false, $ctx);
            if ($json2) {
                $r2 = json_decode($json2, true);
                if (!empty($r2[0]['lat']) && !empty($r2[0]['lon'])) {
                    return ['lat' => (float) $r2[0]['lat'], 'lng' => (float) $r2[0]['lon']];
                }
            }
            return $defaultLapuLapu;
        }
        return $defaultManila;
    }

    foreach ($results as $r) {
        $la = isset($r['lat']) ? floatval($r['lat']) : 0;
        $ln = isset($r['lon']) ? floatval($r['lon']) : 0;
        if ($la < $phBounds['latMin'] || $la > $phBounds['latMax'] || $ln < $phBounds['lngMin'] || $ln > $phBounds['lngMax']) {
            continue;
        }
        if ($isLapuLapuOrCebu) {
            if ($la >= $cebuBounds['latMin'] && $la <= $cebuBounds['latMax'] && $ln >= $cebuBounds['lngMin'] && $ln <= $cebuBounds['lngMax']) {
                return ['lat' => $la, 'lng' => $ln];
            }
        } else {
            return ['lat' => $la, 'lng' => $ln];
        }
    }

    if ($isLapuLapuOrCebu) {
        $addr = strtolower($property['address'] ?? '');
        $fallbackQuery = 'Cebu City, Cebu, Philippines';
        if (strpos($addr, 'maribago') !== false) $fallbackQuery = 'Maribago, Lapu-Lapu City, Cebu, Philippines';
        elseif (strpos($addr, 'talamban') !== false) $fallbackQuery = 'Talamban, Cebu City, Cebu, Philippines';
        elseif (strpos($addr, 'lapu-lapu') !== false) $fallbackQuery = 'Lapu-Lapu City, Cebu, Philippines';
        $url2 = 'https://nominatim.openstreetmap.org/search?format=json&q=' . rawurlencode($fallbackQuery) . '&limit=1';
        $json2 = @file_get_contents($url2, false, $ctx);
        if ($json2) {
            $r2 = json_decode($json2, true);
            if (!empty($r2[0]['lat']) && !empty($r2[0]['lon'])) {
                return ['lat' => (float) $r2[0]['lat'], 'lng' => (float) $r2[0]['lon']];
            }
        }
        return $defaultLapuLapu;
    }
    foreach ($results as $r) {
        $la = isset($r['lat']) ? floatval($r['lat']) : 0;
        $ln = isset($r['lon']) ? floatval($r['lon']) : 0;
        if ($la >= $phBounds['latMin'] && $la <= $phBounds['latMax'] && $ln >= $phBounds['lngMin'] && $ln <= $phBounds['lngMax']) {
            return ['lat' => $la, 'lng' => $ln];
        }
    }
    $first = $results[0];
    return ['lat' => (float) $first['lat'], 'lng' => (float) $first['lon']];
}
