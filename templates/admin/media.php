<?php

use Sklad\Core\View;
use Sklad\Model\Photo;

/**
 * Библиотека снимков.
 *
 * @var array      $rows
 * @var array|null $product товар, для которого выбираем
 * @var array      $attached уже прикреплённые к нему снимки
 */
$e = [View::class, 'e'];

// Изменения слева от значений по умолчанию: у объединения массивов
// побеждает левая сторона, и при обратном порядке кнопка «Сбросить»
// вела бы на саму себя — что она и делала.
$link = static function (array $change) use ($query, $free, $product, $adminPath): string {
    $params = array_filter($change + [
        'q' => $query !== '' ? $query : null,
        'free' => $free ? '1' : null,
        'for' => $product !== null ? $product['id'] : null,
    ], static fn ($v): bool => $v !== null && $v !== '');

    return $adminPath('media') . ($params ? '?' . http_build_query($params) : '');
};
?>

<div class="head">
  <h1>Фотографии <span class="head__count"><?= (int) $total ?></span></h1>
  <?php if ($product !== null): ?>
    <a class="btn" href="<?= $e($adminPath('catalog/' . $product['id'])) ?>">К товару</a>
  <?php endif; ?>
</div>

<?php if ($product !== null): ?>
  <p class="flash flash--ok">Выбираете фотографию для товара
    «<?= $e($product['title']) ?>». Нажмите «Прикрепить» под нужной.</p>
<?php endif; ?>

<form class="filters" method="get" action="<?= $e($adminPath('media')) ?>">
  <?php if ($product !== null): ?>
    <input type="hidden" name="for" value="<?= (int) $product['id'] ?>">
  <?php endif; ?>
  <input class="val" type="search" name="q" value="<?= $e($query) ?>"
         placeholder="Имя файла — например, cddk20">
  <label class="check">
    <input type="checkbox" name="free" value="1"<?= $free ? ' checked' : '' ?>>
    <span>только ничьи</span>
  </label>
  <button class="btn" type="submit">Найти</button>
  <?php if ($query !== '' || $free): ?>
    <a class="btn" href="<?= $e($link(['q' => null, 'free' => null])) ?>">Сбросить</a>
  <?php endif; ?>
</form>

<?php if (!$rows): ?>
  <p class="note">Ничего не нашлось. Снимки появляются здесь после загрузки в карточку товара.</p>
<?php else: ?>
  <ul class="media">
    <?php foreach ($rows as $row): ?>
      <li class="media__item">
        <img src="<?= $e(Photo::card($row['file'])) ?>" alt="" loading="lazy" width="160" height="120">
        <p class="media__name" title="<?= $e($row['original_name'] ?: $row['file']) ?>">
          <?= $e($row['original_name'] ?: basename($row['file'])) ?>
        </p>
        <p class="media__meta">
          <?= (int) $row['width'] ?>×<?= (int) $row['height'] ?>
          <?php if ((int) $row['used'] > 0): ?>
            · у <?= (int) $row['used'] ?> <?= (int) $row['used'] === 1 ? 'товара' : 'товаров' ?>
          <?php else: ?>
            · <span class="muted">ничья</span>
          <?php endif; ?>
        </p>

        <?php if ($product !== null): ?>
          <?php if (in_array((int) $row['id'], array_map('intval', $attached), true)): ?>
            <p class="media__done">уже у товара</p>
          <?php else: ?>
            <form method="post" action="<?= $e($adminPath('catalog/' . $product['id'] . '/image/attach')) ?>">
              <input type="hidden" name="_token" value="<?= $e($token) ?>">
              <input type="hidden" name="media_id" value="<?= (int) $row['id'] ?>">
              <button class="btn btn--primary" type="submit">Прикрепить</button>
            </form>
          <?php endif; ?>
        <?php elseif ((int) $row['used'] === 0): ?>
          <?php // Удаление предлагаем только ничьим: пока снимок стоит у
                // товара, на его месте осталась бы битая рамка. ?>
          <form method="post" action="<?= $e($adminPath('media/' . $row['id'] . '/delete')) ?>"
                data-confirm="Удалить снимок совсем? Файлы будут стёрты.">
            <input type="hidden" name="_token" value="<?= $e($token) ?>">
            <button class="btn" type="submit">Удалить</button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
      <?php if ($n === $page): ?>
        <span class="pager__on"><?= $n ?></span>
      <?php else: ?>
        <a href="<?= $e($link(['page' => $n > 1 ? $n : null])) ?>"><?= $n ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
