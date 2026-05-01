<?php
/**
 * Google OAuth 2.0 Configuration
 * Lee las credenciales desde variables de entorno (.env)
 */

require_once __DIR__ . '/env.php';

$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = getenv('GOOGLE_REDIRECT_URI');

// Validar que las variables de entorno estén configuradas
if (!$clientId || !$clientSecret) {
    error_log("ERROR: Variables de entorno de Google no configuradas. Verifica tu archivo .env");
    // En desarrollo, proporcionar valores por defecto
    $clientId = 'YOUR_GOOGLE_CLIENT_ID';
    $clientSecret = 'YOUR_GOOGLE_CLIENT_SECRET';
}

if (!$redirectUri) {
    $redirectUri = 'http://localhost:5500/api/google-callback.php';
}

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
    ],
    'certs_uri' => 'https://www.googleapis.com/oauth2/v3/certs'
];
?>
