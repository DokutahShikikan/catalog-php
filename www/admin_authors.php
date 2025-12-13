<?php
declare(strict_types=1);
require __DIR__.'/_boot.php'; require_admin();
$c=db();

$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=$_POST['action']??'';
  if($a==='add'){
    $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??'');
    $country=trim($_POST['country']??''); $bd=$_POST['birth_date']??null; $dd=$_POST['death_date']??null; $sd=trim($_POST['short_description']??'');
    if($fn===''||$ln===''){ $err='Имя и фамилия обязательны'; }
    else{
      $ok=pg_query_params($c,"INSERT INTO author(first_name,last_name,country,birth_date,death_date,short_description) VALUES($1,$2,$3,$4,$5,$6)",
        [$fn,$ln,$country,$bd?:null,$dd?:null,$sd]);
      $msg=$ok?'Автор добавлен':'Ошибка: '.pg_last_error($c);
    }
  }elseif($a==='update'){
    $id=(int)($_POST['id']??0);
    $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??'');
    $country=trim($_POST['country']??''); $bd=$_POST['birth_date']??null; $dd=$_POST['death_date']??null; $sd=trim($_POST['short_description']??'');
    $ok=pg_query_params($c,"UPDATE author SET first_name=$1,last_name=$2,country=$3,birth_date=$4,death_date=$5,short_description=$6 WHERE id=$7",
      [$fn,$ln,$country,$bd?:null,$dd?:null,$sd,$id]);
    $msg=$ok?'Автор обновлён':'Ошибка: '.pg_last_error($c);
  }elseif($a==='delete'){
    $id=(int)($_POST['id']??0);
    $ok=pg_query_params($c,"DELETE FROM author WHERE id=$1",[$id]);
    $msg=$ok?'Автор удалён':'Ошибка: '.pg_last_error($c);
  }
}

$rows=[]; $r=pg_query($c,"SELECT id,first_name,last_name,country,birth_date,death_date,short_description FROM author ORDER BY last_name,first_name");
while($x=pg_fetch_assoc($r)) $rows[]=$x;

top_nav('Админ — авторы'); ?>
<h2>Авторы</h2>
<?php if($msg):?><div class="ok"><?=$msg?></div><?php endif;?>
<?php if($err):?><div class="err"><?=$err?></div><?php endif;?>

<h3 class="mt">Добавить автора</h3>
<form method="post" class="row" style="grid-template-columns:repeat(2,1fr);gap:12px">
  <input type="hidden" name="action" value="add">
  <div><label>Имя</label><input name="first_name" required></div>
  <div><label>Фамилия</label><input name="last_name" required></div>
  <div><label>Страна</label><input name="country"></div>
  <div class="row" style="grid-template-columns:1fr 1fr;gap:12px">
    <div><label>Дата рождения</label><input type="date" name="birth_date"></div>
    <div><label>Дата смерти</label><input type="date" name="death_date"></div>
  </div>
  <div style="grid-column:1/-1"><label>Краткое описание</label><textarea name="short_description" rows="3"></textarea></div>
  <div><button class="mt">Добавить</button></div>
</form>

<h3 class="mt">Список авторов</h3>
<table>
  <tr><th>ID</th><th>ФИО</th><th>Страна</th><th>Годы жизни</th><th>Описание</th><th></th></tr>
  <?php foreach($rows as $a): ?>
  <tr id="id-<?= (int)$a['id'] ?>">
    <td><?= (int)$a['id'] ?></td>
    <td>
      <form method="post" class="row" style="grid-template-columns:repeat(2,1fr);gap:6px">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <input name="first_name" value="<?= h($a['first_name']) ?>">
        <input name="last_name"  value="<?= h($a['last_name'])  ?>">
        <input name="country"    value="<?= h($a['country'])    ?>" style="grid-column:1/-1">
        <input type="date" name="birth_date" value="<?= h($a['birth_date']) ?>">
        <input type="date" name="death_date" value="<?= h($a['death_date']) ?>">
        <textarea name="short_description" rows="2" style="grid-column:1/-1"><?= h($a['short_description']) ?></textarea>
        <button>Сохранить</button>
      </form>
    </td>
    <td><?= h($a['country']) ?></td>
    <td><?= h($a['birth_date'] ?: '—') ?> — <?= h($a['death_date'] ?: '—') ?></td>
    <td style="max-width:420px"><?= nl2br(h($a['short_description'])) ?></td>
    <td>
      <form method="post" onsubmit="return confirm('Удалить автора?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button>Удалить</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php bottom_nav(); ?>
