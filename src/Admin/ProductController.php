<?php

declare(strict_types=1);

namespace Sklad\Admin;

/**
 * Товары.
 *
 * Главный экран админки: менеджер проводит здесь больше времени, чем
 * везде остальное вместе взятое. Поэтому список, а не дерево — с 247
 * позициями дерево нечитаемо, — и поиск сразу по названию, артикулу и
 * коду модели.
 *
 * Отдельно фильтры «без цены», «без фото», «без описания». Каталог,
 * перенесённый со старого сайта, всегда неполон, и без такого фильтра
 * непонятно, с чего начинать.
 */
final class ProductController extends Controller
{
    protected string $section = 'catalog';

    private const PER_PAGE = 30;

    public function index(array $params = []): string
    {
        $db = $this->app->db;

        $query = trim((string) ($_GET['q'] ?? ''));
        $categoryId = (int) ($_GET['category'] ?? 0);
        $gap = (string) ($_GET['gap'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));

        // Убранные показываем отдельным отбором, а не прячем навсегда.
        // Удаление без возврата — ловушка: нажали не туда, и достать
        // товар можно только через базу, то есть через разработчика.
        $where = [$gap === 'deleted' ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL'];
        $args = [];

        if ($query !== '') {
            $where[] = '(p.title LIKE ? OR p.article LIKE ? OR p.slug LIKE ?)';
            $like = '%' . $query . '%';
            array_push($args, $like, $like, $like);
        }

        if ($categoryId > 0) {
            // Раздел и всё, что под ним: адрес хранится целиком, поэтому
            // достаточно совпадения начала.
            $path = $db->value('SELECT path FROM category WHERE id = ?', [$categoryId]);
            if ($path !== null) {
                $where[] = 'p.path LIKE ?';
                $args[] = $path . '%';
            }
        }

        if ($gap === 'price') {
            $where[] = 'p.price <= 0';
        } elseif ($gap === 'photo') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM product_image i WHERE i.product_id = p.id)';
        } elseif ($gap === 'text') {
            $where[] = "(p.body IS NULL OR p.body = '')";
        } elseif ($gap === 'hidden') {
            $where[] = 'p.is_published = 0';
        }

        $sql = ' FROM product p LEFT JOIN category c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);

        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $args);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $rows = $db->all(
            'SELECT p.*, c.title AS category_title,'
            . ' (SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . '  WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo'
            . $sql . ' ORDER BY c.path, p.sort, p.id LIMIT ' . self::PER_PAGE
            . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $args
        );

        return $this->render('admin/products', [
            'title' => 'Товары',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'query' => $query,
            'categoryId' => $categoryId,
            'gap' => $gap,
            'categories' => $db->all('SELECT id, title, path FROM category WHERE deleted_at IS NULL ORDER BY path'),
            'gaps' => $this->gapCounts(),
        ]);
    }

    /**
     * Порядок товаров — прямо из списка.
     *
     * То же поле, что «Место в разделе» в карточке, но менять его по
     * одному товару нельзя: чтобы переставить десяток позиций, приходилось
     * открыть и сохранить десять карточек. Здесь виден весь отфильтрованный
     * список, и порядок правится за один заход.
     *
     * Меньше число — выше товар: и в разделе, и в подборе «по
     * популярности», и в блоке популярных на главной.
     */
    public function saveOrder(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $changed = 0;
        foreach ((array) ($_POST['sort'] ?? []) as $id => $value) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $sort = max(0, (int) $value);
            // Пишем только изменившиеся: незачем трогать всю страницу
            // списка и переставлять метку правки у нетронутых товаров.
            $before = $db->value('SELECT sort FROM product WHERE id = ? AND deleted_at IS NULL', [$id]);
            if ($before === null || (int) $before === $sort) {
                continue;
            }
            $db->update('product', ['sort' => $sort, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            $changed++;
        }

        if ($changed > 0) {
            (new \Sklad\Core\Audit($db, $this->app->auth))
                ->write('product', 0, 'reorder', [], ['changed' => $changed]);
        }

        $this->flash($changed > 0
            ? 'Порядок сохранён: ' . $changed . ' поз. На витрине применится сразу.'
            : 'Ничего не изменилось.');

        // Возвращаем на ту же страницу списка с теми же условиями отбора.
        $back = (string) ($_POST['back'] ?? '');
        $this->app->redirect($this->app->adminPath('catalog')
            . ($back !== '' ? '?' . ltrim($back, '?') : ''));
    }

    /**
     * Создание.
     *
     * Спрашиваем только название и раздел — остального при заведении
     * товара обычно ещё не знают. Всё прочее правится в карточке, а
     * товар до первой публикации лежит скрытым.
     */
    public function create(array $params = []): string
    {
        return $this->render('admin/product-new', [
            'title' => 'Новый товар',
            'categories' => $this->app->db->all('SELECT id, title, path FROM category WHERE deleted_at IS NULL ORDER BY path'),
        ]);
    }

    public function store(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $title = $this->input('title', 255);
        $categoryId = $this->int('category_id');

        if ($title === '' || $categoryId === null) {
            $this->flash('Нужны название и раздел', 'bad');
            $this->app->redirect($this->app->adminPath('catalog/new'));
        }

        $slug = \Sklad\Model\Slug::unique($db, 'product', \Sklad\Model\Slug::make($title), $categoryId);
        $paths = new \Sklad\Model\Paths($db);
        $now = date('Y-m-d H:i:s');

        $id = $db->insert('product', [
            'category_id' => $categoryId,
            'slug' => $slug,
            'path' => $paths->productPath($categoryId, $slug),
            'title' => $title,
            // Новый товар скрыт: пока нет ни цены, ни фотографии,
            // показывать его покупателю нечего.
            'is_published' => 0,
            'sort' => (int) $db->value(
                'SELECT COALESCE(MAX(sort), 0) + 1 FROM product WHERE category_id = ?',
                [$categoryId]
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        (new \Sklad\Core\Audit($db, $this->app->auth))
            ->write('product', $id, 'create', [], ['title' => $title]);

        $this->flash('Товар заведён и пока скрыт. Заполните цену и фотографии, потом откройте на витрине.');
        $this->app->redirect($this->app->adminPath('catalog/' . $id));
    }

    public function edit(array $params): string
    {
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $product = $db->one('SELECT * FROM product WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($product === null) {
            http_response_code(404);
            $this->flash('Товар не найден', 'bad');
            $this->app->redirect($this->app->adminPath('catalog'));
        }

        // Набранное, если прошлое сохранение отказало, — поверх записи из
        // базы. Забирается один раз, как сообщение: иначе всплыло бы на
        // карточке, открытой следующей.
        $product = $this->oldInput() + $product;

        return $this->render('admin/product', [
            'title' => $product['title'],
            'product' => $product,
            // Правит ли эта роль цены — от этого зависит, показывать поля
            // правимыми или только для чтения. Сохранение их всё равно не
            // возьмёт, и правимое поле обмануло бы человека.
            'canPrice' => $this->app->auth->canPrice(),
            'categories' => $db->all('SELECT id, title, path FROM category WHERE deleted_at IS NULL ORDER BY path'),
            'options' => $this->optionsFor((int) $product['category_id'], $id),
            'images' => $db->all(
                'SELECT pi.id, pi.sort, m.file, m.width, m.height FROM product_image pi'
                . ' JOIN media m ON m.id = pi.media_id WHERE pi.product_id = ? ORDER BY pi.sort, pi.id',
                [$id]
            ),
            'history' => (new \Sklad\Core\Audit($db, $this->app->auth))->history('product', $id, 20),
            'linked' => $db->column('SELECT related_id FROM product_link WHERE product_id = ? ORDER BY sort', [$id]),
            'candidates' => $this->candidates($id, (int) $product['category_id']),
            // Что окажется в выдаче с учётом шаблона раздела: менеджер
            // должен видеть итог, а не гадать, сработает шаблон или нет.
            'effective' => (new \Sklad\Model\Meta($db))->forProduct($product),
            'limits' => [
                'title' => \Sklad\Model\Meta::TITLE_LIMIT,
                'description' => \Sklad\Model\Meta::DESCRIPTION_LIMIT,
            ],
        ]);
    }

    /**
     * Из чего выбирать похожие.
     *
     * Соседи по разделу — почти всегда то, что нужно, поэтому они идут
     * первыми. Плюс всё, что уже выбрано: связь могли поставить на товар
     * из другого раздела, и он не должен пропасть из списка при
     * следующем сохранении.
     */
    private function candidates(int $productId, int $categoryId): array
    {
        return $this->app->db->all(
            'SELECT p.id, p.title, p.article, c.title AS category_title'
            . ' FROM product p LEFT JOIN category c ON c.id = p.category_id'
            . ' WHERE p.deleted_at IS NULL AND p.id <> ?'
            . '   AND (p.category_id = ? OR p.id IN (SELECT related_id FROM product_link WHERE product_id = ?))'
            . ' ORDER BY c.path, p.sort, p.id',
            [$productId, $categoryId, $productId]
        );
    }

    public function save(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $before = $db->one('SELECT * FROM product WHERE id = ?', [$id]);
        if ($before === null) {
            $this->app->redirect($this->app->adminPath('catalog'));
        }

        $title = $this->input('title', 255);
        if ($title === '') {
            $this->flash('Без названия товар не сохранить', 'bad');
            $this->app->redirect($this->app->adminPath('catalog/' . $id));
        }

        $categoryId = $this->int('category_id');
        $slug = \Sklad\Model\Slug::make($this->input('slug', 190) ?: $title);
        $slug = \Sklad\Model\Slug::unique($db, 'product', $slug, $categoryId, $id);

        // Цены применяем не всякому: менеджеру каталог открыт, чтобы он
        // видел, что заказали, а назначает цену тот, кто за неё отвечает
        // (см. Auth::canPrice). Присланные им ценовые поля ниже не берутся
        // вовсе — поэтому и разбирать их незачем: описка в поле, которое
        // всё равно не сохранится, иначе не давала бы сохранить остальную
        // карточку.
        $canPrice = $this->app->auth->canPrice();

        // Цены проверяем до записи — оба поля одинаково.
        //
        // Непонятная строка это ошибка человека, а не ноль. Записанная
        // нулём, она рапортовала «Сохранено.», уводила товар в «Цена по
        // запросу» и роняла его из выгрузки для Яндекса, куда идут только
        // товары с ценой, — а менеджер узнавал об этом через месяц по
        // пропавшим заявкам. Поэтому возвращаем его в форму и называем
        // введённое: с двумя ценовыми полями иначе не понять, какое из них
        // и чем не угодило. Поле, которого форма не прислала, и пустое
        // поле сюда не попадают — money() отдаёт по ним законный ноль.
        foreach (['price' => 'Цена', 'old_price' => 'Старая цена'] as $key => $label) {
            if ($canPrice && $this->money($key) === null) {
                // Набранное кладём рядом с сообщением: после переадресации
                // форма перерисовывается из базы, и переписанное описание,
                // мета-теги и связи пропали бы вместе с опиской в цене.
                // Поправить нужно одно поле, а не заполнять карточку заново.
                $this->keepInput($this->postedFields());
                $this->flash(
                    $label . ': «' . $this->input($key, 100) . '» — это не число. Напишите цифрами:'
                    . ' 1 100 000 или 1100000,50. Пустое поле означает «цена по запросу».',
                    'bad'
                );
                $this->app->redirect($this->app->adminPath('catalog/' . $id));
            }
        }

        // Поля, которых форма не прислала, остаются как были: неполная
        // отправка не должна стирать заполненное.
        $fields = $this->onlyGiven([
            'article' => $this->input('article', 100),
            'price' => $this->money('price'),
            'old_price' => $this->money('old_price'),
            'promo_until' => $this->date('promo_until'),
            'vendor' => $this->input('vendor', 190),
            'made_in' => $this->input('made_in', 190),
            'intro' => $this->input('intro', 500),
            'body' => $this->body(),
            'h1' => $this->input('h1', 255),
            'canonical' => $this->input('canonical', 500),
            'meta_title' => $this->input('meta_title', 255),
            'meta_description' => $this->input('meta_description', 500),
            'sort' => max(0, (int) ($_POST['sort'] ?? 0)),
            'availability' => in_array($_POST['availability'] ?? '', ['in_stock', 'on_order', 'out'], true)
                ? $_POST['availability']
                : 'on_order',
        ]) + [
            'category_id' => $categoryId,
            'slug' => $slug,
            'title' => $title,
            'is_indexable' => empty($_POST['is_indexable']) ? 0 : 1,
            'is_published' => empty($_POST['is_published']) ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Кому цена не подчиняется — у того она остаётся прежней. Иначе
        // запрет обходился бы карточкой товара: то же самое, что кнопкой
        // на экране акций, только тихо. Срок акции здесь заодно с ценами:
        // на витрине скидка гаснет по нему, и оставь мы его правимым —
        // акцию снимали бы, не тронув ни одной цены.
        if (!$canPrice) {
            unset($fields['price'], $fields['old_price'], $fields['promo_until']);
        }

        $db->update('product', $fields, 'id = ?', [$id]);
        $this->saveLinks($id);

        // Адрес и переадресация — одной операцией. Половина этой работы
        // хуже, чем ничего: страница уедет, а ссылки на неё останутся.
        $paths = new \Sklad\Model\Paths($db);
        $newPath = $paths->productPath($categoryId, $slug);
        $moved = $paths->moveProduct($id, $newPath);

        $this->saveOptions($id, $categoryId);

        (new \Sklad\Core\Audit($db, $this->app->auth))->write('product', $id, 'update', $before, $fields);

        $this->flash($moved > 0
            ? 'Сохранено. Адрес изменился, со старого поставлена переадресация.'
            : 'Сохранено.');
        $this->app->redirect($this->app->adminPath('catalog/' . $id));
    }

    /**
     * Характеристики раздела со значениями товара.
     *
     * Список полей задаётся разделом: у тележек нет ёмкости батареи, и
     * показывать её в их карточке незачем.
     */
    private function optionsFor(int $categoryId, int $productId): array
    {
        return $this->app->db->all(
            'SELECT o.id, o.key, o.title, o.unit, o.group, o.type, po.value'
            . ' FROM `option` o'
            . ' JOIN category_option co ON co.option_id = o.id AND co.category_id = ?'
            . ' LEFT JOIN product_option po ON po.option_id = o.id AND po.product_id = ?'
            . ' ORDER BY o.group, co.sort, o.sort',
            [$categoryId, $productId]
        );
    }

    /**
     * Похожие товары.
     *
     * Порядок в блоке — порядок выбора в списке. Пустой выбор означает
     * «подбирай сам»: сайт покажет соседей по разделу.
     */
    private function saveLinks(int $productId): void
    {
        $db = $this->app->db;
        $chosen = array_values(array_unique(array_map('intval', (array) ($_POST['related'] ?? []))));

        $db->delete('product_link', 'product_id = ?', [$productId]);

        $sort = 0;
        foreach ($chosen as $relatedId) {
            if ($relatedId <= 0 || $relatedId === $productId) {
                continue;
            }
            $db->run(
                'INSERT IGNORE INTO product_link (product_id, related_id, sort) VALUES (?, ?, ?)',
                [$productId, $relatedId, $sort++]
            );
        }
    }

    private function saveOptions(int $productId, ?int $categoryId): void
    {
        $db = $this->app->db;
        $given = (array) ($_POST['option'] ?? []);

        // Значения от прежнего раздела убираем.
        //
        // Карточка показывает только те характеристики, что заведены у
        // текущего раздела. После переноса товара значения от старого в
        // форму не попадают — а в базе остаются, и витрина продолжает их
        // показывать. Убрать их было нечем: в карточке их не видно.
        $keep = array_map(
            static fn (array $o): int => (int) $o['id'],
            $this->optionsFor((int) $categoryId, $productId)
        );
        $db->run(
            'DELETE FROM product_option WHERE product_id = ?'
            . ($keep ? ' AND option_id NOT IN (' . implode(',', $keep) . ')' : ''),
            [$productId]
        );

        foreach ($this->optionsFor((int) $categoryId, $productId) as $option) {
            $value = trim((string) ($given[$option['id']] ?? ''));

            if ($value === '') {
                $db->delete('product_option', 'product_id = ? AND option_id = ?', [$productId, $option['id']]);
                continue;
            }

            // Число храним отдельно: по нему работает подбор «от и до».
            // Сравнивать диапазон по строке нельзя — «1000» окажется
            // меньше «900».
            $clean = str_replace([' ', "\xc2\xa0"], '', $value);
            $clean = str_replace(',', '.', $clean);
            $number = is_numeric($clean) ? (float) $clean : null;

            $db->run(
                'INSERT INTO product_option (product_id, option_id, value, value_num) VALUES (?, ?, ?, ?)'
                . ' ON DUPLICATE KEY UPDATE value = VALUES(value), value_num = VALUES(value_num)',
                [$productId, $option['id'], mb_substr($value, 0, 500), $number]
            );
        }
    }

    /**
     * Убрать товар с сайта.
     *
     * Помечаем удалённым, а не стираем. Стёртый уносит с собой адрес, а
     * на адресе висят ссылки из выдачи, из рекламы и из чужих каталогов.
     * Поэтому карточка пропадает с витрины и из админки, но её адрес
     * остаётся занят и ведёт переадресацией в раздел — покупатель
     * попадает к похожим моделям, а не на пустую страницу.
     *
     * Заявки, где этот товар заказывали, не трогаем: там своя копия
     * названия и цены, и заявка обязана остаться такой, какой её
     * оформили.
     */
    public function delete(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $product = $db->one('SELECT * FROM product WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($product === null) {
            $this->app->redirect($this->app->adminPath('catalog'));
        }

        $db->update(
            'product',
            ['deleted_at' => date('Y-m-d H:i:s'), 'is_published' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        // Ведём в раздел, а если товар был вне разделов — на главную.
        $to = $product['category_id']
            ? (string) $db->value('SELECT path FROM category WHERE id = ?', [$product['category_id']])
            : '/';
        if ($to !== '' && $to !== $product['path']) {
            $db->run(
                'INSERT INTO redirect (from_path, to_path, code, is_auto, hits, created_at)'
                . ' VALUES (?, ?, 301, 1, 0, ?)'
                . ' ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)',
                [$product['path'], $to, date('Y-m-d H:i:s')]
            );
        }

        (new \Sklad\Core\Audit($db, $this->app->auth))
            ->write('product', $id, 'delete', $product, ['deleted_at' => date('Y-m-d H:i:s')]);

        $this->flash('Товар убран с сайта. Со старого адреса поставлена переадресация в раздел.');
        $this->app->redirect($this->app->adminPath('catalog'));
    }

    /** Вернуть убранный товар на сайт. */
    public function restore(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $product = $db->one('SELECT * FROM product WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($product === null) {
            $this->app->redirect($this->app->adminPath('catalog?gap=deleted'));
        }

        // Раздел мог исчезнуть, пока товар лежал убранным.
        //
        // Опустевший раздел разрешено убрать, и наш товар в его пересчёте
        // не участвовал: там считаются только позиции с deleted_at IS NULL.
        // Так «Тележки» уходят следом за последним товаром, а через месяц
        // товар просят вернуть.
        //
        // Из двух выходов — вернуть, оставив переадресацию, или отказать —
        // выбран отказ. Возврат с оставленной переадресацией выглядит как
        // успех, но товара на витрине всё равно нет: Visible прячет всё,
        // что лежит под убранным разделом. Менеджер увидит товар в списке,
        // решит, что дело сделано, и разбираться пойдёт недели через две.
        // Отказ называет помеху сразу, ничего при этом не ломая: пока мы
        // не тронули строку, адрес товара по-прежнему отдаёт рабочий 301 в
        // раздел. И это уже знакомое поведение — раздел ровно так же не
        // даёт убрать себя, пока внутри есть непереселённое.
        $category = $product['category_id']
            ? $db->one('SELECT title, deleted_at FROM category WHERE id = ?', [(int) $product['category_id']])
            : null;
        // Товар вне разделов возвращаем как раньше: прятать его некому.
        if ($product['category_id'] && ($category === null || $category['deleted_at'] !== null)) {
            $this->flash(
                'Раздел товара ' . ($category === null ? 'не найден' : '«' . $category['title'] . '» убран')
                . '. Сначала верните раздел, потом товар: под убранным разделом карточка на витрине'
                . ' всё равно не покажется, а её адрес вместо переадресации в раздел начнёт отдавать 404.',
                'bad'
            );
            $this->app->redirect($this->app->adminPath('catalog?gap=deleted'));
        }

        $db->update(
            'product',
            ['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );

        // Переадресацию, поставленную при удалении, снимаем: иначе адрес
        // товара уводил бы в раздел, минуя саму карточку.
        $db->delete('redirect', 'from_path = ? AND is_auto = 1', [$product['path']]);

        (new \Sklad\Core\Audit($db, $this->app->auth))
            ->write('product', $id, 'restore', [], ['deleted_at' => '']);

        $this->flash('Товар возвращён. Проверьте галочку «Показывать на сайте» — она снята.');
        $this->app->redirect($this->app->adminPath('catalog/' . $id));
    }

    public function uploadImage(array $params): void
    {
        $this->guard();
        $id = (int) ($params['id'] ?? 0);

        $media = new \Sklad\Model\Media($this->app->db, $this->app->root() . '/public');
        $added = 0;
        $problem = null;

        // Порядковый номер продолжаем с последнего, а не начинаем с нуля:
        // переменная нигде не объявлялась, и первый загруженный файл получал
        // сортировку null — на витрине он становился главным снимком товара,
        // подвинув прежний. Заметно это только после загрузки.
        $nextSort = (int) $this->app->db->value(
            'SELECT COALESCE(MAX(sort), 0) + 1 FROM product_image WHERE product_id = ?',
            [$id]
        );

        // Файлы приходят массивом: менеджер выбирает сразу несколько.
        $files = $_FILES['photo'] ?? null;
        foreach ((array) ($files['name'] ?? []) as $index => $name) {
            try {
                $mediaId = $media->accept([
                    'name' => $name,
                    'tmp_name' => $files['tmp_name'][$index],
                    'error' => $files['error'][$index],
                ]);
                $this->app->db->run(
                    'INSERT IGNORE INTO product_image (product_id, media_id, alt, sort) VALUES (?, ?, ?, ?)',
                    [$id, $mediaId, '', $nextSort++]
                );
                $added++;
            } catch (\Throwable $e) {
                $problem = $e->getMessage();
            }
        }

        if ($added > 0) {
            $this->flash($added === 1 ? 'Фотография добавлена' : "Добавлено фотографий: {$added}");
        }
        if ($problem !== null) {
            $this->flash($problem, 'bad');
        }

        $this->app->redirect($this->app->adminPath('catalog/' . $id) . '#photos');
    }

    public function removeImage(array $params): void
    {
        $this->guard();
        $id = (int) ($params['id'] ?? 0);
        $imageId = (int) ($params['image'] ?? 0);

        // Убираем снимок у товара, но сам файл не трогаем: он может быть
        // привязан и к другой позиции — снимки моделей одной линейки
        // часто совпадают.
        $this->app->db->delete('product_image', 'id = ? AND product_id = ?', [$imageId, $id]);

        $this->flash('Фотография убрана');
        $this->app->redirect($this->app->adminPath('catalog/' . $id) . '#photos');
    }

    /**
     * Цена из формы: менеджер пишет «1 100 000» и «1100000,50».
     *
     * Различаем два разных случая, которые раньше сходились в один ноль.
     * Пустое поле — законный ноль: это «цена по запросу», так товар и
     * заводят, пока цену не согласовали. А непустая строка, которую
     * разобрать не вышло, возвращает null — это ошибка ввода, и разбирает
     * её вызывающий (см. save()). Пока обе дороги вели к нулю, «1.250.000»
     * и «1250000₽» молча обнуляли настоящую цену.
     */
    /**
     * Что человек набрал в форме карточки — чтобы вернуть ему это при отказе.
     *
     * Берём только присланное: поля, которых в отправке не было, подставит
     * запись из базы. Множественные поля (снимки, связанные товары) сюда не
     * идут — их рисует не значение, а отдельные списки.
     *
     * @return array<string, mixed>
     */
    private function postedFields(): array
    {
        $keys = [
            'title', 'slug', 'article', 'vendor', 'made_in', 'availability',
            'price', 'old_price', 'promo_until', 'sort', 'category_id',
            'meta_title', 'meta_description', 'canonical', 'intro', 'body',
        ];

        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $_POST) && !is_array($_POST[$key])) {
                $out[$key] = (string) $_POST[$key];
            }
        }
        $out['is_published'] = empty($_POST['is_published']) ? 0 : 1;
        $out['is_indexable'] = empty($_POST['is_indexable']) ? 0 : 1;

        return $out;
    }

    private function money(string $key): ?float
    {
        $raw = str_replace([' ', "\xc2\xa0"], '', (string) ($_POST[$key] ?? ''));
        $raw = str_replace(',', '.', $raw);

        if ($raw === '') {
            return 0.0;
        }

        return is_numeric($raw) ? max(0, (float) $raw) : null;
    }

    private function date(string $key): ?string
    {
        $raw = trim((string) ($_POST[$key] ?? ''));
        if ($raw === '') {
            return null;
        }
        $time = strtotime($raw);

        return $time === false ? null : date('Y-m-d', $time);
    }

    /**
     * Сколько товаров в каждой прорехе. Считается одним запросом:
     * четыре отдельных на каждую отрисовку списка — расточительно.
     */
    private function gapCounts(): array
    {
        $row = $this->app->db->one(
            'SELECT'
            . ' SUM(price <= 0) AS price,'
            . ' SUM(NOT EXISTS (SELECT 1 FROM product_image i WHERE i.product_id = p.id)) AS photo,'
            . " SUM(body IS NULL OR body = '') AS text,"
            . ' SUM(is_published = 0) AS hidden'
            . ' FROM product p WHERE p.deleted_at IS NULL'
        ) ?: [];

        $row['deleted'] = $this->app->db->value(
            'SELECT COUNT(*) FROM product WHERE deleted_at IS NOT NULL'
        );

        return array_map('intval', $row);
    }
}
