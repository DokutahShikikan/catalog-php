<?php
declare(strict_types=1);

require_once __DIR__ . '/_boot.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ====== ENV ====== */
$clientId     = getenv('VK_CLIENT_ID') ?: '';
$clientSecret = getenv('VK_CLIENT_SECRET') ?: '';
$redirectUri  = getenv('VK_REDIRECT_URI') ?: '';

if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
  http_response_code(500);
  exit('VK env is not set');
}

/* ====== CHECK STATE ====== */
$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (
  $code === '' ||
  $state === '' ||
  empty($_SESSION['vk_state']) ||
  $state !== $_SESSION['vk_state']
) {
  http_response_code(400);
  exit('Bad state or code');
}

$codeVerifier = $_SESSION['vk_code_verifier'] ?? '';
if ($codeVerifier === '') {
  http_response_code(400);
  exit('Missing code_verifier');
}

/* ====== EXCHANGE CODE → TOKEN ====== */
$tokenUrl = 'https://id.vk.com/oauth2/auth';

$post = [
  'grant_type'    => 'authorization_code',
  'client_id'     => $clientId,
  'client_secret' => $clientSecret,
  'redirect_uri'  => $redirectUri,
  'code'          => $code,
  'code_verifier' => $codeVerifier,
];

if (!empty($_GET['device_id'])) {
  $post['device_id'] = $_GET['device_id'];
}

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($post),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
]);

$res  = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false || $http >= 400) {
  http_response_code(500);
  exit('Token exchange failed');
}

$data = json_decode($res, true);
if (!is_array($data)) {
  http_response_code(500);
  exit('Invalid JSON from VK');
}

/* ====== GET VK USER ID ====== */
$vkUserId = $data['user_id']
  ?? ($data['user']['id'] ?? null);

if (!$vkUserId) {
  http_response_code(500);
  exit('VK user id not found');
}

/* ====== CHECK ADMIN IN DB ====== */
$c = db();

$r = pg_query_params(
  $c,
  'SELECT id FROM admin_user WHERE vk_id = $1',
  [$vkUserId]
);

$admin = pg_fetch_assoc($r);

if (!$admin) {
  http_response_code(403);
  exit('Вы не администратор');
}

/* ====== LOGIN ====== */
$_SESSION['admin_id']  = (int)$admin['id'];
$_SESSION['vk_user_id'] = (string)$vkUserId;

/* защита от повторного state */
unset($_SESSION['vk_state'], $_SESSION['vk_code_verifier']);

header('Location: /admin.php');
exit;
