<?php

use Sklad\Core\View;

$e = [View::class, 'e'];

$byGroup = [];
foreach ($options as $option) {
    $byGroup[$option['group'] !== '' ? $option['group'] : 'Прочее'][] = $option;
}
?>

<div class="head">
  <h1><?= $e($category['title']) ?></h1>
  <a class="btn" href="<?= $e($category['path']) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
  <a class="btn" href="<?= $e($adminPath('category')) ?>">К списку</a>
</div>

<?php if ($category['deleted_at'] !== null): ?>
  <p class="flash flash--bad">Раздел убран с сайта. Правки сохранятся, но показываться он не будет, пока не вернёте — кнопка внизу страницы.</p>
<?php endif; ?>

<form method="post" action="<?= $e($adminPath('category/' . $category['id'])) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">

  <div class="two">
    <div class="pane">
      <h2>Раздел</h2>

      <div>
        <label class="lbl" for="title">Название — в меню и хлебных крошках</label>
        <input class="val" id="title" name="title" value="<?= $e($category['title']) ?>" required>
      </div>

      <div>
        <label class="lbl" for="h1">Заголовок на странице</label>
        <input class="val" id="h1" name="h1" value="<?= $e($category['h1']) ?>"
               placeholder="<?= $e($category['title']) ?>">
        <p class="note">Пусто — возьмётся название. Разные нужны, когда в меню
           коротко, а на странице подробно.</p>
      </div>

      <div class="row">
        <div style="flex:1">
          <label class="lbl" for="parent_id">Внутри раздела</label>
          <select class="val" id="parent_id" name="parent_id">
            <option value="">— верхний уровень —</option>
            <?php // Убранный родитель — только тот, внутри которого раздел
                  // уже лежит, и только помеченным. Выбрать убранный заново
                  // нельзя: внутри него раздел и товары пропадают с витрины.
                  // Но и подменять его молча «верхним уровнем» нельзя —
                  // «Сохранить» переставило бы раздел со всем содержимым. ?>
            <?php if ($parentGone !== null): ?>
              <option value="<?= (int) $parentGone['id'] ?>" selected>
                <?= $e($parentGone['title'] . ' — убран с сайта') ?>
              </option>
            <?php endif; ?>
            <?php foreach ($parents as $row): ?>
              <option value="<?= (int) $row['id'] ?>"<?= (int) $category['parent_id'] === (int) $row['id'] ? ' selected' : '' ?>>
                <?= $e(str_repeat('— ', max(0, substr_count(trim($row['path'], '/'), '/'))) . $row['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($parentGone !== null): ?>
            <p class="note">Раздел лежит внутри убранного «<?= $e($parentGone['title']) ?>» — на сайте
               не видно ни его, ни товаров внутри, даже с галочкой «Показывать на сайте».
               Выберите живой раздел или верните убранный.</p>
          <?php endif; ?>
        </div>
        <div style="width:9rem">
          <label class="lbl" for="sort">Место</label>
          <input class="val num" type="number" id="sort" name="sort" min="0" value="<?= (int) $category['sort'] ?>">
        </div>
      </div>

      <div>
        <label class="lbl" for="slug">Адрес</label>
        <input class="val art" id="slug" name="slug" value="<?= $e($category['slug']) ?>">
        <p class="note">Сейчас: <span class="art"><?= $e($category['path']) ?></span>.
           <?php if ($goods > 0): ?>
             Смена адреса или родителя переставит и <b><?= (int) $goods ?></b> товаров внутри —
             на каждый старый адрес встанет переадресация.
           <?php endif; ?>
        </p>
      </div>

      <div>
        <label class="lbl" for="intro">Текст над списком товаров</label>
        <textarea class="val" id="intro" name="intro" rows="3"><?= $e($category['intro']) ?></textarea>
      </div>

      <div>
        <label class="lbl" for="body">Текст под списком</label>
        <textarea class="val" id="body" name="body" rows="6" data-editor><?= $e($category['body']) ?></textarea>
      </div>

      <div>
        <label class="lbl" for="tags">Популярные запросы</label>
        <textarea class="val art" id="tags" name="tags" rows="5"
                  placeholder="Название запроса | /razdel/podrazdel/"><?= $e($category['tags'] ?? '') ?></textarea>
        <p class="note">Ссылки под плитками подразделов. По строке на запрос: подпись,
           вертикальная черта, адрес на этом сайте. Адрес пишется от корня, со
           слэша: <code>/razdel/podrazdel/</code> — полный, с https://, не подойдёт.
           Решётку добавлять не нужно — её ставит сайт. Больше 12 строк и строки
           без черты при сохранении отбрасываются. Если адреса на сайте нет,
           строка останется в поле, но на странице не покажется — об этом скажем
           при сохранении. Список действует и на вложенные разделы, пока у них
           пусто, — вписывать его в каждый не нужно.</p>
      </div>

      <label class="check">
        <input type="checkbox" name="is_published" value="1"<?= $category['is_published'] ? ' checked' : '' ?>>
        <span>Показывать на сайте</span>
      </label>
    </div>

    <div class="pane">
      <h2>Title и Description</h2>
      <?php // Как на витрине (CatalogController): пустой Title — заголовок
            // раздела на странице (h1), а без него — название, с припиской. ?>
      <?= $view->render('admin/_seo', [
            'row' => $category,
            'autoTitle' => \Sklad\Model\Meta::withSuffix(
              (string) (($category['h1'] ?? '') !== '' ? $category['h1'] : $category['title']),
              (string) $settings->get('meta_title_suffix')
            ),
            'autoDescription' => '',
            'url' => $app->siteUrl((string) ($category['path'] ?? '')),
          ]) ?>

      <h2>Мета-теги товаров раздела</h2>
      <p class="note">Заполняются у всех товаров внутри разом. Собственное значение
         товара, если оно есть, шаблон не трогает. Подстановки: <span class="art">{название}</span>,
         <span class="art">{артикул}</span>, <span class="art">{раздел}</span>,
         <span class="art">{цена}</span> и любая характеристика по ключу —
         <span class="art">{capacity}</span> даст число, <span class="art">{capacity_unit}</span>
         — число с единицей.</p>
      <div><label class="lbl" for="product_title_tpl">Шаблон заголовка окна</label>
        <input class="val" id="product_title_tpl" name="product_title_tpl"
               value="<?= $e($category['product_title_tpl']) ?>"
               placeholder="{название} — купить, цена"></div>
      <div><label class="lbl" for="product_description_tpl">Шаблон описания</label>
        <textarea class="val" id="product_description_tpl" name="product_description_tpl" rows="3"
                  placeholder="{название}: грузоподъёмность {capacity_unit}. Официальный дилер, гарантия 12 месяцев."><?= $e($category['product_description_tpl']) ?></textarea></div>

      <label class="check">
        <input type="checkbox" name="is_indexable" value="1"<?= $category['is_indexable'] ? ' checked' : '' ?>>
        <span>Пускать раздел в поиск</span>
      </label>

      <h2>Характеристики в подборе</h2>
      <p class="note">Отмеченные показываются покупателю в фильтре этого раздела.
         У тележек нет ёмкости батареи — и пустой фильтр там читается как поломка.</p>

      <?php foreach ($byGroup as $group => $list): ?>
        <h3 class="group"><?= $e($group) ?></h3>
        <?php foreach ($list as $option): ?>
          <label class="check">
            <input type="checkbox" name="option[]" value="<?= (int) $option['id'] ?>"<?= $option['выбрана'] ? ' checked' : '' ?>>
            <span><?= $e($option['title']) ?><?= $option['unit'] !== '' ? ', ' . $e($option['unit']) : '' ?></span>
          </label>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <?= $view->render('admin/_city-content', [
        'row' => $category, 'cities' => $cities, 'cityContent' => $cityContent,
      ]) ?>

  <div class="save-bar">
    <button class="btn btn--primary" type="submit">Сохранить</button>
  </div>
</form>
<?php // Убранному разделу предлагать удаление незачем — ему нужен возврат.
      // Кнопка, которая ничего не делает, хуже отсутствующей: по ней жмут
      // и решают, что сломалось. ?>
<?php if ($category['deleted_at'] !== null): ?>
  <form class="pane pane--danger" method="post"
        action="<?= $e($adminPath('category/' . $category['id'] . '/restore')) ?>">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <h2>Раздел убран</h2>
    <p class="note">Убран <?= $e(View::date($category['deleted_at'], 'd.m.Y в H:i')) ?>.
       На сайте его нет, адрес ведёт в раздел выше. Вернуть можно в любой момент —
       раздел появится скрытым.</p>
    <button class="btn btn--primary" type="submit">Вернуть раздел</button>
  </form>
<?php else: ?>
  <?php // Ниже всего и вне основной формы: кнопка «убрать» рядом с
        // «сохранить» рано или поздно нажимается по ошибке. ?>
  <form class="pane pane--danger" method="post"
        action="<?= $e($adminPath('category/' . $category['id'] . '/delete')) ?>"
        data-confirm="Убрать раздел «<?= $e($category['title']) ?>»?">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <h2>Убрать раздел</h2>
    <p class="note">Раздел пропадёт с сайта и из этого списка. Адрес останется занят и
       будет вести в раздел выше — ссылки из выдачи и рекламы не превратятся в
       «страница не найдена». Раздел с товарами или подразделами убрать нельзя:
       сначала перенесите то, что внутри.</p>
    <button class="btn" type="submit">Убрать раздел</button>
  </form>
<?php endif; ?>
