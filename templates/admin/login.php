<?php

use Sklad\Core\View;

$e = [View::class, 'e'];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Вход</title>
<link rel="stylesheet" href="/admin.css">
</head>
<body class="login-page">

<form class="login" method="post" action="<?= $e($adminPath('login')) ?>">
  <h1>Вход в админку</h1>

  <?php if ($error === 'slow'): ?>
    <p class="flash flash--bad">Слишком много неудачных попыток. Подождите четверть часа.</p>
  <?php elseif ($error): ?>
    <p class="flash flash--bad">Почта или пароль не подошли.</p>
  <?php endif; ?>

  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <label>Почта
    <input type="email" name="email" autocomplete="username" required autofocus>
  </label>
  <label>Пароль
    <input type="password" name="password" autocomplete="current-password" required>
  </label>
  <button type="submit">Войти</button>
</form>

</body>
</html>
