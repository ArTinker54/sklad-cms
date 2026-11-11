<?php use Sklad\Core\View; $e = [View::class, 'e']; ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?></title>
<style>body{font:16px/1.6 system-ui;max-width:44rem;margin:3rem auto;padding:0 1rem;color:#23272f}
code{background:#f2f4f7;padding:.1rem .35rem;border-radius:3px}</style>
</head><body>
<h1><?= $e($title) ?></h1>
<p><?= $e($note) ?></p>
<p style="color:#6b7280">Витрина ещё не собрана — работает разрешение адресов из базы.</p>
</body></html>
