<?php
/**
 * BuildConnect Google OAuth 2.0 Login Redirect Handler
 */
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect to role area
if (isLoggedIn()) {
    $user = currentUser();
    if ($user) {
        redirect(get_role_redirect_url($user['role']));
    }
}

$client_id = GOOGLE_CLIENT_ID;
$redirect_uri = GOOGLE_REDIRECT_URI;

if (empty($client_id)) {
    set_flash_message('Google Sign-In is not configured yet. Please configure GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in environment settings.', 'warning');
    redirect('login.php');
}

// Generate CSRF OAuth State parameter
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
}

$params = [
    'client_id'     => $client_id,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['oauth_state'],
    'prompt'        => 'select_account'
];

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $auth_url);
exit();
