<?php

use Sklad\Core\View;

$e = [View::class, 'e'];

$link = static function (array $override) use ($adminPath, $status, $query): string {
    $args = array_filter(['status' => $status, 'q' => $query], static fn ($v): bool => $v !== '');
    $args = array_filter(array_merge($args, $override), static fn ($v): bool => $v !== '' && $v !== null);

    return $adminPath('request') . ($args ? '?' . http_build_query($args) : '');
};
?>

<div class="head">
  <h1>Заявки <span class="head__count"><?= (int) $total ?></span></h1>
  <?php // Ссылка несёт тот же отбор, что и экран: и состояние, и поиск. ?>
  <?php $filter = array_filter(['status' => $status, 'q' => $query]); ?>
  <a class="btn" href="<?= $e($adminPath('request/export') . ($filter ? '?' . http_build_query($filter) : '')) ?>">
    Выгрузить в Excel
  </a>
</div>

<form class="filters" method="get" action="<?= $e($adminPath('request')) ?>">
  <input class="filters__search" type="search" name="q" value="<?= $e($query) ?>"
         placeholder="Номер, имя, телефон, почта или компания">
  <?php if ($status !== ''): ?>
    <input type="hidden" name="status" value="<?= $e($status) ?>">
  <?php endif; ?>
  <button class="btn" type="submit">Найти</button>
</form>

<div class="chips">
  <?php foreach ($states as $key => $label): ?>
    <?php $count = (int) ($counts[$key] ?? 0); ?>
    <a class="chip<?= $status === $key ? ' chip--on' : '' ?>"
       href="<?= $e($link(['status' => $status === $key ? null : $key, 'page' => null])) ?>">
      <?= $e($label) ?> <b><?= $count ?></b>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <p class="empty">
    <?php if ($total === 0 && $query === '' && $status === ''): ?>
      Заявок пока нет. Они появятся здесь, как только кто-то отправит форму на сайте.
    <?php else: ?>
      Под эти условия ничего не нашлось.
    <?php endif; ?>
  </p>
<?php else: ?>
<table class="grid">
  <thead>
    <tr><th class="w-article">Номер</th><th>Кто</th><th>Что нужно</th><th class="w-price">Когда</th><th class="w-state">Состояние</th></tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td class="w-article"><a href="<?= $e($adminPath('request/' . $row['id'])) ?>"><?= $e($row['number']) ?></a></td>
        <td>
          <span class="grid__title"><?= $e($row['name']) ?></span>
          <span class="grid__path"><?= $e($row['phone']) ?><?= $row['company'] !== '' ? ' · ' . $e($row['company']) : '' ?></span>
        </td>
        <td>
          <?php if ((int) $row['items'] > 0): ?>
            Корзина, позиций <?= (int) $row['items'] ?>
            <?php if ($row['items_total'] !== null): ?>
              · <span class="num"><?= $e(View::price($row['items_total'])) ?></span>
            <?php endif; ?>
          <?php else: ?>
            <span class="muted"><?= $e($row['type']) ?></span>
          <?php endif; ?>
        </td>
        <td class="w-price"><?= $e(View::date($row['created_at'], 'd.m.Y H:i')) ?></td>
        <td class="w-state">
          <span class="tag tag--<?= $e($row['status']) ?>"><?= $e($states[$row['status']] ?? $row['status']) ?></span>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
      <?php if ($n === $page): ?><span class="pager__on"><?= $n ?></span>
      <?php else: ?><a href="<?= $e($link(['page' => $n])) ?>"><?= $n ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>
