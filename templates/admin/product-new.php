<?php
use Sklad\Core\View;
$e = [View::class, 'e'];
?>
<div class="head">
  <h1>Новый товар</h1>
  <a class="btn" href="<?= $e($adminPath('catalog')) ?>">Отмена</a>
</div>

<form class="pane" method="post" action="<?= $e($adminPath('catalog/new')) ?>" style="max-width:40rem">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">

  <div>
    <label class="lbl" for="title">Название</label>
    <input class="val" id="title" name="title" required autofocus
           placeholder="Штабелёр CDDK20 2000 кг">
  </div>

  <div>
    <label class="lbl" for="category_id">Раздел</label>
    <select class="val" id="category_id" name="category_id" required>
      <option value="">— выберите —</option>
      <?php foreach ($categories as $row): ?>
        <option value="<?= (int) $row['id'] ?>">
          <?= $e(str_repeat('— ', max(0, substr_count(trim($row['path'], '/'), '/'))) . $row['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p class="note">От раздела зависит адрес страницы и набор характеристик
       в карточке. Потом его можно поменять — со старого адреса встанет
       переадресация.</p>
  </div>

  <div><button class="btn btn--primary" type="submit">Завести</button></div>
  <p class="note">Товар появится скрытым: пока нет цены и фотографии, показывать
     его покупателю нечего. Откроете на витрине галочкой в карточке.</p>
</form>