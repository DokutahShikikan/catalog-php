<?php
declare(strict_types=1);
require __DIR__.'/_boot.php'; require_admin();
$c=db();

$msg=''; $err='';

// справочники
$authors=[]; $ra=pg_query($c,"SELECT id, first_name, last_name FROM author ORDER BY last_name, first_name");
while($x=pg_fetch_assoc($ra)) $authors[]=$x;

$genres=[]; $rg=pg_query($c,"SELECT id, name FROM genre ORDER BY name");
while($x=pg_fetch_assoc($rg)) $genres[]=$x;

function save_extra_genres(PgSql\Connection $c, int $book_id, array $genre_ids): void {
  pg_query_params($c,"DELETE FROM book_to_genre WHERE book_id=$1",[$book_id]);
  foreach ($genre_ids as $gid) {
    $gid=(int)$gid; if($gid<=0) continue;
    pg_query_params($c,"INSERT INTO book_to_genre(genre_id,book_id) VALUES($1,$2)",[$gid,$book_id]);
  }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=$_POST['action']??'';
  if($a==='add'){
    $title=trim($_POST['title']??''); $author_id=(int)($_POST['author_id']??0);
    $pub=$_POST['publication_date']??null; $main_genre=(int)($_POST['genre_name']??0);
    $desc=trim($_POST['book_desc']??''); $extra=$_POST['extra_genres']??[];
    if($title===''||$author_id<=0){ $err='Название и Автор обязательны'; }
    else{
      $ok=pg_query_params($c,"INSERT INTO book(title,publication_date,author_id,genre_name,book_desc) VALUES($1,$2,$3,$4,$5) RETURNING id",
        [$title,$pub?:null,$author_id,$main_genre?:null,$desc]);
      if($row=pg_fetch_assoc($ok)){
        save_extra_genres($c,(int)$row['id'],(array)$extra);
        $msg='Книга добавлена';
      } else { $err='Ошибка: '.pg_last_error($c); }
    }
  }elseif($a==='update'){
    $id=(int)($_POST['id']??0);
    $title=trim($_POST['title']??''); $author_id=(int)($_POST['author_id']??0);
    $pub=$_POST['publication_date']??null; $main_genre=(int)($_POST['genre_name']??0);
    $desc=trim($_POST['book_desc']??''); $extra=$_POST['extra_genres']??[];
    $ok=pg_query_params($c,"UPDATE book SET title=$1,publication_date=$2,author_id=$3,genre_name=$4,book_desc=$5 WHERE id=$6",
      [$title,$pub?:null,$author_id,$main_genre?:null,$desc,$id]);
    if($ok){ save_extra_genres($c,$id,(array)$extra); $msg='Книга обновлена'; }
    else { $err='Ошибка: '.pg_last_error($c); }
  }elseif($a==='delete'){
    $id=(int)($_POST['id']??0);
    pg_query_params($c,"DELETE FROM book_to_genre WHERE book_id=$1",[$id]);
    $ok=pg_query_params($c,"DELETE FROM book WHERE id=$1",[$id]);
    $msg=$ok?'Книга удалена':'Ошибка: '.pg_last_error($c);
  }
}

// список книг
$books=[];
$q=pg_query($c,"SELECT b.id,b.title,b.publication_date,b.book_desc,b.author_id,b.genre_name,
                       a.first_name,a.last_name,
                       (SELECT array_agg(genre_id ORDER BY genre_id) FROM book_to_genre btg WHERE btg.book_id=b.id) AS extra
                FROM book b
                JOIN author a ON a.id=b.author_id
                ORDER BY b.title");
while($x=pg_fetch_assoc($q)) $books[]=$x;

top_nav('Админ — книги'); ?>
<h2>Книги</h2>
<?php if($msg):?><div class="ok"><?=$msg?></div><?php endif;?>
<?php if($err):?><div class="err"><?=$err?></div><?php endif;?>

<h3 class="mt">Добавить книгу</h3>
<form method="post" class="row" style="grid-template-columns:1fr 1fr;gap:12px">
  <input type="hidden" name="action" value="add">
  <div><label>Название</label><input name="title" required></div>
  <div><label>Автор</label>
    <select name="author_id" required>
      <option value="">— Выберите автора —</option>
      <?php foreach($authors as $a): ?>
        <option value="<?=$a['id']?>"><?=h($a['last_name'].' '.$a['first_name'])?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div><label>Дата публикации</label><input type="date" name="publication_date"></div>
  <div><label>Основной жанр</label>
    <select name="genre_name">
      <option value="">— не выбран —</option>
      <?php foreach($genres as $g): ?><option value="<?=$g['id']?>"><?=h($g['name'])?></option><?php endforeach; ?>
    </select>
  </div>
  <div style="grid-column:1/-1">
    <label>Доп. жанры (book_to_genre)</label>
    <select name="extra_genres[]" multiple size="5" style="width:100%">
      <?php foreach($genres as $g): ?><option value="<?=$g['id']?>"><?=h($g['name'])?></option><?php endforeach; ?>
    </select>
  </div>
  <div style="grid-column:1/-1"><label>Описание</label><textarea name="book_desc" rows="3"></textarea></div>
  <div><button class="mt">Добавить</button></div>
</form>

<h3 class="mt">Список книг</h3>
<table>
  <tr><th>ID</th><th>Основные поля</th><th>Доп. жанры</th><th>Действия</th></tr>
  <?php foreach($books as $b):
    $extra = $b['extra'] ? array_map('intval', explode(',', trim($b['extra'],'{}'))) : [];
  ?>
  <tr id="id-<?= (int)$b['id'] ?>">
    <td><?= (int)$b['id'] ?></td>
    <td>
      <form method="post" class="row" style="grid-template-columns:1fr 1fr;gap:6px">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <input name="title" value="<?= h($b['title']) ?>">
        <select name="author_id">
          <?php foreach($authors as $a): ?>
            <option value="<?=$a['id']?>" <?= ((int)$b['author_id']==(int)$a['id'])?'selected':'' ?>>
              <?= h($a['last_name'].' '.$a['first_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="publication_date" value="<?= h($b['publication_date']) ?>">
        <select name="genre_name">
          <option value="">— не выбран —</option>
          <?php foreach($genres as $g): ?>
            <option value="<?=$g['id']?>" <?= ((int)$b['genre_name']==(int)$g['id'])?'selected':'' ?>><?=h($g['name'])?></option>
          <?php endforeach; ?>
        </select>
        <textarea name="book_desc" rows="2" style="grid-column:1/-1"><?= h($b['book_desc']) ?></textarea>
        <div style="grid-column:1/-1">
          <select name="extra_genres[]" multiple size="4" style="width:100%">
            <?php foreach($genres as $g): ?>
              <option value="<?=$g['id']?>" <?= in_array((int)$g['id'],$extra,true)?'selected':'' ?>>
                <?= h($g['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button>Сохранить</button>
      </form>
    </td>
    <td style="max-width:240px">
      <?php if($extra): ?>
        <?php foreach($genres as $g){ if(in_array((int)$g['id'],$extra,true)) echo '<span>'.h($g['name']).'</span><br>'; } ?>
      <?php else: ?>—<?php endif; ?>
    </td>
    <td>
      <form method="post" onsubmit="return confirm('Удалить книгу?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <button>Удалить</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php bottom_nav(); ?>
