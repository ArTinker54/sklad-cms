<?php use Sklad\Core\View; $e = [View::class, 'e']; ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Страница не найдена</title></head><body>
<h1>Страница не найдена</h1>
<p>Адрес <code><?= $e($path ?? '') ?></code> не существует. Возможно, товар переименовали.</p>
<p><a href="/">На главную</a></p>
</body></html>
