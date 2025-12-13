<?php // /www/logout.php
declare(strict_types=1);
require __DIR__.'/_boot.php';
session_destroy();
header('Location:/login.php');
