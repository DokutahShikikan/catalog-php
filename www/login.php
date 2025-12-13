<?php
declare(strict_types=1);
require __DIR__.'/_boot.php';
$c=db(); ensure_admin_table($c);
$err=''; if($_SERVER['REQUEST_METHOD']==='POST'){
  $email=trim($_POST['email']??''); $pass=(string)($_POST['pass']??'');
  $r=pg_query_params($c,"SELECT id,pass_hash FROM admin_user WHERE email=$1",[$email]);
  if($row=pg_fetch_assoc($r)){
    if(password_verify($pass,$row['pass_hash'])){ $_SESSION['admin_id']=(int)$row['id']; header('Location:/admin.php'); exit; }
  }
  $err='Неверная почта или пароль';
}
top_nav('Вход админа'); ?>
<h2>Вход администратора</h2>
<?php if(isset($_GET['ok'])):?><div class="ok">Админ создан. Войдите.</div><?php endif;?>
<?php if($err):?><div class="err"><?=$err?></div><?php endif;?>
<form method="post" style="max-width:420px" class="mt">
  <label>Email</label><input name="email" type="email" required>
  <label class="mt">Пароль</label><input name="pass" type="password" required>
  <button class="mt">Войти</button>
</form>
<?php bottom_nav(); ?>
