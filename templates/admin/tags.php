<?php

use Sklad\Core\View;

/**
 * Теги — страницы-подборки внутри разделов (05.10.2026).
 *
 * @var array $rows        теги с разделом и числом товаров
 * @var array $categories  живые разделы — для «внутри раздела» и отбора
 * @var bool  $deleted     экран убранных
 * @var int   $only        отбор по разделу (0 — все)
 * @var int   $deletedCount
 */
$e = [View::class, 'e'];
$depth = static fn (string $path): int => max(0, substr_count(trim($path, '/'), '/'));
?>

<div class="head">
  <h1><?= $deleted ? 'Убранные теги' : 'Теги' ?> <span class="head__count"><?= count($rows) ?></span></h1>
  <?php if ($deleted): ?>
    <a class="btn" href="<?= $e($adminPath('tag')) ?>">К тегам</a>
  <?php elseif ($deletedCount > 0): ?>
    <a class="btn" href="<?= $e($adminPath('tag')) ?>?show=deleted">Убранные (<?= (int) $deletedCount ?>)</a>
  <?php endif; ?>
</div>

<?php if (!$deleted): ?>
  <p class="note" style="max-width:60rem">Тег — отдельная страница внутри раздела со своим адресом,
     заголовком и текстами, например «Рохли 2 тонны» в разделе «Тележки». Товары в неё вы отбираете
     сами — в карточке тега или галочкой в карточке товара. Из своего раздела товар никуда не уходит:
     в теге он показывается ещё раз. Ссылка на тег сама встаёт первой в «Популярные запросы» раздела.</p>

  <form class="pane pane--inline" method="post" action="<?= $e($adminPath('tag/new')) ?>">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <div class="row">
      <div style="flex:2">
        <label class="lbl" for="new-title">Новый тег</label>
        <input class="val" id="new-title" name="title" placeholder="Например: Рохли 2 тонны" required>
      </div>
      <div style="flex:2">
        <label class="lbl" for="new-category">Внутри раздела</label>
        <select class="val" id="new-category" name="category_id" required>
          <option value="">— выберите раздел —</option>
          <?php foreach ($categories as $row): ?>
            <option value="<?= (int) $row['id'] ?>"<?= (int) $row['id'] === $only ? ' selected' : '' ?>>
              <?= $e(str_repeat('— ', $depth((string) $row['path'])) . $row['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="align-self:end">
        <button class="btn btn--primary" type="submit">Завести</button>
      </div>
    </div>
    <p class="note">Адрес соберётся из названия: раздел + название, его можно поменять в карточке.
       Новый тег сразу не показывается — включите его, когда отберёте товары.</p>
  </form>

  <form class="tagfilter" method="get" action="<?= $e($adminPath('tag')) ?>">
    <label class="lbl" for="only">Показать теги раздела</label>
    <div class="tagfilter__row">
      <select class="val" id="only" name="category" style="max-width:28rem">
        <option value="">— все разделы —</option>
        <?php foreach ($categories as $row): ?>
          <option value="<?= (int) $row['id'] ?>"<?= (int) $row['id'] === $only ? ' selected' : '' ?>>
            <?= $e(str_repeat('— ', $depth((string) $row['path'])) . $row['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Показать</button>
    </div>
  </form>
<?php endif; ?>

<?php if (!$rows): ?>
  <p class="note"><?= $deleted ? 'Убранных тегов нет.' : ($only > 0 ? 'В этом разделе тегов пока нет — заведите первый выше.' : 'Тегов пока нет — заведите первый выше.') ?></p>
<?php else: ?>
  <table class="grid grid--tags">
    <thead><tr><th>Тег</th><th>Раздел</th><th class="w-cat">Адрес</th><th class="w-state">Товаров</th><th class="w-state"></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr<?= (int) $row['visible'] === 1 && (int) $row['shown'] > 0 ? '' : ' class="is-hidden"' ?>>
          <td><a class="grid__title" href="<?= $e($adminPath('tag/' . $row['id'])) ?>"><?= $e($row['title']) ?></a></td>
          <td><a href="<?= $e($adminPath('tag')) ?>?category=<?= (int) $row['category_id'] ?>"><?= $e($row['category_title']) ?></a></td>
          <td class="w-cat art"><?= $e($row['path']) ?></td>
          <td class="w-state num" title="видно на сайте / всего в теге"><?= (int) $row['shown'] ?><?= (int) $row['goods'] !== (int) $row['shown'] ? ' / ' . (int) $row['goods'] : '' ?></td>
          <td class="w-state">
            <?php if ($deleted): ?>
              <form method="post" action="<?= $e($adminPath('tag/' . $row['id'] . '/restore')) ?>">
                <input type="hidden" name="_token" value="<?= $e($token) ?>">
                <button class="btn" type="submit">Вернуть</button>
              </form>
            <?php elseif (!$row['is_published']): ?>
              <span class="tag tag--off">скрыт</span>
            <?php elseif ((int) $row['visible'] !== 1): ?>
              <span class="tag tag--off" title="Раздел над тегом скрыт или убран">раздел скрыт</span>
            <?php elseif ((int) $row['shown'] === 0): ?>
              <span class="tag tag--off">пустой</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
