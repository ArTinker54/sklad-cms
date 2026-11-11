<?php

use Sklad\Core\View;

/**
 * Обвязка всех страниц витрины.
 *
 * @var string $content
 * @var array  $meta   заголовок, описание, канонический адрес, запрет индексации
 */
$e = [View::class, 'e'];
$s = $settings;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($meta['title']) ?></title>
<meta name="description" content="<?= $e($meta['description']) ?>">

<?php if (!empty($meta['noindex'])): ?>
  <meta name="robots" content="noindex, follow">
<?php else: ?>
  <link rel="canonical" href="<?= $e($meta['canonical']) ?>">
<?php endif; ?>

<meta property="og:type" content="<?= $e($meta['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= $e($meta['title']) ?>">
<meta property="og:description" content="<?= $e($meta['description']) ?>">
<meta property="og:url" content="<?= $e($meta['canonical']) ?>">
<?php if (!empty($meta['image'])): ?>
  <meta property="og:image" content="<?= $e($meta['image']) ?>">
<?php endif; ?>
<meta property="og:locale" content="ru_RU">

<?php
// Версия к стилям по времени изменения файла. Без неё браузер держит
// старый CSS после правки, и клиент на превью видит вчерашний вид,
// пока не сбросит кэш руками. С меткой имя меняется — берётся свежий.
// Правило одно на весь движок: App::asset(). Прежнее имя оставлено,
// чтобы не править полсотни мест в разметке.
$cssVer = static fn (string $path): string => $app->asset($path);
?>
<?php // Значок вкладки — знак Brand, как на прежнем сайте. В файле три слоя
      // (16, 32, 48): один крупный браузер ужимал бы сам и мазал, а размеры
      // перечислены, иначе он считает, что в файле только один из них.
      // Метка версии здесь особенно нужна: значок браузер держит в памяти
      // дольше всего остального и без смены имени показывал бы старый. ?>
<link rel="icon" href="<?= $e($cssVer('/assets/img/favicon.ico')) ?>" sizes="16x16 32x32 48x48">
<link rel="apple-touch-icon" href="<?= $e($cssVer('/assets/img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= $e($cssVer('/assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= $e($cssVer('/assets/css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= $e($cssVer('/assets/css/main.css')) ?>">
<link rel="stylesheet" href="<?= $e($cssVer('/assets/css/extra.css')) ?>">
</head>
<?php // Формулировка согласия и адрес политики — в data-атрибутах: окна
      // заявок и форму запчастей рисует request.js, и взять их ему больше
      // неоткуда. Своей копии текста в скрипте быть не должно: в журнал
      // согласий сервер пишет значение из настроек, и после правки в
      // админке запись переставала совпадать с тем, что видел человек. ?>
<?php // По той же причине здесь и пример модели для формы запроса
      // запчастей: в скрипте он был вписан как «Brand CDD15B» и стоял в
      // подсказке у всех сайтов сети, включая те, где такой техники нет.
      // Марку и модель знает сервер — отдаём готовой строкой, а нечем
      // заполнить, отдаём пустую: скрипт тогда обойдётся без примера.
      $modelHint = \Sklad\Model\SearchHint::example($app->db, $s);
      $modelHint = $modelHint === '' ? '' : trim($s->get('brand') . ' ' . $modelHint); ?>
<body data-consent-wording="<?= $e($s->get('consent_wording')) ?>"
      data-policy-url="<?= $e($s->get('policy_url', '/politika-konfidencialnosti/')) ?>"
      data-model-hint="<?= $e($modelHint) ?>">

<a class="visually-hidden" href="#main">Перейти к содержимому</a>

<?php // Пример модели в подсказке — из каталога этого сайта, а не вписанный
      // сюда: шаблон один на всю сеть, и вписанная модель Brand стояла в
      // подсказке у Партнёра, Tisel и Hangcha, где такой техники нет. Как
      // выбирается — см. SearchHint. Считаем один раз: подсказка нужна двум
      // полям сразу — в полосе поиска и в панели меню на телефоне, — а
      // SearchHint ходит в базу. ?>
<?php $searchHint = \Sklad\Model\SearchHint::text($app->db, $s, 'Модель, код или тип техники'); ?>
<?php // position:relative у шапки переехал из инлайнового стиля в main.css
      // 22.09.2026 и остался там: правило видно рядом с остальной шапкой, а
      // инлайновый стиль было не перебить из таблицы. Шапка — опора для
      // .searchbar: полоса поиска раскрывается поверх неё. ?>
<header class="header">
  <div class="searchbar" data-open="false">
    <div class="container searchbar__row" style="position:relative">
      <input type="search" placeholder="<?= $e($searchHint) ?>"
             aria-label="Поиск по каталогу" autocomplete="off">
      <button class="searchbar__close" type="button" aria-label="Закрыть поиск">✕</button>
      <ul class="suggest" data-open="false" role="listbox" aria-label="Подсказки"></ul>
    </div>
  </div>
  <div class="container header__bar">
    <a class="header__logo" href="/" aria-label="На главную">
      <?php // В подписи к знаку — марка, а не юрлицо: на логотипе написана она.
            //
            // Размеры берём у самого файла, а не пишем числами. Тема одна на
            // всю сеть, а знаки у марок разной формы: у Brand вытянутый, у TOR
            // почти квадратный. С чужими числами браузер занимал под знак
            // 92 точки, а после загрузки тот оказывался 48 — и шапка дёргалась
            // на каждом заходе. getimagesize читает только заголовок файла.
            $logoPath = $app->root() . '/public/assets/img/logo-light.png';
            $logoSize = is_file($logoPath) ? @getimagesize($logoPath) : false; ?>
      <?php // Метка версии нужна знаку не меньше, чем стилям: имя файла у
            // всей сети одно, и у того, кто открывал этот адрес под прежней
            // маркой, браузер месяц показывал бы чужой знак в шапке. ?>
      <img src="<?= $e($cssVer('/assets/img/logo-light.png')) ?>"
           alt="<?= $e($s->get('brand') ?: $s->get('site_name')) ?>"
           <?php if ($logoSize): ?>width="<?= (int) $logoSize[0] ?>" height="<?= (int) $logoSize[1] ?>"<?php endif; ?>>
    </a>
    <span class="header__tagline">Официальный дилер<br>складской техники в России</span>

    <?php // Город — в шапке на каждой странице: покупателю важно знать, в
          // какой регион считается доставка, а поисковику — что у площадки
          // есть городская версия. Список открывается окном. ?>
    <?php // В шапке — только название города со стрелкой вниз. Подпись
          // «Выбрать другой город» стояла второй строкой и в узкой полосе
          // ломалась на три, наезжая на меню. Что это выбор, показывает
          // стрелка; для чтения с голоса название расшифровано в aria-label. ?>
    <button class="header__city" type="button" data-city-open aria-haspopup="dialog"
            aria-label="Ваш город: <?= $e($city['name'] ?? 'Москва') ?>. Выбрать другой">
      <?= $e($city['name'] ?? 'Москва') ?>
      <svg viewBox="0 0 10 6" aria-hidden="true"><path d="M1.5 1.5 5 4.5 8.5 1.5"/></svg>
    </button>

    <nav class="header__nav" aria-label="Основное меню">
      <a href="/katalog/">Каталог</a>
      <a href="/akcii/">Акции</a>
      <a href="/o-brende/">О компании</a>
      <a href="/kontakty/">Контакты</a>
    </nav>

    <?php if ($s->get('phone') !== ''): ?>
      <a class="header__phone" href="tel:<?= $e($s->get('phone_href')) ?>"><?= $e($s->get('phone')) ?></a>
    <?php endif; ?>
    <?php // Почта — только на узком экране, вторым рядом под телефоном
          // (main.css, до 767). В широкой полосе её показывать негде: там
          // уже восемь элементов в ряд, и девятый выдавливает меню. ?>
    <?php if ($s->get('email') !== ''): ?>
      <a class="header__mail" href="mailto:<?= $e($s->get('email')) ?>"><?= $e($s->get('email')) ?></a>
    <?php endif; ?>
    <?php if ($s->get('messenger_url') !== ''): ?>
      <a class="header__max" href="<?= $e($s->get('messenger_url')) ?>" target="_blank" rel="noopener"
         aria-label="Написать нам в <?= $e($s->get('messenger_title')) ?>">
        <img src="/assets/img/max.svg" alt="" width="18" height="18">
        <span>Написать в <?= $e($s->get('messenger_title')) ?></span>
      </a>
    <?php endif; ?>

    <?php // .header__actions — это правое крыло полосы на компьютере, а на
          // телефоне (main.css, до 767) — два нижних ряда шапки целиком:
          // полоски, поиск, значки, корзина и под ними «Оставить заявку».
          // Полоски лежат здесь, а не отдельным предметом полосы, именно
          // поэтому: снаружи они отняли бы у крыла 52 точки, и кнопка
          // заявки перестала бы тянуться во всю ширину экрана. ?>
    <div class="header__actions">
      <?php // Три полоски — как на b2b-store.example.ru. Это ссылка, а не кнопка:
            // без скрипта она уводит к панели меню, лежащей в конце страницы,
            // и разделы остаются достижимыми. Мёртвая кнопка «меню» хуже
            // длинной страницы. Со скриптом menu.js перехватывает переход и
            // превращает её в раскрывашку. На компьютере полосок нет: их
            // прячет main.css. ?>
      <a class="header__burger" href="#mobmenu" aria-controls="mobmenu" aria-expanded="false">
        <span class="visually-hidden">Меню</span>
        <span class="header__burger-bars" aria-hidden="true"></span>
      </a>
      <button class="header__search" type="button" aria-label="Поиск по каталогу">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5 21 21"/></svg>
      </button>
      <a class="header__icon" href="/izbrannoe/" data-fav-link hidden title="Избранное" aria-label="Избранное">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5 4.4 13a4.6 4.6 0 0 1 6.5-6.5l1.1 1.1 1.1-1.1A4.6 4.6 0 0 1 19.6 13Z"/></svg>
        <span class="header__badge" data-fav-count hidden>0</span>
      </a>
      <a class="header__icon" href="/sravnenie/" data-compare-link hidden title="Сравнение" aria-label="Сравнение">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V9M12 20V4M19 20v-7"/></svg>
        <span class="header__badge" data-compare-count hidden>0</span>
      </a>
      <a class="header__cart" href="/korzina/">Корзина
        <span data-cart-count aria-label="товаров в корзине" hidden>0</span></a>
      <button class="header__cta" type="button" data-action="request">Оставить заявку</button>
    </div>
  </div>
</header>

<?php if (!empty($crumbs)): ?>
  <div class="container">
    <nav class="crumbs" aria-label="Вы находитесь здесь">
      <ol itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($crumbs as $index => $crumb): ?>
          <?php if (!empty($crumb['url'])): ?>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
              <a itemprop="item" href="<?= $e($crumb['url']) ?>"><span itemprop="name"><?= $e($crumb['name']) ?></span></a>
              <meta itemprop="position" content="<?= $index + 1 ?>">
            </li>
          <?php else: ?>
            <?php // Текущая страница — тоже элемент цепочки, просто без ссылки.
                  // Без разметки на разделах верхнего уровня в цепочке
                  // оставался один пункт «Главная», и смысла в ней не было. ?>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"
                aria-current="page">
              <span itemprop="name"><?= $e($crumb['name']) ?></span>
              <meta itemprop="position" content="<?= $index + 1 ?>">
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </nav>
  </div>
<?php endif; ?>

<?php // Полосы «Ваш город — Калуга?» здесь больше нет. Она всплывала над
      // каждой страницей, в том числе после отправки заявки и при открытии
      // товара, и мешала (Роман, 01.09). Город выбирают сами — кнопкой в
      // шапке; определение по адресу посетителя убрано вместе с ней.
      //
      // Выбор города — окном со списком ссылок. Именно ссылками, а не
      // выпадающим списком: из него поисковик адреса не достаёт, и о
      // городских поддоменах не узнал бы. Москва — основной адрес, у неё
      // своего поддомена нет. ?>
<div class="modal" id="cityselect" hidden>
  <div class="modal__backdrop" data-city-close></div>
  <div class="modal__win modal__win--wide" role="dialog" aria-modal="true" aria-labelledby="cityselect-title">
    <button class="modal__close" type="button" data-city-close aria-label="Закрыть">✕</button>
    <div class="modal__body">
      <h2 id="cityselect-title">Выберите город</h2>
      <ul class="citylist">
        <li><a href="<?= $e($app->cityLink('')) ?>"<?= empty($city) ? ' class="is-on"' : '' ?>>Москва</a></li>
        <?php foreach (\Sklad\Model\City::all() as $item): ?>
          <?php if ($item['slug'] === 'moskva') { continue; } ?>
          <li><a href="<?= $e($app->cityLink($item['slug'])) ?>"<?= !empty($city) && $item['slug'] === $city['slug']
            ? ' class="is-on"' : '' ?>><?= $e($item['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<?= $content ?>

<footer class="footer">
  <div class="container">
    <p>© 2012–<?= date('Y') ?><?= ($who = $s->get('company') ?: $s->get('site_name')) !== ''
         ? ' ' . $e($who) . '.' : '' ?>
       <a href="/kontakty/">Контакты</a> ·
       <a href="<?= $e($s->get('policy_url')) ?>">Политика конфиденциальности</a></p>
    <?php // Телефон и почта в подвале появились 22.09.2026. Повод — шапка,
          // где номер тогда стал значком, — отпал в тот же день, а строка
          // осталась: она нужна ровно тому, кто дочитал страницу до низа и
          // решил звонить (шапка к этому мигу уже прокручена), и поисковику,
          // который сверяет телефон сайта с карточкой организации. ?>
    <?php if ($s->get('phone') !== '' || $s->get('email') !== ''): ?>
      <p class="footer__contacts">
        <?php if ($s->get('phone') !== ''): ?>
          <a href="tel:<?= $e($s->get('phone_href')) ?>"><?= $e($s->get('phone')) ?></a>
        <?php endif; ?>
        <?php if ($s->get('phone') !== '' && $s->get('email') !== ''): ?> · <?php endif; ?>
        <?php if ($s->get('email') !== ''): ?>
          <a href="mailto:<?= $e($s->get('email')) ?>"><?= $e($s->get('email')) ?></a>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <?php // ИНН и ОГРН в подвале — не формальность: по ним Яндекс сверяет
          // сайт с ЕГРЮЛ, а покупатель-юрлицо проверяет поставщика,
          // прежде чем просить счёт. ?>
    <?php if ($s->get('inn') !== ''): ?>
      <p class="footer__legal">ИНН <?= $e($s->get('inn')) ?><?= $s->get('ogrn') !== ''
        ? ' · ОГРН ' . $e($s->get('ogrn')) : '' ?><?= $s->get('kpp') !== ''
        ? ' · КПП ' . $e($s->get('kpp')) : '' ?></p>
    <?php endif; ?>
    <?php if (!empty($blocks['price_disclaimer'])): ?>
      <p class="disclaimer"><?= $e($blocks['price_disclaimer']) ?></p>
    <?php endif; ?>
    <?php // Оговорка про авторские права — рядом с оговоркой про цены, тем же
          // тоном: обе про правовую сторону и обе не для чтения первым делом.
          // Блок footer_note лежал в базе всех сайтов сети, но не печатался
          // нигде — на hangcha-rus.ru фраза в подвале есть, а на стенде её не
          // было. Пустой блок ничего не рисует: строка-призрак в подвале
          // читается как недоделка. ?>
    <?php if (!empty($blocks['footer_note'])): ?>
      <p class="disclaimer"><?= $e($blocks['footer_note']) ?></p>
    <?php endif; ?>
  </div>
</footer>

<?php // Панель меню на телефоне (три полоски в шапке).
      //
      // Лежит после подвала, а не внутри шапки, по двум причинам: шапка
      // красит себе фон и заводит свой слой — панель внутри неё не смогла
      // бы накрыть страницу целиком; и без скрипта блок в конце страницы —
      // естественная цель для ссылки «#mobmenu».
      //
      // Роль «диалог» навешивает menu.js: пока скрипта нет, это обычный
      // кусок страницы, и называть его диалогом было бы враньём для
      // диктора. По той же причине здесь нет и aria-modal. ?>
<?php // Панель под тремя полосками — один в один как у b2b-store.example.ru, по
      // просьбе заказчика 25.09.2026: светлая, сверху поиск-«таблетка»,
      // крестик и круглая кнопка звонка; блок со значками, блок страниц,
      // контакты и выбор города. Значки — те же файлы, что у storefront
      // (assets/template/img/ui/*.svg), у сети они лежат в assets/menu.
      //
      // Второй блок собирается из страниц, которые у сайта правда есть.
      // Адреса у сетевых сайтов разные: у Партнёра «О компании» живёт на
      // /o-kompanii/, у остальных — на /o-brende/, и вписанная раньше
      // ссылка у Партнёра вела в никуда.
      $menuPagesWanted = [
          '/akcii/' => 'Акции',
          '/o-brende/' => 'О компании',
          '/o-kompanii/' => 'О компании',
          '/dostavka-i-oplata/' => 'Доставка и оплата',
          '/dostavka/' => 'Доставка',
          '/oplata/' => 'Оплата',
          '/garantiya/' => 'Гарантия',
      ];
      $menuPagesHave = $app->db->column(
          'SELECT path FROM page WHERE path IN ('
          . implode(',', array_fill(0, count($menuPagesWanted), '?'))
          . ') AND is_published = 1 AND deleted_at IS NULL',
          array_keys($menuPagesWanted)
      );
      $menuPartsPath = trim((string) $s->get('parts_path', '')); ?>
<div class="mobmenu" id="mobmenu" data-open="false">
  <div class="mobmenu__backdrop" data-menu-close></div>
  <div class="mobmenu__win">
    <div class="mobmenu__top">
      <?php // Поиск — обычная форма на /poisk/, тем же адресом, что «Показать
            // все результаты» у лупы в шапке. Без скрипта страница поиска
            // пуста (её наполняет search-page.js), поэтому поле js-only. ?>
      <form class="mobmenu__search js-only" action="/poisk/" method="get" role="search">
        <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><circle cx="6.8" cy="6.8" r="5.3" fill="none" stroke="#333" stroke-width="1.6"/><path d="m10.8 10.8 4.2 4.2" stroke="#333" stroke-width="1.6" stroke-linecap="round"/></svg>
        <input type="search" name="s" autocomplete="off" aria-label="Поиск по каталогу">
      </form>
      <button class="mobmenu__close js-only" type="button" data-menu-close aria-label="Закрыть меню">
        <svg viewBox="0 0 18 18" width="18" height="18" aria-hidden="true"><path d="M1.5 1.5l15 15M16.5 1.5l-15 15" stroke="#2c2c2c" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
      <?php if ($s->get('phone') !== ''): ?>
        <a class="mobmenu__call" href="tel:<?= $e($s->get('phone_href')) ?>" aria-label="Позвонить: <?= $e($s->get('phone')) ?>">
          <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path fill="#fff" d="M6.6 10.8a15.2 15.2 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>
        </a>
      <?php endif; ?>
    </div>
    <div class="mobmenu__rule"></div>

    <div class="mobmenu__body">
      <ul class="mobmenu__list">
        <li><a href="/"><img src="<?= $e($cssVer('/assets/menu/home.svg')) ?>" alt="" width="20" height="20">Главная</a></li>
        <li><a href="/katalog/"><img src="<?= $e($cssVer('/assets/menu/catalog.svg')) ?>" alt="" width="20" height="20">Каталог</a></li>
        <?php if ($menuPartsPath !== ''): ?>
          <li><a href="<?= $e($menuPartsPath) ?>"><img src="<?= $e($cssVer('/assets/menu/parts.svg')) ?>" alt="" width="20" height="20">Запчасти</a></li>
        <?php endif; ?>
      </ul>
      <div class="mobmenu__rule"></div>

      <ul class="mobmenu__list">
        <?php foreach ($menuPagesWanted as $menuPath => $menuTitle): ?>
          <?php if (in_array($menuPath, $menuPagesHave, true)): ?>
            <li><a href="<?= $e($menuPath) ?>"><?= $e($menuTitle) ?></a></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
      <div class="mobmenu__rule"></div>

      <ul class="mobmenu__list">
        <li><a href="/kontakty/">Контакты</a></li>
        <?php if ($s->get('phone') !== ''): ?>
          <li><a href="tel:<?= $e($s->get('phone_href')) ?>"><img src="<?= $e($cssVer('/assets/menu/phone.svg')) ?>" alt="" width="20" height="20"><?= $e($s->get('phone')) ?></a></li>
        <?php endif; ?>
        <?php if ($s->get('email') !== ''): ?>
          <li><a href="mailto:<?= $e($s->get('email')) ?>"><img src="<?= $e($cssVer('/assets/menu/email.svg')) ?>" alt="" width="20" height="20"><?= $e($s->get('email')) ?></a></li>
        <?php endif; ?>
      </ul>

      <?php // Город — как у storefront: название цветом марки и под ним
            // «Выбрать другой город». Та же кнопка data-city-open, что в
            // шапке: окно городов откроется поверх панели. ?>
      <button class="mobmenu__city" type="button" data-city-open aria-haspopup="dialog">
        <span class="mobmenu__city-name"><?= $e($city['name'] ?? 'Москва') ?></span>
        <span class="mobmenu__city-pick">Выбрать другой город</span>
      </button>
    </div>
  </div>
</div>

<?php // Без скрипта панель — обычный блок в конце страницы, а три полоски —
      // ссылка на него. Крестик, подложка и всё, что работает только
      // скриптом (заявка, поиск), там не нужны: закрывать нечего, а
      // мёртвая кнопка читается как поломка.
      // style-src в нашей политике разрешает inline-стили (App::securityPolicy),
      // поэтому nonce здесь не нужен. ?>
<noscript><style>
@media (max-width: 767px) {
  .mobmenu { position: static; display: block; }
  .mobmenu__win { width: auto; height: auto; }
  .mobmenu__body { overflow: visible; }
  .mobmenu__backdrop, .mobmenu .js-only { display: none; }
}
</style></noscript>

<?php // Счётчик ставим только на настоящем сайте. Стенд живёт на чужом
      // домене и закрыт от поисковиков, но Метрику это не останавливает:
      // всё, что мы и заказчик натыкаем на стенде, ложилось в боевую
      // статистику — визиты, отказы, цели. isPublic() отличает боевой
      // сайт от стенда по той же настройке stage, что и noindex. ?>
<?php if ($app->isPublic() && $s->get('metrika_id') !== '' && ctype_digit($s->get('metrika_id'))): ?>
  <script nonce="<?= $e($app->nonce()) ?>">window.SKLAD_METRIKA_ID=<?= (int) $s->get('metrika_id') ?>;</script>
  <script nonce="<?= $e($app->nonce()) ?>">(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
  m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}
  k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
  (window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
  ym(<?= (int) $s->get('metrika_id') ?>,"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});</script>
  <noscript><div><img src="https://mc.yandex.ru/watch/<?= (int) $s->get('metrika_id') ?>"
       style="position:absolute;left:-9999px" alt=""></div></noscript>
<?php endif; ?>

<?php // Версия к скриптам — тем же ключом, что и к стилям: правку JS
      // браузер иначе держит закэшированной, и клиент видит старое. ?>
<?php // Выбор города: показать и спрятать окно. Отдельным файлом заводить
      // незачем — здесь весь код целиком. ?>
<script nonce="<?= $e($app->nonce()) ?>">
(function () {
  var box = document.getElementById('cityselect');
  if (!box) return;
  var lastFocus = null;
  function show(on) {
    // Окно объявлено aria-modal, но сам по себе этот атрибут фокус не
    // держит: с клавиатуры человек уходил табом на страницу под окном, а
    // после закрытия начинал обход шапки заново. Поэтому фокус переносим
    // внутрь и возвращаем на кнопку, которой окно открыли, — как это
    // сделано для окна заявки в request.js.
    if (on) { lastFocus = document.activeElement; }
    box.hidden = !on;
    document.body.style.overflow = on ? 'hidden' : '';
    if (on) {
      var first = box.querySelector('.citylist a');
      if (first) first.focus();
    } else if (lastFocus && lastFocus.focus) {
      lastFocus.focus();
    }
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-city-open]')) { e.preventDefault(); show(true); }
    else if (e.target.closest('[data-city-close]')) { show(false); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !box.hidden) show(false);
    // Пока окно открыто, Tab ходит по кругу внутри него.
    if (e.key === 'Tab' && !box.hidden) {
      var f = box.querySelectorAll('a[href], button');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* Определения города по адресу посетителя здесь больше нет.

     Вместе с полосой «Ваш город — Калуга?» убран и запрос к службе:
     без подсказки его ответ читать некому. Заодно ушли и печенье
     sklad_city, и обращение к ip-api.com на каждого нового посетителя —
     треть секунды на запрос и внешняя зависимость, которой теперь не
     нужно. Город выбирают кнопкой в шапке, переход идёт на поддомен, и
     дальше его помнит сам адрес страницы. */
})();
</script>

<script src="<?= $e($cssVer('/assets/js/images.js')) ?>"></script>
<script src="<?= $e($cssVer('/assets/js/cart.js')) ?>"></script>
<script src="<?= $e($cssVer('/assets/js/compare.js')) ?>"></script>
<script src="<?= $e($cssVer('/assets/js/favorites.js')) ?>"></script>
<script src="<?= $e($cssVer('/assets/js/search.js')) ?>"></script>
<?php // menu.js — строго перед request.js. Оба слушают щелчки на документе,
      // а слушатели срабатывают в порядке подписки: по кнопке «Оставить
      // заявку» в панели меню сначала закрывается панель, и только потом
      // открывается окно заявки. В обратном порядке панель закрывалась бы
      // поверх уже открытого окна и сняла бы его замок прокрутки. ?>
<script src="<?= $e($cssVer('/assets/js/menu.js')) ?>"></script>
<script src="<?= $e($cssVer('/assets/js/request.js')) ?>"></script>
<?php if (!empty($pageScripts)): ?>
  <?php foreach ($pageScripts as $script): ?>
    <script src="<?= $e($cssVer('/assets/js/' . $script)) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
