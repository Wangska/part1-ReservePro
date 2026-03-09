<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/google_oauth.php';

// If already logged in, send to normal destination
if (isLoggedIn()) {
    require_once __DIR__ . '/config/database.php';
    $user = getCurrentUser();
    if ($user && isset($user['role'])) {
        switch ($user['role']) {
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
    }
}

// Protect against CSRF with state parameter
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'include_granted_scopes' => 'true',
    'state'         => $state,
    'prompt'        => 'select_account',
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $authUrl);
exit();

