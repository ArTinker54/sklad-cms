<?php

use Sklad\Core\View;

/**
 * Избранное — отложенные модели.
 *
 * Список живёт в памяти браузера, карточки дорисовывает favorites-page.js
 * из products.json: сервер о выборе человека не знает и знать не должен.
 *
 * Разметка здесь — не украшение, а договор со скриптом. Он ищет по номерам
 * fav-view, fav-grid, fav-empty, fav-count, fav-n и fav-clear; в пустом
 * блоке ему нужны заголовок и абзац — при обрыве сети он переписывает в них
 * текст на сообщение об ошибке. Переименуете номер — страница молча
 * останется пустой.
 *
 * Пустое состояние выводится разметкой сразу, а не дорисовывается скриптом:
 * страница без него читается как поломка, а не как «вы ещё ничего не
 * отложили».
 *
 * @var array $page
 */
$e = [View::class, 'e'];
?>
<?php
// Разметка избранного может лежать в базе, в тексте страницы: так заведён
// Brand — у него этот шаблон долго был заглушкой, выводившей текст
// страницы как есть. Если в тексте уже есть рабочая разметка (узнаём по
// главному элементу, #fav-view), выводим только её, как заглушка, — иначе
// на странице окажутся две копии с одинаковыми id и скрипт запутается.
// Новые сайты заводятся с пустым текстом — им разметку даёт шаблон, а
// текст из базы, если он есть, идёт вступлением над ней.
$legacy = str_contains((string) $page['body'], 'id="fav-view"');
?>
<main id="main" class="container">
<?php if ($legacy): ?>
  <div class="cathead"><h1><?= $e($page['title']) ?></h1></div>
  <?= $page['body'] ?>
<?php else: ?>
  <div class="cathead">
    <h1><?= $e($page['title'] !== '' ? $page['title'] : 'Избранное') ?></h1>
    <p class="cathead__count" id="fav-count"></p>
  </div>

  <?php if (trim((string) $page['body']) !== ''): ?>
    <div class="prose"><?= $page['body'] ?></div>
  <?php endif; ?>

  <div id="fav-view" hidden>
    <div class="cmp-toolbar">
      <span class="cmp-toolbar__hint">Отложено моделей: <b id="fav-n">0</b></span>
      <button class="cmp-clear" type="button" id="fav-clear">Очистить список</button>
    </div>

    <ul class="cards" id="fav-grid"></ul>
  </div>

  <div class="cartempty" id="fav-empty">
    <h2>Вы пока ничего не отложили</h2>
    <p>Нажимайте на сердечко в каталоге — техника соберётся здесь, чтобы
       вернуться к ней позже и отправить одной заявкой.</p>
    <a class="btn btn--primary" href="/katalog/">Перейти в каталог</a>
  </div>
<?php endif; ?>
</main>
