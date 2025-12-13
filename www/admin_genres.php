<?php
declare(strict_types=1);
require __DIR__.'/_boot.php'; require_admin();
$c=db();

$msg=''; $err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='add'){
    $name=trim($_POST['name']??''); $descr=trim($_POST['descr']??'');
    if($name===''){ $err='Название не может быть пустым'; }
    else{
      $ok=pg_query_params($c,"INSERT INTO genre(name,descriptions) VALUES($1,$2)",[$name,$descr]);
      $msg=$ok?'Жанр добавлен':'Ошибка: '.pg_last_error($c);
    }
  }elseif($action==='update'){
    $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $descr=trim($_POST['descr']??'');
    $ok=pg_query_params($c,"UPDATE genre SET name=$1, descriptions=$2 WHERE id=$3",[$name,$descr,$id]);
    $msg=$ok?'Жанр обновлён':'Ошибка: '.pg_last_error($c);
  }elseif($action==='delete'){
    $id=(int)($_POST['id']??0);
    // безопасное удаление — можно каскада не делать, просто оставим связи как есть
    $ok=pg_query_params($c,"DELETE FROM genre WHERE id=$1",[$id]);
    $msg=$ok?'Жанр удалён':'Ошибка: '.pg_last_error($c);
  }
}

$rows=[]; $r=pg_query($c,"SELECT id,name,descriptions FROM genre ORDER BY id ASC");
while($x=pg_fetch_assoc($r)) $rows[]=$x;

top_nav('Админ — жанры'); ?>
<h2>Жанры</h2>
<?php if($msg):?><div class="ok"><?=$msg?></div><?php endif;?>
<?php if($err):?><div class="err"><?=$err?></div><?php endif;?>

<h3 class="mt">Добавить жанр</h3>
<form method="post" class="row">
  <input type="hidden" name="action" value="add">
  <div><label>Название</label><input name="name" required></div>
  <div><label>Описание</label><input name="descr"></div>
  <div><button class="mt">Добавить</button></div>
</form>

<h3 class="mt">Список жанров</h3>
<table>
  <tr><th>ID</th><th>Название</th><th>Описание</th><th>Действия</th></tr>
  <?php foreach($rows as $g): ?>
    <tr id="id-<?= (int)$g['id'] ?>">
      <td><?= (int)$g['id'] ?></td>
      <td>
        <form method="post" class="row" style="grid-template-columns:1fr 2fr auto auto;gap:6px">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
          <input name="name" value="<?= h($g['name']) ?>">
          <input name="descr" value="<?= h($g['descriptions']) ?>">
          <button>Сохранить</button>
        </form>
      </td>
      <td><?= h($g['descriptions']) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Удалить жанр?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
          <button>Удалить</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php bottom_nav(); ?>
