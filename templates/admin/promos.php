<?php

use Sklad\Core\View;

/**
 * Акции — витрина товаров со скидкой и форма назначения.
 *
 * @var array  $active     товары в акции (старая цена выше текущей)
 * @var array  $candidates товары с ценой, ещё не в акции
 * @var string $today      Y-m-d, для отметки истёкших и min у даты
 */
$e = [View::class, 'e'];
?>

<div class="head">
  <h1>Акции <span class="head__count"><?= count($active) ?></span></h1>
  <a class="btn" href="<?= $e($adminPath('catalog')) ?>">← К товарам</a>
</div>

<p class="note" style="margin-top:0">
  Ставите товару <b>цену по акции — ниже обычной</b>. Обычная цена становится зачёркнутой,
  загорается метка «Акция», и товар сам попадает на страницу
  <a href="/akcii/" target="_blank" rel="noopener">/akcii/</a>. Кнопка «Снять» вернёт обычную цену.
</p>

<?php // Форма назначения. Без товаров с ценой ставить нечего — тогда
      // вместо формы подсказываем, с чего начать. ?>
<?php if ($candidates): ?>
  <form class="card" method="post" action="<?= $e($adminPath('promos')) ?>" style="margin-bottom:24px">
    <input type="hidden" name="_token" value="<?= $e($token) ?>">
    <div class="row" style="align-items:flex-end">
      <div style="flex:2;min-width:240px">
        <label class="lbl" for="product_id">Товар</label>
        <select class="val" id="product_id" name="product_id" required>
          <option value="">— выберите товар с ценой —</option>
          <?php foreach ($candidates as $c): ?>
            <option value="<?= (int) $c['id'] ?>">
              <?= $e($c['title']) ?> — <?= $e(View::price($c['price'])) ?><?= $c['category_title']
                ? ' · ' . $e($c['category_title']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1;min-width:130px">
        <label class="lbl" for="sale_price">Цена по акции, ₽</label>
        <input class="val num" id="sale_price" name="sale_price" inputmode="decimal"
               placeholder="ниже обычной" required>
      </div>
      <div style="flex:1;min-width:130px">
        <label class="lbl" for="promo_until">Акция до</label>
        <input class="val" type="date" id="promo_until" name="promo_until" min="<?= $e($today) ?>">
      </div>
      <div>
        <button class="btn btn--primary" type="submit">В акцию</button>
      </div>
    </div>
    <p class="note">Цена по акции должна быть ниже обычной — это и есть скидка.
       «Акция до» необязательна: без даты акция бессрочная.</p>
  </form>
<?php else: ?>
  <p class="empty">Пока нет товаров с ценой — акцию не от чего считать.
     Проставьте цены в разделе <a href="<?= $e($adminPath('catalog')) ?>">Товары</a>, потом возвращайтесь.</p>
<?php endif; ?>

<?php if (!$active): ?>
  <p class="empty">Сейчас ни одной акции нет.<?= $candidates ? ' Поставьте первую формой выше.' : '' ?></p>
<?php else: ?>
<table class="grid">
  <thead>
    <tr>
      <th class="w-photo"></th>
      <th>Товар</th>
      <th class="w-price">Цена</th>
      <th class="w-price">Старая</th>
      <th class="w-price">Скидка</th>
      <th>Акция до</th>
      <th class="w-state"></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($active as $row): ?>
      <?php
      $price = (float) $row['price'];
      $old = (float) $row['old_price'];
      $save = $old - $price;
      $percent = $old > 0 ? (int) round($save / $old * 100) : 0;
      $until = trim((string) ($row['promo_until'] ?? ''));
      $expired = $until !== '' && !str_starts_with($until, '0000') && $until < $today;
      ?>
      <tr<?= $expired ? ' class="is-hidden"' : '' ?>>
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
        <td class="w-price"><b><?= $e(View::price($price)) ?></b></td>
        <td class="w-price"><s class="muted"><?= $e(View::price($old)) ?></s></td>
        <td class="w-price"><b><?= $e(View::price($save)) ?></b> <span class="muted">−<?= $percent ?>%</span></td>
        <td>
          <?php if ($until === '' || str_starts_with($until, '0000')): ?>
            <span class="muted">бессрочно</span>
          <?php elseif ($expired): ?>
            <span class="tag tag--off">истекла <?= $e(View::date($until)) ?></span>
          <?php else: ?>
            <?= $e(View::date($until)) ?>
          <?php endif; ?>
        </td>
        <td class="w-state">
          <form method="post" action="<?= $e($adminPath('promos/' . $row['id'] . '/remove')) ?>">
            <input type="hidden" name="_token" value="<?= $e($token) ?>">
            <button class="btn" type="submit">Снять</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
