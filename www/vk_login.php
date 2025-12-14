<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$clientId = getenv('VK_CLIENT_ID') ?: '';
$redirectUri = getenv('VK_REDIRECT_URI') ?: '';

if ($clientId === '' || $redirectUri === '') {
  http_response_code(500);
  exit('VK env is not set (VK_CLIENT_ID / VK_REDIRECT_URI)');
}

// state (защита от CSRF)
$state = bin2hex(random_bytes(16));
$_SESSION['vk_state'] = $state;

// PKCE
$codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$_SESSION['vk_code_verifier'] = $codeVerifier;

$hash = hash('sha256', $codeVerifier, true);
$codeChallenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

// Авторизация VK ID
$authUrl = 'https://id.vk.com/authorize?' . http_build_query([
  'client_id' => $clientId,
  'redirect_uri' => $redirectUri,
  'response_type' => 'code',
  'scope' => 'email',            
  'state' => $state,
  'code_challenge' => $codeChallenge,
  'code_challenge_method' => 'S256',
]);

header('Location: ' . $authUrl);
exit;
