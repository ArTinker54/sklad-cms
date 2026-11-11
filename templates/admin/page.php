<?php
use Sklad\Core\View;
$e = [View::class, 'e'];
?>
<div class="head">
  <h1><?= $e($page['title']) ?></h1>
  <a class="btn" href="<?= $e($page['path']) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
  <a class="btn" href="<?= $e($adminPath('page')) ?>">К списку</a>
</div>

<?php if ($page['deleted_at'] !== null): ?>
  <p class="flash flash--bad">Страница убрана с сайта. Правки сохранятся, но показываться она не будет, пока не вернёте — кнопка внизу страницы.</p>
<?php endif; ?>

<form method="post" action="<?= $e($adminPath('page/' . $page['id'])) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <div class="two">
    <div class="pane">
      <h2>Страница</h2>
      <div><label class="lbl" for="title">Название</label>
        <input class="val" id="title" name="title" value="<?= $e($page['title']) ?>" required></div>
      <div><label class="lbl" for="slug">Адрес</label>
        <input class="val art" id="slug" name="slug" value="<?= $e($page['slug']) ?>">
        <p class="note">Сейчас: <span class="art"><?= $e($page['path']) ?></span>. При смене
           со старого адреса встанет переадресация.</p></div>
      <div><label class="lbl" for="body">Текст</label>
        <textarea class="val" id="body" name="body" rows="16" data-editor><?= $e($page['body']) ?></textarea></div>
    </div>

    <div class="pane">
      <h2>Показ</h2>
      <label class="check"><input type="checkbox" name="is_published" value="1"<?= $page['is_published'] ? ' checked' : '' ?>>
        <span>Показывать на сайте</span></label>
      <label class="check"><input type="checkbox" name="is_indexable" value="1"<?= $page['is_indexable'] ? ' checked' : '' ?>>
        <span>Пускать в поиск</span></label>
      <p class="note">Корзину, сравнение и избранное в поиск не пускают: их содержимое
         появляется только из памяти браузера, роботу там смотреть нечего.</p>

      <div style="width:9rem"><label class="lbl" for="sort">Место</label>
        <input class="val num" type="number" id="sort" name="sort" min="0" value="<?= (int) $page['sort'] ?>"></div>

      <h2>Title и Description</h2>
      <?php // Как на витрине (PageController): пустой Title — название
            // страницы с припиской из настроек, у главной — название сайта. ?>
      <?= $view->render('admin/_seo', [
            'row' => $page,
            'autoTitle' => \Sklad\Model\Meta::withSuffix(
              $page['path'] === '/' ? (string) $settings->get('site_name') : (string) $page['title'],
              (string) $settings->get('meta_title_suffix')
            ),
            'autoDescription' => '',
            'url' => $app->siteUrl((string) $page['path']),
            'emptyNote' => $page['path'] === '/'
              ? 'Пусто — Яндекс соберёт текст под ссылкой сам, у главной обычно из шапки: город, меню, телефон.'
              : 'Пусто — поисковик соберёт текст под ссылкой сам, со страницы.',
          ]) ?>
    </div>
  </div>
  <?= $view->render('admin/_city-content', [
        'row' => $page, 'cities' => $cities, 'cityContent' => $cityContent,
      ]) ?>

  <div class="save-bar"><button class="btn btn--primary" type="submit">Сохранить</button></div>
</form>
<?php if ($page['deleted_at'] !== null): ?>
  <form class="pane pane--danger" method="post"
        action="<?= $e($adminPath('page/' . $page['id'] . '/restore')) ?>">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <h2>Страница убрана</h2>
    <p class="note">Убрана <?= $e(View::date($page['deleted_at'], 'd.m.Y в H:i')) ?>.
       На сайте её нет, адрес ведёт на главную. Вернуть можно в любой момент —
       страница появится скрытой.</p>
    <button class="btn btn--primary" type="submit">Вернуть страницу</button>
  </form>
<?php // Служебные страницы — корзину, сравнение, поиск, контакты —
      // убрать нельзя: на них ведут кнопки сайта. ?>
<?php elseif ($canDelete): ?>
  <form class="pane pane--danger" method="post"
        action="<?= $e($adminPath('page/' . $page['id'] . '/delete')) ?>"
        data-confirm="Убрать страницу «<?= $e($page['title']) ?>»?">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <h2>Убрать страницу</h2>
    <p class="note">Страница пропадёт с сайта. Адрес останется занят и будет вести
       на главную — ссылки на неё не превратятся в «страница не найдена».</p>
    <button class="btn" type="submit">Убрать страницу</button>
  </form>
<?php endif; ?>
