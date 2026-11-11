<?php

use Sklad\Core\View;

/**
 * Порядок характеристик.
 *
 * @var array  $rows   характеристики с номером и числом заполненных товаров
 * @var string $pinned ключ характеристики, закреплённой первой
 */
$e = [View::class, 'e'];
?>

<div class="head">
  <h1>Характеристики <span class="head__count"><?= count($rows) ?></span></h1>
  <a class="btn" href="<?= $e($adminPath('catalog')) ?>">← К товарам</a>
</div>

<p class="note" style="margin-top:0">
  Номер задаёт порядок: <b>меньше — выше</b>. В этом же порядке характеристики идут
  на плитке в каталоге, в карточке товара и в таблице характеристик.
  Заводятся и удаляются они вместе с каталогом — здесь только порядок.
</p>

<form method="post" action="<?= $e($adminPath('specs')) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">

  <div class="card" style="margin-bottom:20px">
    <label class="lbl" for="pinned">Всегда первой</label>
    <input class="val" id="pinned" name="pinned" value="<?= $e($pinned) ?>" style="max-width:280px">
    <p class="note">Ключ характеристики, которая показывается первой всегда — даже если
       в разделе она у всех одинаковая. По умолчанию <b>capacity</b> (грузоподъёмность).</p>
  </div>

  <table class="grid">
    <thead>
      <tr>
        <th style="width:90px">Порядок</th>
        <th>Характеристика</th>
        <th style="width:150px">Ключ</th>
        <th style="width:150px">Группа</th>
        <th style="width:110px">Заполнено</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr<?= $row['key'] === $pinned ? ' class="is-pinned"' : '' ?>>
          <td>
            <input class="val num" type="number" name="sort[<?= (int) $row['id'] ?>]"
                   value="<?= (int) $row['sort'] ?>" min="0" style="width:80px">
          </td>
          <td>
            <b><?= $e($row['title']) ?></b><?= $row['unit'] !== '' ? ', ' . $e($row['unit']) : '' ?>
            <?php if ($row['key'] === $pinned): ?>
              <span class="tag">всегда первая</span>
            <?php endif; ?>
          </td>
          <td><span class="grid__path"><?= $e($row['key']) ?></span></td>
          <td><?= $e($row['group'] !== '' ? $row['group'] : '—') ?></td>
          <td class="<?= (int) $row['filled'] === 0 ? 'muted' : '' ?>">
            <?= (int) $row['filled'] ?> тов.
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p style="margin-top:20px">
    <button class="btn btn--primary" type="submit">Сохранить порядок</button>
  </p>
</form>
