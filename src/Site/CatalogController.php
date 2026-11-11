<?php

declare(strict_types=1);

namespace Sklad\Site;

use Sklad\Model\Meta;
use Sklad\Model\Tags;
use Sklad\Model\Visible;

/**
 * Разделы каталога и карточки товаров.
 *
 * Первая страница раздела приходит с сервера — её и видит поисковик.
 * Подбор по параметрам работает на стороне браузера по индексу всего
 * раздела: фильтр обязан искать по всем 142 моделям, а не по тем двум
 * десяткам, что успели прийти на первую страницу.
 */
final class CatalogController extends Page
{
    public function category(array $category): string
    {
        $db = $this->app->db;
        $perPage = max(6, $this->app->settings->int('per_page', 24));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $this->crumbs = $this->crumbsFor($category['path'], $category['h1'] ?: $category['title']);
        $this->pageScripts[] = 'catalog.js';

        $total = (int) $db->value(
            'SELECT COUNT(*) FROM product p WHERE p.path LIKE ? AND ' . Visible::product(),
            [$category['path'] . '%']
        );
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $products = $db->all(
            'SELECT p.*, (SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . '  WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo'
            . ' FROM product p WHERE p.path LIKE ? AND ' . Visible::product()
            . ' ORDER BY p.sort, p.id LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            [$category['path'] . '%']
        );

        // Набор подписей считаем по всему разделу, а не по видимой
        // странице: подбор рисует карточки по всему разделу и обязан
        // показывать те же характеристики. Точка отсчёта — корень ветки,
        // а не открытый раздел: карточка товара считает свой набор по
        // подразделу товара, и наборы обязаны совпадать (см. branchRoot).
        $whole = $db->all(
            'SELECT p.id FROM product p WHERE p.path LIKE ? AND ' . Visible::product(),
            [$this->branchRoot($category['path']) . '%']
        );
        $fields = $this->fieldsFor($whole);
        $children = $this->children($category);

        return $this->show('category', [
            'category' => $category,
            'products' => $this->withSpecs($products, $fields),
            'shownFields' => array_column($fields, 'key'),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            // Число моделей в подразделе считаем сразу: без него человек
            // заходит в подраздел с двумя позициями и возвращается обратно.
            // Считаем по поддереву через path — товар может лежать глубже.
            // Условие видимости общее с сеткой и счётчиком: с облегчённым
            // товары снятого с витрины подраздела попадали в счётчик, но
            // не в список — числа на странице расходились между собой.
            'children' => $children,
            'tags' => $this->tags($category, $children),
            'filters' => $this->filters($category),
            'sections' => $this->sections($category),
        ], [
            'title' => $category['meta_title'] !== ''
                ? $category['meta_title']
                : ($category['h1'] ?: $category['title']),
            'from_template' => $category['meta_title'] === '',
            'description' => $category['meta_description'],
            'canonical' => trim((string) $category['canonical']) !== ''
                ? $this->url($category['canonical'])
                : $this->url($category['path']),
            // Вторая и дальше страницы в индексе не нужны: там те же
            // товары, только другой их кусок.
            'noindex' => !$category['is_indexable'] || $page > 1,
        ]);
    }

    public function product(array $product): string
    {
        $db = $this->app->db;
        $category = $product['category_id']
            ? $db->one('SELECT * FROM category WHERE id = ?', [$product['category_id']])
            : null;

        $effective = (new Meta($db))->forProduct($product, $category);

        $this->crumbs = $this->crumbsFor(
            $category !== null ? $category['path'] : '/',
            $effective['h1'],
            true
        );

        $images = $db->all(
            'SELECT m.file, m.width, m.height FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . ' WHERE pi.product_id = ? ORDER BY pi.sort, pi.id',
            [$product['id']]
        );

        $this->pageScripts[] = 'product.js';

        return $this->show('product', [
            'product' => $product,
            'category' => $category,
            'effective' => $effective,
            'images' => $images,
            'related' => $this->withSpecs($this->related($product, $category)),
            'specs' => $this->specs((int) $product['id']),
            'keySpecs' => $this->keySpecs($product, $category),
        ], [
            'title' => $effective['title'],
            'from_template' => $effective['from_template'],
            'description' => $effective['description'],
            'canonical' => trim((string) $product['canonical']) !== ''
                ? $this->url($product['canonical'])
                : $this->url($product['path']),
            'noindex' => !$product['is_indexable'],
            'og_type' => 'product',
            'image' => $images ? $this->url($images[0]['file']) : '',
        ]);
    }

    /**
     * Похожие: отмеченные вручную, а если не отмечены — соседи по
     * разделу. Пустой блок на карточке хуже неточного подбора.
     */
    private function related(array $product, ?array $category): array
    {
        $db = $this->app->db;
        $photo = '(SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . '  WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo';

        // Условие видимости во всех трёх запросах общее с карточкой: на
        // товар пускают только по Visible::product(), и с облегчённым
        // условием в «похожие» попадали товары снятого с витрины
        // подраздела — переход по такой ссылке отдавал 404.
        $rows = $db->all(
            "SELECT p.*, {$photo} FROM product_link l JOIN product p ON p.id = l.related_id"
            . ' WHERE l.product_id = ? AND ' . Visible::product()
            . ' ORDER BY l.sort LIMIT 3',
            [$product['id']]
        );
        if ($rows || $category === null) {
            return $rows;
        }

        // Свой подраздел первым: у тележки с весами похожие — тоже тележки
        // с весами, а не что попало из каталога.
        $own = $db->all(
            "SELECT p.*, {$photo} FROM product p"
            . ' WHERE p.category_id = ? AND p.id <> ? AND ' . Visible::product()
            . ' ORDER BY p.sort LIMIT 3',
            [$category['id'], $product['id']]
        );
        if (count($own) >= 3) {
            return $own;
        }

        /*
         * Добираем соседями по разделу.
         *
         * В половине подразделов две-три модели, и блок оставался пустым
         * или с одной карточкой — выглядит как поломка, а человеку и
         * сравнить не с чем. Порядок сохраняем: сначала свой подраздел,
         * потом остальное из того же раздела.
         */
        $parent = $category['parent_id']
            ? $db->one('SELECT path FROM category WHERE id = ?', [(int) $category['parent_id']])
            : null;
        $branch = $parent['path'] ?? $category['path'];

        $taken = array_map(static fn (array $r): int => (int) $r['id'], $own);
        $taken[] = (int) $product['id'];
        $skip = implode(',', $taken);

        $more = $db->all(
            "SELECT p.*, {$photo} FROM product p"
            . ' WHERE p.path LIKE ? AND p.id NOT IN (' . $skip . ')'
            . ' AND ' . Visible::product()
            . ' ORDER BY p.sort, p.id LIMIT ' . (3 - count($own)),
            [$branch . '%']
        );

        return array_merge($own, $more);
    }

    /**
     * Ключевые характеристики карточки — тем же набором и в том же порядке,
     * что и в плитке этого раздела.
     *
     * Иначе выходит подмена: на плитке человек выбрал модель по одним
     * цифрам, открыл карточку — а наверху другие. Набор считает та же
     * fieldsFor() по всему разделу, поэтому «важное» здесь и там совпадает.
     *
     * @return array<int, array{title: string, unit: string, value: string}>
     */
    private function keySpecs(array $product, ?array $category): array
    {
        if ($category === null) {
            return [];
        }

        $fields = $this->fieldsFor($this->app->db->all(
            'SELECT p.id FROM product p WHERE p.path LIKE ? AND ' . Visible::product(),
            [$this->branchRoot($category['path']) . '%']
        ));
        if (!$fields) {
            return [];
        }

        $own = [];
        foreach ($this->app->db->all(
            'SELECT o.key, o.title, o.unit, po.value FROM product_option po'
            . ' JOIN `option` o ON o.id = po.option_id'
            . " WHERE po.product_id = ? AND po.value <> ''",
            [(int) $product['id']]
        ) as $row) {
            $own[$row['key']] = $row;
        }

        // Порядок задаёт список полей раздела, а не то, как легли строки
        // товара: у незаполненного поля карточка просто пропускает строку.
        $out = [];
        foreach ($fields as $field) {
            if (isset($own[$field['key']])) {
                $out[] = $own[$field['key']];
            }
        }

        return $out;
    }

    /**
     * Корень ветки каталога — по нему считается набор характеристик.
     *
     * Плитка и карточка отсчитывают набор от разных мест: плитка — от
     * раздела, который человек открыл, карточка — от подраздела своего
     * товара. Пока считали каждый от своего, на плитке была одна пара
     * цифр, а в карточке другая: узкий подраздел выбрасывает поле, у
     * которого внутри него все значения одинаковы (см. fieldsFor).
     * Общая у них только ветка целиком — от неё и считаем оба набора.
     */
    private function branchRoot(string $path): string
    {
        $first = explode('/', trim($path, '/'))[0];

        return $first === '' ? $path : '/' . $first . '/';
    }

    /**
     * Характеристики по группам — так их читают в паспорте техники.
     *
     * Порядок задаёт `option.sort`, а не название группы. По группе
     * алфавитом «Габариты» опережали «Основные», и первой характеристикой
     * товара оказывались габариты, а не грузоподъёмность — то самое, ради
     * чего карточку и открывают. Группы идут за своей старшей строкой:
     * PHP сохраняет порядок добавления ключей.
     */
    private function specs(int $productId): array
    {
        $rows = $this->app->db->all(
            'SELECT o.title, o.unit, o.group, po.value FROM product_option po'
            . ' JOIN `option` o ON o.id = po.option_id WHERE po.product_id = ?'
            . ' ORDER BY o.sort, o.id',
            [$productId]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group'] !== '' ? $row['group'] : 'Прочее'][] = $row;
        }

        return $grouped;
    }

    /**
     * Состав подбора считается по тому, что заполнено именно здесь.
     * Поле, пустое у большинства, из фильтра убираем: выбрать по нему
     * ничего нельзя, а место оно занимает и читается как поломка.
     */
    private function filters(array $category): array
    {
        $db = $this->app->db;
        // Тем же условием, что сетка и products.json. С облегчённым в подборе
        // оставалась галочка со значением из снятого с витрины подраздела:
        // человек её ставил и получал «ничего не нашлось» при ненулевом числе
        // рядом с самой галочкой.
        $ids = $db->column(
            'SELECT p.id FROM product p WHERE p.path LIKE ? AND ' . Visible::product(),
            [$category['path'] . '%']
        );
        if (count($ids) < 2) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));

        // Какие характеристики вообще участвуют в подборе здесь.
        //
        // Спрашиваем по всему поддереву, а не по самому разделу. Страница
        // «Штабелёры» показывает товары всех вложенных разделов, а
        // фильтруемой характеристика отмечена именно у вложенных: если
        // спрашивать только про сам раздел, на верхнем уровне подбора не
        // будет вовсе, хотя фильтровать там есть что.
        $fields = $db->all(
            'SELECT DISTINCT o.id, o.key, o.title, o.unit, o.type, o.sort'
            . ' FROM category_option co'
            . " JOIN category c ON c.id = co.category_id AND c.path LIKE CONCAT(?, '%')"
            . ' JOIN `option` o ON o.id = co.option_id AND co.is_filterable = 1'
            . ' ORDER BY o.sort',
            [$category['path']]
        );
        if (!$fields) {
            return [];
        }

        $options = implode(',', array_map(static fn (array $f): int => (int) $f['id'], $fields));
        $byOption = [];
        foreach ($db->all(
            'SELECT option_id, value, value_num FROM product_option'
            . " WHERE product_id IN ({$in}) AND option_id IN ({$options}) AND value <> ''"
        ) as $row) {
            $byOption[(int) $row['option_id']]['values'][] = $row['value'];
            if ($row['value_num'] !== null) {
                $byOption[(int) $row['option_id']]['numbers'][] = (float) $row['value_num'];
            }
        }

        $byKey = [];
        foreach ($fields as $field) {
            $data = $byOption[(int) $field['id']] ?? null;
            if ($data !== null) {
                $byKey[$field['key']] = $data + ['field' => $field];
            }
        }

        $filters = [];
        $need = max(3, count($ids) * 0.25);
        foreach ($byKey as $key => $data) {
            if (count($data['values']) < $need) {
                continue;
            }
            $field = $data['field'];

            if ($field['type'] === 'number' && !empty($data['numbers'])) {
                $min = min($data['numbers']);
                $max = max($data['numbers']);
                if ($min === $max) {
                    continue;
                }
                $filters[] = [
                    'key' => $key, 'title' => $field['title'], 'unit' => $field['unit'],
                    'type' => 'range', 'min' => $min, 'max' => $max,
                ];
                continue;
            }

            $counts = array_count_values($data['values']);
            arsort($counts);
            if (count($counts) < 2 || count($counts) > 12) {
                continue;
            }
            $filters[] = [
                'key' => $key, 'title' => $field['title'], 'unit' => $field['unit'],
                'type' => 'set', 'options' => $counts,
            ];
        }

        return $filters;
    }

    /**
     * Разделы для выпадающего списка: сначала те, где выбор больше.
     *
     * Счёт вынесен во вложенный запрос, а не в HAVING: HAVING без GROUP BY
     * MySQL понимает, но на строгом режиме это не гарантировано, и падать
     * оно будет уже на боевом хостинге с чужими настройками.
     */
    /**
     * Подразделы со снимком и числом моделей.
     *
     * Снимок — свой, если задан в админке, иначе первое фото любого товара
     * подраздела: плитка без картинки читается как незаполненный раздел, а
     * заводить снимок каждому подразделу руками никто не будет.
     */
    private function children(array $category): array
    {
        $db = $this->app->db;

        $rows = $db->all(
            'SELECT c.*,'
            . '  (SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(c.path, \'%\')'
            . '    AND ' . Visible::product() . ') AS goods'
            . ' FROM category c WHERE c.parent_id = ? AND c.is_published = 1 AND c.deleted_at IS NULL'
            . ' ORDER BY c.sort, c.title',
            [$category['id']]
        );

        // Снимки добираем одним обращением к базе, а не по обращению на
        // каждый подраздел без своей картинки: в крупном разделе таких
        // полтора десятка, и столько же лишних запросов уходило на каждый
        // показ страницы. Отбор внутри тот же — первое фото первого товара.
        $need = [];
        foreach ($rows as $row) {
            if (trim((string) ($row['image'] ?? '')) === '') {
                $need[] = (int) $row['id'];
            }
        }

        if ($need) {
            $photos = $db->pairs(
                'SELECT c.id,'
                . '  (SELECT m.file FROM product p'
                . '     JOIN product_image pi ON pi.product_id = p.id'
                . '     JOIN media m ON m.id = pi.media_id'
                . "     WHERE p.path LIKE CONCAT(c.path, '%') AND " . Visible::product()
                . '     ORDER BY p.sort, pi.sort LIMIT 1) AS file'
                . ' FROM category c WHERE c.id IN (' . implode(',', $need) . ')'
            );

            foreach ($rows as &$row) {
                if (trim((string) ($row['image'] ?? '')) === '') {
                    $row['image'] = (string) ($photos[(int) $row['id']] ?? '');
                }
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Популярные запросы — ссылки под плитками подразделов.
     *
     * Курируемый список наследуется вниз по ветке: задан на «Гидравлических
     * тележках» — виден и на их подразделах. Иначе один и тот же набор
     * пришлось бы вписывать в каждый подраздел руками, а значит, где-то он
     * остался бы старым.
     *
     * @param array $children подразделы — те самые плитки на странице
     * @return list<array{title: string, url: string}>
     */
    private function tags(array $category, array $children): array
    {
        $db = $this->app->db;

        $paths = [];
        $walked = '';
        foreach (array_filter(explode('/', trim((string) $category['path'], '/'))) as $segment) {
            $walked .= '/' . $segment;
            $paths[] = $walked . '/';
        }

        if ($paths) {
            // Вся ветка одним запросом, а не по запросу на уровень: страница
            // раздела и так собирается десятком обращений к базе. Длинный
            // адрес первым — это и есть «снизу вверх», от самого раздела.
            $rows = $db->column(
                'SELECT c.tags FROM category c'
                . ' WHERE c.path IN (' . implode(',', array_fill(0, count($paths), '?')) . ')'
                . " AND c.tags IS NOT NULL AND c.tags <> '' AND " . Visible::category()
                . ' ORDER BY LENGTH(c.path) DESC',
                $paths
            );

            foreach ($rows as $raw) {
                $tags = $this->liveTags(Tags::parse((string) $raw), (string) $category['path']);
                // Пустым список остаётся, только если от него ничего не
                // уцелело. Тогда идём выше по ветке: лучше набор от
                // родителя, чем дыра на месте блока.
                if ($tags) {
                    return $tags;
                }
            }
        }

        // По ветке не задано ничего — собираем список сами.
        //
        // Раньше здесь стоял отказ: раздел с подразделами и любой раздел
        // верхнего уровня не получали блока вовсе. Получалось наоборот
        // тому, чего ждёт человек: в «Штабелёрах» запросы были, а в
        // «Складской технике» над ними — нет. У Brand и Партнёра этого не
        // видно, там списки заполнены руками, а у новой площадки пусто
        // везде, и дыра оказывается как раз на самых посещаемых страницах.
        //
        // Ведём туда, чего на странице ещё нет. Плитки подразделов стоят
        // выше — повторять их ссылками значит показать один набор дважды
        // подряд. Поэтому сначала разделы через уровень: их на странице
        // не видно, а человеку из «Складской техники» нужны не соседи, а
        // «Штабелёры самоходные».
        $rows = [];
        if ($children) {
            $ids = implode(',', array_map(static fn (array $row): int => (int) $row['id'], $children));
            $rows = $db->all(
                'SELECT c.title, c.path FROM category c'
                . ' WHERE c.parent_id IN (' . $ids . ') AND ' . Visible::category()
                . ' ORDER BY c.sort, c.title LIMIT ' . Tags::LIMIT
            );
        }

        // Затем соседи. Тупиковому подразделу увести больше некуда: из
        // «Тележек 2500» человеку нужны «Тележки 3000», а не кнопка
        // «назад». У раздела верхнего уровня соседи — остальные разделы
        // каталога; сравнение «parent_id = NULL» не находило ничего
        // никогда, поэтому у верхних разделов блок и пустовал.
        if (count($rows) < Tags::LIMIT) {
            $rows = array_merge($rows, $db->all(
                'SELECT c.title, c.path FROM category c'
                . ' WHERE c.id <> ? AND c.parent_id '
                . ($category['parent_id'] ? '= ' . (int) $category['parent_id'] : 'IS NULL')
                . ' AND ' . Visible::category()
                . ' ORDER BY c.sort, c.title LIMIT ' . Tags::LIMIT,
                [(int) $category['id']]
            ));
        }

        // Дюжина — общая мера с курируемым списком (Tags::LIMIT): дальше
        // блок перерастает список товаров, ради которого зашли.
        return array_map(
            static fn (array $row): array => [
                'title' => (string) $row['title'],
                'url' => (string) $row['path'],
            ],
            array_slice($rows, 0, Tags::LIMIT)
        );
    }

    /**
     * Оставляем только ссылки, по которым правда что-то открывается.
     *
     * Список пишут руками и правят редко, а каталог живёт своей жизнью:
     * раздел снимают с витрины, переименовывают, в адресе делают опечатку.
     * Проверки при сохранении мало — она была верна на тот день. Поэтому
     * сверяем перед показом, иначе битая ссылка висела бы не на одной
     * странице, а на всей ветке разом: список-то наследуется вниз.
     *
     * Заодно убираем ссылку на саму открытую страницу. У родителя в списке
     * стоят как раз адреса потомков, и на потомке одна из них ведёт туда,
     * где человек уже стоит. Хлебные крошки по той же причине не делают
     * ссылкой последнюю.
     *
     * @param list<array{title: string, url: string}> $tags
     * @return list<array{title: string, url: string}>
     */
    private function liveTags(array $tags, string $here): array
    {
        $tags = array_values(array_filter(
            $tags,
            static fn (array $tag): bool => $tag['url'] !== $here
        ));

        if (!$tags) {
            return [];
        }

        $missing = array_flip(Tags::missing($this->app->db, array_column($tags, 'url')));

        return array_values(array_filter(
            $tags,
            static fn (array $tag): bool => !isset($missing[$tag['url']])
        ));
    }

    private function sections(?array $category = null): array
    {
        $db = $this->app->db;

        // Внутри раздела список ведёт по его подразделам, а не по корню
        // каталога: из «Тележек» уходят в «С весами», а не в «Штабелёры».
        // Точка отсчёта — сам раздел, если подразделы у него есть, иначе
        // родитель: так из подраздела видны соседи по нему.
        $hub = null;
        if ($category !== null) {
            $hasChildren = (int) $db->value(
                'SELECT COUNT(*) FROM category WHERE parent_id = ?'
                . ' AND is_published = 1 AND deleted_at IS NULL',
                [(int) $category['id']]
            ) > 0;

            // Родителя берём тем же условием видимости, что и открытие
            // раздела: снятый с витрины родитель становился первой строкой
            // списка и вёл на 404. Не нашёлся — уходим к корню каталога.
            $hub = $hasChildren
                ? $category
                : ($category['parent_id']
                    ? $db->one(
                        'SELECT c.* FROM category c WHERE c.id = ? AND ' . Visible::category(),
                        [(int) $category['parent_id']]
                    )
                    : null);
        }

        if ($hub !== null) {
            $rows = $this->sectionRows('c.parent_id = ?', [(int) $hub['id']]);
            if ($rows) {
                // Сам раздел — первой строкой: из подраздела надо уметь
                // вернуться ко всему разделу целиком.
                array_unshift($rows, [
                    // Метка «это раздел, остальные — его подразделы»: по ней
                    // шаблон отбивает вложенные и не отбивает корневой список.
                    'is_hub' => true,
                    'id' => (int) $hub['id'],
                    'title' => $hub['title'],
                    'path' => $hub['path'],
                    // Условие видимости общее со счётчиком в шапке раздела:
                    // с облегчённым число у раздела в списке не сходилось с
                    // числом на его же странице.
                    'goods' => (int) $db->value(
                        "SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(?, '%')"
                        . ' AND ' . Visible::product(),
                        [$hub['path']]
                    ),
                ]);

                return $rows;
            }
        }

        // Раздела нет или он без подразделов — ведём по корню каталога.
        return $this->sectionRows('c.parent_id IS NULL', []);
    }

    /**
     * Разделы с числом моделей. Порядок — заданный в админке: по числу
     * моделей вперёд выходил самый крупный, а очередь задаёт продавец.
     */
    private function sectionRows(string $where, array $args): array
    {
        return $this->app->db->all(
            'SELECT * FROM ('
            . '  SELECT c.id, c.title, c.path, c.sort,'
            . "   (SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(c.path, '%')"
            . '     AND ' . Visible::product() . ') AS goods'
            . '  FROM category c WHERE ' . $where . ' AND c.is_published = 1 AND c.deleted_at IS NULL'
            . '   AND NOT EXISTS (SELECT 1 FROM page WHERE page.path = c.path AND page.is_published = 1 AND page.deleted_at IS NULL)'
            . ') AS s WHERE goods > 0 ORDER BY sort, title',
            $args
        );
    }

    /** @return array<int, array{name:string, url?:string}> */
    private function crumbsFor(string $path, string $current, bool $keepLast = false): array
    {
        $crumbs = [['name' => 'Главная', 'url' => '/']];

        $walked = '';
        foreach (array_filter(explode('/', trim($path, '/'))) as $segment) {
            $walked .= '/' . $segment;
            $row = $this->app->db->one('SELECT title, path FROM category WHERE path = ?', [$walked . '/']);
            if ($row !== null) {
                $crumbs[] = ['name' => $row['title'], 'url' => $row['path']];
            }
        }

        // На странице раздела последняя крошка — он сам, и ссылкой быть
        // не должна: ссылка на текущую страницу сбивает с толку.
        if (!$keepLast && count($crumbs) > 1) {
            array_pop($crumbs);
        }
        $crumbs[] = ['name' => $current];

        return $crumbs;
    }
}
