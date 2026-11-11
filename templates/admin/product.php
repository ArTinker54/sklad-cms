<?php

use Sklad\Core\View;

$e = [View::class, 'e'];

// Характеристики разложены по группам — так их читают в паспорте
// техники, и так же они показаны на витрине.
$byGroup = [];
foreach ($options as $option) {
    $byGroup[$option['group'] !== '' ? $option['group'] : 'Прочее'][] = $option;
}
?>

<div class="head">
  <h1><?= $e($product['title']) ?></h1>
  <a class="btn" href="<?= $e($product['path']) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
  <a class="btn" href="<?= $e($adminPath('catalog')) ?>">К списку</a>
</div>

<form method="post" action="<?= $e($adminPath('catalog/' . $product['id'])) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">

  <div class="two">
    <div class="pane">
      <h2>Товар</h2>

      <div>
        <label class="lbl" for="title">Название</label>
        <input class="val" id="title" name="title" value="<?= $e($product['title']) ?>" required>
      </div>

      <div class="row">
        <div style="flex:1">
          <label class="lbl" for="article">Артикул</label>
          <input class="val" id="article" name="article" value="<?= $e($product['article']) ?>">
        </div>
        <div style="flex:1">
          <label class="lbl" for="category_id">Раздел</label>
          <select class="val" id="category_id" name="category_id">
            <?php foreach ($categories as $row): ?>
              <option value="<?= (int) $row['id'] ?>"<?= (int) $product['category_id'] === (int) $row['id'] ? ' selected' : '' ?>>
                <?= $e(str_repeat('— ', max(0, substr_count(trim($row['path'], '/'), '/'))) . $row['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label class="lbl" for="slug">Адрес страницы</label>
        <input class="val art" id="slug" name="slug" value="<?= $e($product['slug']) ?>">
        <p class="note">Сейчас: <span class="art"><?= $e($product['path']) ?></span>.
           Если поменять адрес или раздел, со старого встанет переадресация —
           ссылки из выдачи и рекламы не потеряются.</p>
      </div>

      <div>
        <label class="lbl" for="intro">Короткое описание — показывается в плитке каталога</label>
        <textarea class="val" id="intro" name="intro" rows="2"><?= $e($product['intro']) ?></textarea>
      </div>

      <div>
        <label class="lbl" for="body">Описание</label>
        <textarea class="val" id="body" name="body" rows="8" data-editor><?= $e($product['body']) ?></textarea>
      </div>

      <h2 id="photos">Фотографии</h2>
      <div class="gallery">
        <?php foreach ($images as $index => $image): ?>
          <figure class="gallery__item<?= $index === 0 ? ' gallery__item--main' : '' ?>">
            <img src="<?= $e($image['file']) ?>" alt="" loading="lazy">
            <?php if ($index === 0): ?><figcaption>главная</figcaption><?php endif; ?>
            <?php // Удаление отдельной формой, а не ссылкой: по ссылке
                  // снимок сносился бы одним переходом — и от чужой
                  // страницы, открытой в соседней вкладке, тоже. Сама форма
                  // лежит ниже, вне формы карточки, а кнопка привязана к ней
                  // атрибутом form — иначе крестик оказался бы формой внутри
                  // формы. ?>
            <button class="gallery__drop" type="submit" title="Убрать"
                    form="image-drop-<?= (int) $image['id'] ?>">✕</button>
          </figure>
        <?php endforeach; ?>
        <?php if (!$images): ?>
          <p class="note">Фотографий нет. Товар без снимка в каталоге почти не открывают.</p>
        <?php endif; ?>
      </div>
      <?php // Снимки моделей одной линейки часто совпадают: проще выбрать
            // из уже загруженных, чем заливать тот же файл заново. ?>
      <p class="note">
        <a class="btn" href="<?= $e($adminPath('media') . '?for=' . (int) $product['id']) ?>">Выбрать
          из уже загруженных</a>
      </p>
    </div>

    <div class="pane">
      <h2>Цена и наличие</h2>

      <?php // Кому цена не подчиняется — тому поля показываем, но закрытыми.
            // Сохранение их всё равно не примет, и оставь мы их правимыми,
            // человек вписал бы цену, прочёл «Сохранено» и ушёл уверенным,
            // что поменял. Молчаливое «ничего не произошло» читается как
            // «сделано» — экран акций этого избегает так же. ?>
      <div class="row">
        <div style="flex:1">
          <label class="lbl" for="price">Цена, ₽</label>
          <input class="val val--strong num" id="price" name="price"
                 value="<?= $product['price'] > 0 ? $e(rtrim(rtrim(number_format((float) $product['price'], 2, ',', ' '), '0'), ',')) : '' ?>"
                 placeholder="по запросу"<?= $canPrice ? '' : ' readonly' ?>>
        </div>
        <div style="flex:1">
          <label class="lbl" for="old_price">Старая цена, ₽</label>
          <input class="val num" id="old_price" name="old_price"
                 value="<?= $product['old_price'] > 0 ? $e(rtrim(rtrim(number_format((float) $product['old_price'], 2, ',', ' '), '0'), ',')) : '' ?>"<?= $canPrice ? '' : ' readonly' ?>>
        </div>
      </div>
      <?php if ($canPrice): ?>
        <p class="note">Пустая цена — на сайте будет «по запросу». Старая цена выше
           текущей включает метку акции.</p>
      <?php else: ?>
        <p class="note">Цены и срок акции правит администратор или контент-менеджер —
           у вашей роли они только для просмотра.</p>
      <?php endif; ?>

      <div class="row">
        <div style="flex:1">
          <label class="lbl" for="promo_until">Акция до</label>
          <input class="val" type="date" id="promo_until" name="promo_until"
                 value="<?= $e($product['promo_until'] ?? '') ?>"<?= $canPrice ? '' : ' readonly' ?>>
        </div>
        <div style="flex:1">
          <label class="lbl" for="availability">Наличие</label>
          <select class="val" id="availability" name="availability">
            <?php foreach (['in_stock' => 'На складе', 'on_order' => 'Под заказ', 'out' => 'Нет в наличии'] as $key => $label): ?>
              <option value="<?= $e($key) ?>"<?= $product['availability'] === $key ? ' selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <p class="note">Дата прошла — метка пропадает, товар уходит со страницы акций.
         Цену при этом система не меняет: это ваше решение.</p>

      <div>
        <label class="lbl" for="sort">Место в разделе</label>
        <input class="val num" type="number" id="sort" name="sort" min="0"
               value="<?= (int) $product['sort'] ?>">
        <p class="note">Чем меньше число, тем выше товар в списке раздела и в
           подборе «по популярности». У остальных сортировок свой порядок —
           по цене и по названию их задаёт покупатель.</p>
      </div>

      <label class="check">
        <input type="checkbox" name="is_published" value="1"<?= $product['is_published'] ? ' checked' : '' ?>>
        <span>Показывать на сайте</span>
      </label>


      <h2>Для поиска</h2>

      <div>
        <label class="lbl" for="h1">Заголовок на странице</label>
        <input class="val" id="h1" name="h1" value="<?= $e($product['h1']) ?>"
               placeholder="<?= $e($product['title']) ?>">
        <p class="note">Пусто — возьмётся название. Разные нужны, когда в списке
           коротко, а на странице полно и с ключевым словом.</p>
      </div>

      <?php // Пустые Title и Description товара собираются по шаблону раздела
            // (Meta::forProduct) — подсказка и предпросмотр показывают именно
            // их, как увидит поисковик. ?>
      <?= $view->render('admin/_seo', [
            'row' => $product,
            'autoTitle' => (string) $effective['title'],
            'autoDescription' => (string) $effective['description'],
            'url' => $app->siteUrl((string) ($product['path'] ?? '')),
          ]) ?>

      <label class="check">
        <input type="checkbox" name="is_indexable" value="1"<?= $product['is_indexable'] ? ' checked' : '' ?>>
        <span>Пускать в поиск</span>
      </label>

      <div>
        <label class="lbl" for="canonical">Основной адрес</label>
        <input class="val art" id="canonical" name="canonical" value="<?= $e($product['canonical']) ?>">
        <p class="note">Заполняют, когда товар дублирует другой: страница остаётся,
           но поисковику показывают, какая из них главная. Пусто — своя.</p>
      </div>

      <div class="row">
        <div style="flex:1">
          <label class="lbl" for="vendor">Производитель</label>
          <input class="val" id="vendor" name="vendor" value="<?= $e($product['vendor']) ?>">
        </div>
        <div style="flex:1">
          <label class="lbl" for="made_in">Страна</label>
          <input class="val" id="made_in" name="made_in" value="<?= $e($product['made_in']) ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="pane" style="margin-top:1.5rem">
    <h2>Характеристики</h2>
    <?php if (!$options): ?>
      <p class="note">Для этого раздела характеристики не заданы. Их набор
         настраивается у раздела — так в карточке тележки не появятся поля от
         штабелёра.</p>
    <?php else: ?>
      <?php foreach ($byGroup as $group => $list): ?>
        <h3 class="group"><?= $e($group) ?></h3>
        <div class="specs">
          <?php foreach ($list as $option): ?>
            <label class="spec">
              <span class="spec__name">
                <?= $e($option['title']) ?><?= $option['unit'] !== '' ? ', ' . $e($option['unit']) : '' ?>
              </span>
              <input class="val<?= $option['type'] === 'number' ? ' num' : '' ?>"
                     name="option[<?= (int) $option['id'] ?>]"
                     value="<?= $e($option['value'] ?? '') ?>"
                     <?= $option['type'] === 'number' ? 'inputmode="decimal"' : '' ?>>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="pane" style="margin-top:1.5rem">
    <h2>Похожие товары</h2>
    <?php if (!$candidates): ?>
      <p class="note">В этом разделе больше ничего нет — выбирать не из чего.</p>
    <?php else: ?>
      <select class="val picker" name="related[]" multiple size="10">
        <?php foreach ($candidates as $row): ?>
          <option value="<?= (int) $row['id'] ?>"<?= in_array((string) $row['id'], array_map('strval', $linked), true) ? ' selected' : '' ?>>
            <?= $e($row['title']) ?><?= $row['article'] !== '' ? ' · ' . $e($row['article']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="note">
        Держите Ctrl, чтобы отметить несколько. Порядок в блоке на сайте — порядок
        в этом списке сверху вниз.
        <?php if (!$linked): ?>
          <b>Сейчас ничего не выбрано, и сайт подбирает похожие сам</b> — берёт
          соседей по разделу. Как только отметите хоть один, показываться будут
          только отмеченные.
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="save-bar">
    <button class="btn btn--primary" type="submit">Сохранить</button>
  </div>
</form>

<?php // Формы удаления снимков стоят здесь, а не в самой галерее: браузер
      // не допускает форму внутри формы — вложенную он выбрасывает, а её
      // закрывающий тег обрывает внешнюю. Форма карточки заканчивалась на
      // первом снимке: «Сохранить» оказывалась снаружи и молчала, а крестик
      // отправлял обрубок карточки без характеристик и похожих товаров —
      // и они затирались. Скрыты, потому что нужны только как адресат
      // кнопок из галереи. ?>
<?php foreach ($images as $image): ?>
  <form method="post" class="gallery__drop-form" hidden
        id="image-drop-<?= (int) $image['id'] ?>"
        action="<?= $e($adminPath('catalog/' . $product['id'] . '/image/' . $image['id'] . '/remove')) ?>">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
  </form>
<?php endforeach; ?>

<form class="pane" style="margin-top:1.5rem" method="post" enctype="multipart/form-data"
      action="<?= $e($adminPath('catalog/' . $product['id'] . '/image')) ?>">
  <h2>Добавить фотографии</h2>
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <input class="val" type="file" name="photo[]" accept="image/*" multiple required>
  <p class="note">Снимки пережимаются при загрузке — с телефона можно лить как есть.
     Первая по порядку становится главной.</p>
  <button class="btn" type="submit">Загрузить</button>
</form>

<?php if ($history): ?>
  <div class="pane" style="margin-top:1.5rem">
    <h2>История изменений</h2>
    <table class="grid">
      <thead><tr><th class="w-price">Когда</th><th class="w-cat">Кто</th><th>Что изменилось</th></tr></thead>
      <tbody>
        <?php foreach ($history as $row): ?>
          <tr>
            <td class="w-price num"><?= $e(View::date($row['created_at'], 'd.m.Y H:i')) ?></td>
            <td class="w-cat"><?= $e($row['user_name'] ?? 'система') ?></td>
            <td>
              <?php
              $changes = $row['changes'] ? json_decode($row['changes'], true) : null;
              if (!is_array($changes) || !$changes) {
                  echo '<span class="muted">' . $e($row['action']) . '</span>';
              } else {
                  $parts = [];
                  foreach ($changes as $field => $pair) {
                      $parts[] = '<b>' . $e($field) . '</b>: '
                          . '<s>' . $e((string) ($pair['было'] ?? '')) . '</s> → '
                          . $e((string) ($pair['стало'] ?? ''));
                  }
                  echo implode('<br>', $parts);
              }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php // Удаление отдельной формой, ниже всего остального и вне основной
      // формы: кнопка «убрать с сайта» рядом с «сохранить» рано или
      // поздно нажимается по ошибке. ?>
<form class="pane pane--danger" method="post"
      action="<?= $e($adminPath('catalog/' . $product['id'] . '/delete')) ?>"
      data-confirm="Убрать «<?= $e($product['title']) ?>» с сайта?">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <h2>Убрать с сайта</h2>
  <p class="note">Карточка пропадёт с витрины и из этого списка. Адрес останется
     занят и будет вести в раздел — ссылки из выдачи и рекламы не превратятся
     в «страница не найдена». Заявки, где товар заказывали, останутся как есть.</p>
  <button class="btn" type="submit">Убрать товар с сайта</button>
</form>
