<?php
use Sklad\Core\View;
$e = [View::class, 'e'];
?>
<div class="head">
  <h1><?= $deleted ? 'Убранные страницы' : 'Страницы' ?>
    <span class="head__count"><?= count($rows) ?></span></h1>
  <?php if ($deleted): ?>
    <a class="btn" href="<?= $e($adminPath('page')) ?>">К страницам</a>
  <?php elseif ($deletedCount > 0): ?>
    <a class="btn" href="<?= $e($adminPath('page')) ?>?show=deleted">Убранные (<?= (int) $deletedCount ?>)</a>
  <?php endif; ?>
</div>

<form class="pane pane--inline" method="post" action="<?= $e($adminPath('page/new')) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <div class="row">
    <div style="flex:3">
      <label class="lbl" for="new-page">Новая страница</label>
      <input class="val" id="new-page" name="title" placeholder="Например: Лизинг и рассрочка" required>
    </div>
    <div style="align-self:end">
      <button class="btn btn--primary" type="submit">Завести</button>
    </div>
  </div>
  <p class="note">Заведётся текстовой и скрытой. Адрес соберётся из названия,
     потом его можно поправить в карточке.</p>
</form>

<table class="grid">
  <thead><tr><th>Название</th><th class="w-cat">Адрес</th><th class="w-state">В поиске</th><th class="w-state"></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $row): ?>
      <tr<?= $row['is_published'] ? '' : ' class="is-hidden"' ?>>
        <td><a class="grid__title" href="<?= $e($adminPath('page/' . $row['id'])) ?>"><?= $e($row['title']) ?></a></td>
        <td class="w-cat art"><?= $e($row['path']) ?></td>
        <td class="w-state"><?= $row['is_indexable'] ? 'да' : '<span class="muted">нет</span>' ?></td>
        <td class="w-state">
          <?php if ($deleted): ?>
            <form method="post" action="<?= $e($adminPath('page/' . $row['id'] . '/restore')) ?>">
              <input type="hidden" name="_token" value="<?= $e($token) ?>">
              <button class="btn" type="submit">Вернуть</button>
            </form>
          <?php elseif (!$row['is_published']): ?>
            <span class="tag tag--off">скрыта</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Блоки текста</h2>
<p class="note">Куски, которые живут вне страниц: подпись в подвале, реквизиты,
   оговорка про цены. Меняются чаще всего.</p>

<table class="grid">
  <thead><tr><th>Блок</th><th>Где показывается</th></tr></thead>
  <tbody>
    <?php foreach ($blocks as $row): ?>
      <tr>
        <td><a class="grid__title" href="<?= $e($adminPath('block/' . $row['key'])) ?>"><?= $e($row['title']) ?></a></td>
        <td class="sub"><?= $e($row['hint']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>