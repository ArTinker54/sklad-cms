<?php

use Sklad\Core\View;

$e = [View::class, 'e'];
?>

<div class="head">
  <h1><?= $deleted ? 'Убранные разделы' : 'Разделы' ?> <span class="head__count"><?= count($rows) ?></span></h1>
  <?php if ($deleted): ?>
    <a class="btn" href="<?= $e($adminPath('category')) ?>">К разделам</a>
  <?php elseif ($deletedCount > 0): ?>
    <a class="btn" href="<?= $e($adminPath('category')) ?>?show=deleted">Убранные (<?= (int) $deletedCount ?>)</a>
  <?php endif; ?>
</div>

<?php // Заводим прямо отсюда, одним полем: остальное заполняется в
      // карточке, куда и попадаем сразу после создания. ?>
<form class="pane pane--inline" method="post" action="<?= $e($adminPath('category/new')) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <div class="row">
    <div style="flex:2">
      <label class="lbl" for="new-title">Новый раздел</label>
      <input class="val" id="new-title" name="title" placeholder="Например: Ричтраки" required>
    </div>
    <div style="flex:2">
      <label class="lbl" for="new-parent">Внутри раздела</label>
      <select class="val" id="new-parent" name="parent_id">
        <option value="">— верхний уровень —</option>
        <?php foreach ($rows as $row): ?>
          <option value="<?= (int) $row['id'] ?>">
            <?= $e(str_repeat('— ', max(0, substr_count(trim($row['path'], '/'), '/'))) . $row['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="align-self:end">
      <button class="btn btn--primary" type="submit">Завести</button>
    </div>
  </div>
  <p class="note">Новый раздел сразу не показывается на сайте — включите его в карточке,
     когда заполните название и текст.</p>
</form>

<table class="grid">
  <thead><tr><th>Название</th><th class="w-cat">Адрес</th><th class="w-state">Товаров</th><th class="w-state"></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $row): ?>
      <?php $depth = max(0, substr_count(trim($row['path'], '/'), '/')); ?>
      <tr<?= $row['is_published'] ? '' : ' class="is-hidden"' ?>>
        <td>
          <span style="padding-left: <?= $depth * 1.5 ?>rem">
            <a class="grid__title" href="<?= $e($adminPath('category/' . $row['id'])) ?>"><?= $e($row['title']) ?></a>
          </span>
        </td>
        <td class="w-cat art"><?= $e($row['path']) ?></td>
        <td class="w-state num"><?= (int) $row['goods'] ?></td>
        <td class="w-state">
          <?php if ($deleted): ?>
            <form method="post" action="<?= $e($adminPath('category/' . $row['id'] . '/restore')) ?>">
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