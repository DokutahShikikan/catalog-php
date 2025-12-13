<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
function db(): PgSql\Connection {
  static $c = null;
  if ($c) return $c;
var_dump([
'DB_HOST' => getenv('DB_HOST'),
'DB_PORT' => getenv('DB_PORT'),
'DB_NAME' => getenv('DB_NAME'),
'DB_USER' => getenv('DB_USER'),
]);
exit;
$c = pg_connect(sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    getenv('DB_HOST'),
    getenv('DB_PORT'),
    getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASSWORD')
));

  if (!$c) { http_response_code(500); exit('DB connect error'); }
  pg_query($c, "SET client_encoding TO 'UTF8'");
  return $c;
}

function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void { if (!is_admin()) { header('Location: /login.php'); exit; } }

function ensure_admin_table(PgSql\Connection $c): void {
  $sql = "CREATE TABLE IF NOT EXISTS admin_user(
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            pass_hash TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT now()
          )";
  if (!pg_query($c, $sql)) { http_response_code(500); exit('DB error: '.pg_last_error($c)); }
}

function top_nav(string $title = 'Админ'): void {
  ?>
  <!doctype html><html lang="ru"><meta charset="utf-8"><title><?=h($title)?></title>
  <style>
    body{font-family:Segoe UI,Tahoma,Arial;margin:0;background:#f5f5f5;color:#2c3e50}
    .bar{background:linear-gradient(135deg,#2c3e50,#4a6491);color:#fff;padding:14px 18px;display:flex;gap:18px;align-items:center;justify-content:space-between}
    .links a{color:#fff;text-decoration:none;margin-right:12px}
    .wrap{max-width:1100px;margin:20px auto;background:#fff;padding:18px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top}
    input,select,textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:6px}
    button{background:#3498db;color:#fff;border:0;border-radius:6px;padding:8px 12px;cursor:pointer}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .mt{margin-top:12px}
    .ok{background:#e8fff0;color:#146c2e;border:1px solid #bfe6cc;padding:8px 10px;border-radius:8px}
    .err{background:#ffecec;color:#8b0000;border:1px solid #ffc8c8;padding:8px 10px;border-radius:8px}
  </style>
  <div class="bar">
    <div class="links">
      <a href="/admin.php"><b>Админ-панель</b></a>
      <a href="/admin_genres.php">Жанры</a>
      <a href="/admin_authors.php">Авторы</a>
      <a href="/admin_books.php">Книги</a>
      <a href="/index.php">Сайт</a>
    </div>
    <div class="links">
      <?php if (is_admin()): ?>
        <span>Вы вошли как админ</span>
        <a href="/logout.php">Выход</a>
      <?php else: ?>
        <a href="/login.php">Вход</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="wrap">
  <?php
}
function bottom_nav(): void { echo '</div></html>'; }
