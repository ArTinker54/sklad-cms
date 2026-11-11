<?php

declare(strict_types=1);

namespace Sklad\Admin;

/**
 * Первый экран после входа.
 *
 * Показывает не «добро пожаловать», а то, что требует внимания: новые
 * заявки, товары без цены и без фотографии, обращения к несуществующим
 * адресам. На складском каталоге именно это и копится.
 */
final class DashboardController extends Controller
{
    public function index(array $params = []): string
    {
        $db = $this->app->db;

        $counts = [
            'products' => (int) $db->value('SELECT COUNT(*) FROM product WHERE deleted_at IS NULL'),
            'categories' => (int) $db->value('SELECT COUNT(*) FROM category WHERE deleted_at IS NULL'),
            'pages' => (int) $db->value('SELECT COUNT(*) FROM page WHERE deleted_at IS NULL'),
        ];

        // Заявки видит не всякий: у контент-менеджера доступа к ним нет.
        // Плюс их могут читать только в почте — тогда и на главной админки
        // им делать нечего, иначе настройка прячет вкладку, а счётчик
        // «новых заявок» остаётся висеть и сбивает с толку.
        $requests = null;
        if ($this->app->auth->can('requests') && $this->app->settings->flag('show_requests')) {
            $requests = [
                'new' => (int) $db->value("SELECT COUNT(*) FROM request WHERE status = 'new'"),
                'today' => (int) $db->value('SELECT COUNT(*) FROM request WHERE DATE(created_at) = CURDATE()'),
                'last' => $db->all('SELECT id, number, name, phone, created_at, status FROM request ORDER BY id DESC LIMIT 5'),
            ];
        }

        $attention = [];
        if ($this->app->auth->can('catalog')) {
            $attention['без цены'] = (int) $db->value(
                'SELECT COUNT(*) FROM product WHERE deleted_at IS NULL AND is_published = 1 AND price <= 0'
            );
            $attention['без фотографии'] = (int) $db->value(
                'SELECT COUNT(*) FROM product p WHERE p.deleted_at IS NULL AND p.is_published = 1'
                . ' AND NOT EXISTS (SELECT 1 FROM product_image i WHERE i.product_id = p.id)'
            );
            $attention['без описания'] = (int) $db->value(
                "SELECT COUNT(*) FROM product WHERE deleted_at IS NULL AND is_published = 1 AND (body IS NULL OR body = '')"
            );
        }

        $notFound = $this->app->auth->can('redirects')
            ? $db->all('SELECT path, hits, last_at FROM not_found ORDER BY hits DESC LIMIT 5')
            : [];

        // Проверки настройки — только администратору: чинится это в
        // настройках, конфиге и DNS, контент-менеджеру там делать нечего.
        $checks = $this->app->auth->can('settings') ? $this->siteChecks() : null;

        return $this->render('admin/dashboard', [
            'title' => 'Сводка',
            'checks' => $checks,
            'counts' => $counts,
            'requests' => $requests,
            'attention' => $attention,
            'notFound' => $notFound,
        ]);
    }

    /**
     * Проверки настройки сайта — то, на чём сайты сети спотыкались молча.
     *
     * Каждая строка родом из настоящего случая. Письма о заявках молча
     * пропадали, потому что запись SPF домена не разрешала сервер хостинга, а
     * mail() при этом отвечала «отправлено» (storefront, сентябрь 2026). У
     * главной Партнёра было пустое «Описание для выдачи», и Яндекс показывал под
     * ссылкой меню и телефон из шапки. Стенд со стадией «dev» закрыт от
     * поиска целиком — перенесённый на боевой с этой стадией сайт выпал бы
     * из выдачи.
     *
     * Показываем только то, что не так: пустой список — всё в порядке.
     *
     * @return list<array{level:string, text:string, fix:string, link:?string}>
     */
    private function siteChecks(): array
    {
        $db = $this->app->db;
        $s = $this->app->settings;
        $out = [];
        $add = static function (string $level, string $text, string $fix = '', ?string $link = null) use (&$out): void {
            $out[] = ['level' => $level, 'text' => $text, 'fix' => $fix, 'link' => $link];
        };

        // Стадия. Не «production» — robots.txt запрещает обход целиком.
        $stage = (string) ($this->app->config['stage'] ?? 'production');
        if ($stage !== 'production') {
            $add('warn', "Сайт закрыт от поисковиков: стадия «{$stage}», robots.txt запрещает обход.",
                "Для стенда так и нужно. На боевом домене — 'stage' => 'production' в config/config.php.");
        }

        // Куда уходят заявки. Пусто — письма не уходят никуда, а человек
        // видит «заявка отправлена».
        $managers = trim((string) $s->get('manager_email'));
        if ($managers === '') {
            $add('bad', 'Не указана почта, куда падают заявки: письма о заявках не уходят никуда.',
                'Настройки → «Куда падают заявки».', $this->app->adminPath('setting'));
        }

        // Доставка писем. SMTP в движке нет — письма уходят через mail() с
        // сервера хостинга (раздел mail в config.php не читается). Почта
        // домена на Mail.ru или Яндексе принимает их, только если запись SPF
        // домена разрешает этот сервер; иначе выбрасывает без отказа.
        // Проверяем лишь на боевом: стенд шлёт от чужого домена.
        $domain = trim((string) $s->get('site_domain'));
        if ($domain === '') {
            $domain = (string) parse_url((string) ($this->app->config['site']['url'] ?? ''), PHP_URL_HOST);
        }
        $domain = (string) preg_replace('/^www\./', '', strtolower($domain));
        if ($stage === 'production' && $managers !== '' && $domain !== '') {
            $spf = '';
            foreach (@dns_get_record($domain, DNS_TXT) ?: [] as $r) {
                $t = (string) ($r['txt'] ?? implode('', $r['entries'] ?? []));
                if (stripos($t, 'v=spf1') === 0) {
                    $spf = $t;
                    break;
                }
            }
            // За nginx адрес бывает внутренним — тогда сверяем только по
            // записи хостинга, без своего IP.
            $ip = (string) ($_SERVER['SERVER_ADDR'] ?? '');
            $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            if ($spf === '') {
                $add('warn', "У домена {$domain} нет записи SPF: почтовые службы охотнее отбраковывают письма о заявках.",
                    'DNS домена → TXT-запись v=spf1 с серверами хостинга и почтовой службы домена.');
            } elseif (!str_contains($spf, 'include:_spf.hosting.reg.ru') && !($public && str_contains($spf, 'ip4:' . $ip))) {
                $add('bad', 'Письма о заявках уходят с сервера хостинга' . ($public ? " ({$ip})" : '')
                    . ", а запись SPF домена {$domain} его не разрешает. Почта домена может молча их выбрасывать — сайт при этом пишет «отправлено».",
                    'В TXT-запись SPF добавить include:_spf.hosting.reg.ru' . ($public ? " и ip4:{$ip}" : '')
                    . ' перед redirect= или include: почтовой службы. Сейчас: ' . $spf);
            }
        }

        // Описание главной для выдачи.
        $home = $db->one("SELECT id, meta_description FROM page WHERE path = '/' AND deleted_at IS NULL");
        if ($home !== null && trim((string) $home['meta_description']) === '') {
            $add('warn', 'У главной пустое «Описание для выдачи»: Яндекс соберёт текст под ссылкой сам — обычно из шапки: город, меню, телефон.',
                'Страницы → «Главная» → «Описание для выдачи». Метка {городе} подставит город поддомена.',
                $this->app->adminPath('page/' . (int) $home['id']));
        }

        // Остальные страницы, которые видит поисковик. Служебные — корзина,
        // сравнение, избранное, поиск — закрыты от выдачи, им описание ни к чему.
        $bare = $db->all(
            "SELECT id, title FROM page WHERE deleted_at IS NULL AND is_published = 1 AND is_indexable = 1"
            . " AND path <> '/' AND template NOT IN ('cart', 'compare', 'favorites', 'search')"
            . " AND (meta_description IS NULL OR meta_description = '') ORDER BY sort, id"
        );
        if ($bare) {
            $names = array_map(static fn (array $r): string => '«' . $r['title'] . '»', array_slice($bare, 0, 4));
            $add('warn', 'Страниц без описания для выдачи: ' . count($bare) . ' — ' . implode(', ', $names)
                . (count($bare) > 4 ? ' и другие' : '') . '.',
                'Страницы → нужная страница → «Описание для выдачи».', $this->app->adminPath('page'));
        }

        // Название сайта. Им подписаны письма о заявках (имя отправителя),
        // выгрузка для Яндекса (имя магазина) и заголовок главной без своего
        // Title. У Brand оно было пустым — письма шли без имени.
        if (trim((string) $s->get('site_name')) === '') {
            $add('warn', 'Не заполнено название сайта: письма о заявках приходят без имени отправителя, в выгрузке для Яндекса пустое имя магазина, а главная без своего Title получит пустой заголовок.',
                'Настройки → «Название сайта».', $this->app->adminPath('setting'));
        }

        // Счётчик Метрики.
        $metrika = trim((string) $s->get('metrika_id'));
        if ($metrika === '' || !ctype_digit($metrika)) {
            $add('warn', 'Не указан счётчик Яндекс.Метрики: посещения и заявки не считаются.',
                'Настройки → номер счётчика Метрики.', $this->app->adminPath('setting'));
        }

        // Контакты для клиентов.
        foreach (['phone' => 'телефон', 'email' => 'почта для клиентов'] as $key => $label) {
            if (trim((string) $s->get($key)) === '') {
                $add('warn', "Не указан {$label}: в шапке и на странице контактов пусто.",
                    'Настройки → «Контакты».', $this->app->adminPath('setting'));
            }
        }

        // Страна сборки. Пусто — строка «Производство» в карточке не
        // выводится; это не ошибка, но решать это надо осознанно.
        if (trim((string) $s->get('country')) === '') {
            $add('note', 'Не указана страна сборки: строка «Производство» в карточках товаров не показывается.',
                'Настройки → «Страна сборки».', $this->app->adminPath('setting'));
        }

        return $out;
    }
}
