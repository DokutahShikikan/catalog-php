<?php
declare(strict_types=1);
require __DIR__.'/_boot.php'; $conn = db();

$conn = pg_connect("host=postgresql dbname=bookstore user=useradmin password=5531");
if (!$conn) { http_response_code(500); echo "Не удалось подключиться к БД."; exit; }
pg_query($conn, "SET client_encoding TO 'UTF8'");

$selectedGenreId = isset($_GET['genre']) ? (int)$_GET['genre'] : null;
$search = trim($_GET['q'] ?? '');

$genres = [];
$r = pg_query($conn, "SELECT id, name, descriptions AS descr FROM genre ORDER BY id ASC");
while ($row = pg_fetch_assoc($r)) { $genres[] = $row; }

$authors = [];
$r = pg_query($conn, "
  SELECT a.id, a.first_name, a.last_name, COUNT(b.id) AS books_count
  FROM author a
  LEFT JOIN book b ON b.author_id = a.id
  GROUP BY a.id
  ORDER BY books_count DESC NULLS LAST, a.last_name ASC
  LIMIT 8
");
while ($row = pg_fetch_assoc($r)) { $authors[] = $row; }

function getGenreCounts($conn): array {
    $sql = "
      SELECT g.id, COUNT(DISTINCT b.id) AS books_count
      FROM genre g
      LEFT JOIN book b
        ON b.genre_name = g.id
         OR EXISTS (SELECT 1 FROM book_to_genre btg WHERE btg.book_id = b.id AND btg.genre_id = g.id)
      GROUP BY g.id
      ORDER BY g.id ASC
    ";
    $res = pg_query($conn, $sql);
    $out = [];
    while ($row = pg_fetch_assoc($res)) { $out[(int)$row['id']] = (int)$row['books_count']; }
    return $out;
}
$genreCounts = getGenreCounts($conn);

function getBooksByGenre($conn, int $genreId, string $search = ''): array {
    $params = [$genreId, $genreId];
    $whereSearch = '';
    if ($search !== '') {
        $whereSearch = " AND (
            b.title ILIKE $3
            OR (a.first_name || ' ' || a.last_name) ILIKE $3
            OR (a.last_name || ' ' || a.first_name) ILIKE $3
            OR b.book_desc ILIKE $3
        )";
        $params[] = "%$search%";
    }
    $sql = "
      SELECT DISTINCT
        b.id, b.title, b.publication_date, b.book_desc,
        a.first_name, a.last_name, a.country
      FROM book b
      JOIN author a ON a.id = b.author_id
      WHERE
        (b.genre_name = $1)
        OR EXISTS (SELECT 1 FROM book_to_genre btg WHERE btg.book_id = b.id AND btg.genre_id = $2)
      $whereSearch
      ORDER BY b.publication_date DESC NULLS LAST, b.title ASC
    ";
    $res = pg_query_params($conn, $sql, $params);
    $out = [];
    while ($row = pg_fetch_assoc($res)) { $out[] = $row; }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Жанры</title>
    <style>
        :root{--bg:#f5f5f5;--text:#2c3e50;--muted:#666;--accent:#3498db;--card:#fff;--cardSoft:#f8f9fa;--shadow:0 2px 10px rgba(0,0,0,.05);}
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
        .genre-list a{color:#3498db;text-decoration:none;display:block;padding:8px;border-radius:4px;transition:background-color .3s}
        .genre-list a:hover{background:#f8f9fa}
        .author-list li{margin-bottom:8px;padding-left:10px;border-left:3px solid var(--accent)}
        .main-content{flex:1;background:var(--card);border-radius:8px;padding:20px;box-shadow:var(--shadow)}
        .search-box{margin-bottom:20px;position:relative}
        .search-box input{width:100%;padding:12px 15px;border:1px solid #ddd;border-radius:6px;font-size:16px}
        .search-box button{position:absolute;right:5px;top:5px;background:#3498db;border:none;color:#fff;padding:7px 15px;border-radius:6px;cursor:pointer}
        .section-title{font-size:28px;margin:10px 0 15px;padding-bottom:10px;border-bottom:2px solid #eaeaea}
        .row-like-index{display:grid;grid-template-columns:29.5% 1.6% 63.7%;gap:12px;align-items:start;margin-top:14px}
        .cell-left{background:var(--cardSoft);border-radius:8px;padding:15px}
        .cell-right{background:var(--cardSoft);border-radius:8px;padding:15px}
        .title-lg{font-weight:700;font-size:18px;margin-bottom:6px}
        .muted{font-size:13px;color:var(--muted)}
        .book-row{display:grid;grid-template-columns:29.5% 1.6% 63.7%;gap:12px;align-items:start;margin-top:14px}
        .book-left{background:var(--cardSoft);border-radius:8px;padding:15px}
        .book-right{background:var(--cardSoft);border-radius:8px;padding:15px}
        .book-title{font-weight:700;font-size:18px;margin-bottom:6px}
        .book-author{color:#7f8c8d;font-size:14px;margin-bottom:10px}
        .book-desc{font-size:15px;color:#333}

        .divider{height:8px;background:#000;margin:25px 0;border-radius:4px}
        @media (max-width:900px){
          .content{flex-direction:column}
          .sidebar{margin-right:0;margin-bottom:20px}
          .row-like-index,.book-row{grid-template-columns:1fr;gap:10px}
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div class="header-top">
            <div class="site-title">Библиотечный каталог</div>
            <div class="user-actions">
                <a href="/login.php" style="color:#fff;margin-right:12px">Вход</a>
                <a href="/register.php" style="color:#fff">Регистрация</a>
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
            <h2 id="genres">Жанры</h2>
            <ul class="genre-list">
                <?php foreach ($genres as $g): ?>
                    <li><a href="?genre=<?= (int)$g['id'] ?>"><?= h($g['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <h2 id="authors">Популярные авторы</h2>
            <ul class="author-list">
                <?php foreach ($authors as $a): ?>
                    <li><a href="/index.php?q=<?= urlencode(trim($a['first_name'].' '.$a['last_name'])) ?>">
                        <?= h(trim($a['first_name'].' '.$a['last_name'])) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            <div class="search-box">
                <form method="get" action="<?= $selectedGenreId ? '' : '/index.php' ?>">
                    <?php if ($selectedGenreId): ?>
                        <input type="hidden" name="genre" value="<?= (int)$selectedGenreId ?>">
                    <?php endif; ?>
                    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Поиск книг, авторов..." />
                    <button type="submit">Найти</button>
                </form>
            </div>

            <?php if ($selectedGenreId === null): ?>
                <h2 class="section-title">Список жанров</h2>

                <?php foreach ($genres as $g): ?>
                  <div class="row-like-index">
                      <div class="cell-left">
                          <div class="title-lg">
                              <a href="?genre=<?= (int)$g['id'] ?>" style="color:inherit"><?= h($g['name']) ?></a>
                          </div>
                          <div class="muted">Книг: <?= (int)($genreCounts[(int)$g['id']] ?? 0) ?></div>
                      </div>
                      <div></div>
                      <div class="cell-right">
                          <?= $g['descr'] ? nl2br(h($g['descr'])) : '<em class="muted">Описание отсутствует.</em>' ?>
                      </div>
                  </div>
                <?php endforeach; ?>

                <div class="divider"></div>

            <?php else:
                $genreRow = null;
                foreach ($genres as $g) { if ((int)$g['id'] === $selectedGenreId) { $genreRow = $g; break; } }
                if (!$genreRow) { echo '<p>Жанр не найден.</p>'; }
                else {
                    $books = getBooksByGenre($conn, $selectedGenreId, $search);
            ?>
                <h2 class="section-title"><?= h($genreRow['name']) ?></h2>
                <?php if (!empty($genreRow['descr'])): ?>
                    <div style="margin:-4px 0 14px;color:#444"><?= nl2br(h($genreRow['descr'])) ?></div>
                <?php endif; ?>

                <?php if (!$books): ?>
                    <p style="margin-top:14px">В этом жанре пока нет книг.</p>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                    <div class="book-row">
                        <div class="book-left">
                            <div class="book-title"><?= h($book['title']) ?></div>
                            <div class="book-author">
                                <?= h(trim($book['first_name'].' '.$book['last_name'])) ?>
                                <?php if (!empty($book['country'])): ?> — <?= h($book['country']) ?><?php endif; ?>
                            </div>
                            <div style="font-size:14px;color:#666">
                                Дата написания: <?= h($book['publication_date'] ? date('d.m.Y', strtotime($book['publication_date'])) : '—') ?>
                            </div>
                        </div>
                        <div></div>
                        <div class="book-right">
                            <div class="book-desc">
                                <?= $book['book_desc'] !== null && $book['book_desc'] !== ''
                                    ? nl2br(h($book['book_desc']))
                                    : '<em class="muted">Описание отсутствует.</em>' ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="divider"></div>
                <?php endif; ?>
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
