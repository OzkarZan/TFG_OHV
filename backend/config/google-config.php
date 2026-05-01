<?php
// Cargar variables de entorno desde .env
require_once __DIR__ . '/env.php';

$clientId = getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET';
$redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:5500/api/google-callback.php';

return [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'authorization_uri' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_uri' => 'https://oauth2.googleapis.com/token',
    'userinfo_uri' => 'https://www.googleapis.com/oauth2/v2/userinfo',
    'scopes' => [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ]
];
?>
