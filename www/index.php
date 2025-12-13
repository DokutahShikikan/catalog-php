<?php
declare(strict_types=1);
require __DIR__.'/_boot.php'; $conn = db();

// --- Подключение к БД ---
$conn = pg_connect("host=postgresql dbname=bookstore user=useradmin password=5531");
if (!$conn) {
    http_response_code(500);
    echo "Не удалось подключиться к БД.";
    exit;
}
pg_query($conn, "SET client_encoding TO 'UTF8'");

// Утилита для экранирования
$search = trim($_GET['q'] ?? '');

// Данные для сайдбара
$genres = [];
$genresRes = pg_query($conn, "SELECT id, name FROM genre ORDER BY id ASC");
while ($row = pg_fetch_assoc($genresRes)) { $genres[] = $row; }

// Топ-авторы по числу книг
$authors = [];
$authorsRes = pg_query($conn, "
  SELECT a.id, a.first_name, a.last_name, a.country, COUNT(b.id) AS books_count
  FROM author a
  LEFT JOIN book b ON b.author_id = a.id
  GROUP BY a.id
  ORDER BY books_count DESC NULLS LAST, a.last_name ASC
  LIMIT 8
");
while ($row = pg_fetch_assoc($authorsRes)) { $authors[] = $row; }

// Функция получения книг по жанру (учитываем и поле book.genre_name, и связку book_to_genre)
function getBooksByGenre($conn, int $genreId, string $search = ''): array {
    $params = [$genreId, $genreId];
    $whereSearch = '';
    if ($search !== '') {
        $whereSearch = " AND (
            b.title ILIKE $3
            OR (a.first_name || ' ' || a.last_name) ILIKE $3
            OR (a.last_name || ' ' || a.first_name) ILIKE $3
        )";
        $params[] = "%$search%";
    }

    $sql = "
      SELECT 
        b.id,
        b.title,
        b.publication_date,
        b.book_desc,          -- NEW: описание книги
        a.first_name,
        a.last_name,
        a.country
      FROM book b
      JOIN author a ON a.id = b.author_id
      WHERE 
        (b.genre_name = $1)
        OR EXISTS (
            SELECT 1 FROM book_to_genre btg
            WHERE btg.book_id = b.id AND btg.genre_id = $2
        )
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Книги</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        body{background:#f5f5f5;color:#333;line-height:1.6}
        .container{max-width:1200px;margin:0 auto;padding:0 15px}
        header{background:linear-gradient(135deg,#2c3e50 0%,#4a6491 100%);color:#fff;padding:20px 0;box-shadow:0 2px 10px rgba(0,0,0,.1)}
        .header-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .site-title{font-size:28px;font-weight:700}
        nav.main-menu{display:flex;justify-content:center;background:rgba(255,255,255,.1);border-radius:5px;padding:10px}
        nav.main-menu a{color:#fff;text-decoration:none;padding:8px 15px;margin:0 5px;border-radius:4px;transition:background-color .3s}
        nav.main-menu a:hover{background:rgba(255,255,255,.2)}
        .content{display:flex;margin:20px 0}
        .sidebar{flex:0 0 250px;background:#fff;border-radius:8px;padding:20px;margin-right:20px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .sidebar h2{margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #eaeaea;color:#2c3e50}
        .genre-list,.author-list{list-style:none}
        .genre-list li{margin-bottom:10px}
        .genre-list a{color:#3498db;text-decoration:none;display:block;padding:8px;border-radius:4px;transition:background-color .3s}
        .genre-list a:hover{background:#f8f9fa}
        .author-list li{margin-bottom:8px;padding-left:10px;border-left:3px solid #3498db}
        .author-list a{color:#2c3e50;text-decoration:none}
        .main-content{flex:1;background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
        .search-box{margin-bottom:20px;position:relative}
        .search-box input{width:100%;padding:12px 15px;border:1px solid #ddd;border-radius:4px;font-size:16px}
        .search-box button{position:absolute;right:5px;top:5px;background:#3498db;border:none;color:#fff;padding:7px 15px;border-radius:4px;cursor:pointer}
        .section-title{font-size:24px;margin:25px 0 15px;color:#2c3e50;padding-bottom:10px;border-bottom:2px solid #eaeaea}
        .books-grid{display:grid;grid-template-columns:29.5% 1.6% 63.7%;grid-template-rows:auto;gap:12px 12px;align-items:start}
        .book-left{background:#f8f9fa;border-radius:8px;padding:15px}
        .book-right{background:#f8f9fa;border-radius:8px;padding:15px}
        .book-title{font-weight:700;font-size:16px;margin-bottom:6px;color:#2c3e50}
        .book-author{color:#7f8c8d;font-size:14px;margin-bottom:10px}
        .book-desc{font-size:15px;color:#333}
        .divider{height:8px;background:#000;margin:25px 0;border-radius:4px}
        @media (max-width:900px){
            .content{flex-direction:column}
            .sidebar{margin-right:0;margin-bottom:20px}
            .books-grid{grid-template-columns:1fr;gap:10px}
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
            <a href="authors.php">Список авторов</a>
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
                    <li><a href="#genre-<?= (int)$g['id'] ?>"><?= h($g['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>

            <h2 id="authors">Популярные авторы</h2>
            <ul class="author-list">
                <?php foreach ($authors as $a): ?>
                    <li>
                        <a href="?q=<?= urlencode(trim($a['first_name'].' '.$a['last_name'])) ?>">
                            <?= h(trim($a['first_name'].' '.$a['last_name'])) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main class="main-content">
            <div class="search-box" id="search">
                <form method="get" action="">
                    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Поиск книг, авторов..." />
                    <button type="submit">Найти</button>
                </form>
            </div>

            <?php
            $topicIndex = 1;
            foreach ($genres as $g):
                $books = getBooksByGenre($conn, (int)$g['id'], $search);
                if (!$books) { continue; }
            ?>
                <h2 class="section-title" id="genre-<?= (int)$g['id'] ?>">
                    <?= h($g['name']) ?>
                </h2>

                <?php foreach ($books as $book): ?>
                <div class="books-grid">
                    <div class="book-left">
                        <div class="book-title"><?= h($book['title']) ?></div>
                        <div class="book-author">
                            <?= h(trim($book['first_name'].' '.$book['last_name'])) ?>
                            <?php if (!empty($book['country'])): ?>
                                — <?= h($book['country']) ?>
                            <?php endif; ?>
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
                                : '<em>Описание отсутствует.</em>' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="divider"></div>
            <?php
                $topicIndex++;
            endforeach;

            if ($topicIndex === 1):
            ?>
                <p>По вашему запросу ничего не найдено.</p>
            <?php endif; ?>
        </main>
    </div>
</div>

<footer>
    <div class="container" style="background:#2c3e50;color:#fff;padding:30px 15px;margin-top:40px;border-radius:8px">
        <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
            <div style="flex:1;min-width:250px">
                <h3>О нас</h3>
                <p>«Библиотечный каталог»</p>
            </div>
            <div style="flex:1;min-width:250px">
                <h3>Контакты</h3>
                <p>Адрес: ул. Книжная, д. 15, Омск</p>
                <p>Телефон: +7 (495) 123-45-67</p>
                <p>Email: info@kniga.ru</p>
            </div>
        </div>
        <div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,.15)">
            © <?= date('Y') ?> «Библиотечный каталог». Все права защищены.
        </div>
    </div>
</footer>
</body>
</html>
