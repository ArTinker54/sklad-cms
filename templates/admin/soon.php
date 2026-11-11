<?php

use Sklad\Core\View;

$e = [View::class, 'e'];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Раздел готовится</title>
<link rel="stylesheet" href="/admin.css">
</head>
<body class="login-page">

<div class="login">
  <h1>Этот раздел ещё не готов</h1>
  <p class="note">Адрес <code><?= $e($path) ?></code> пока не занят. Это не сбой —
     до раздела просто не дошла очередь.</p>
  <p><a class="btn btn--primary" href="<?= $e($back) ?>">Вернуться в админку</a></p>
</div>

</body>
</html>
