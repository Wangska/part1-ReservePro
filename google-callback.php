<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_schema.php';
require_once __DIR__ . '/config/google_oauth.php';

// Validate state to prevent CSRF
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])) {
    unset($_SESSION['google_oauth_state']);
    header('Location: login.php?error=google_state');
    exit();
}
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code'])) {
    header('Location: login.php?error=google_no_code');
    exit();
}

$code = $_GET['code'];

// Exchange authorization code for tokens
$tokenResponse = fetchJson('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

if (!$tokenResponse || empty($tokenResponse['access_token'])) {
    header('Location: login.php?error=google_token');
    exit();
}

$accessToken = $tokenResponse['access_token'];

// Fetch user info
$userInfo = fetchJson('https://www.googleapis.com/oauth2/v3/userinfo', [], $accessToken);

if (!$userInfo || empty($userInfo['email'])) {
    header('Location: login.php?error=google_userinfo');
    exit();
}

$email       = $userInfo['email'];
$firstName   = $userInfo['given_name'] ?? '';
$lastName    = $userInfo['family_name'] ?? '';
$emailVerified = !empty($userInfo['email_verified']);

// Ensure extended schema exists
initializeHostTables();

$conn = getDBConnection();

// Check if user already exists
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Existing user: log them in
    $_SESSION['user_id'] = (int)$row['id'];
    $role = $row['role'] ?? 'guest';
    $stmt->close();
    $conn->close();
} else {
    $stmt->close();

    // New user: create guest account with random password, mark email as verified (from Google)
    $randomPassword = bin2hex(random_bytes(16));
    $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
    $role           = 'guest';
    $verifiedFlag   = $emailVerified ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password, role, email_verified, verification_token)
        VALUES (?, ?, ?, ?, ?, ?, NULL)
    ");
    $stmt->bind_param("sssssi", $firstName, $lastName, $email, $hashedPassword, $role, $verifiedFlag);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        $_SESSION['user_id'] = (int)$userId;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: login.php?error=google_create_user');
        exit();
    }

    $stmt->close();
    $conn->close();
}

// Redirect based on role, same logic as requireGuest()
switch ($role) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'host':
        header('Location: host/dashboard.php');
        break;
    default:
        header('Location: home.php');
        break;
}
exit();

/**
 * Helper to POST/GET JSON from Google endpoints.
 *
 * @param string      $url
 * @param array       $postData   If non-empty, sends POST; otherwise GET.
 * @param string|null $accessToken Optional Bearer token.
 * @return array|null
 */
function fetchJson(string $url, array $postData = [], ?string $accessToken = null): ?array {
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
    ];
    if ($accessToken) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (!empty($postData)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return null;
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

