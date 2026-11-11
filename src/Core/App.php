<?php

declare(strict_types=1);

namespace Sklad\Core;

use RuntimeException;
use Throwable;

/**
 * Сборка приложения и обработка запроса.
 *
 * Ни контейнера, ни автоматического связывания: зависимостей семь штук,
 * и собрать их руками короче и понятнее, чем описывать правила сборки.
 */
final class App
{
    public array $config;
    public Db $db;
    public Auth $auth;
    public Router $router;
    public View $view;
    public Settings $settings;

    /**
     * Город регионального поддомена или null на основном адресе.
     *
     * @var array{slug:string, name:string, in:string, of:string}|null
     */
    public ?array $city = null;

    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');

        $configFile = $this->root . '/config/config.php';
        if (!is_file($configFile)) {
            // Не поднялись, потому что не установлены, — это разные вещи,
            // и говорить о них надо по-разному.
            // Установщик лежит выше веб-корня и по адресу недостижим —
            // звать туда бессмысленно. Говорим, что делать на самом деле.
            $this->fail(
                'Сайт не установлен: нет config/config.php. Скопируйте config.sample.php'
                . ' и заполните доступы к базе, либо запустите install/index.php из командной строки.',
                503
            );
        }

        $this->config = require $configFile;
        $this->db = new Db($this->config['db']);
        $this->auth = new Auth($this->db);
        $this->router = new Router();
        $this->settings = new Settings($this->db);

        // Город определяем по адресу, с которым пришли, а не по настройке:
        // так один и тот же код обслуживает и основной домен, и полсотни
        // региональных поддоменов, и подключать под каждый ничего не надо.
        $this->city = \Sklad\Model\City::fromHost($_SERVER['HTTP_HOST'] ?? null);

        $theme = $this->config['site']['theme'] ?? 'default';
        $this->view = new View($this->root . '/templates');
        $this->view->share('app', $this);
        $this->view->share('theme', $theme);
        $this->view->share('settings', $this->settings);
        $this->view->share('city', $this->city);
    }

    /**
     * Адрес площадки с учётом города.
     *
     * На региональном поддомене канонический адрес обязан вести на него же,
     * а не на основной домен: иначе поисковик считает страницу копией и в
     * городскую выдачу она не попадает — весь смысл поддоменов теряется.
     */
    public function cityUrl(string $path = ''): string
    {
        $base = rtrim($this->config['site']['url'] ?? '', '/');

        // Хост собираем из настройки и проверенного слага, а не из заголовка
        // запроса. Из заголовка сюда приходил любой домен, и «Host: evil.tld»
        // уводил туда canonical, og:url и все адреса в карте сайта — то есть
        // подменял поисковику адрес площадки.
        if ($this->city !== null) {
            $scheme = (string) (parse_url($base, PHP_URL_SCHEME) ?: 'https');
            $host = (string) parse_url($base, PHP_URL_HOST);
            if ($host !== '') {
                $base = $scheme . '://' . $this->city['slug'] . '.' . $host;
            }
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Адрес этой же страницы в другом городе.
     *
     * Меняем только первую часть имени: остальное — домен площадки, каким
     * бы он ни был. Поэтому переключатель работает и на боевом домене, и
     * на превью, где к имени добавлен ещё один уровень.
     *
     * Пустой ключ уводит на адрес без города — «вся Россия».
     *
     * Путь по умолчанию — текущая страница, а не корень: переключатель
     * обещает «этот же товар в другом городе», а уводил на главную, и
     * посетитель искал карточку заново. Строку запроса отбрасываем: там
     * живут метки рекламных кампаний и постраничная навигация, тащить их
     * на другой поддомен незачем.
     */
    public function cityLink(string $slug, ?string $path = null): string
    {
        if ($path === null) {
            $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        }

        $base = rtrim($this->config['site']['url'] ?? '', '/');
        $scheme = (string) (parse_url($base, PHP_URL_SCHEME) ?: 'https');

        // Голое имя площадки берём из настройки, а не из заголовка запроса.
        // Из заголовка приходило что угодно: с «www» получалось
        // kazan.www.example.ru, а подставленный чужой Host уводил все
        // семьдесят пять ссылок переключателя на чужой домен.
        $bare = (string) parse_url($base, PHP_URL_HOST);
        if ($bare === '') {
            $bare = (string) preg_replace('/^www\.|:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        }

        return $scheme . '://' . ($slug !== '' ? $slug . '.' : '') . $bare . '/' . ltrim($path, '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Адрес файла из папки public с меткой версии.
     *
     * Имена у всей сети одинаковые: logo-light.png, brand-hero.png,
     * favicon.ico. Пока сайт один, это неважно, но стенд живёт на чужом
     * освободившемся адресе, и у того, кто открывал его под прежней маркой,
     * браузер месяц будет показывать старый знак — файл он держит по
     * Cache-Control, а имя не поменялось. Метка времени файла снимает
     * вопрос и ничего не стоит: заголовок не читаем, только mtime.
     */
    public function asset(string $path): string
    {
        $mtime = @filemtime($this->root . '/public' . $path);

        return $path . ($mtime ? '?v=' . $mtime : '');
    }

    public function siteUrl(string $path = ''): string
    {
        return rtrim($this->config['site']['url'] ?? '', '/') . '/' . ltrim($path, '/');
    }

    public function adminPath(string $path = ''): string
    {
        return rtrim($this->config['admin_path'] ?? '/admin', '/') . '/' . ltrim($path, '/');
    }

    public function isDebug(): bool
    {
        return !empty($this->config['debug']);
    }

    /**
     * Разовый ключ для встроенных блоков страницы.
     *
     * Политика безопасности запрещает выполнять то, что написано прямо в
     * разметке: браузер не отличает наш счётчик от чужого кода, который
     * однажды туда пролезет. Ключ выдаётся на один ответ и ставится
     * только своим блокам — счётчику и карточке организации.
     *
     * Одно значение на весь ответ: страница отрисовывается один раз, и
     * разные ключи в одном ответе просто не дали бы ничему выполниться.
     */
    public function nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }

        return $nonce;
    }

    /**
     * Что странице разрешено загружать и выполнять.
     *
     * Списком разрешённого, а не запрещённого. Своё — со своего домена,
     * шрифты и счётчик — с названных, всё остальное браузер не пустит.
     * Встроенные блоки выполняются только со своим ключом.
     */
    public function securityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . $this->nonce() . "' https://mc.yandex.ru",
            // Оформление отчасти сидит в атрибуте style — вычистить его
            // из перенесённой вёрстки отдельная работа, и пока честнее
            // это признать, чем городить исключение и забыть про него.
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self'",
            "img-src 'self' data: https://mc.yandex.ru",
            // wss — это Вебвизор: он пишет сеанс через веб-сокет, а тот
            // проверяется по connect-src отдельно от https. Без него в
            // консоли на каждой странице висела ошибка, визиты считались,
            // а записей сеансов не было.
            "connect-src 'self' https://mc.yandex.ru wss://mc.yandex.ru",
            "frame-src https://mc.yandex.ru",
            "form-action 'self'",
            "base-uri 'self'",
            // Открыть сайт в рамке на чужой странице нельзя: так подводят
            // к нажатию не туда.
            "frame-ancestors 'self'",
        ]);
    }

    /**
     * Настоящий это сайт или контур разработки.
     *
     * Контур — полная копия будущего сайта с теми же текстами, тем же
     * каталогом и настоящим телефоном заказчика. Пока он открыт
     * поисковику, он конкурирует с самим сайтом за те же запросы, и
     * разбираться, почему выдача показывает адрес на railway.app, будут
     * долго.
     *
     * Ключ отсутствует — считаем настоящим: на обычном хостинге его
     * никто не проставит, а закрытый по умолчанию боевой сайт хуже.
     * Временный домен при этом закрываем всегда: настоящий сайт на нём
     * не живёт.
     */
    public function isPublic(): bool
    {
        if (($this->config['stage'] ?? 'production') !== 'production') {
            return false;
        }

        $host = (string) parse_url($this->config['site']['url'] ?? '', PHP_URL_HOST);

        foreach (['.railway.app', '.vercel.app', '.onrender.com', 'localhost'] as $temporary) {
            if ($host === 'localhost' || str_ends_with($host, $temporary)) {
                return false;
            }
        }

        return true;
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        try {
            $route = $this->router->match($method, $path);
            if ($route !== null) {
                $this->send($this->call($route['handler'], $route['params']));

                return;
            }

            // Адреса каталога и страниц заданы не правилами, а лежат в
            // базе целиком. Поэтому сначала маршруты, потом поиск по
            // таблицам, и только если ничего — редиректы и 404.
            $response = (new \Sklad\Site\Resolver($this))->resolve($path);
            if ($response !== null) {
                $this->send($response);

                return;
            }

            $this->notFound($path);
        } catch (Throwable $e) {
            $this->error($e);
        }
    }

    /**
     * Вызывает обработчик.
     *
     * Маршрут описывается парой [класс, метод] — так видно, куда идти
     * читать, прямо в списке адресов. Класс получает приложение
     * конструктором: контейнера здесь нет и не нужно.
     */
    private function call($handler, array $params)
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            [$class, $method] = $handler;
            $controller = new $class($this);

            return $controller->{$method}($params);
        }

        return $handler($this, $params);
    }

    private function send($response): void
    {
        if (is_string($response)) {
            echo $response;
        }
    }

    /** Переадресация с прекращением работы: удобнее, чем возвращать её отовсюду. */
    public function redirect(string $path, int $code = 302): void
    {
        // Абсолютный адрес собираем через адрес текущего города, а не через
        // адрес площадки из настроек. Иначе на региональном поддомене любая
        // переадресация — приведение адреса к виду с косой, правило из
        // таблицы, перенос со старого сайта — уводила посетителя и робота на
        // основной домен: город терялся, а страница городской выдачи
        // оказывалась перевешена на общую. На основном домене города нет, и
        // cityUrl() отдаёт ровно то же, что siteUrl().
        header('Location: ' . (str_starts_with($path, 'http') ? $path : $this->cityUrl($path)), true, $code);
        exit;
    }

    /**
     * Адрес не нашёлся.
     *
     * Прежде чем показывать четырёхсотку, смотрим таблицу редиректов:
     * товар могли переименовать, и на старом адресе висят ссылки и
     * позиции в выдаче.
     */
    private function notFound(string $path): void
    {
        $clean = '/' . trim($path, '/');

        // Ищем и по адресу со строкой запроса, и без неё.
        //
        // Старый сайт держал каталог на адресах вида
        // /index.php?route=product/product&product_id=336 — без
        // параметров они не отличаются друг от друга. Искать только по
        // пути значило бы, что ни одно правило переезда не сработает, а
        // выяснится это падением обращений через месяц.
        $full = $clean;
        $query = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
        if ($query !== '') {
            $full = rtrim($clean, '/') . '?' . $query;
        }

        // Косая на конце тоже не должна ничего решать. У разделов старого
        // сайта адрес с ней, а сюда путь приходит уже обрезанным — правило
        // просто не находилось бы, и раздел, по которому сайт стоял в
        // выдаче, отдавал бы 404.
        $redirect = $this->db->one(
            'SELECT id, to_path, code FROM redirect WHERE from_path IN (?, ?, ?)'
            . ' ORDER BY LENGTH(from_path) DESC LIMIT 1',
            [$full, $clean, $clean . '/']
        );
        if ($redirect !== null) {
            $this->db->run(
                'UPDATE redirect SET hits = hits + 1, last_hit_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s'), $redirect['id']]
            );
            // Тоже через адрес города: правило переезда со старого сайта
            // должно приводить на тот же поддомен, с которого пришли, —
            // иначе весь перенос ссылок и позиций сливается на основной
            // домен (см. redirect()).
            header('Location: ' . $this->cityUrl($redirect['to_path']), true, (int) $redirect['code']);

            return;
        }

        // Промах внутри админки — это не потерянная страница сайта, а
        // раздел, до которого ещё не дошли руки. Говорить об этом надо
        // по-другому, иначе выглядит как сломавшаяся админка.
        $adminPath = rtrim($this->config['admin_path'] ?? '/admin', '/');
        if ($adminPath !== '' && str_starts_with($clean, $adminPath)) {
            http_response_code(404);
            echo $this->view->render('admin/soon', [
                'path' => $clean,
                'back' => $adminPath,
            ]);

            return;
        }

        // Запоминаем промах: по этому списку потом видно, что чинить.
        // Со строкой запроса — без неё непонятно, какой именно адрес
        // старого сайта не нашёл себе правила.
        $this->db->run(
            'INSERT INTO not_found (path, hits, referer, first_at, last_at) VALUES (?, 1, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE hits = hits + 1, last_at = VALUES(last_at)',
            [
                mb_substr($full, 0, 500),
                mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]
        );

        // Страница ненайденного адреса — своя у каждой темы, если она её
        // завела. Общая остаётся запасной: она без оформления, и показывать
        // её на живом сайте — всё равно что признаться в поломке.
        //
        // Настройки передаём сюда же: 404 отдаётся на любой адрес, а тянуть
        // ради неё обвязку с запросами к базе незачем — телефон и марка
        // берутся из уже загруженных настроек.
        $theme = $this->config['site']['theme'] ?? 'default';
        $own = $this->root . '/templates/site/' . $theme . '/404.php';
        http_response_code(404);
        echo $this->view->render(
            is_file($own) ? 'site/' . $theme . '/404' : 'site/404',
            ['path' => $clean, 'settings' => $this->settings]
        );
    }

    private function error(Throwable $e): void
    {
        error_log('[sklad] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        http_response_code(500);
        if ($this->isDebug()) {
            echo '<pre>' . View::e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';

            return;
        }
        echo $this->view->render('site/500');
    }

    private function fail(string $message, int $code): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Сайт недоступен</title>'
            . '<body style="font:16px/1.5 system-ui;margin:4rem auto;max-width:32rem;padding:0 1rem">'
            . '<h1 style="font-size:1.25rem">Сайт недоступен</h1><p>' . View::e($message) . '</p>';
        exit;
    }

    /** Простейшая автозагрузка: пространство имён Sklad\ лежит в src/. */
    public static function registerAutoload(string $root): void
    {
        spl_autoload_register(static function (string $class) use ($root): void {
            if (strpos($class, 'Sklad\\') !== 0) {
                return;
            }
            $path = $root . '/src/' . str_replace('\\', '/', substr($class, 6)) . '.php';
            if (is_file($path)) {
                require $path;
            }
        });
    }
}
