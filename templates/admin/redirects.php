<?php
use Sklad\Core\View;
$e = [View::class, 'e'];
?>
<div class="head"><h1>Адреса <span class="head__count"><?= (int) $total ?></span></h1></div>

<p class="note">На старых адресах висят ссылки из выдачи, из рекламы и из чужих
   статей. Каждая четырёхсотка — потерянный посетитель, который уже искал именно вас.</p>

<?php if ($misses): ?>
  <div class="pane" style="margin-bottom:1.25rem">
    <h2>Заходят и не находят</h2>
    <p class="note">Готовый список того, что стоит закрыть переадресацией.</p>
    <table class="grid">
      <thead><tr><th>Адрес</th><th class="w-state">Заходов</th><th class="w-price">Последний</th><th class="w-cat"></th></tr></thead>
      <tbody>
        <?php foreach ($misses as $row): ?>
          <tr>
            <td class="art"><?= $e($row['path']) ?></td>
            <td class="w-state num"><?= (int) $row['hits'] ?></td>
            <td class="w-price num"><?= $e(View::date($row['last_at'], 'd.m.Y H:i')) ?></td>
            <td class="w-cat">
              <form method="post" action="<?= $e($adminPath('redirect/forget')) ?>" style="display:inline">
                <input type="hidden" name="_token" value="<?= $e($token) ?>">
                <input type="hidden" name="path" value="<?= $e($row['path']) ?>">
                <button class="btn" type="submit">Забыть</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<form class="pane" method="post" action="<?= $e($adminPath('redirect/add')) ?>" style="margin-bottom:1.25rem">
  <h2>Добавить переадресацию</h2>
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <div class="row">
    <div style="flex:1"><label class="lbl" for="from_path">Со старого адреса</label>
      <input class="val art" id="from_path" name="from_path" placeholder="/staryy-adres" required></div>
    <div style="flex:1"><label class="lbl" for="to_path">На новый</label>
      <input class="val art" id="to_path" name="to_path" placeholder="/shtabelery/cddk20" required></div>
  </div>
  <div><button class="btn btn--primary" type="submit">Добавить</button></div>
</form>

<form class="filters" method="get" action="<?= $e($adminPath('redirect')) ?>">
  <input class="filters__search" type="search" name="q" value="<?= $e($query) ?>" placeholder="Поиск по адресу">
  <button class="btn" type="submit">Найти</button>
</form>

<?php if (!$rows): ?>
  <p class="empty">Переадресаций пока нет.</p>
<?php else: ?>
<table class="grid">
  <thead><tr><th>Откуда</th><th>Куда</th><th class="w-state">Переходов</th><th class="w-cat">Кем</th><th class="w-state"></th></tr></thead>
  <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td class="art"><?= $e($row['from_path']) ?></td>
        <td class="art"><a href="<?= $e($row['to_path']) ?>" target="_blank" rel="noopener"><?= $e($row['to_path']) ?></a></td>
        <td class="w-state num"><?= (int) $row['hits'] ?></td>
        <td class="w-cat sub"><?= $row['is_auto'] ? 'при переименовании' : 'вручную' ?></td>
        <td class="w-state">
          <?php // Формой, а не ссылкой: правило переадресации сносится
                // одним переходом, и открытая где-то чужая страница
                // могла бы сделать это за администратора. С вопросом:
                // список идёт по числу переходов, поэтому первой строкой
                // стоит самое посещаемое правило, а рядом в такой же
                // колонке — безобидное «Забыть». Восстановить удалённое
                // правило нечем, поэтому в вопросе адрес и переходы —
                // чтобы было видно, что именно сносится. ?>
          <form method="post" action="<?= $e($adminPath('redirect/' . $row['id'] . '/remove')) ?>"
                data-confirm="Удалить переадресацию с «<?= $e($row['from_path']) ?>»? Переходов по ней: <?= (int) $row['hits'] ?>. Дальше эти посетители будут попадать на «страница не найдена».">
            <input type="hidden" name="_token" value="<?= $e($token) ?>">
            <button class="btn" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
      <?php if ($n === $page): ?><span class="pager__on"><?= $n ?></span>
      <?php else: ?><a href="<?= $e($adminPath('redirect') . '?page=' . $n . ($query !== '' ? '&q=' . urlencode($query) : '')) ?>"><?= $n ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>