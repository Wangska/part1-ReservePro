<?php
/**
 * PayMongo Checkout API helpers.
 *
 * Add keys via environment variables, or copy paymongo.local.example.php
 * to paymongo.local.php (gitignored) and define constants there.
 */
if (is_readable(__DIR__ . '/paymongo.local.php')) {
    require_once __DIR__ . '/paymongo.local.php';
}

if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', getenv('PAYMONGO_SECRET_KEY') ?: '');
}
if (!defined('PAYMONGO_PUBLIC_KEY')) {
    define('PAYMONGO_PUBLIC_KEY', getenv('PAYMONGO_PUBLIC_KEY') ?: '');
}
if (!defined('PAYMONGO_WEBHOOK_SECRET')) {
    define('PAYMONGO_WEBHOOK_SECRET', getenv('PAYMONGO_WEBHOOK_SECRET') ?: '');
}
/** No trailing slash; used for success/cancel URLs if set */
if (!defined('PAYMONGO_APP_BASE_URL')) {
    define('PAYMONGO_APP_BASE_URL', getenv('PAYMONGO_APP_BASE_URL') ?: '');
}
/** Comma-separated PayMongo payment_method_types (must be enabled on your account) */
if (!defined('PAYMONGO_PAYMENT_METHOD_TYPES')) {
    define('PAYMONGO_PAYMENT_METHOD_TYPES', getenv('PAYMONGO_PAYMENT_METHOD_TYPES') ?: 'card,gcash');
}

function paymongo_is_configured(): bool
{
    return trim((string) PAYMONGO_SECRET_KEY) !== '';
}

function paymongo_app_base_url(): string
{
    if (PAYMONGO_APP_BASE_URL !== '') {
        return rtrim((string) PAYMONGO_APP_BASE_URL, '/');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return $scheme . '://' . $host;
    }
    return $scheme . '://' . $host . $dir;
}

/**
 * @return array{checkout_url:string,session_id:string}|null
 */
function paymongo_create_checkout_session(
    int $amountCentavos,
    string $referenceNumber,
    array $metadata,
    string $successUrl,
    string $cancelUrl,
    string $lineName,
    string $description
): ?array {
    $sk = trim((string) PAYMONGO_SECRET_KEY);
    if ($sk === '' || $amountCentavos < 1) {
        return null;
    }

    $methods = array_map('trim', explode(',', (string) PAYMONGO_PAYMENT_METHOD_TYPES));
    $methods = array_values(array_filter($methods));
    if ($methods === []) {
        $methods = ['card', 'gcash'];
    }

    $metaStrings = [];
    foreach ($metadata as $k => $v) {
        $metaStrings[(string) $k] = (string) $v;
    }

    $payload = [
        'data' => [
            'attributes' => [
                'line_items' => [[
                    'amount' => $amountCentavos,
                    'currency' => 'PHP',
                    'name' => $lineName,
                    'quantity' => 1,
                    'description' => substr($description, 0, 255),
                ]],
                'payment_method_types' => $methods,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'reference_number' => substr($referenceNumber, 0, 191),
                'description' => substr($description, 0, 255),
                'metadata' => $metaStrings,
            ],
        ],
    ];

    $body = json_encode($payload);
    if ($body === false) {
        return null;
    }

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($sk . ':'),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
    ]);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        error_log('PayMongo checkout_sessions error HTTP ' . $code . ' body: ' . substr((string) $resp, 0, 2000));
        return null;
    }

    $json = json_decode((string) $resp, true);
    if (!is_array($json)) {
        return null;
    }

    $checkoutUrl = $json['data']['attributes']['checkout_url'] ?? null;
    $sessionId = $json['data']['id'] ?? null;
    if (!is_string($checkoutUrl) || $checkoutUrl === '' || !is_string($sessionId) || $sessionId === '') {
        error_log('PayMongo checkout_sessions unexpected response: ' . substr((string) $resp, 0, 1500));
        return null;
    }

    return ['checkout_url' => $checkoutUrl, 'session_id' => $sessionId];
}

/**
 * Verifies Paymongo-Signature when PAYMONGO_WEBHOOK_SECRET is set.
 */
function paymongo_verify_webhook_signature(string $payload, string $signatureHeader, string $webhookSecret): bool
{
    $webhookSecret = trim($webhookSecret);
    if ($webhookSecret === '') {
        return true;
    }

    $parts = explode(',', $signatureHeader);
    if (count($parts) < 3) {
        return false;
    }

    $timestamp = explode('=', $parts[0], 2)[1] ?? '';
    $testSig = explode('=', $parts[1], 2)[1] ?? '';
    $liveSig = explode('=', $parts[2], 2)[1] ?? '';
    $comparisonSignature = $testSig !== '' ? $testSig : $liveSig;
    if ($comparisonSignature === '' || $timestamp === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
    return hash_equals($expected, $comparisonSignature);
}
