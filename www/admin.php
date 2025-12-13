<?php
declare(strict_types=1);
require __DIR__.'/_boot.php';
require_admin();

$c = db();

$genre_cnt  = (int)pg_fetch_result(pg_query($c, "SELECT COUNT(*) FROM genre"), 0, 0);
$author_cnt = (int)pg_fetch_result(pg_query($c, "SELECT COUNT(*) FROM author"), 0, 0);
$book_cnt   = (int)pg_fetch_result(pg_query($c, "SELECT COUNT(*) FROM book"), 0, 0);


$latest_genres  = pg_query($c, "SELECT id, name FROM genre ORDER BY id DESC LIMIT 5");
$latest_authors = pg_query($c, "SELECT id, first_name, last_name FROM author ORDER BY id DESC LIMIT 5");
$latest_books   = pg_query($c, "SELECT id, title FROM book ORDER BY id DESC LIMIT 5");

top_nav('Админ-панель');
?>
<h2>Админ-панель</h2>

<div class="row" style="grid-template-columns:repeat(3,1fr);gap:14px">
  <div style="background:#f8f9fa;border-radius:10px;padding:14px">
    <div style="font-size:13px;color:#666">Жанры</div>
    <div style="font-size:28px;font-weight:700;line-height:1"><?= $genre_cnt ?></div>
    <div class="mt">
      <a href="/admin_genres.php"><button>Управлять жанрами</button></a>
    </div>
  </div>
  <div style="background:#f8f9fa;border-radius:10px;padding:14px">
    <div style="font-size:13px;color:#666">Авторы</div>
    <div style="font-size:28px;font-weight:700;line-height:1"><?= $author_cnt ?></div>
    <div class="mt">
      <a href="/admin_authors.php"><button>Управлять авторами</button></a>
    </div>
  </div>
  <div style="background:#f8f9fa;border-radius:10px;padding:14px">
    <div style="font-size:13px;color:#666">Книги</div>
    <div style="font-size:28px;font-weight:700;line-height:1"><?= $book_cnt ?></div>
    <div class="mt">
      <a href="/admin_books.php"><button>Управлять книгами</button></a>
    </div>
  </div>
</div>

<h3 class="mt">Быстрое добавление</h3>
<div class="row" style="grid-template-columns:repeat(3,1fr);gap:14px">
  <form method="post" action="/admin_genres.php" style="background:#fff;border:1px solid #eee;border-radius:10px;padding:12px">
    <input type="hidden" name="action" value="add">
    <label>Жанр</label>
    <input name="name" placeholder="Название" required>
    <input class="mt" name="descr" placeholder="Описание">
    <button class="mt">Добавить жанр</button>
  </form>

  <form method="post" action="/admin_authors.php" style="background:#fff;border:1px solid #eee;border-radius:10px;padding:12px">
    <input type="hidden" name="action" value="add">
    <label>Автор</label>
    <input name="first_name" placeholder="Имя" required>
    <input class="mt" name="last_name" placeholder="Фамилия" required>
    <input class="mt" name="country" placeholder="Страна">
    <div class="row mt" style="grid-template-columns:1fr 1fr;gap:8px">
      <input type="date" name="birth_date" placeholder="Дата рождения">
      <input type="date" name="death_date" placeholder="Дата смерти">
    </div>
    <textarea class="mt" name="short_description" rows="2" placeholder="Краткое описание"></textarea>
    <button class="mt">Добавить автора</button>
  </form>

  <?php
  // для селектов автора/жанра
  $authors = pg_query($c, "SELECT id, first_name, last_name FROM author ORDER BY last_name, first_name");
  $genres  = pg_query($c, "SELECT id, name FROM genre ORDER BY name");
  ?>
  <form method="post" action="/admin_books.php" style="background:#fff;border:1px solid #eee;border-radius:10px;padding:12px">
    <input type="hidden" name="action" value="add">
    <label>Книга</label>
    <input name="title" placeholder="Название" required>
    <div class="row mt" style="grid-template-columns:1fr 1fr;gap:8px">
      <select name="author_id" required>
        <option value="">— Автор —</option>
        <?php while($a = pg_fetch_assoc($authors)): ?>
          <option value="<?= (int)$a['id'] ?>"><?= h($a['last_name'].' '.$a['first_name']) ?></option>
        <?php endwhile; ?>
      </select>
      <input type="date" name="publication_date">
    </div>
    <select class="mt" name="genre_name">
      <option value="">— Основной жанр —</option>
      <?php while($g = pg_fetch_assoc($genres)): ?>
        <option value="<?= (int)$g['id'] ?>"><?= h($g['name']) ?></option>
      <?php endwhile; ?>
    </select>
    <textarea class="mt" name="book_desc" rows="2" placeholder="Описание"></textarea>
    <button class="mt">Добавить книгу</button>
  </form>
</div>

<h3 class="mt">Последние изменения</h3>
<div class="row" style="grid-template-columns:repeat(3,1fr);gap:14px">
  <div style="background:#fff;border:1px solid #eee;border-radius:10px;padding:12px">
    <b>Жанры</b>
    <ul style="margin:8px 0 0 16px;line-height:1.7">
      <?php while($g = pg_fetch_assoc($latest_genres)): ?>
        <li><a href="/admin_genres.php#id-<?= (int)$g['id'] ?>"><?= h($g['name']) ?></a></li>
      <?php endwhile; ?>
    </ul>
  </div>
  <div style="background:#fff;border:1px solid #eee;border-radius:10px;padding:12px">
    <b>Авторы</b>
    <ul style="margin:8px 0 0 16px;line-height:1.7">
      <?php while($a = pg_fetch_assoc($latest_authors)): ?>
        <li><a href="/admin_authors.php#id-<?= (int)$a['id'] ?>"><?= h($a['last_name'].' '.$a['first_name']) ?></a></li>
      <?php endwhile; ?>
    </ul>
  </div>
  <div style="background:#fff;border:1px solid #eee;border-radius:10px;padding:12px">
    <b>Книги</b>
    <ul style="margin:8px 0 0 16px;line-height:1.7">
      <?php while($b = pg_fetch_assoc($latest_books)): ?>
        <li><a href="/admin_books.php#id-<?= (int)$b['id'] ?>"><?= h($b['title']) ?></a></li>
      <?php endwhile; ?>
    </ul>
  </div>
</div>

<?php bottom_nav(); ?>
