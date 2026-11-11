<?php

use Sklad\Core\View;

/**
 * Страница поиска.
 *
 * Ищет search-page.js по products.json — тому же индексу, что и подсказка в
 * шапке. Один индекс на оба места намеренно: иначе подсказка и страница
 * результатов однажды разойдутся, и объяснить это будет нечем.
 *
 * Разметка — договор со скриптом: он ищет sform, s, results, found-note и
 * nothing, а запрос подставляет в поле сам, из адреса.
 *
 * @var array $page
 */
$e = [View::class, 'e'];
?>
<?php
// Разметка поиска может лежать в базе, в тексте страницы: так заведён
// Brand — у него этот шаблон долго был заглушкой, выводившей текст
// страницы как есть. Если в тексте уже есть рабочая разметка (узнаём по
// главному элементу, #sform), выводим только её, как заглушка, — иначе
// на странице окажутся две копии с одинаковыми id и скрипт запутается.
// Новые сайты заводятся с пустым текстом — им разметку даёт шаблон, а
// текст из базы, если он есть, идёт вступлением над ней.
$legacy = str_contains((string) $page['body'], 'id="sform"');
?>
<main id="main" class="container">
<?php if ($legacy): ?>
  <div class="cathead"><h1><?= $e($page['title']) ?></h1></div>
  <?= $page['body'] ?>
<?php else: ?>
  <div class="cathead">
    <h1><?= $e($page['title'] !== '' ? $page['title'] : 'Поиск по каталогу') ?></h1>
    <p class="cathead__count" id="found-note"></p>
  </div>

  <?php // Форма обычная, с методом get: поиск должен работать и без скриптов,
        // и ссылкой на результат можно поделиться. ?>
  <form class="searchpage__form" id="sform" role="search" method="get" action="/poisk/">
    <label class="visually-hidden" for="s">Что ищем</label>
    <?php // Имя поля — s, ровно как в ссылке из шапки: /poisk/?s=... Скрипт
          // читает из адреса именно его и сам подставляет в поле. С другим
          // именем адрес открывался бы с пустым поиском. ?>
    <input type="search" id="s" name="s" placeholder="Модель, артикул или тип техники"
           autocomplete="off">
    <button class="btn btn--primary" type="submit">Найти</button>
  </form>

  <ul class="cards" id="results"></ul>

  <div class="cartempty" id="nothing" hidden>
    <h2>Ничего не нашлось</h2>
    <p>Попробуйте короче — например, только модель или только тип техники.
       Или напишите нам, подберём по задаче.</p>
    <a class="btn btn--primary" href="/katalog/">Перейти в каталог</a>
  </div>
<?php endif; ?>
</main>
