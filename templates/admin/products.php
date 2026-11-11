<?php

use Sklad\Core\View;

$e = [View::class, 'e'];

/** Ссылка на список с изменённым набором условий. */
$link = static function (array $override) use ($adminPath, $query, $categoryId, $gap): string {
    $args = array_filter([
        'q' => $query,
        'category' => $categoryId ?: '',
        'gap' => $gap,
    ], static fn ($v): bool => $v !== '' && $v !== 0);
    $args = array_merge($args, $override);
    $args = array_filter($args, static fn ($v): bool => $v !== '' && $v !== null);

    return $adminPath('catalog') . ($args ? '?' . http_build_query($args) : '');
};
?>

<div class="head">
  <h1>Товары <span class="head__count"><?= (int) $total ?></span></h1>
  <a class="btn btn--primary" href="<?= $e($adminPath('catalog/new')) ?>">Добавить товар</a>
</div>

<form class="filters" method="get" action="<?= $e($adminPath('catalog')) ?>">
  <input class="filters__search" type="search" name="q" value="<?= $e($query) ?>"
         placeholder="Название, артикул или код модели">

  <select name="category">
    <option value="">Все разделы</option>
    <?php foreach ($categories as $row): ?>
      <option value="<?= (int) $row['id'] ?>"<?= $categoryId === (int) $row['id'] ? ' selected' : '' ?>>
        <?= $e(str_repeat('— ', max(0, substr_count(trim($row['path'], '/'), '/'))) . $row['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button class="btn" type="submit">Показать</button>
  <?php if ($query !== '' || $categoryId || $gap !== ''): ?>
    <a class="filters__reset" href="<?= $e($adminPath('catalog')) ?>">Сбросить</a>
  <?php endif; ?>
</form>

<?php
// Прорехи в каталоге. Перенесённый со старого сайта каталог всегда
// неполон, и без такой подсказки непонятно, с чего начинать.
// «Убранные» стоят последними и отделены: это не прореха, а корзина —
// оттуда товар возвращают, если убрали не тот.
$labels = ['price' => 'без цены', 'photo' => 'без фото', 'text' => 'без описания',
           'hidden' => 'скрытые', 'deleted' => 'убранные'];
?>
<div class="chips">
  <?php foreach ($labels as $key => $label): ?>
    <?php $count = $gaps[$key] ?? 0; ?>
    <a class="chip<?= $gap === $key ? ' chip--on' : '' ?><?= $count ? '' : ' chip--empty' ?>"
       href="<?= $e($link(['gap' => $gap === $key ? null : $key, 'page' => null])) ?>">
      <?= $e($label) ?> <b><?= (int) $count ?></b>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <p class="empty">Под эти условия ничего не нашлось.</p>
<?php else: ?>

<?php // Форма порядка стоит рядом с таблицей, а не оборачивает её: внутри
      // таблицы своя форма возврата убранного товара, а форма в форме —
      // недопустимая разметка. Поля привязаны к ней атрибутом form. ?>
<form id="order-form" method="post" action="<?= $e($adminPath('catalog/order')) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <input type="hidden" name="back"
         value="<?= $e(parse_url($link(['page' => $page > 1 ? $page : null]), PHP_URL_QUERY) ?? '') ?>">
</form>

<p class="note" style="margin-bottom:8px">
  Столбец <b>№</b> — порядок вручную: чем меньше число, тем выше товар
  в разделе, в подборе «по популярности» и в блоке популярных на главной.
  Отберите раздел фильтром выше, проставьте числа и сохраните.
</p>

<table class="grid grid--products">
  <thead>
    <tr>
      <th style="width:74px">№</th>
      <th class="w-photo"></th>
      <th>Название</th>
      <th class="w-article">Артикул</th>
      <th class="w-price">Цена</th>
      <th class="w-cat">Раздел</th>
      <th class="w-state"></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $row): ?>
      <tr<?= $row['is_published'] ? '' : ' class="is-hidden"' ?>>
        <td>
          <?php // Убранный товар не переставляем: он и так вне витрины. ?>
          <?php if ($gap !== 'deleted'): ?>
            <input class="val num" type="number" form="order-form" min="0" style="width:64px"
                   name="sort[<?= (int) $row['id'] ?>]" value="<?= (int) $row['sort'] ?>">
          <?php endif; ?>
        </td>
        <td class="w-photo">
          <?php if ($row['photo']): ?>
            <img src="<?= $e($row['photo']) ?>" alt="" loading="lazy">
          <?php else: ?>
            <span class="nophoto" title="Фотографии нет">—</span>
          <?php endif; ?>
        </td>
        <td>
          <a class="grid__title" href="<?= $e($adminPath('catalog/' . $row['id'])) ?>"><?= $e($row['title']) ?></a>
          <span class="grid__path"><?= $e($row['path']) ?></span>
        </td>
        <td class="w-article"><?= $e($row['article']) ?></td>
        <td class="w-price">
          <?php if ((float) $row['price'] > 0): ?>
            <b><?= $e(View::price($row['price'])) ?></b>
            <?php if ((float) $row['old_price'] > (float) $row['price']): ?>
              <s><?= $e(View::price($row['old_price'])) ?></s>
            <?php endif; ?>
          <?php else: ?>
            <span class="muted">по запросу</span>
          <?php endif; ?>
        </td>
        <td class="w-cat"><?= $e($row['category_title'] ?? '—') ?></td>
        <td class="w-state">
          <?php if ($gap === 'deleted'): ?>
            <form method="post" action="<?= $e($adminPath('catalog/' . $row['id'] . '/restore')) ?>">
              <input type="hidden" name="_token" value="<?= $e($token) ?>">
              <button class="btn" type="submit">Вернуть</button>
            </form>
          <?php elseif (!$row['is_published']): ?>
            <span class="tag tag--off">скрыт</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($gap !== 'deleted'): ?>
  <p style="margin-top:16px">
    <button class="btn btn--primary" type="submit" form="order-form">Сохранить порядок</button>
    <span class="note" style="margin-left:12px">Сохраняются числа только с этой страницы списка.</span>
  </p>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
      <?php if ($n === $page): ?>
        <span class="pager__on"><?= $n ?></span>
      <?php else: ?>
        <a href="<?= $e($link(['page' => $n])) ?>"><?= $n ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>
