<?php

use Sklad\Core\View;

/**
 * Сравнение моделей.
 *
 * Таблицу строит compare-page.js из products.json: строки — характеристики,
 * колонки — отложенные модели. Сервер список не хранит, он в памяти браузера.
 *
 * Разметка — договор со скриптом. Он ищет cmp-view, cmp-table, cmp-body,
 * cmp-empty, cmp-count, cmp-clear, only-diff и diff-hint; в пустом блоке ему
 * нужны заголовок и абзац — при обрыве сети он переписывает в них текст.
 *
 * Таблица лежит внутри cmp-scroll: колонок бывает четыре, и на узком экране
 * крутиться должна она, а не вся страница.
 *
 * @var array $page
 */
$e = [View::class, 'e'];
?>
<?php
// Разметка сравнения может лежать в базе, в тексте страницы: так заведён
// Brand — у него этот шаблон долго был заглушкой, выводившей текст
// страницы как есть. Если в тексте уже есть рабочая разметка (узнаём по
// главному элементу, #cmp-view), выводим только её, как заглушка, — иначе
// на странице окажутся две копии с одинаковыми id и скрипт запутается.
// Новые сайты заводятся с пустым текстом — им разметку даёт шаблон, а
// текст из базы, если он есть, идёт вступлением над ней.
$legacy = str_contains((string) $page['body'], 'id="cmp-view"');
?>
<main id="main" class="container">
<?php if ($legacy): ?>
  <div class="cathead"><h1><?= $e($page['title']) ?></h1></div>
  <?= $page['body'] ?>
<?php else: ?>
  <div class="cathead">
    <h1><?= $e($page['title'] !== '' ? $page['title'] : 'Сравнение моделей') ?></h1>
    <p class="cathead__count" id="cmp-count"></p>
  </div>

  <?php if (trim((string) $page['body']) !== ''): ?>
    <div class="prose"><?= $page['body'] ?></div>
  <?php endif; ?>

  <div id="cmp-view" hidden>
    <div class="cmp-toolbar">
      <?php // Переключатель «только различия» — главное, зачем сюда приходят:
            // совпадающие строки у складской техники занимают пол-экрана. ?>
      <label class="cmp-switch">
        <input type="checkbox" id="only-diff"> Показывать только различия
      </label>
      <span class="cmp-toolbar__hint" id="diff-hint"></span>
      <button class="cmp-clear" type="button" id="cmp-clear">Очистить сравнение</button>
    </div>

    <div class="cmp-scroll">
      <table class="cmp" id="cmp-table">
        <tbody id="cmp-body"></tbody>
      </table>
    </div>
  </div>

  <div class="cartempty" id="cmp-empty">
    <h2>Пока нечего сравнивать</h2>
    <p>Отметьте в каталоге две-три модели значком со столбиками — здесь они
       встанут рядом, характеристика к характеристике.</p>
    <a class="btn btn--primary" href="/katalog/">Перейти в каталог</a>
  </div>
<?php endif; ?>
</main>
