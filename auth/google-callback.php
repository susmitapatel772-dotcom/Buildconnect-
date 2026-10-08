<?php
/**
 * BuildConnect Google OAuth 2.0 Callback Handler
 */
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect to role area
if (isLoggedIn()) {
    $user = currentUser();
    if ($user) {
        redirect(get_role_redirect_url($user['role']));
    }
}

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

// Check for user cancellation or OAuth error
if ($error) {
    set_flash_message('Google sign-in was cancelled or encountered an issue. Please try again.', 'warning');
    redirect('login.php');
}

// Validate OAuth state to prevent CSRF attacks
if (empty($state) || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    unset($_SESSION['oauth_state']);
    set_flash_message('Security validation failed (OAuth state mismatch). Please try again.', 'danger');
    redirect('login.php');
}

unset($_SESSION['oauth_state']);

if (empty($code)) {
    set_flash_message('Unable to sign in with Google. No authorization code received.', 'danger');
    redirect('login.php');
}

$client_id     = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET;
$redirect_uri  = GOOGLE_REDIRECT_URI;

if (empty($client_id) || empty($client_secret)) {
    set_flash_message('Google Sign-In is not configured yet. Missing client ID or secret in environment settings.', 'danger');
    redirect('login.php');
}

// Exchange authorization code for access token via HTTP POST to Google Token endpoint
$token_url   = 'https://oauth2.googleapis.com/token';
$post_fields = [
    'code'          => $code,
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response   = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error || !$response) {
    set_flash_message('Unable to communicate with Google authentication server. Please try again.', 'danger');
    redirect('login.php');
}

$token_data = json_decode($response, true);
if (empty($token_data['access_token'])) {
    set_flash_message('Google authentication failed. Invalid token response.', 'danger');
    redirect('login.php');
}

$access_token = $token_data['access_token'];

// Retrieve Google User Profile using Access Token
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$userinfo_response = curl_exec($ch);
curl_close($ch);

$google_user = json_decode($userinfo_response, true);
$email = filter_var($google_user['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    set_flash_message('Unable to retrieve a valid email address from your Google profile.', 'danger');
    redirect('login.php');
}

// Find existing BuildConnect account by email
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    if ($user['status'] === 'suspended') {
        set_flash_message('Your account is suspended. Please contact system administration.', 'danger');
        redirect('login.php');
    }

    // Secure Session Regeneration on login
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_email'] = $user['email'];

    set_flash_message('Signed in successfully with Google! Welcome back, ' . sanitize($user['name']) . '.', 'success');
    redirect(get_role_redirect_url($user['role']));
} else {
    // Account does not exist in BuildConnect - redirect to registration with notification
    set_flash_message('No BuildConnect account found matching ' . sanitize($email) . '. Please create an account to get started.', 'warning');
    redirect('register.php?email=' . urlencode($email));
}
