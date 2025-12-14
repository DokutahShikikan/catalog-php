<?php
declare(strict_types=1);

require __DIR__.'/auth.php';
$conn = db();

$selectedAuthorId = isset($_GET['author']) ? (int)$_GET['author'] : null;
$search = trim($_GET['q'] ?? '');

$genres = [];
$r = pg_query($conn, "SELECT id, name FROM genre ORDER BY id ASC");
while ($row = pg_fetch_assoc($r)) { $genres[] = $row; }

$authors = [];
$r = pg_query($conn, "
  SELECT a.id, a.first_name, a.last_name, a.country, a.birth_date, a.death_date, a.short_description
  FROM author a
  ORDER BY a.last_name ASC, a.first_name ASC
");
while ($row = pg_fetch_assoc($r)) { $authors[] = $row; }

function getBookCountsByAuthor($conn): array {
    $res = pg_query($conn, "SELECT author_id, COUNT(*) AS c FROM book GROUP BY author_id");
    $out = [];
    while ($row = pg_fetch_assoc($res)) $out[(int)$row['author_id']] = (int)$row['c'];
    return $out;
}
$authorCounts = getBookCountsByAuthor($conn);

function getBooksByAuthor($conn, int $authorId, string $search=''): array {
    $params = [$authorId];
    $where = "";
    if ($search !== '') { $where = " AND (b.title ILIKE $2 OR b.book_desc ILIKE $2)"; $params[] = "%$search%"; }
    $sql = "
      SELECT b.id, b.title, b.publication_date, b.book_desc
      FROM book b
      WHERE b.author_id = $1
      $where
      ORDER BY b.publication_date DESC NULLS LAST, b.title ASC
    ";
    $res = pg_query_params($conn, $sql, $params);
    $out = [];
    while ($row = pg_fetch_assoc($res)) $out[] = $row;
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
.author-list li{margin-bottom:10px}
.author-list a{color:#3498db}
.main-content{flex:1;background:var(--card);border-radius:8px;padding:20px;box-shadow:var(--shadow)}
.search-box{margin-bottom:20px;position:relative}
.search-box input{width:100%;padding:12px 15px;border:1px solid #ddd;border-radius:6px;font-size:16px}
.search-box button{position:absolute;right:5px;top:5px;background:#3498db;border:none;color:#fff;padding:7px 15px;border-radius:6px;cursor:pointer}
.section-title{font-size:28px;margin:10px 0 15px;padding-bottom:10px;border-bottom:2px solid #eaeaea}

.row-like-index{display:grid;grid-template-columns:29.5% 1.6% 63.7%;gap:12px;align-items:start;margin-top:14px}
.cell-left{background:var(--soft);border-radius:8px;padding:15px}
.cell-right{background:var(--soft);border-radius:8px;padding:15px}
.title-lg{font-weight:700;font-size:18px;margin-bottom:6px}
.muted{font-size:13px;color:var(--muted)}
.meta{font-size:14px;color:#7f8c8d;margin-top:8px}

.book-row{display:grid;grid-template-columns:29.5% 1.6% 63.7%;gap:12px;align-items:start;margin-top:14px}
.book-left{background:var(--soft);border-radius:8px;padding:15px}
.book-right{background:var(--soft);border-radius:8px;padding:15px}
.book-title{font-weight:700;font-size:18px;margin-bottom:6px}
.book-desc{font-size:15px;color:#333}

.divider{height:8px;background:#000;margin:25px 0;border-radius:4px}
@media (max-width:900px){.content{flex-direction:column}.sidebar{margin-right:0;margin-bottom:20px}.row-like-index,.book-row{grid-template-columns:1fr;gap:10px}}
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
      <a href="/genres.php">Список жанров</a>
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
        <?php foreach ($authors as $a): ?>
          <li><a href="/authors.php?author=<?= (int)$a['id'] ?>"><?= h(trim($a['first_name'].' '.$a['last_name'])) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </aside>

    <main class="main-content">
      <div class="search-box" id="search">
        <form method="get" action="">
          <?php if ($selectedAuthorId): ?>
            <input type="hidden" name="author" value="<?= (int)$selectedAuthorId ?>">
          <?php endif; ?>
          <input type="text" name="q" value="<?= h($search) ?>" placeholder="<?= $selectedAuthorId ? 'Поиск книг у этого автора…' : 'Поиск авторов...' ?>">
          <button type="submit">Найти</button>
        </form>
      </div>

      <?php if ($selectedAuthorId === null): ?>
        <h2 class="section-title">Список авторов</h2>

        <?php foreach ($authors as $a): ?>
          <div class="row-like-index">
            <div class="cell-left">
              <div class="title-lg">
                <a style="color:inherit" href="/authors.php?author=<?= (int)$a['id'] ?>">
                  <?= h(trim($a['first_name'].' '.$a['last_name'])) ?>
                </a>
                <?php if (is_admin()): ?>
                  <a href="/admin_authors.php#id-<?= (int)$a['id'] ?>" title="Править автора" style="font-size:14px;margin-left:8px">✏️</a>
                <?php endif; ?>
              </div>
              <div class="meta">
                <?= h($a['country'] ?? '—') ?>
                <?php
                  $bd = $a['birth_date'] ? date('d.m.Y', strtotime($a['birth_date'])) : '—';
                  $dd = $a['death_date'] ? date('d.m.Y', strtotime($a['death_date'])) : '—';
                ?>
                <br>Годы жизни: <?= $bd ?> — <?= $dd ?>
                <br>Книг: <?= (int)($authorCounts[(int)$a['id']] ?? 0) ?>
              </div>
            </div>
            <div></div>
            <div class="cell-right">
              <?= $a['short_description'] ? nl2br(h($a['short_description'])) : '<em class="muted">Описание отсутствует.</em>' ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="divider"></div>

      <?php else:
        $authorRow = null;
        foreach ($authors as $a) { if ((int)$a['id'] === $selectedAuthorId) { $authorRow = $a; break; } }
        if (!$authorRow) { echo '<p>Автор не найден.</p>'; }
        else {
          $books = getBooksByAuthor($conn, $selectedAuthorId, $search);
      ?>

        <h2 class="section-title">
          <?= h($authorRow['first_name'].' '.$authorRow['last_name']) ?>
          <?php if (is_admin()): ?>
            <a href="/admin_authors.php#id-<?= (int)$authorRow['id'] ?>" 
               title="Править автора" 
               style="font-size:14px;margin-left:8px">✏️</a>
          <?php endif; ?>
        </h2>

        <div class="meta" style="margin:-4px 0 14px">
          Страна: <?= h($authorRow['country'] ?? '—') ?>,
          Годы жизни: <?= $authorRow['birth_date'] ? date('d.m.Y', strtotime($authorRow['birth_date'])) : '—' ?>
          —
          <?= $authorRow['death_date'] ? date('d.m.Y', strtotime($authorRow['death_date'])) : '—' ?>
        </div>
        <?php if (!empty($authorRow['short_description'])): ?>
          <div class="cell-right" style="margin-bottom:12px"><?= nl2br(h($authorRow['short_description'])) ?></div>
        <?php endif; ?>

        <?php if (!$books): ?>
          <p>У этого автора пока нет книг.</p>
        <?php else: foreach ($books as $b): ?>
          <div class="book-row">
            <div class="book-left">
              <div class="book-title"><?= h($b['title']) ?></div>
              <div class="muted">Дата написания: <?= $b['publication_date'] ? h(date('d.m.Y', strtotime($b['publication_date']))) : '—' ?></div>
            </div>
            <div></div>
            <div class="book-right">
              <div class="book-desc">
                <?= $b['book_desc'] ? nl2br(h($b['book_desc'])) : '<em class="muted">Описание отсутствует.</em>' ?>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <div class="divider"></div>
      <?php } endif; ?>
    </main>
  </div>
</div>

<footer>
  <div class="container" style="background:#2c3e50;color:#fff;padding:30px 15px;margin-top:40px;border-radius:8px">
    <div style="text-align:center">
      © <?= date('Y') ?> «Библиотечный каталог». Все права защищены.
    </div>
  </div>
</footer>
</body>
</html>
