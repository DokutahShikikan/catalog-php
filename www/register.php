<?php
declare(strict_types=1);
require __DIR__.'/_boot.php';
$c = db(); ensure_admin_table($c);

$exists = (int)pg_fetch_result(pg_query($c,"SELECT COUNT(*) FROM admin_user"),0,0);
if ($exists>0) { header('Location:/login.php'); exit; }

$err=''; if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??''); $p=(string)($_POST['pass']??''); $p2=(string)($_POST['pass2']??'');
  if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $err='Неверный email';
  elseif(strlen($p)<6) $err='Пароль от 6 символов';
  elseif($p!==$p2) $err='Пароли не совпадают';
  else{
    $hash=password_hash($p,PASSWORD_DEFAULT);
    $ok=pg_query_params($c,"INSERT INTO admin_user(email,pass_hash) VALUES($1,$2)",[$email,$hash]);
    if($ok){ header('Location:/login.php?ok=1'); exit; }
    $err='PG error: '.h(pg_last_error($c));
  }
}
top_nav('Регистрация админа');
?>
<h2>Первичная регистрация администратора</h2>
<?php if($err):?><div class="err"><?=$err?></div><?php endif;?>
<form method="post" class="mt" style="max-width:420px">
  <label>Email</label><input name="email" type="email" required>
  <label class="mt">Пароль</label><input name="pass" type="password" required minlength="6">
  <label class="mt">Повтор пароля</label><input name="pass2" type="password" required minlength="6">
  <button class="mt">Создать</button>
</form>
<?php bottom_nav(); ?>
