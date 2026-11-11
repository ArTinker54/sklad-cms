<?php

declare(strict_types=1);

/**
 * Список адресов.
 *
 * Здесь только заданные кодом: админка и служебные адреса витрины.
 * Товары, разделы и страницы сюда не попадают — их адреса лежат в базе
 * целиком, и подбирать их правилами незачем. Ими занимается Resolver,
 * когда ни один маршрут не подошёл.
 *
 * Пара [класс, метод] вместо замыкания: из списка сразу видно, куда
 * идти читать.
 *
 * @var Sklad\Core\App $app
 */

use Sklad\Admin\AuthController;
use Sklad\Admin\DashboardController;
use Sklad\Admin\ProductController;
use Sklad\Admin\PromoController;
use Sklad\Admin\SpecController;
use Sklad\Admin\RequestController;
use Sklad\Admin\CategoryController;
use Sklad\Admin\ContentController;
use Sklad\Admin\SettingController;
use Sklad\Admin\RedirectController;
use Sklad\Admin\UserController;
use Sklad\Admin\SeoController;

$admin = rtrim($app->config['admin_path'] ?? '/admin', '/');

// ---------------------------------------------------------------------------
// Админка
// ---------------------------------------------------------------------------
$app->router->get($admin . '/login', [AuthController::class, 'form']);
$app->router->post($admin . '/login', [AuthController::class, 'login']);
$app->router->post($admin . '/logout', [AuthController::class, 'logout']);

$app->router->get($admin, [DashboardController::class, 'index']);

$app->router->get($admin . '/catalog', [ProductController::class, 'index']);
// Карточка товара. Загрузка и удаление фотографий отдельными адресами:
// они меняют данные и не должны уезжать вместе с формой полей.
// Создание раньше карточки: иначе адрес /catalog/new попал бы в шаблон
// /catalog/{id} и был бы понят как номер товара.
$app->router->get($admin . '/catalog/new', [ProductController::class, 'create']);
$app->router->post($admin . '/catalog/new', [ProductController::class, 'store']);
// Порядок товаров списком. Тоже раньше карточки: иначе «order» попал бы
// в шаблон /catalog/{id} и был бы понят как номер товара.
$app->router->post($admin . '/catalog/order', [ProductController::class, 'saveOrder']);
$app->router->get($admin . '/catalog/{id}', [ProductController::class, 'edit']);
$app->router->post($admin . '/catalog/{id}', [ProductController::class, 'save']);
$app->router->post($admin . '/catalog/{id}/image', [ProductController::class, 'uploadImage']);
$app->router->post($admin . '/catalog/{id}/image/{image}/remove', [ProductController::class, 'removeImage']);
$app->router->post($admin . '/catalog/{id}/delete', [ProductController::class, 'delete']);
$app->router->post($admin . '/catalog/{id}/restore', [ProductController::class, 'restore']);

// Акции — та же цена товара, отдельной витриной: список скидок и
// назначение/снятие акции без захода в карточку.
$app->router->get($admin . '/promos', [PromoController::class, 'index']);
$app->router->post($admin . '/promos', [PromoController::class, 'assign']);
$app->router->post($admin . '/promos/{id}/remove', [PromoController::class, 'remove']);

// Порядок характеристик — тем же порядком они идут в плитке, в карточке
// и в таблице: витрина сортирует по option.sort.
$app->router->get($admin . '/specs', [SpecController::class, 'index']);
$app->router->post($admin . '/specs', [SpecController::class, 'save']);
// Прикрепление уже загруженного снимка. Отдельным адресом от загрузки:
// файл не передаётся, только номер записи в библиотеке.
$app->router->post($admin . '/catalog/{id}/image/attach', [Sklad\Admin\MediaController::class, 'attach']);
$app->router->get($admin . '/media', [Sklad\Admin\MediaController::class, 'index']);
$app->router->post($admin . '/media/{id}/delete', [Sklad\Admin\MediaController::class, 'delete']);

// Выгрузка идёт раньше карточки: иначе /request/export попал бы в
// шаблон /request/{id} и был бы понят как номер заявки.
$app->router->get($admin . '/request/export', [RequestController::class, 'export']);
$app->router->get($admin . '/request', [RequestController::class, 'index']);
$app->router->get($admin . '/request/{id}', [RequestController::class, 'show']);
$app->router->post($admin . '/request/{id}', [RequestController::class, 'save']);

// ---------------------------------------------------------------------------
// Разделы, страницы, настройки, адреса, люди
//
// Действия с побочным эффектом — удаление, добавление — идут через POST
// или отдельным адресом: ссылку, которая что-то меняет, может открыть
// поисковый робот, обходя админку по чужой ссылке.
// ---------------------------------------------------------------------------
$app->router->get($admin . '/category', [CategoryController::class, 'index']);
// Создание раньше карточки: иначе /category/new попал бы в шаблон
// /category/{id} и был бы понят как номер раздела.
$app->router->post($admin . '/category/new', [CategoryController::class, 'create']);
$app->router->get($admin . '/category/{id}', [CategoryController::class, 'edit']);
$app->router->post($admin . '/category/{id}', [CategoryController::class, 'save']);
$app->router->post($admin . '/category/{id}/delete', [CategoryController::class, 'delete']);
$app->router->post($admin . '/category/{id}/restore', [CategoryController::class, 'restore']);

$app->router->get($admin . '/page', [ContentController::class, 'pages']);
$app->router->post($admin . '/page/new', [ContentController::class, 'createPage']);
$app->router->get($admin . '/page/{id}', [ContentController::class, 'editPage']);
$app->router->post($admin . '/page/{id}', [ContentController::class, 'savePage']);
$app->router->post($admin . '/page/{id}/delete', [ContentController::class, 'deletePage']);
$app->router->post($admin . '/page/{id}/restore', [ContentController::class, 'restorePage']);
$app->router->get($admin . '/block/{key}', [ContentController::class, 'editBlock']);
$app->router->post($admin . '/block/{key}', [ContentController::class, 'saveBlock']);

$app->router->get($admin . '/setting', [SettingController::class, 'index']);
$app->router->post($admin . '/setting', [SettingController::class, 'save']);

$app->router->get($admin . '/redirect', [RedirectController::class, 'index']);
$app->router->post($admin . '/redirect/add', [RedirectController::class, 'add']);
$app->router->post($admin . '/redirect/forget', [RedirectController::class, 'forget']);
$app->router->post($admin . '/redirect/{id}/remove', [RedirectController::class, 'remove']);

$app->router->get($admin . '/user', [UserController::class, 'index']);
$app->router->post($admin . '/user', [UserController::class, 'save']);
$app->router->get($admin . '/seo', [SeoController::class, 'index']);

// ---------------------------------------------------------------------------
// Файлы для поисковиков
//
// Отдаются запросом, а не лежат на диске: каталог меняется в админке
// каждый день, и файл пришлось бы пересобирать руками.
// ---------------------------------------------------------------------------
$app->router->get('/robots.txt', [Sklad\Site\FeedController::class, 'robots']);
$app->router->get('/sitemap.xml', [Sklad\Site\FeedController::class, 'sitemap']);
$app->router->get('/feed.yml', [Sklad\Site\FeedController::class, 'yml']);

// ---------------------------------------------------------------------------
// Витрина
//
// products.json — один индекс на поиск, сравнение, избранное и подбор.
// Собирается из базы на каждый запрос: файл означал бы, что цена живёт в
// двух местах, и правка в админке доезжала бы не везде.
// ---------------------------------------------------------------------------
$app->router->get('/products.json', [Sklad\Site\ApiController::class, 'products']);
$app->router->post('/zayavka', [Sklad\Site\RequestController::class, 'store']);