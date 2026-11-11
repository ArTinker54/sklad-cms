<?php

declare(strict_types=1);

// Только из командной строки.
//
// Если веб-корень окажется не в public/, а в корне проекта — на дешёвом
// хостинге так бывает, — эта папка станет доступна из браузера. Тогда
// обычный запрос к migrate.php --fresh снесёт базу, а smoke.php выдаст
// зашедшему сессию администратора: он для проверки экранов подставляет
// её сам.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}


/**
 * Прогон экранов админки.
 *
 *   php bin/smoke.php
 *
 * Отрисовывает каждый экран с настоящими данными и говорит, сложился он
 * или нет. Нужен потому, что ошибка в шаблоне видна только когда экран
 * открыли: PHP разбирает файл шаблона в момент отрисовки, и опечатка в
 * редко открываемом разделе может пролежать незамеченной неделями.
 *
 * Гоняется при каждом запуске контейнера — сбой сразу виден в журнале.
 */

$root = dirname(__DIR__);
require $root . '/src/Core/App.php';
Sklad\Core\App::registerAutoload($root);

use Sklad\Core\App;

$app = new App($root);

// Экраны админки требуют входа. В командной строке сессии нет, поэтому
// подставляем администратора напрямую — иначе проверять было бы нечего.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$adminId = $app->db->value("SELECT id FROM user WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
if ($adminId === null) {
    fwrite(STDERR, "[smoke] администратора нет, проверять некому\n");
    exit(1);
}
$_SESSION['user_id'] = (int) $adminId;

$screens = [
    'сводка' => [Sklad\Admin\DashboardController::class, 'index', []],
    'товары' => [Sklad\Admin\ProductController::class, 'index', []],
    'товары: поиск' => [Sklad\Admin\ProductController::class, 'index', ['q' => 'CDDK20']],
    'товары: без цены' => [Sklad\Admin\ProductController::class, 'index', ['gap' => 'price']],
    'заявки' => [Sklad\Admin\RequestController::class, 'index', []],
    'заявки: новые' => [Sklad\Admin\RequestController::class, 'index', ['status' => 'new']],
    'новый товар' => [Sklad\Admin\ProductController::class, 'create', []],
    'разделы' => [Sklad\Admin\CategoryController::class, 'index', []],
    'страницы' => [Sklad\Admin\ContentController::class, 'pages', []],
    'настройки' => [Sklad\Admin\SettingController::class, 'index', []],
    'поиск' => [Sklad\Admin\SeoController::class, 'index', []],
    'адреса' => [Sklad\Admin\RedirectController::class, 'index', []],
    'люди' => [Sklad\Admin\UserController::class, 'index', []],
];

// Карточку товара проверяем на настоящем: там больше всего мест, где
// шаблон может споткнуться о пустое поле.
$sampleProduct = $app->db->value('SELECT id FROM product WHERE deleted_at IS NULL ORDER BY id LIMIT 1');
if ($sampleProduct !== null) {
    $screens['карточка товара'] = [Sklad\Admin\ProductController::class, 'edit', []];
}
$sampleCategory = $app->db->value('SELECT id FROM category ORDER BY id LIMIT 1');
if ($sampleCategory !== null) {
    $screens['карточка раздела'] = [Sklad\Admin\CategoryController::class, 'edit', []];
}
$samplePage = $app->db->value('SELECT id FROM page ORDER BY id LIMIT 1');
if ($samplePage !== null) {
    $screens['карточка страницы'] = [Sklad\Admin\ContentController::class, 'editPage', []];
}

$failed = 0;
$checked = 0;

// ---------------------------------------------------------------------------
// Витрина
//
// Проверяется тем же прогоном и по той же причине: страница раздела с
// пустым фильтром или карточка товара без единой характеристики ломаются
// не при выкладке, а когда на них зайдут.
// ---------------------------------------------------------------------------
$site = [];

$site['главная'] = static fn (App $app): string => (new Sklad\Site\PageController($app))->home();

$sampleCat = $app->db->one("SELECT * FROM category WHERE is_published = 1 ORDER BY id LIMIT 1");
if ($sampleCat !== null) {
    $site['раздел'] = static fn (App $app): string => (new Sklad\Site\CatalogController($app))->category($sampleCat);
}

$sampleGoods = $app->db->one(
    'SELECT * FROM product WHERE is_published = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1'
);
if ($sampleGoods !== null) {
    $site['карточка'] = static fn (App $app): string => (new Sklad\Site\CatalogController($app))->product($sampleGoods);
}

foreach ($app->db->all('SELECT * FROM page WHERE is_published = 1 ORDER BY sort, id') as $row) {
    if ($row['path'] === '/') {
        continue;                                  // главная уже в списке
    }
    $site['страница ' . $row['path']] = static fn (App $app): string
        => (new Sklad\Site\PageController($app))->render($row);
}

foreach ($site as $name => $render) {
    $checked++;
    $_GET = [];
    $_SERVER['REQUEST_URI'] = '/';
    try {
        $html = $render(new App($root));
        $length = strlen($html);
        if ($length < 500) {
            printf("[smoke] %-24s подозрительно короткий ответ: %d знаков\n", $name, $length);
            $failed++;
        } else {
            printf("[smoke] %-24s ок, %d знаков\n", $name, $length);
        }
    } catch (Throwable $e) {
        printf(
            "[smoke] %-24s СБОЙ: %s @ %s:%d\n",
            $name,
            $e->getMessage(),
            basename($e->getFile()),
            $e->getLine()
        );
        $failed++;
    }
}

foreach ($screens as $name => [$class, $method, $query]) {
    $checked++;
    $_GET = $query;
    try {
        // Приложение пересоздаём на каждый экран: контроллер читает
        // сессию в конструкторе, и общее состояние тут только мешает.
        $controller = new $class(new App($root));
        $id = match ($name) {
            'карточка товара' => ['id' => (int) $sampleProduct],
            'карточка раздела' => ['id' => (int) $sampleCategory],
            'карточка страницы' => ['id' => (int) $samplePage],
            default => [],
        };
        $html = $controller->{$method}($id);
        $length = strlen((string) $html);

        if ($length < 500) {
            printf("[smoke] %-24s подозрительно короткий ответ: %d знаков\n", $name, $length);
            $failed++;
        } else {
            printf("[smoke] %-24s ок, %d знаков\n", $name, $length);
        }
    } catch (Throwable $e) {
        printf("[smoke] %-24s СБОЙ: %s @ %s:%d\n", $name, $e->getMessage(), basename($e->getFile()), $e->getLine());
        $failed++;
    }
}

printf("[smoke] проверено %d, сбоев %d\n", $checked, $failed);

exit($failed > 0 ? 1 : 0);
