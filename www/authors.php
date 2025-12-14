<?php
declare(strict_types=1);

require __DIR__.'/_boot.php';
$conn = db();

$selectedAuthorId = isset($_GET['author']) ? (int)$_GET['author'] : null;
$search = trim($_GET['q'] ?? '');

/* ---------- ЖАНРЫ ---------- */
$genres = [];
$r = pg_query($conn, "SELECT id, name FROM genre ORDER BY id ASC");
while ($row = pg_fetch_assoc($r)) {
    $genres[] = $row;
}

/* ---------- АВТОРЫ ---------- */
$authors = [];
$r = pg_query($conn, "
    SELECT id, first_name, last_name, country, birth_date, death_date, short_description
    FROM author
    ORDER BY last_name ASC, first_name ASC
");
while ($row = pg_fetch_assoc($r)) {
    $authors[] = $row;
}

/* ---------- КОЛ-ВО КНИГ У АВТОРОВ ---------- */
function getBookCountsByAuthor(PgSql\Connection $conn): array {
    $res = pg_query($conn, "SELECT author_id, COUNT(*) AS c FROM book GROUP BY author_id");
    $out = [];
    while ($row = pg_fetch_assoc($res)) {
        $out[(int)$row['author_id']] = (int)$row['c'];
    }
    return $out;
}
$authorCounts = getBookCountsByAuthor($conn);

/* ---------- КНИГИ АВТОРА ---------- */
function getBooksByAuthor(PgSql\Connection $conn, int $authorId, string $search = ''): array {
    $params = [$authorId];
    $where = '';

    if ($search !== '') {
        $where = "AND (b.title ILIKE $2 OR b.book_desc ILIKE $2)";
        $params[] = "%$search%";
    }

    $sql = "
        SELECT id, title, publication_date, book_desc
        FROM book b
        WHERE b.author_id = $1
        $where
        ORDER BY publication_date DESC NULLS LAST, title ASC
    ";

    $res = pg_query_params($conn, $sql, $params);
    $out = [];
    while ($row = pg_fetch_assoc($res)) {
        $out[] = $row;
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Авторы</title>
<style>
:root{--bg:#f5f5f5;--text:#2c3e50;--muted:#666;--accent:#3498db;--card:#fff;--soft:#f8f9fa;--shadow:0 2px 10px rgba(0,0,0,.05);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Verdana,sans-serif}
body{background:var(--bg);color:var(--text)}
a{text-decoration:none;color:inherit}
.container{max-width:1200px;margin:0 auto;padding:0 15px}
header{background:linear-gradient(135deg,#2c3e50,#4a6491);color:#fff;padding:20px 0}
.header-top{display:flex;justify-content:space-between;align-items:center}
.site-title{font-size:28px;font-weight:700}
nav{margin-top:15px;display:flex;justify-content:center;gap:10px}
nav a{color:#fff;padding:8px 14px;border-radius:4px}
nav a:hover{background:rgba(255,255,255,.2)}
.content{display:flex;margin:20px 0}
.sidebar{width:250px;background:var(--card);padding:20px;border-radius:8px;box-shadow:var(--shadow);margin-right:20px}
.main{flex:1;background:var(--card);padding:20px;border-radius:8px;box-shadow:var(--shadow)}
h2{margin-bottom:10px}
ul{list-style:none}
li{margin-bottom:8px}
.search{margin-bottom:20px}
.search input{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px}
.author-card{display:grid;grid-template-columns:30% 65%;gap:20px;background:var(--soft);padding:15px;border-radius:8px;margin-bottom:15px}
.muted{color:var(--muted);font-size:13px}
@media(max-width:900px){.content{flex-direction:column}.sidebar{width:100%;margin-bottom:20px}}
</style>
</head>
<body>

<header>
  <div class="container">
    <div class="header-top">
      <div class="site-title">Библиотечный каталог</div>
      <div>
        <?php if (is_admin()): ?>
          <a href="/admin.php" style="color:#fff;margin-right:10px">Админ</a>
          <a href="/logout.php" style="color:#fff">Выход</a>
        <?php else: ?>
          <a href="/login.php" style="color:#fff;margin-right:10px">Вход</a>
          <a href="/register.php" style="color:#fff">Регистрация</a>
        <?php endif; ?>
      </div>
    </div>
    <nav>
      <a href="/index.php">Книги</a>
      <a href="/genres.php">Жанры</a>
      <a href="/authors.php">Авторы</a>
      <a href="/search.php">Поиск</a>
    </nav>
  </div>
</header>

<div class="container">
  <div class="content">

    <aside class="sidebar">
      <h2>Жанры</h2>
      <ul>
        <?php foreach ($genres as $g): ?>
          <li><a href="/genres.php?genre=<?= (int)$g['id'] ?>"><?= h($g['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <main class="main">
      <div class="search">
        <form>
          <?php if ($selectedAuthorId): ?>
            <input type="hidden" name="author" value="<?= $selectedAuthorId ?>">
          <?php endif; ?>
          <input type="text" name="q" placeholder="Поиск..." value="<?= h($search) ?>">
        </form>
      </div>

      <?php if ($selectedAuthorId === null): ?>
        <h2>Список авторов</h2>
        <?php foreach ($authors as $a): ?>
          <div class="author-card">
            <div>
              <strong>
                <a href="?author=<?= (int)$a['id'] ?>">
                  <?= h($a['first_name'].' '.$a['last_name']) ?>
                </a>
              </strong>
              <div class="muted">
                <?= h($a['country'] ?? '—') ?><br>
                Книг: <?= (int)($authorCounts[(int)$a['id']] ?? 0) ?>
              </div>
            </div>
            <div>
              <?= $a['short_description']
                ? nl2br(h($a['short_description']))
                : '<em class="muted">Описание отсутствует</em>' ?>
            </div>
          </div>
        <?php endforeach; ?>

      <?php else:
        $author = null;
        foreach ($authors as $a) if ((int)$a['id'] === $selectedAuthorId) $author = $a;
        if (!$author) { echo '<p>Автор не найден</p>'; }
        else {
          $books = getBooksByAuthor($conn, $selectedAuthorId, $search);
      ?>
        <h2><?= h($author['first_name'].' '.$author['last_name']) ?></h2>
        <?php foreach ($books as $b): ?>
          <div class="author-card">
            <div>
              <strong><?= h($b['title']) ?></strong>
              <div class="muted">
                <?= $b['publication_date'] ? date('d.m.Y', strtotime($b['publication_date'])) : '—' ?>
              </div>
            </div>
            <div>
              <?= $b['book_desc']
                ? nl2br(h($b['book_desc']))
                : '<em class="muted">Описание отсутствует</em>' ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php } endif; ?>
    </main>

  </div>
</div>

</body>
</html>
