<?php

use Sklad\Core\View;

/** @var string $content */
/** @var array|null $user */
/** @var callable $adminPath */
/** @var array|null $flash */
$e = [View::class, 'e'];

/*
 * Разделы админки. Третьим значением — готов ли раздел.
 *
 * Незаконченные показываем серым и без ссылки. Ссылка на несуществующий
 * раздел выбрасывает на страницу «не найдено», и это читается как
 * поломка всей админки, а не как «ещё не сделано».
 */
$sections = [
    ['catalog', 'catalog', 'Товары', true],
    ['catalog', 'promos', 'Акции', true],
    ['catalog', 'category', 'Разделы', true],
    ['catalog', 'specs', 'Характеристики', true],
    ['catalog', 'media', 'Фотографии', true],
    ['requests', 'request', 'Заявки', true],
    ['content', 'page', 'Страницы', true],
    ['content', 'seo', 'Поиск', true],
    ['redirects', 'redirect', 'Адреса', true],
    ['settings', 'setting', 'Настройки', true],
    ['users', 'user', 'Люди', true],
];

// Заявки читают в почте: письмо приходит таблицей и с фотографией во
// вложении, отвечают прямо из него, а в админку за этим не заходят. Где
// так заведено — вкладку убирают настройкой (Роман, 01.09).
//
// Убираем именно из списка, а не признаком готовности: с ним раздел не
// исчезает, а становится серой надписью «Раздел готовится» — читалось бы
// как поломка.
//
// Сама заявка при этом продолжает записываться, и страница её остаётся
// доступной по прямой ссылке. Отключать хранение нельзя: почта на дешёвом
// хостинге отваливается молча, и без записи обращение пропало бы вместе с
// телефоном человека.
if (!$app->settings->flag('show_requests')) {
    $sections = array_values(array_filter(
        $sections,
        static fn (array $s): bool => $s[1] !== 'request'
    ));
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title ?? 'Админка') ?></title>
<link rel="stylesheet" href="/admin.css">
</head>
<body>

<header class="top">
  <a class="top__logo" href="<?= $e($adminPath()) ?>">Каталог</a>
  <nav class="top__nav">
    <?php foreach ($sections as [$right, $url, $label, $ready]): ?>
      <?php if (!$app->auth->can($right)) { continue; } ?>
      <?php if ($ready): ?>
        <a href="<?= $e($adminPath($url)) ?>"><?= $e($label) ?></a>
      <?php else: ?>
        <span class="top__soon" title="Раздел готовится"><?= $e($label) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="top__user">
    <span><?= $e($user['name'] ?? '') ?></span>
    <form method="post" action="<?= $e($adminPath('logout')) ?>">
      <input type="hidden" name="_token" value="<?= $e($token) ?>">
      <button type="submit">Выйти</button>
    </form>
  </div>
</header>

<main class="wrap">
  <?php if ($flash): ?>
    <p class="flash flash--<?= $e($flash['kind']) ?>"><?= $e($flash['text']) ?></p>
  <?php endif; ?>

  <?= $content ?>
</main>

<?php // Редактор подключается на всех экранах, а включается только там,
      // где есть поле с пометкой data-editor. Отдельного списка экранов
      // не заводим: забыть дописать в него новый экран легче, чем
      // заметить лишние три килобайта. ?>
<script src="/assets/admin/editor.js"></script>
<script src="/assets/admin/seo.js"></script>

</body>
</html>
