<?php

use Sklad\Core\View;

/**
 * Ненайденный адрес.
 *
 * Страница самостоятельная, без раскладки: она отдаётся на любой адрес, в том
 * числе когда что-то пошло не так, и тянуть ради неё меню с запросами к базе
 * незачем. Из данных — только настройки, они уже загружены.
 *
 * До этого здесь работала общая заглушка на 440 байт: заголовок, строчка и
 * ссылка «На главную». Человек с битой ссылки попадал на страницу без шапки,
 * подвала и поиска — то есть в тупик, который выглядит как поломка сайта.
 *
 * Пути к стилям корневые. С вложенного адреса вроде /rohli/rohli-standart/нет
 * относительные вели бы в никуда, и страница открылась бы без оформления.
 *
 * @var string $path
 * @var \Sklad\Core\Settings $settings
 */
$e = [View::class, 'e'];
$s = $settings;
$brand = $s->get('brand') ?: $s->get('site_name');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Страница не найдена — <?= $e($brand) ?></title>
<?php // Робот не индексирует, но по ссылкам с неё ходит: иначе он не узнает,
      // что каталог на месте, и решит, что раздел сайта умер целиком. ?>
<meta name="robots" content="noindex, follow">
<link rel="icon" href="/assets/img/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/main.css">
<link rel="stylesheet" href="/assets/css/extra.css">
</head>
<body>

<?php // Шапка урезанная: знак и связь. Меню и поиск в шапке тянут скрипты и
      // запросы к базе, а на этой странице ни того, ни другого нет. ?>
<header class="header">
  <div class="container header__bar">
    <a class="header__logo" href="/" aria-label="На главную">
      <img src="/assets/img/logo-light.png" alt="<?= $e($brand) ?>" height="48">
    </a>
    <?php if ($s->get('phone') !== ''): ?>
      <a class="header__phone" style="margin-left:auto"
         href="tel:<?= $e($s->get('phone_href')) ?>"><?= $e($s->get('phone')) ?></a>
    <?php endif; ?>
  </div>
</header>

<main id="main" class="container">
  <div class="cathead">
    <h1>Такого адреса на сайте нет</h1>
    <?php if (($path ?? '') !== ''): ?>
      <p class="cathead__count">Вы искали <code><?= $e($path) ?></code></p>
    <?php endif; ?>
  </div>

  <p>Возможно, модель сняли с производства или в адресе опечатка.
     Найдите технику поиском или откройте каталог целиком.</p>

  <?php // Поиск прямо здесь — самый короткий путь обратно. Имя поля s, как в
        // ссылке из шапки: страница поиска читает из адреса именно его. ?>
  <form class="searchpage__form" action="/poisk/" method="get" role="search"
        style="margin-top:var(--s-5)">
    <label class="visually-hidden" for="s">Что ищем</label>
    <?php // Пример модели — из каталога этого сайта: вписанный сюда был
          // моделью Brand и на остальных сайтах сети звал искать то, чего
          // у них нет. См. SearchHint. ?>
    <input type="search" id="s" name="s" autocomplete="off"
           placeholder="<?= $e(\Sklad\Model\SearchHint::text(
             $app->db,
             $s,
             'Название или код модели'
           )) ?>">
    <button class="btn btn--primary" type="submit">Найти</button>
  </form>

  <p style="margin-top:var(--s-6);display:flex;gap:var(--s-3);flex-wrap:wrap">
    <a class="btn btn--ghost" href="/katalog/">Открыть каталог</a>
    <a class="btn btn--ghost" href="/">На главную</a>
  </p>

  <div class="next" style="max-width:640px">
    <h3>Найдём за вас</h3>
    <p style="margin:0;color:var(--c-muted);font-size:var(--fs-sm)">
      Опишите задачу — какой груз, на какую высоту, ширина проходов на складе.
      Подберём модель и пришлём счёт.
      <?php if ($s->get('phone') !== ''): ?>
        <br><a href="tel:<?= $e($s->get('phone_href')) ?>"><?= $e($s->get('phone')) ?></a><?php
        if ($s->get('email') !== ''): ?> · <a href="mailto:<?= $e($s->get('email')) ?>"><?= $e($s->get('email')) ?></a><?php endif;
      endif; ?>
    </p>
  </div>
</main>

<footer class="footer">
  <div class="container">
    <p>© <?= date('Y') ?><?= ($who = $s->get('company')) !== '' ? ' ' . $e($who) : '' ?> ·
       <a href="/kontakty/">Контакты</a> ·
       <a href="/politika-konfidencialnosti/">Политика конфиденциальности</a></p>
  </div>
</footer>

</body>
</html>
