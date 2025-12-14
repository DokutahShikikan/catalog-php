<?php
require __DIR__.'/_boot.php';

$state = bin2hex(random_bytes(16));
$_SESSION['vk_oauth_state'] = $state;

$params = [
  'client_id' => getenv('VK_CLIENT_ID'),
  'redirect_uri' => getenv('VK_REDIRECT_URI'),
  'response_type' => 'code',
  'scope' => 'email',
  'state' => $state,
];

$url = 'https://id.vk.com/authorize?'.http_build_query($params);
?>
<!doctype html>
<html lang="ru">
<meta charset="utf-8">
<title>Вход</title>
<a href="<?=htmlspecialchars($url)?>">Войти через VK</a>
