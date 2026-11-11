<?php

declare(strict_types=1);

namespace Sklad\Site;

use Sklad\Core\App;
use Sklad\Model\City;
use Sklad\Model\Meta;
use Sklad\Model\Visible;

/**
 * Общее для страниц витрины: мета-теги, крошки, отрисовка.
 *
 * Мета собираются в одном месте намеренно. Канонический адрес и запрет
 * индексации легко забыть проставить на новом типе страниц, а замечает
 * это потом поисковик — через месяц и молча.
 */
abstract class Page
{
    protected array $crumbs = [];
    protected array $pageScripts = [];

    /** Какие характеристики показаны в плитках — их же берёт подбор. */
    protected array $shownFields = [];

    public function __construct(protected App $app)
    {
    }

    /**
     * @param array $meta title, description, canonical, noindex, image, og_type
     */
    protected function show(string $template, array $data, array $meta): string
    {
        $theme = $this->app->config['site']['theme'] ?? 'default';
        $base = rtrim($this->app->config['site']['url'] ?? '', '/');

        $meta += [
            'title' => '',
            'description' => '',
            'canonical' => rtrim($this->app->cityUrl(), '/') . ($_SERVER['REQUEST_URI'] ?? '/'),
            'noindex' => false,
            'og_type' => 'website',
            // Заголовок собран нами, а не написан руками.
            'from_template' => true,
        ];

        // Город в заголовок и описание подставляем до всего остального.
        // Иначе проверка «город тут уже есть» ниже смотрела бы на «{городе}»
        // и не находила названия, а потом дописала бы его вторым разом —
        // «Тележки в Туле в Туле».
        $meta['title'] = City::fill((string) $meta['title'], $this->app->city);
        $meta['description'] = City::fill((string) $meta['description'], $this->app->city);

        // На контуре разработки запрещаем индексацию каждой страницы.
        // Одного robots.txt мало: он запрещает обход, но не попадание в
        // индекс — страницу, на которую есть ссылка со стороны, поисковик
        // покажет и не заходя на неё. Запрет в самой странице — надёжнее.
        if (!$this->app->isPublic()) {
            $meta['noindex'] = true;
        }

        // Пустой заголовок вкладки — не мелочь: именно его показывает
        // поисковик строкой ссылки, и вместо названия там окажется адрес.
        // Поэтому подставляем то, что есть, вплоть до имени домена.
        if (trim((string) $meta['title']) === '') {
            $meta['title'] = $this->app->settings->get('site_name')
                ?: ($this->app->settings->get('company')
                    ?: (string) parse_url($base, PHP_URL_HOST));
        }

        /*
         * Приписка к заголовку окна — например, « — Бренд».
         *
         * Настройка была в админке с самого начала, но её никто не
         * читал: человек её заполнял, сохранял, а в заголовках ничего
         * не менялось. Настройка, которая ничего не делает, хуже
         * отсутствующей — по ней принимают решения.
         *
         * Дописываем только к собранным нами заголовкам: см. Meta.
         */
        /*
         * Город регионального поддомена — в заголовок и описание.
         *
         * Ради этого поддомены и заводят: «Штабелёры — купить в Казани»
         * попадает в городскую выдачу, а общий заголовок — нет. Дописываем
         * ко всем заголовкам, включая написанные руками: на городском
         * поддомене страница без города бессмысленна, а руками их писали
         * для основного адреса, когда поддоменов ещё не было. Уже
         * упомянутый город второй раз не приписываем.
         */
        if ($this->app->city !== null) {
            $city = $this->app->city;

            // Считаем город названным, если встретилась ЛЮБАЯ его форма, а не
            // только предложная. Раньше сверялись лишь с «Туле», и заголовок,
            // написанный редактором как «Доставка в Тулу», получал приписку
            // второй раз: «Доставка в Тулу — от 2 дней в Туле». С городскими
            // текстами это стало обычным делом — их пишут под город и город
            // в них называют.
            //
            // Основу берём по самому длинному общему началу форм: у «Тулы»
            // это «Тул», у «Санкт-Петербурга» — «Санкт-Петербург». Так ловятся
            // и падежи, которых в списке нет, вроде винительного «Тулу».
            $named = static function (string $text) use ($city): bool {
                $forms = [(string) $city['name'], (string) $city['in'], (string) $city['of']];
                $stem = $forms[0];
                foreach ($forms as $f) {
                    $n = 0;
                    $max = min(mb_strlen($stem), mb_strlen($f));
                    while ($n < $max && mb_substr($stem, $n, 1) === mb_substr($f, $n, 1)) {
                        $n++;
                    }
                    $stem = mb_substr($stem, 0, $n);
                }

                // Основа короче трёх букв ни о чём не говорит: она совпала бы
                // со случайным куском чужого слова.
                return mb_strlen($stem) >= 3 && mb_stripos($text, $stem) !== false;
            };

            // Хвост режем регулярным выражением с /u, а не rtrim: список
            // символов у rtrim побайтовый, и длинное тире (E2 80 94) вносит
            // в него байты 80 и 94 — те самые, которыми заканчиваются «р»
            // (D1 80) и «Д» (D0 94). «…официальный дилер» превращалось в
            // «…официальный диле» с оборванным байтом, и заголовок вкладки
            // становился невалидным UTF-8.
            if (!$named($meta['title'])) {
                $title = preg_replace('/[\s.,\x{2014}\x{2013}-]+$/u', '', (string) $meta['title']);

                // Город обязан влезть в показываемую длину заголовка. Иначе
                // приписка делается впустую: и выдача, и Meta::withSuffix
                // ниже режут заголовок с хвоста — ровно там, где стоит город.
                // Режем сам заголовок, а не город: ради города поддомен и
                // заводят. Место под приписку вычитаем сразу, её допишут
                // следующим шагом; плюс знак — на пробел, которым
                // withSuffix отбивает приписку, написанную без разделителя.
                $suffix = !empty($meta['from_template'])
                    ? rtrim((string) $this->app->settings->get('meta_title_suffix'))
                    : '';
                $tail = ' в ' . $city['in'];
                $room = Meta::TITLE_LIMIT - mb_strlen($tail)
                    - ($suffix === '' ? 0 : mb_strlen($suffix) + 1);
                if ($room > 0 && mb_strlen($title) > $room) {
                    $title = rtrim(mb_substr($title, 0, $room - 1)) . '…';
                }

                $meta['title'] = $title . $tail;
            }
            if (trim((string) $meta['description']) !== ''
                && !$named((string) $meta['description'])) {
                $meta['description'] = rtrim((string) $meta['description'], ' .')
                    . '. Доставка в ' . $city['in'] . '.';
            }
        }

        if (!empty($meta['from_template'])) {
            $meta['title'] = Meta::withSuffix(
                (string) $meta['title'],
                (string) $this->app->settings->get('meta_title_suffix')
            );
        }

        // Канонический адрес не должен тащить за собой параметры отбора
        // и страницы: иначе каждая комбинация фильтров объявит себя
        // отдельной страницей, и поисковик увидит сотни дублей.
        $meta['canonical'] = strtok($meta['canonical'], '?') ?: $meta['canonical'];

        // Подстановку по готовой странице делаем одним проходом, а не в
        // каждом шаблоне: тексты приходят из десятка мест — описание
        // раздела, текст страницы, блок в подвале, — и обойти их поимённо
        // значит однажды забыть один и не заметить этого месяцами.
        return City::fill(
            $this->app->view->renderIn(
                'site/' . $theme . '/layout',
                'site/' . $theme . '/' . $template,
                $data + [
                    'meta' => $meta,
                    'crumbs' => $this->crumbs,
                    'pageScripts' => $this->pageScripts,
                    'blocks' => $this->blocks(),
                    'menuSections' => $this->menuSections(),
                ]
            ),
            $this->app->city
        );
    }

    /**
     * Верхние разделы каталога с числом моделей — для меню в шапке.
     *
     * Отдаётся всем страницам, а не только каталогу: меню висит в обвязке,
     * и собирать его в каждом контроллере по отдельности означает однажды
     * забыть об этом в новом типе страниц.
     *
     * Считаем по поддереву через path, а не по прямой привязке: товар лежит
     * на четвёртом уровне, и подсчёт по category_id показал бы у раздела ноль.
     */
    protected function menuSections(): array
    {
        static $sections = null;
        if ($sections === null) {
            // Пустые разделы в меню не показываем: пункт со счётчиком 0 висел
            // на каждой странице сайта и вёл на «в этом разделе пока пусто».
            // Условие видимости общее с витриной — иначе три списка разделов
            // на одном сайте показывают разный набор.
            //
            // Исключение ровно одно и то же, что и у плиток каталога
            // (PageController::tiles): раздел запчастей пуст по замыслу —
            // детали не выбирают из списка, а запрашивают по технике, и сам
            // раздел открывается формой запроса. Без этой оговорки «Запчасти»
            // не появились бы в меню ни у Hangcha, ни у Tisel, хотя у
            // b2b-store.example.ru, на который равняется заказчик, этот пункт в
            // меню стоит третьим.
            $parts = trim((string) $this->app->settings->get('parts_path', ''));

            $sections = $this->app->db->all(
                'SELECT * FROM ('
                . '  SELECT c.id, c.title, c.path, c.sort,'
                . '   (SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(c.path, \'%\')'
                . '     AND ' . Visible::product() . ') AS products'
                . '  FROM category c'
                . '  WHERE c.parent_id IS NULL AND c.is_published = 1 AND c.deleted_at IS NULL'
                . ') AS t WHERE t.products > 0 OR t.path = ?'
                // Порядок — заданный в админке, и берётся он снаружи
                // подзапроса: ORDER BY внутри производной таблицы MySQL
                // выполнять не обязан, и на боевом сервере список мог бы
                // прийти в другом порядке, чем плитки каталога.
                . ' ORDER BY t.sort, t.title',
                [$parts]
            );
        }

        return $sections;
    }

    /** Куски текста, которые правятся в админке. */
    protected function blocks(): array
    {
        static $blocks = null;
        if ($blocks === null) {
            $blocks = $this->app->db->pairs('SELECT `key`, body FROM block');
        }

        return $blocks;
    }

    /**
     * Адрес страницы для canonical и микроразметки.
     *
     * На региональном поддомене ведёт на него же: канонический адрес,
     * указывающий на основной домен, объявляет городскую страницу копией,
     * и в городскую выдачу она не попадает — ради чего поддомены и заводят.
     */
    protected function url(string $path): string
    {
        return rtrim($this->app->cityUrl(), '/') . $path;
    }

    /**
     * Характеристики, которые различают именно этот список товаров.
     *
     * Считаются по самому списку, а не по всему каталогу. Единый набор на
     * весь сайт годится только при узкой номенклатуре: здесь в каталоге и
     * рохли, и сварочные аппараты, и три самых частых поля каталога
     * («грузоподъёмность», «высота подъёма») у сварочника пустые — плитка
     * выходила с одной строкой «Вес», хотя у товара пятнадцать полей.
     *
     * Поле берётся, если заполнено хотя бы у четверти списка и значения
     * различаются: одинаковое во всех плитках ничего не сообщает.
     *
     * @param array $products товары списка
     * @return array<int, array> поля с id, key, title, unit
     */
    protected function fieldsFor(array $products): array
    {
        if (!$products) {
            return [];
        }

        $ids = implode(',', array_map(static fn (array $p): int => (int) $p['id'], $products));
        $rows = $this->app->db->all(
            'SELECT o.id, o.key, o.title, o.unit,'
            . ' COUNT(po.product_id) AS filled,'
            . ' COUNT(DISTINCT po.value) AS variants'
            . ' FROM `option` o'
            . " JOIN product_option po ON po.option_id = o.id AND po.value <> ''"
            . " WHERE po.product_id IN ({$ids})"
            . ' GROUP BY o.id'
            . ' ORDER BY filled DESC, variants DESC, o.sort'
        );

        $threshold = max(1, (int) ceil(count($products) / 4));
        $fields = [];

        // Грузоподъёмность показываем всегда и первой. По одной только
        // различимости она вылетала: в узком разделе она у всех одна и та
        // же, «значения не различаются» — и плитка оставалась без главной
        // цифры техники. Ключ берём из настройки: у площадок сети он свой.
        $pinned = trim((string) $this->app->settings->get('specs_pinned', 'capacity'));
        foreach ($rows as $row) {
            if ($pinned !== '' && $row['key'] === $pinned) {
                $fields[] = ['id' => $row['id'], 'key' => $row['key'], 'title' => $row['title'], 'unit' => $row['unit']];
                break;
            }
        }

        foreach ($rows as $row) {
            if ($row['key'] === $pinned) {
                continue;                       // уже стоит первой
            }
            if ((int) $row['filled'] < $threshold || (int) $row['variants'] < 2) {
                continue;
            }
            $fields[] = ['id' => $row['id'], 'key' => $row['key'], 'title' => $row['title'], 'unit' => $row['unit']];
            if (count($fields) === 4) {
                break;
            }
        }

        // Порог не прошло ничего — список слишком разнородный. Показываем
        // самые заполненные, иначе плитка останется без цифр, а различать
        // модели тогда нечем. Считаем и закреплённую: с ней одной плитка
        // тоже пустовата.
        if (count($fields) < 2) {
            $have = array_column($fields, 'key');
            foreach ($rows as $row) {
                if (in_array($row['key'], $have, true)) {
                    continue;
                }
                $fields[] = ['id' => $row['id'], 'key' => $row['key'], 'title' => $row['title'], 'unit' => $row['unit']];
                if (count($fields) === 3) {
                    break;
                }
            }
        }

        return $fields;
    }

    /**
     * Дописывает плиткам короткие характеристики — одним запросом на весь
     * список, а не по запросу на плитку.
     *
     * Тот же набор полей отдаётся подбору: если он посчитает свой, то
     * после первого движения фильтра плитки молча сменят подписи, и
     * выглядит это как подмена товара.
     */
    protected function withSpecs(array $products, ?array $fields = null): array
    {
        // Набор полей можно передать готовым. На странице раздела он
        // считается по всем товарам раздела, а не по видимой странице:
        // подбор работает по всему разделу, и посчитай мы набор по первым
        // двадцати четырём — после первого движения фильтра подписи
        // сменились бы.
        $fields ??= $this->fieldsFor($products);
        $this->shownFields = $fields;

        if (!$products || !$fields) {
            return $products;
        }

        $ids = implode(',', array_map(static fn (array $p): int => (int) $p['id'], $products));
        $options = implode(',', array_map(static fn (array $f): int => (int) $f['id'], $fields));

        $values = [];
        foreach ($this->app->db->all(
            "SELECT product_id, option_id, value FROM product_option"
            . " WHERE product_id IN ({$ids}) AND option_id IN ({$options}) AND value <> ''"
        ) as $row) {
            $values[(int) $row['product_id']][(int) $row['option_id']] = $row['value'];
        }

        foreach ($products as &$product) {
            $product['specs'] = [];
            foreach ($fields as $field) {
                $value = $values[(int) $product['id']][(int) $field['id']] ?? null;
                if ($value !== null) {
                    $product['specs'][] = $field + ['value' => $value];
                }
            }
        }

        return $products;
    }
}
