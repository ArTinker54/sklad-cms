<?php

use Sklad\Core\View;

/**
 * Карточка тега — страницы-подборки внутри раздела (05.10.2026).
 *
 * @var array      $tag
 * @var array      $categories  живые разделы
 * @var array|null $parentGone  раздел тега, если он убран
 * @var array      $chosen      товары в теге, по порядку
 * @var array      $pool        остальные товары каталога: адрес раздела => [title, rows]
 * @var bool       $visible     тег сейчас виден на сайте
 */
$e = [View::class, 'e'];
$depth = static fn (string $path): int => max(0, substr_count(trim($path, '/'), '/'));
$shownCount = count(array_filter($chosen, static fn (array $row): bool => (int) $row['visible'] === 1));
$homeTitle = $parentGone !== null ? (string) $parentGone['title'] : '';
foreach ($categories as $row) {
    if ((int) $row['id'] === (int) $tag['category_id']) {
        $homeTitle = (string) $row['title'];
    }
}
?>

<div class="head">
  <h1>Тег: <?= $e($tag['title']) ?></h1>
  <a class="btn" href="<?= $e($tag['path']) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
  <a class="btn" href="<?= $e($adminPath('tag')) ?>?category=<?= (int) $tag['category_id'] ?>">К тегам раздела</a>
</div>

<?php if ($tag['deleted_at'] !== null): ?>
  <p class="flash flash--bad">Тег убран с сайта: его адрес ведёт в раздел. Вернуть — кнопка внизу страницы.</p>
<?php elseif (!$visible || $shownCount === 0): ?>
  <p class="flash flash--bad">
    <?php if (!(int) $tag['is_published']): ?>
      Тег пока скрыт — на сайте его нет. Отберите товары и поставьте галочку «Показывать на сайте».
    <?php elseif (!$visible): ?>
      Раздел «<?= $e($homeTitle) ?>» или раздел над ним скрыт — вместе с ним не виден и тег.
    <?php else: ?>
      В теге нет ни одного товара, видимого на сайте: страница пустая и в «Популярных запросах» не показывается.
    <?php endif; ?>
  </p>
<?php endif; ?>

<form method="post" action="<?= $e($adminPath('tag/' . $tag['id'])) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">

  <div class="two">
    <div class="pane">
      <h2>Тег</h2>

      <div>
        <label class="lbl" for="title">Название — в «Популярных запросах» и в хлебных крошках</label>
        <input class="val" id="title" name="title" value="<?= $e($tag['title']) ?>" required>
      </div>

      <div>
        <label class="lbl" for="h1">Заголовок на странице (H1)</label>
        <input class="val" id="h1" name="h1" value="<?= $e($tag['h1']) ?>" placeholder="<?= $e($tag['title']) ?>">
        <p class="note">Пусто — возьмётся название. Разные нужны, когда в ссылке коротко
           («Рохли 2 тонны»), а на странице подробно («Гидравлические тележки грузоподъёмностью 2 тонны»).</p>
      </div>

      <div class="row">
        <div style="flex:1">
          <label class="lbl" for="category_id">Внутри раздела</label>
          <select class="val" id="category_id" name="category_id">
            <?php if ($parentGone !== null): ?>
              <option value="<?= (int) $parentGone['id'] ?>" selected><?= $e($parentGone['title'] . ' — убран с сайта') ?></option>
            <?php endif; ?>
            <?php foreach ($categories as $row): ?>
              <option value="<?= (int) $row['id'] ?>"<?= (int) $row['id'] === (int) $tag['category_id'] ? ' selected' : '' ?>>
                <?= $e(str_repeat('— ', $depth((string) $row['path'])) . $row['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="width:9rem">
          <label class="lbl" for="sort">Место</label>
          <input class="val num" type="number" id="sort" name="sort" min="0" value="<?= (int) $tag['sort'] ?>">
        </div>
      </div>
      <p class="note">Раздел задаёт адрес тега и где ссылка на него появится: в «Популярных запросах»
         этого раздела и его подразделов, у которых нет своих тегов. «Место» — порядок ссылок там:
         меньше число — левее.</p>

      <div>
        <label class="lbl" for="slug">Адрес</label>
        <input class="val art" id="slug" name="slug" value="<?= $e($tag['slug']) ?>">
        <p class="note">Сейчас: <span class="art"><?= $e($tag['path']) ?></span>. При смене адреса
           или раздела со старого адреса встанет переадресация.</p>
      </div>

      <div>
        <label class="lbl" for="intro">Текст над списком товаров</label>
        <textarea class="val" id="intro" name="intro" rows="3"><?= $e($tag['intro']) ?></textarea>
      </div>

      <div>
        <label class="lbl" for="body">Текст под списком</label>
        <textarea class="val" id="body" name="body" rows="6" data-editor><?= $e($tag['body']) ?></textarea>
      </div>

      <label class="check">
        <input type="checkbox" name="is_published" value="1"<?= $tag['is_published'] ? ' checked' : '' ?>>
        <span>Показывать на сайте</span>
      </label>
    </div>

    <div class="pane">
      <h2>Title и Description</h2>
      <?= $view->render('admin/_seo', [
            'row' => $tag,
            'autoTitle' => \Sklad\Model\Meta::withSuffix(
              (string) (($tag['h1'] ?? '') !== '' ? $tag['h1'] : $tag['title']),
              (string) $settings->get('meta_title_suffix')
            ),
            'autoDescription' => '',
            'url' => $app->siteUrl((string) ($tag['path'] ?? '')),
          ]) ?>

      <label class="check">
        <input type="checkbox" name="is_indexable" value="1"<?= $tag['is_indexable'] ? ' checked' : '' ?>>
        <span>Пускать тег в поиск</span>
      </label>
      <p class="note">Пока в теге нет товаров, страница закрыта от поиска сама — независимо от галочки.</p>
    </div>
  </div>

  <div class="pane" id="goods" style="margin-top:1.5rem">
    <h2>Товары тега <span class="head__count"><?= count($chosen) ?></span></h2>
    <input type="hidden" name="products_present" value="1">
    <p class="note">Товар остаётся в своём разделе и по своему адресу — здесь он только показывается
       ещё раз. Отметить товар можно и в его карточке, в блоке «Теги».</p>

    <?php if ($chosen): ?>
      <table class="grid tagpick__chosen">
        <thead><tr><th class="w-state">В теге</th><th>Товар</th><th>Раздел</th><th class="w-state">Место</th></tr></thead>
        <tbody>
          <?php foreach ($chosen as $i => $row): ?>
            <tr<?= (int) $row['visible'] === 1 ? '' : ' class="is-hidden"' ?>>
              <td class="w-state"><input type="hidden" name="shown[]" value="<?= (int) $row['id'] ?>"><input
                     type="checkbox" name="product[]" value="<?= (int) $row['id'] ?>" checked
                     aria-label="Оставить «<?= $e($row['title']) ?>» в теге"></td>
              <td>
                <a href="<?= $e($adminPath('catalog/' . $row['id'])) ?>" target="_blank" rel="noopener"><?= $e($row['title']) ?></a>
                <?php if ((string) $row['article'] !== ''): ?><span class="art"> · <?= $e($row['article']) ?></span><?php endif; ?>
                <?php if ((int) $row['visible'] !== 1): ?><span class="tag tag--off">не виден на сайте</span><?php endif; ?>
              </td>
              <td class="note"><?= $e((string) ($row['category_title'] ?? '')) ?></td>
              <td class="w-state"><input class="val num" type="number" min="0" step="1" name="sort_of[<?= (int) $row['id'] ?>]"
                     value="<?= ($i + 1) * 10 ?>" aria-label="Место «<?= $e($row['title']) ?>»"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="note">Снимите галочку — товар уйдёт из тега (из каталога он не пропадёт).
         «Место» — порядок на странице тега: меньше число — выше. Числа идут через 10,
         чтобы ставить между: 15 встанет между первым и вторым.</p>
    <?php else: ?>
      <p class="note note--bad">В теге пока нет товаров — отметьте их в списке ниже и нажмите «Сохранить».</p>
    <?php endif; ?>

    <h3 class="group">Добавить товары</h3>
    <div class="tagpick" data-picker>
      <input class="val" type="search" placeholder="Найти по названию или артикулу" data-picker-search
             aria-label="Найти товар для тега">
      <p class="note" data-picker-count>Отмеченные встанут в конец списка после «Сохранить».</p>
      <div class="tagpick__list">
        <?php $first = true; ?>
        <?php foreach ($pool as $group): ?>
          <details class="tagpick__group"<?= $first ? ' open' : '' ?>>
            <summary><?= $e($group['title']) ?> <span class="note"><?= count($group['rows']) ?></span></summary>
            <?php foreach ($group['rows'] as $row): ?>
              <label class="tagpick__item" data-picker-item
                     data-text="<?= $e(mb_strtolower($row['title'] . ' ' . $row['article'])) ?>">
                <input type="checkbox" name="add[]" value="<?= (int) $row['id'] ?>">
                <span><?= $e($row['title']) ?></span>
                <?php if ((string) $row['article'] !== ''): ?><span class="art"><?= $e($row['article']) ?></span><?php endif; ?>
                <?php if (!(int) $row['is_published']): ?><span class="tag tag--off">скрыт</span><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </details>
          <?php $first = false; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?= $view->render('admin/_city-content', [
        'row' => $tag, 'cities' => $cities, 'cityContent' => $cityContent, 'entity' => 'tag',
        'autoTitleBare' => (string) (($tag['h1'] ?? '') !== '' ? $tag['h1'] : $tag['title']),
        'titleSuffix' => (string) $settings->get('meta_title_suffix'),
      ]) ?>

  <div class="save-bar">
    <button class="btn btn--primary" type="submit">Сохранить</button>
  </div>
</form>

<?php if ($tag['deleted_at'] !== null): ?>
  <form class="pane pane--danger" method="post" action="<?= $e($adminPath('tag/' . $tag['id'] . '/restore')) ?>">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <h2>Тег убран</h2>
    <p class="note">Убран <?= $e(View::date($tag['deleted_at'], 'd.m.Y в H:i')) ?>. Его адрес ведёт в раздел.
       Вернуть можно в любой момент — тег появится скрытым, отобранные товары сохранились.</p>
    <button class="btn btn--primary" type="submit">Вернуть тег</button>
  </form>
<?php else: ?>
  <form class="pane pane--danger" method="post" action="<?= $e($adminPath('tag/' . $tag['id'] . '/delete')) ?>"
        data-confirm="Убрать тег «<?= $e($tag['title']) ?>»?">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <h2>Убрать тег</h2>
    <p class="note">Страница тега пропадёт с сайта и из «Популярных запросов», товары останутся в своих
       разделах. Адрес тега будет вести в раздел «<?= $e($homeTitle) ?>» — ссылки из выдачи не превратятся
       в «страница не найдена».</p>
    <button class="btn" type="submit">Убрать тег</button>
  </form>
<?php endif; ?>
