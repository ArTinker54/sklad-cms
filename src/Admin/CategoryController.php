<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;
use Sklad\Model\Paths;
use Sklad\Model\Slug;
use Sklad\Model\Tags;

/**
 * Разделы каталога.
 *
 * Здесь же задаётся, какие характеристики показывать в подборе раздела.
 * Это не мелочь: у тележек нет ёмкости батареи, и пустой фильтр в их
 * разделе читается как поломка сайта.
 */
final class CategoryController extends Controller
{
    protected string $section = 'catalog';

    public function index(array $params = []): string
    {
        $deleted = ($_GET['show'] ?? '') === 'deleted';

        $rows = $this->app->db->all(
            'SELECT c.*,'
            . ' (SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(c.path, \'%\')'
            . '   AND p.deleted_at IS NULL) AS goods'
            . ' FROM category c WHERE c.deleted_at IS ' . ($deleted ? 'NOT NULL' : 'NULL')
            . ' ORDER BY c.path'
        );

        return $this->render('admin/categories', [
            'title' => 'Разделы',
            'rows' => $rows,
            'deleted' => $deleted,
            'deletedCount' => (int) $this->app->db->value(
                'SELECT COUNT(*) FROM category WHERE deleted_at IS NOT NULL'
            ),
        ]);
    }

    /**
     * Новый раздел.
     *
     * Заводится сразу записью в базе и открывается обычной карточкой:
     * отдельная форма «создания» отличалась бы от формы правки, и
     * поддерживать пришлось бы две. Пока раздел не заполнили, он снят с
     * витрины — пустой раздел в меню хуже отсутствующего.
     */
    public function create(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $title = $this->input('title', 255);
        if ($title === '') {
            $this->flash('Название нового раздела не может быть пустым', 'bad');
            $this->app->redirect($this->app->adminPath('category'));
        }

        $parentId = $this->int('parent_id');

        // Внутрь убранного раздела не заводим.
        //
        // На экране убранных список «Внутри раздела» показывает как раз
        // убранные, и выбрать такого родителя проще всего по ошибке.
        // Новый раздел получил бы адрес внутри убранного, а витрина
        // прячет по началу адреса всё, что внутри: на сайте раздела нет,
        // галочка «Показывать на сайте» стоит, а причину увидеть негде.
        $parentSaid = '';
        if ($parentId !== null) {
            $parent = $db->one('SELECT title, deleted_at FROM category WHERE id = ?', [$parentId]);
            if ($parent !== null && $parent['deleted_at'] !== null) {
                $parentSaid = ' Раздел «' . $parent['title'] . '» убран с сайта,'
                    . ' поэтому новый заведён на верхнем уровне.';
                $parentId = null;
            }
        }

        $slug = \Sklad\Model\Slug::unique($db, 'category', \Sklad\Model\Slug::make($title), $parentId);
        $path = (new \Sklad\Model\Paths($db))->categoryPath($parentId, $slug);

        if ($db->value('SELECT id FROM category WHERE path = ?', [$path]) !== null) {
            $this->flash('Раздел с таким адресом уже есть', 'bad');
            $this->app->redirect($this->app->adminPath('category'));
        }

        // Адрес занят не только разделом: в любой установке есть /katalog/
        // и /akcii/. Резолвер разбирает адрес в порядке «страница, раздел,
        // товар», поэтому раздел с адресом страницы не спорит с ней, а
        // молча остаётся недостижимым.
        $taken = $this->pathTaken($path);
        if ($taken !== null) {
            $this->flash('Такой адрес уже занят: ' . $taken . '. Назовите раздел иначе.', 'bad');
            $this->app->redirect($this->app->adminPath('category'));
        }

        $now = date('Y-m-d H:i:s');
        $id = $db->insert('category', [
            'parent_id' => $parentId,
            'slug' => $slug,
            'path' => $path,
            'title' => $title,
            'is_published' => 0,
            'is_indexable' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        (new \Sklad\Core\Audit($db, $this->app->auth))->write('category', $id, 'create', [], ['title' => $title]);

        $this->flash('Раздел заведён. Заполните и поставьте галочку «Показывать на сайте».' . $parentSaid);
        $this->app->redirect($this->app->adminPath('category/' . $id));
    }

    /**
     * Кто уже занял этот адрес.
     *
     * Смотрим по всем трём таблицам, а не только по своей: адреса живут
     * в одном пространстве, и первым его разбирает Resolver — страница,
     * раздел, товар. Совпадение не даёт ошибки, оно молча прячет то, что
     * идёт следом: раздел с адресом страницы становится недостижим, а
     * прежний его адрес переадресуется на чужую страницу.
     *
     * @return string|null человеческое название занявшего, или null
     */
    private function pathTaken(string $path, int $exceptCategoryId = 0): ?string
    {
        $db = $this->app->db;

        $page = $db->one('SELECT title FROM page WHERE path = ? AND deleted_at IS NULL', [$path]);
        if ($page !== null) {
            return 'страница «' . $page['title'] . '»';
        }

        $category = $db->one(
            'SELECT title FROM category WHERE path = ? AND id <> ? AND deleted_at IS NULL',
            [$path, $exceptCategoryId]
        );
        if ($category !== null) {
            return 'раздел каталога «' . $category['title'] . '»';
        }

        // У товара адрес без косой на конце — сравниваем в его виде.
        $product = $db->one(
            'SELECT title FROM product WHERE path = ? AND deleted_at IS NULL',
            [rtrim($path, '/')]
        );

        return $product !== null ? 'товар «' . $product['title'] . '»' : null;
    }

    /**
     * Убрать раздел.
     *
     * Раздел с товарами или подразделами не трогаем: они остались бы
     * висеть по адресам внутри убранного, то есть в никуда. Сначала
     * переносим содержимое, потом убираем пустое — так менеджер видит,
     * что именно мешает, а не гадает, почему кнопка не сработала.
     */
    public function delete(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $category = $db->one('SELECT * FROM category WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($category === null) {
            $this->app->redirect($this->app->adminPath('category'));
        }

        $goods = (int) $db->value(
            'SELECT COUNT(*) FROM product WHERE path LIKE ? AND deleted_at IS NULL',
            [$category['path'] . '%']
        );
        $children = (int) $db->value(
            'SELECT COUNT(*) FROM category WHERE parent_id = ? AND deleted_at IS NULL',
            [$id]
        );
        if ($goods > 0 || $children > 0) {
            $this->flash(
                'Сначала перенесите то, что внутри: товаров ' . $goods . ', подразделов ' . $children . '.',
                'bad'
            );
            $this->app->redirect($this->app->adminPath('category/' . $id));
        }

        $db->update(
            'category',
            ['deleted_at' => date('Y-m-d H:i:s'), 'is_published' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
        $this->redirectAway($category['path'], $category['parent_id']);

        (new Audit($db, $this->app->auth))->write('category', $id, 'delete', $category, ['deleted_at' => 'убран']);

        $this->flash('Раздел убран. Со старого адреса поставлена переадресация.');
        $this->app->redirect($this->app->adminPath('category'));
    }

    public function restore(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $category = $db->one('SELECT * FROM category WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($category === null) {
            $this->app->redirect($this->app->adminPath('category?show=deleted'));
        }

        // Предок мог исчезнуть, пока раздел лежал убранным. Вернуть раздел
        // внутрь убранного — значит вернуть его в никуда: витрина прячет по
        // совпадению начала адреса и раздел, и все товары в нём, а в админке
        // будет стоять галочка «Показывать на сайте», и понять причину нечем.
        // Так же поступает и возврат товара: сперва верните раздел.
        $gone = $db->one(
            'SELECT title FROM category'
            . ' WHERE deleted_at IS NOT NULL AND id <> ? AND ? LIKE CONCAT(path, '%')'
            . ' ORDER BY LENGTH(path) DESC LIMIT 1',
            [$id, $category['path']]
        );
        if ($gone !== null) {
            $this->flash(
                'Раздел лежит внутри убранного «' . $gone['title'] . '» — там его не будет видно'
                . ' на сайте. Сначала верните его, потом этот.',
                'bad'
            );
            $this->app->redirect($this->app->adminPath('category?show=deleted'));
        }

        $db->update('category', ['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $db->delete('redirect', 'from_path = ? AND is_auto = 1', [$category['path']]);

        (new Audit($db, $this->app->auth))->write('category', $id, 'restore', [], ['deleted_at' => 'возвращён']);

        $this->flash('Раздел возвращён и пока скрыт — включите его галочкой.');
        $this->app->redirect($this->app->adminPath('category/' . $id));
    }

    /**
     * Переадресация с адреса убранного раздела.
     *
     * Ведём к родителю, а с верхнего уровня — на главную. Адрес раздела
     * обычно старше товаров внутри: на него ведут ссылки из выдачи и из
     * рекламы, и превращать его в «страница не найдена» дороже всего.
     *
     * Родителя ищем вверх по дереву, пока не найдётся живой: разделы
     * убирают пачками, сверху вниз, и ссылка на такого же убранного —
     * это переадресация в никуда. Поисковик считает такую цепочку тем
     * же «не найдено», только обнаруживает её позже.
     *
     * На верхнем уровне — главная, а не «/katalog/». Такого раздела нет
     * на втором сайте вовсе: там разделы лежат прямо в корне, и любой
     * убранный уводил бы на несуществующую страницу.
     */
    private function redirectAway(string $path, $parentId): void
    {
        $db = $this->app->db;

        $to = '/';
        $seen = [];
        while ($parentId !== null && !isset($seen[(int) $parentId])) {
            $seen[(int) $parentId] = true;
            $parent = $db->one(
                'SELECT id, path, parent_id, deleted_at FROM category WHERE id = ?',
                [$parentId]
            );
            if ($parent === null) {
                break;
            }
            if ($parent['deleted_at'] === null && $parent['path'] !== '' && $parent['path'] !== $path) {
                $to = (string) $parent['path'];
                break;
            }
            $parentId = $parent['parent_id'];
        }

        $db->run(
            'INSERT INTO redirect (from_path, to_path, code, is_auto, hits, created_at)'
            . ' VALUES (?, ?, 301, 1, 0, ?) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)',
            [$path, $to, date('Y-m-d H:i:s')]
        );
        $db->delete('redirect', 'from_path = to_path');
    }

    public function edit(array $params): string
    {
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $category = $db->one('SELECT * FROM category WHERE id = ?', [$id]);
        if ($category === null) {
            $this->flash('Раздел не найден', 'bad');
            $this->app->redirect($this->app->adminPath('category'));
        }

        // Пометка «родитель убран» и список родителей считаются по записи из
        // базы, а не по набранному: они говорят, где раздел лежит сейчас.
        $parentGone = $category['parent_id'] === null ? null : $db->one(
            'SELECT id, title FROM category WHERE id = ? AND deleted_at IS NOT NULL',
            [(int) $category['parent_id']]
        );
        $ownPath = (string) $category['path'];

        // Набранное, если прошлое сохранение отказало, — поверх записи из
        // базы. Забирается один раз, как сообщение.
        $category = $this->oldInput() + $category;

        return $this->render('admin/category', [
            'title' => $category['title'],
            'category' => $category,
            // Себя и своих потомков в родители не предлагаем: раздел,
            // ставший потомком самого себя, выпадает из дерева навсегда.
            //
            // Убранных не предлагаем вовсе — как в списке разделов у
            // товара. Здесь они стояли наравне с живыми, ничем не
            // помеченные, и выбор такого родителя уводил раздел под
            // убранный адрес: витрина прячет по началу адреса и сам
            // раздел, и все товары внутри, а галочка «Показывать на
            // сайте» при этом остаётся стоять, и причину не увидеть.
            'parents' => $db->all(
                'SELECT id, title, path FROM category'
                . ' WHERE id <> ? AND path NOT LIKE ? AND deleted_at IS NULL ORDER BY path',
                [$id, $ownPath . '_%']
            ),
            // Раздел, который уже лежит внутри убранного, показываем в
            // списке помеченным. Молча подставленный «верхний уровень»
            // при следующем «Сохранить» переставил бы раздел со всеми
            // товарами и завёл переадресации, которых никто не просил.
            'parentGone' => $parentGone,
            'options' => $db->all(
                'SELECT o.id, o.title, o.unit, o.group, o.is_filterable AS общая,'
                . ' co.category_id IS NOT NULL AS выбрана'
                . ' FROM `option` o'
                . ' LEFT JOIN category_option co ON co.option_id = o.id AND co.category_id = ?'
                . ' ORDER BY o.group, o.sort',
                [$id]
            ),
            'goods' => (int) $db->value(
                'SELECT COUNT(*) FROM product WHERE path LIKE ? AND deleted_at IS NULL',
                [$category['path'] . '%']
            ),
            // Городские тексты — отдельной вкладкой под основной формой.
            // Метки вида «{городе}» подставляют название, но страницы городов
            // остаются копиями друг друга; здесь пишут то, что для города
            // правда своё: доставка, сроки, местные объекты.
            'cities' => \Sklad\Model\City::all(),
            'cityContent' => (new \Sklad\Model\CityContent($db))->forEntity('category', $id),
        ]);
    }

    public function save(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $before = $db->one('SELECT * FROM category WHERE id = ?', [$id]);
        if ($before === null) {
            $this->app->redirect($this->app->adminPath('category'));
        }

        $title = $this->input('title', 255);
        if ($title === '') {
            $this->flash('Без названия раздел не сохранить', 'bad');
            $this->app->redirect($this->app->adminPath('category/' . $id));
        }

        $parentId = $this->int('parent_id');
        $wasParentId = $before['parent_id'] === null ? null : (int) $before['parent_id'];
        // Ещё одна проверка на потомка: список в форме можно подменить.
        $parentSaid = '';
        if ($parentId !== null) {
            $parent = $db->one('SELECT title, path, deleted_at FROM category WHERE id = ?', [$parentId]);
            $parentPath = (string) ($parent['path'] ?? '');
            if ($parentPath === '' || str_starts_with($parentPath, $before['path'])) {
                $parentId = $wasParentId;
            } elseif ($parent['deleted_at'] !== null && $parentId !== $wasParentId) {
                // Убранный родитель — то же самое, что и потомок: раздел
                // уезжает под мёртвый адрес. Витрина прячет по началу
                // адреса и сам раздел, и все товары внутри, а в админке
                // остаётся галочка «Показывать на сайте» — причину было
                // бы не найти. Оставляем прежнего родителя и говорим об
                // этом вслух: молчаливый откат читается как «не
                // сохранилось».
                $parentId = $wasParentId;
                $parentSaid = ' Раздел «' . $parent['title'] . '» убран с сайта, поэтому «Внутри раздела»'
                    . ' оставлено прежним: внутри убранного пропали бы с витрины и раздел, и товары в нём.';
            }
        }

        $slug = Slug::make($this->input('slug', 190) ?: $title);
        $slug = Slug::unique($db, 'category', $slug, $parentId, $id);

        // Занятый адрес отбиваем до записи полей, а не перед самым
        // переносом: иначе половина правки уже лежит в базе, адрес при
        // этом остался прежним, и человек читает «сохранено» там, где
        // переноса не было.
        $paths = new Paths($db);
        $newPath = $paths->categoryPath($parentId, $slug);
        $taken = $newPath !== $before['path'] ? $this->pathTaken($newPath, $id) : null;
        if ($taken !== null) {
            // Набранное кладём рядом с сообщением: без этого переписанные
            // тексты и мета-теги пропадали вместе с занятым адресом,
            // из-за которого отказ и вышел.
            $this->keepInput($this->postedFields());
            $this->flash(
                'Такой адрес уже занят: ' . $taken . '. Раздел не сохранён — задайте другой адрес.',
                'bad'
            );
            $this->app->redirect($this->app->adminPath('category/' . $id));
        }

        $fields = [
            'parent_id' => $parentId,
            'slug' => $slug,
            'title' => $title,
            'h1' => $this->input('h1', 255),
            'meta_title' => $this->input('meta_title', 255),
            'meta_description' => $this->input('meta_description', 500),
            'intro' => $this->body('intro'),
            'body' => $this->body(),
            // Чистим при сохранении, а не при показе: администратор должен
            // увидеть в поле ровно тот список, который попал на сайт, —
            // иначе строку с опечаткой он будет считать работающей.
            'tags' => Tags::normalize((string) ($_POST['tags'] ?? '')),
            'product_title_tpl' => $this->input('product_title_tpl', 255),
            'product_description_tpl' => $this->input('product_description_tpl', 500),
            'is_indexable' => empty($_POST['is_indexable']) ? 0 : 1,
            'sort' => max(0, (int) ($_POST['sort'] ?? 0)),
            'is_published' => empty($_POST['is_published']) ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->update('category', $fields, 'id = ?', [$id]);

        // Смена адреса тянет за собой вложенные разделы и все товары
        // внутри — и на каждый старый адрес встаёт переадресация.
        $moved = $paths->moveCategory($id, $newPath);

        $this->saveOptions($id);

        (new Audit($db, $this->app->auth))->write('category', $id, 'update', $before, $fields);

        $said = $moved > 0
            ? "Сохранено. Адрес изменился, переадресаций поставлено: {$moved}."
            : 'Сохранено.';
        $said .= $parentSaid;

        // Строка «в поле осталось то, что показывается» верна по виду, но
        // не по сути: опечатка в адресе вида тоже не нарушает. Такую
        // ссылку человек считает рабочей, а посетитель попадает в «страница
        // не найдена». Строку не выбрасываем — раздел под неё могут завести
        // завтра, — но говорим вслух.
        $unknown = Tags::missing($db, array_column(Tags::parse($fields['tags']), 'url'));
        if ($unknown) {
            $said .= ' В «Популярных запросах» на сайте не найдено: '
                . implode(', ', $unknown) . '. Пока такие ссылки не показываются.';
        }

        $this->saveCityContent($id);

        $this->flash($said);
        $this->app->redirect($this->app->adminPath('category/' . $id));
    }

    /**
     * Городские тексты раздела.
     *
     * Приходят вложенным массивом city[слаг][поле]. Сохраняем только те
     * города, что были в отправке: форма показывает по одному городу за раз,
     * и если сохранять все, остальные обнулились бы пустыми полями.
     *
     * Слаг сверяем со списком городов. Без сверки в таблицу попал бы любой
     * набор символов из подделанной формы, и на витрине он никогда бы не
     * совпал ни с одним поддоменом — запись лежала бы мёртвым грузом, а
     * редактор гадал, почему текст не показывается.
     */
    private function saveCityContent(int $id): void
    {
        $posted = $_POST['city'] ?? null;
        if (!is_array($posted)) {
            return;
        }

        $known = array_column(\Sklad\Model\City::all(), 'slug');
        $store = new \Sklad\Model\CityContent($this->app->db);
        $who = $this->app->auth->user()['email'] ?? null;

        foreach ($posted as $slug => $values) {
            $slug = (string) $slug;
            if (!is_array($values) || !in_array($slug, $known, true)) {
                continue;
            }
            $store->save('category', $id, $slug, $values, $who);
        }
    }

    /**
     * Что человек набрал в форме раздела — чтобы вернуть ему это при отказе.
     *
     * Только присланное: поля, которых в отправке не было, подставит запись
     * из базы. Отмеченные характеристики сюда не идут — их рисует отдельный
     * список, а не значение поля.
     *
     * @return array<string, mixed>
     */
    private function postedFields(): array
    {
        $keys = [
            'title', 'h1', 'slug', 'sort', 'parent_id', 'meta_title',
            'meta_description', 'intro', 'body', 'tags',
            'product_title_tpl', 'product_description_tpl',
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

    /** Какие характеристики показывать в подборе этого раздела. */
    private function saveOptions(int $categoryId): void
    {
        $db = $this->app->db;
        $chosen = array_map('intval', (array) ($_POST['option'] ?? []));

        $db->delete('category_option', 'category_id = ?', [$categoryId]);
        foreach ($chosen as $sort => $optionId) {
            if ($optionId > 0) {
                $db->run(
                    'INSERT IGNORE INTO category_option (category_id, option_id, is_filterable, sort) VALUES (?, ?, 1, ?)',
                    [$categoryId, $optionId, $sort]
                );
            }
        }
    }
}
