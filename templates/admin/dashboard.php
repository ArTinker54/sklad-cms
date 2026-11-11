<?php

use Sklad\Core\View;

$e = [View::class, 'e'];
?>
<h1>Сводка</h1>

<div class="cards">
  <div class="card"><b><?= (int) $counts['products'] ?></b><span>товаров</span></div>
  <div class="card"><b><?= (int) $counts['categories'] ?></b><span>разделов</span></div>
  <div class="card"><b><?= (int) $counts['pages'] ?></b><span>страниц</span></div>
  <?php if ($requests !== null): ?>
    <div class="card card--accent"><b><?= (int) $requests['new'] ?></b><span>новых заявок</span></div>
  <?php endif; ?>
</div>

<?php if ($checks !== null): ?>
  <?php // Проверки настройки сайта — см. DashboardController::siteChecks().
        // Стоят первыми: пропадающие письма о заявках важнее счётчиков. ?>
  <h2>Проверки сайта</h2>
  <?php if ($checks): ?>
    <ul class="checks">
      <?php foreach ($checks as $c): ?>
        <li class="check check--<?= $e($c['level']) ?>">
          <?= $e($c['text']) ?>
          <?php if ($c['fix'] !== ''): ?>
            <span class="check__fix"><?php if ($c['link'] !== null): ?><a href="<?= $e($c['link']) ?>"><?= $e($c['fix']) ?></a><?php else: ?><?= $e($c['fix']) ?><?php endif; ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="note">Все проверки пройдены: заявки уходят на почту, SPF в порядке, описания для выдачи заполнены.</p>
  <?php endif; ?>
<?php endif; ?>

<?php if ($attention): ?>
  <h2>Требует внимания</h2>
  <ul class="attention">
    <?php foreach ($attention as $label => $count): ?>
      <li<?= $count ? '' : ' class="done"' ?>>
        <b><?= (int) $count ?></b> <?= $e($label) ?>
        <?php if (!$count): ?><span class="note">— всё заполнено</span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($requests !== null && $requests['last']): ?>
  <h2>Последние заявки</h2>
  <table class="grid">
    <thead><tr><th>Номер</th><th>Имя</th><th>Телефон</th><th>Когда</th><th>Состояние</th></tr></thead>
    <tbody>
      <?php foreach ($requests['last'] as $row): ?>
        <tr>
          <td><a href="<?= $e($adminPath('request/' . $row['id'])) ?>"><?= $e($row['number']) ?></a></td>
          <td><?= $e($row['name']) ?></td>
          <td><?= $e($row['phone']) ?></td>
          <td><?= $e(View::date($row['created_at'], 'd.m.Y H:i')) ?></td>
          <td><?= $e($row['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($notFound): ?>
  <h2>Заходят на несуществующие адреса</h2>
  <p class="note">Обычно это ссылки из выдачи или из рекламы на страницы,
     которые переименовали. Каждую стоит закрыть редиректом.</p>
  <table class="grid">
    <thead><tr><th>Адрес</th><th>Обращений</th><th>Последнее</th></tr></thead>
    <tbody>
      <?php foreach ($notFound as $row): ?>
        <tr>
          <td><?= $e($row['path']) ?></td>
          <td><?= (int) $row['hits'] ?></td>
          <td><?= $e(View::date($row['last_at'], 'd.m.Y H:i')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
