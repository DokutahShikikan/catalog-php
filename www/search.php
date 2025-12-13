<?php
declare(strict_types=1);
require __DIR__.'/_boot.php'; $conn = db();

function mark(string $text, string $q): string {
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($q === '') return $safe;
    $pattern = '/' . preg_quote($q, '/') . '/iu';
    return preg_replace($pattern, '<mark>$0</mark>', $safe);
}

$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';

$genres = [];
$gr = pg_query($conn, "SELECT id, name FROM genre ORDER BY id");
while ($row = pg_fetch_assoc($gr)) { $genres[] = $row; }

$authorsSidebar = [];
$as = pg_query($conn, "
  SELECT a.id, a.first_name, a.last_name
  FROM author a
  ORDER BY a.last_name, a.first_name
  LIMIT 12
");
while ($row = pg_fetch_assoc($as)) { $authorsSidebar[] = $row; }

$results = [];

if ($q !== '' && ($type === 'all' || $type === 'books')) {
    $sql = "
      SELECT 
        b.id,
        b.title,
        b.publication_date,
        b.book_desc,
        a.first_name, a.last_name, a.country
      FROM book b
      JOIN author a ON a.id = b.author_id
      WHERE b.title ILIKE $1 OR b.book_desc ILIKE $1
         OR (a.first_name || ' ' || a.last_name) ILIKE $1
      ORDER BY (CASE 
                  WHEN b.title ILIKE $1 THEN 1
                  WHEN (a.first_name || ' ' || a.last_name) ILIKE $1 THEN 2
                  ELSE 3
               END), b.title ASC
      LIMIT 100
    ";
    $res = pg_query_params($conn, $sql, ['%'.$q.'%']);
    while ($row = pg_fetch_assoc($res)) {
        $results[] = [
            'kind' => 'book',
            'left' => $row['title'],
            'right' => $row,
        ];
    }
}

if ($q !== '' && ($type === 'all' || $type === 'authors')) {
    $sql = "
      SELECT 
        a.id, a.first_name, a.last_name, a.country, a.birth_date, a.death_date, a.short_description
      FROM author a
      WHERE (a.first_name || ' ' || a.last_name) ILIKE $1
         OR a.short_description ILIKE $1
         OR a.country ILIKE $1
      ORDER BY a.last_name, a.first_name
      LIMIT 100
    ";
    $res = pg_query_params($conn, $sql, ['%'.$q.'%']);
    while ($row = pg_fetch_assoc($res)) {
        $results[] = [
            'kind' => 'author',
            'left' => trim($row['first_name'].' '.$row['last_name']),
            'right' => $row,
        ];
    }
}

if ($q !== '' && ($type === 'all' || $type === 'genres')) {
    $sql = "
      SELECT 
        g.id, g.name, g.descriptions AS descr,
        COUNT(DISTINCT b.id) AS books_count
      FROM genre g
      LEFT JOIN book b 
        ON b.genre_name = g.id
         OR EXISTS (SELECT 1 FROM book_to_genre btg WHERE btg.book_id=b.id AND btg.genre_id=g.id)
      WHERE g.name ILIKE $1 OR g.descriptions ILIKE $1
      GROUP BY g.id, g.name, g.descriptions
      ORDER BY g.name
      LIMIT 100
    ";
    $res = pg_query_params($conn, $sql, ['%'.$q.'%']);
    while ($row = pg_fetch_assoc($res)) {
        $results[] = [
            'kind' => 'genre',
            'left' => $row['name'],
            'right' => $row,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Поиск</title>
<style>
:root{--bg:#f5f5f5;--text:#2c3e50;--muted:#666;--accent:#3498db;--card:#fff;--soft:#f8f9fa;--shadow:0 2px 10px rgba(0,0,0,.05);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
body{background:var(--bg);color:var(--text);line-height:1.6}
a,a:visited{color:var(--text);text-decoration:none}
.container{max-width:1200px;margin:0 auto;padding:0 15px}
header{background:linear-gradient(135deg,#2c3e50 0%,#4a6491 100%);color:#fff;padding:20px 0;box-shadow:0 2px 10px rgba(0,0,0,.1)}
.header-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.site-title{font-size:28px;font-weight:700}
nav.main-menu{display:flex;justify-content:center;background:rgba(255,255,255,.1);border-radius:5px;padding:10px}
nav.main-menu a{color:#fff;padding:8px 15px;margin:0 5px;border-radius:4px}
nav.main-menu a:hover{background:rgba(255,255,255,.2)}
.content{display:flex;margin:20px 0}
.sidebar{flex:0 0 250px;background:var(--card);border-radius:8px;padding:20px;margin-right:20px;box-shadow:var(--shadow)}
.sidebar h2{margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #eaeaea}
.genre-list,.author-list{list-style:none}
.genre-list li{margin-bottom:10px}
.genre-list a{color:#3498db;display:block;padding:8px;border-radius:4px;transition:background .3s}
.genre-list a:hover{background:var(--soft)}
.author-list li{margin-bottom:8px;padding-left:10px;border-left:3px solid var(--accent)}
.main-content{flex:1;background:var(--card);border-radius:8px;padding:20px;box-shadow:var(--shadow)}
.section-title{font-size:24px;margin:10px 0 15px;padding-bottom:10px;border-bottom:2px solid #eaeaea}

.search-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px}
.search-toolbar form{flex:1;position:relative}
.search-toolbar input[type="text"]{width:100%;padding:12px 15px;border:1px solid #ddd;border-radius:6px;font-size:16px}
.search-toolbar button{position:absolute;right:5px;top:5px;background:#3498db;border:none;color:#fff;padding:7px 15px;border-radius:6px;cursor:pointer}
.search-toolbar select{padding:10px;border:1px solid #ddd;border-radius:6px;background:#fff}

.row{display:grid;grid-template-columns:29.5% 1.6% 63.7%;gap:12px;align-items:start;margin-top:14px}
.cell-left{background:var(--soft);border-radius:8px;padding:15px}
.cell-right{background:var(--soft);border-radius:8px;padding:15px}
.title-lg{font-weight:700;font-size:18px;margin-bottom:6px}
.meta{font-size:14px;color:#7f8c8d}
.muted{font-size:13px;color:var(--muted)}
.divider{height:8px;background:#000;margin:25px 0;border-radius:4px}
mark{background:#fff3a6;padding:0 .15em;border-radius:.2em}
@media (max-width:900px){.content{flex-direction:column}.sidebar{margin-right:0;margin-bottom:20px}.row{grid-template-columns:1fr;gap:10px}}
</style>
</head>
<body>
<header>
  <div class="container">
    <div class="header-top">
      <div class="site-title">Библиотечный каталог</div>
      <div class="user-actions">
        <?php if (is_admin()): ?>
          <a href="/admin.php" style="color:#fff;margin-right:12px">Админ</a>
          <a href="/logout.php" style="color:#fff">Выход</a>
        <?php else: ?>
          <a href="/login.php" style="color:#fff;margin-right:12px">Вход</a>
          <a href="/register.php" style="color:#fff">Регистрация</a>
        <?php endif; ?>
      </div>
    </div>
    <nav class="main-menu">
      <a href="/index.php">Книги</a>
      <a href="/genres.php">Жанры</a>
      <a href="/authors.php">Список авторов</a>
      <a href="/search.php">Поиск</a>
    </nav>
  </div>
</header>

<div class="container">
  <div class="content">
    <aside class="sidebar">
      <h2>Жанры</h2>
      <ul class="genre-list">
        <?php foreach ($genres as $g): ?>
          <li><a href="/genres.php?genre=<?= (int)$g['id'] ?>"><?= h($g['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>

      <h2>Авторы</h2>
      <ul class="author-list">
        <?php foreach ($authorsSidebar as $a): ?>
          <li><a href="/authors.php?author=<?= (int)$a['id'] ?>">
            <?= h(trim($a['first_name'].' '.$a['last_name'])) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <main class="main-content">
      <div class="search-toolbar">
        <form method="get" action="">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Поиск по книгам, авторам и жанрам...">
          <button type="submit">Найти</button>
        </form>
        <form method="get" action="">
          <input type="hidden" name="q" value="<?= h($q) ?>">
          <select name="type" onchange="this.form.submit()">
            <option value="all"    <?= $type==='all'?'selected':'' ?>>Все</option>
            <option value="books"  <?= $type==='books'?'selected':'' ?>>Книги</option>
            <option value="authors"<?= $type==='authors'?'selected':'' ?>>Авторы</option>
            <option value="genres" <?= $type==='genres'?'selected':'' ?>>Жанры</option>
          </select>
        </form>
      </div>

      <h2 class="section-title">Поиск</h2>

      <?php if ($q === ''): ?>
        <p class="muted">Введите слово в поле поиска выше.</p>
      <?php elseif (!$results): ?>
        <p>Ничего не найдено по запросу «<?= h($q) ?>».</p>
      <?php else: ?>
        <?php foreach ($results as $item): ?>
          <?php if ($item['kind'] === 'book'):
              $b = $item['right']; ?>
              <div class="row">
                <div class="cell-left">
                  <div class="title-lg"><a style="color:inherit" href="/index.php?q=<?= urlencode($b['title']) ?>"><?= mark($item['left'], $q) ?></a></div>
                  <div class="meta">
                    <?= h(trim($b['first_name'].' '.$b['last_name'])) ?>
                    <?php if (!empty($b['country'])): ?> — <?= h($b['country']) ?><?php endif; ?>
                    <br>Дата написания: <?= $b['publication_date'] ? h(date('d.m.Y', strtotime($b['publication_date']))) : '—' ?>
                  </div>
                </div>
                <div></div>
                <div class="cell-right">
                  <?= $b['book_desc'] ? nl2br(mark($b['book_desc'], $q)) : '<span class="muted">Описание отсутствует.</span>' ?>
                </div>
              </div>

          <?php elseif ($item['kind'] === 'author'):
              $a = $item['right']; ?>
              <div class="row">
                <div class="cell-left">
                  <div class="title-lg"><a style="color:inherit" href="/authors.php?author=<?= (int)$a['id'] ?>"><?= mark($item['left'], $q) ?></a></div>
                  <div class="meta">
                    Страна: <?= h($a['country'] ?? '—') ?><br>
                    Годы жизни:
                    <?= $a['birth_date'] ? h(date('d.m.Y', strtotime($a['birth_date']))) : '—' ?>
                    —
                    <?= $a['death_date'] ? h(date('d.m.Y', strtotime($a['death_date']))) : '—' ?>
                  </div>
                </div>
                <div></div>
                <div class="cell-right">
                  <?= $a['short_description'] ? nl2br(mark($a['short_description'], $q)) : '<span class="muted">Краткая биография отсутствует.</span>' ?>
                </div>
              </div>

          <?php else:
              $g = $item['right']; ?>
              <div class="row">
                <div class="cell-left">
                  <div class="title-lg"><a style="color:inherit" href="/genres.php?genre=<?= (int)$g['id'] ?>"><?= mark($item['left'], $q) ?></a></div>
                  <div class="meta">Книг: <?= (int)$g['books_count'] ?></div>
                </div>
                <div></div>
                <div class="cell-right">
                  <?= $g['descr'] ? nl2br(mark($g['descr'], $q)) : '<span class="muted">Описание жанра отсутствует.</span>' ?>
                </div>
              </div>
          <?php endif; ?>
        <?php endforeach; ?>

        <div class="divider"></div>
      <?php endif; ?>
    </main>
  </div>
</div>

<footer>
  <div class="container" style="background:#2c3e50;color:#fff;padding:30px 15px;margin-top:40px;border-radius:8px">
    <div style="text-align:center">
      © <?= date('Y') ?> «Библиотечный каталог». Поиск.
    </div>
  </div>
</footer>
</body>
</html>
