<?php
use Sklad\Core\View;
$e = [View::class, 'e'];
?>
<div class="head"><h1>Поиск</h1></div>

<p class="note">Здесь видно, где мета-теги не заполнены, где повторяются и где
   длина такая, что в выдаче обрежется. Сами поля правятся в карточках товара
   и раздела.</p>

<div class="tiles" style="margin-bottom:1.25rem">
  <?php foreach ($counts as $label => $count): ?>
    <div class="tile<?= $count ? '' : ' tile--clear' ?>">
      <b><?= (int) $count ?></b><span><?= $e($label) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($templateless): ?>
  <div class="pane" style="margin-bottom:1.25rem">
    <h2>Разделы без шаблона мета-тегов</h2>
    <p class="note">У товаров этих разделов заголовок собирается из названия —
       для выдачи это слабо. Шаблон задаётся в карточке раздела и применяется
       ко всем товарам внутри разом.</p>
    <table class="grid">
      <thead><tr><th>Раздел</th><th class="w-state">Товаров</th></tr></thead>
      <tbody>
        <?php foreach ($templateless as $row): ?>
          <tr>
            <td><a class="grid__title" href="<?= $e($adminPath('category/' . $row['id'])) ?>"><?= $e($row['title']) ?></a>
                <span class="grid__path"><?= $e($row['path']) ?></span></td>
            <td class="w-state num"><?= (int) $row['goods'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php
$blocks = [
    'Повторяющиеся заголовки окна' => [$duplicateMeta, 'Несколько страниц с одним title ослабляют друг друга: поисковик не понимает, какую показывать.'],
    'Повторяющиеся названия товаров' => [$duplicateTitles, 'Название идёт в заголовок страницы, если своего заголовка нет. Плюс покупатель не отличит их в списке.'],
];
foreach ($blocks as $heading => [$rows, $why]):
?>
  <div class="pane" style="margin-bottom:1.25rem">
    <h2><?= $e($heading) ?></h2>
    <?php if (!$rows): ?>
      <p class="note">Повторов нет.</p>
    <?php else: ?>
      <p class="note"><?= $e($why) ?></p>
      <table class="grid">
        <thead><tr><th>Значение</th><th class="w-state">Страниц</th><th class="w-cat"></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= $e($row['value']) ?></td>
              <td class="w-state num"><?= (int) $row['goods'] ?></td>
              <td class="w-cat">
                <?php foreach (array_slice(explode(',', (string) $row['ids']), 0, 6) as $id): ?>
                  <a class="art" href="<?= $e($adminPath('catalog/' . (int) $id)) ?>">#<?= (int) $id ?></a>
                <?php endforeach; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endforeach; ?>