<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;
use Sklad\Model\Paths;
use Sklad\Model\Slug;
use Sklad\Model\TagPages;
use Sklad\Model\Visible;

/**
 * Теги — страницы-подборки внутри раздела (05.10.2026).
 *
 * Заказчик: «надо, чтобы мы выбирали товары, которые туда будут
 * дублироваться». Тег заводится у раздела, получает свой адрес под ним,
 * заголовок и тексты, а товары в него отбираются здесь или галочкой в
 * карточке товара. Ссылка на тег сама встаёт в «Популярные запросы»
 * раздела — вписывать её туда руками не нужно.
 *
 * Права — как у каталога: кто правит разделы и товары, тот и теги.
 */
final class TagController extends Controller
{
    protected string $section = 'catalog';

    public function index(array $params = []): string
    {
        $db = $this->app->db;
        $deleted = ($_GET['show'] ?? '') === 'deleted';
        $only = (int) ($_GET['category'] ?? 0);

        $rows = $db->all(
            'SELECT t.*, c.title AS category_title, c.path AS category_path,'
            . ' (SELECT COUNT(*) FROM tag_product tp JOIN product p ON p.id = tp.product_id'
            . '   WHERE tp.tag_id = t.id AND p.deleted_at IS NULL) AS goods,'
            . ' (SELECT COUNT(*) FROM tag_product tp JOIN product p ON p.id = tp.product_id'
            . '   WHERE tp.tag_id = t.id AND ' . Visible::product('p') . ') AS shown,'
            . ' (SELECT COUNT(*) FROM tag tv WHERE tv.id = t.id AND ' . Visible::tag('tv') . ') AS visible'
            . ' FROM tag t JOIN category c ON c.id = t.category_id'
            . ' WHERE t.deleted_at IS ' . ($deleted ? 'NOT NULL' : 'NULL')
            . ($only > 0 ? ' AND t.category_id = ' . $only : '')
            . ' ORDER BY c.path, t.sort, t.title'
        );

        return $this->render('admin/tags', [
            'title' => 'Теги',
            'rows' => $rows,
            'deleted' => $deleted,
            'only' => $only,
            'categories' => $this->categories(),
            'deletedCount' => (int) $db->value('SELECT COUNT(*) FROM tag WHERE deleted_at IS NOT NULL'),
        ]);
    }

    /**
     * Новый тег — сразу записью, дальше обычная карточка (как у разделов).
     * Пока не заполнили, он скрыт: пустая подборка в «Популярных запросах»
     * — ссылка на «пока пусто».
     */
    public function create(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $title = $this->input('title', 255);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $back = $this->app->adminPath('tag' . ($categoryId > 0 ? '?category=' . $categoryId : ''));

        if ($title === '') {
            $this->flash('Название нового тега не может быть пустым', 'bad');
            $this->app->redirect($back);
        }
        $category = $db->one('SELECT id, title, deleted_at FROM category WHERE id = ?', [$categoryId]);
        if ($category === null || $category['deleted_at'] !== null) {
            $this->flash('Выберите раздел, внутри которого будет тег', 'bad');
            $this->app->redirect($back);
        }

        $slug = Slug::unique($db, 'tag', Slug::make($title), $categoryId);
        $path = (new Paths($db))->tagPath($categoryId, $slug);
        $taken = $this->pathTaken($path);
        if ($taken !== null) {
            $this->flash('Такой адрес уже занят: ' . $taken . '. Назовите тег иначе.', 'bad');
            $this->app->redirect($back);
        }

        $now = date('Y-m-d H:i:s');
        $id = $db->insert('tag', [
            'category_id' => $categoryId,
            'slug' => $slug,
            'path' => $path,
            'title' => $title,
            'sort' => 10 * (1 + (int) $db->value(
                'SELECT COUNT(*) FROM tag WHERE category_id = ? AND deleted_at IS NULL',
                [$categoryId]
            )),
            'is_published' => 0,
            'is_indexable' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        (new Audit($db, $this->app->auth))->write('tag', $id, 'create', [], ['title' => $title, 'path' => $path]);

        $this->flash('Тег заведён. Отберите товары, заполните тексты и поставьте галочку «Показывать на сайте».');
        $this->app->redirect($this->app->adminPath('tag/' . $id));
    }

    public function edit(array $params): string
    {
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $tag = $db->one('SELECT * FROM tag WHERE id = ?', [$id]);
        if ($tag === null) {
            $this->flash('Тег не найден', 'bad');
            $this->app->redirect($this->app->adminPath('tag'));
        }
        $tag = $this->oldInput() + $tag;

        $chosen = $db->all(
            'SELECT p.id, p.title, p.article, p.path, p.is_published, tp.sort, c.title AS category_title,'
            . ' (' . Visible::product('p') . ') AS visible'
            . ' FROM tag_product tp JOIN product p ON p.id = tp.product_id'
            . ' LEFT JOIN category c ON c.id = p.category_id'
            . ' WHERE tp.tag_id = ? AND p.deleted_at IS NULL'
            . ' ORDER BY tp.sort, p.sort, p.id',
            [$id]
        );
        $taken = array_flip(array_map(static fn (array $row): int => (int) $row['id'], $chosen));

        // Из чего добавлять — весь каталог, разложенный по разделам. Раздел
        // тега первым: из него берут чаще всего.
        $pool = [];
        $titles = (new TagPages($db))->sectionTitles();
        foreach ($db->all(
            'SELECT p.id, p.title, p.article, p.is_published, c.id AS category_id, c.title AS category_title, c.path AS category_path'
            . ' FROM product p LEFT JOIN category c ON c.id = p.category_id'
            . ' WHERE p.deleted_at IS NULL'
            . ' ORDER BY c.path, p.sort, p.id'
        ) as $row) {
            if (isset($taken[(int) $row['id']])) {
                continue;
            }
            $key = (string) ($row['category_path'] ?? '');
            $pool[$key]['title'] = $titles[$key] ?? (string) ($row['category_title'] ?? 'Без раздела');
            $pool[$key]['rows'][] = $row;
        }
        $home = (string) $db->value('SELECT path FROM category WHERE id = ?', [(int) $tag['category_id']]);
        uksort($pool, static function (string $a, string $b) use ($home): int {
            $ia = $home !== '' && str_starts_with($a, $home) ? 0 : 1;
            $ib = $home !== '' && str_starts_with($b, $home) ? 0 : 1;

            return [$ia, $a] <=> [$ib, $b];
        });

        return $this->render('admin/tag', [
            'title' => $tag['title'],
            'tag' => $tag,
            'categories' => $this->categories(),
            'parentGone' => $db->one(
                'SELECT id, title FROM category WHERE id = ? AND deleted_at IS NOT NULL',
                [(int) $tag['category_id']]
            ),
            'chosen' => $chosen,
            'pool' => $pool,
            'cities' => \Sklad\Model\City::all(),
            'cityContent' => (new \Sklad\Model\CityContent($db))->forEntity('tag', $id),
            'visible' => (bool) $db->value('SELECT COUNT(*) FROM tag t WHERE t.id = ? AND ' . Visible::tag('t'), [$id]),
        ]);
    }

    public function save(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $before = $db->one('SELECT * FROM tag WHERE id = ?', [$id]);
        if ($before === null) {
            $this->app->redirect($this->app->adminPath('tag'));
        }

        $title = $this->input('title', 255);
        if ($title === '') {
            $this->flash('Без названия тег не сохранить', 'bad');
            $this->app->redirect($this->app->adminPath('tag/' . $id));
        }

        // Раздел: только живой. Убранный — то же, что «никуда»: витрина
        // прячет по началу адреса и сам тег.
        $categoryId = (int) ($_POST['category_id'] ?? $before['category_id']);
        $said = '';
        if ($categoryId !== (int) $before['category_id']) {
            $category = $db->one('SELECT title, deleted_at FROM category WHERE id = ?', [$categoryId]);
            if ($category === null || $category['deleted_at'] !== null) {
                $said = $category === null ? '' : ' Раздел «' . $category['title'] . '» убран с сайта,'
                    . ' поэтому тег оставлен в прежнем.';
                $categoryId = (int) $before['category_id'];
            }
        }

        $slug = Slug::make($this->input('slug', 190) ?: $title);
        $slug = Slug::unique($db, 'tag', $slug, $categoryId, $id);

        // Убранный тег адрес не меняет: с прежнего стоит переадресация в
        // раздел, и перенос адреса увёл бы её на адрес убранного — в 404.
        if ($before['deleted_at'] !== null
            && ($slug !== $before['slug'] || $categoryId !== (int) $before['category_id'])) {
            $slug = (string) $before['slug'];
            $categoryId = (int) $before['category_id'];
            $said .= ' Тег убран, поэтому адрес и раздел оставлены прежними — поменять их можно после возврата.';
        }

        $paths = new Paths($db);
        $newPath = $paths->tagPath($categoryId, $slug);
        $taken = $newPath !== $before['path'] ? $this->pathTaken($newPath, $id) : null;
        if ($taken !== null) {
            $this->keepInput($this->postedFields());
            $this->flash('Такой адрес уже занят: ' . $taken . '. Тег не сохранён — задайте другой адрес.', 'bad');
            $this->app->redirect($this->app->adminPath('tag/' . $id));
        }

        $fields = [
            'category_id' => $categoryId,
            'slug' => $slug,
            'title' => $title,
            'h1' => $this->input('h1', 255),
            'meta_title' => $this->input('meta_title', 255),
            'meta_description' => $this->input('meta_description', 500),
            'intro' => $this->body('intro'),
            'body' => $this->body(),
            'sort' => max(0, (int) ($_POST['sort'] ?? 0)),
            'is_published' => empty($_POST['is_published']) ? 0 : 1,
            'is_indexable' => empty($_POST['is_indexable']) ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->update('tag', $fields, 'id = ?', [$id]);
        $moved = $paths->moveTag($id, $newPath);

        // Состав тега. Только если форма его прислала: без этой метки
        // неполная отправка очистила бы подборку целиком.
        $change = null;
        if (isset($_POST['products_present'])) {
            $sorts = [];
            foreach ((array) ($_POST['sort_of'] ?? []) as $productId => $value) {
                $sorts[(int) $productId] = (int) $value;
            }
            $change = (new TagPages($db))->setProducts(
                $id,
                (array) ($_POST['shown'] ?? []),
                (array) ($_POST['product'] ?? []),
                $sorts,
                (array) ($_POST['add'] ?? [])
            );
        }

        $this->saveCityContent($id);

        $audit = $fields;
        if ($change !== null && $change['before'] !== $change['after']) {
            $audit['товары'] = implode(', ', $change['after']);
            $before['товары'] = implode(', ', $change['before']);
        }
        (new Audit($db, $this->app->auth))->write('tag', $id, 'update', $before, $audit);

        $said = ($moved > 0 ? 'Сохранено. Адрес изменился, со старого поставлена переадресация.' : 'Сохранено.') . $said;

        // Говорим вслух то, из-за чего тег не виден на сайте: галочку и
        // пустой состав легко не заметить, а человек ждёт страницу.
        $shown = (int) $db->value(
            'SELECT COUNT(*) FROM tag_product tp JOIN product p ON p.id = tp.product_id'
            . ' WHERE tp.tag_id = ? AND ' . Visible::product('p'),
            [$id]
        );
        if (!$fields['is_published']) {
            $said .= ' Тег скрыт — на сайте его нет, пока не поставите «Показывать на сайте».';
        } elseif ($shown === 0) {
            $said .= ' В теге нет ни одного товара, видимого на сайте: страница откроется пустой'
                . ' и в «Популярных запросах» не появится.';
        }

        $this->flash($said);
        $this->app->redirect($this->app->adminPath('tag/' . $id));
    }

    /** Убрать тег: адрес ведёт в раздел, ссылки из выдачи не пропадают. */
    public function delete(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $tag = $db->one('SELECT * FROM tag WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($tag === null) {
            $this->app->redirect($this->app->adminPath('tag'));
        }

        $now = date('Y-m-d H:i:s');
        $db->update('tag', ['deleted_at' => $now, 'is_published' => 0, 'updated_at' => $now], 'id = ?', [$id]);

        $home = $db->one(
            'SELECT title, path FROM category WHERE id = ? AND deleted_at IS NULL',
            [(int) $tag['category_id']]
        );
        $to = (string) ($home['path'] ?? '/');
        $db->run(
            'INSERT INTO redirect (from_path, to_path, code, is_auto, hits, created_at)'
            . ' VALUES (?, ?, 301, 1, 0, ?) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)',
            [$tag['path'], $to, $now]
        );
        $db->delete('redirect', 'from_path = to_path');

        (new Audit($db, $this->app->auth))->write('tag', $id, 'delete', $tag, ['deleted_at' => 'убран']);

        $this->flash($home !== null
            ? 'Тег убран. Его адрес теперь ведёт в раздел «' . $home['title'] . '».'
            : 'Тег убран. Его адрес теперь ведёт на главную.');
        $this->app->redirect($this->app->adminPath('tag?category=' . (int) $tag['category_id']));
    }

    public function restore(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $tag = $db->one('SELECT * FROM tag WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($tag === null) {
            $this->app->redirect($this->app->adminPath('tag?show=deleted'));
        }

        // Раздел тега мог убраться, пока тег лежал убранным: вернуть тег
        // внутрь убранного — значит вернуть его в никуда (так же у товара).
        $home = $db->one('SELECT title, deleted_at FROM category WHERE id = ?', [(int) $tag['category_id']]);
        if ($home === null || $home['deleted_at'] !== null) {
            $this->flash(
                'Тег лежит в убранном разделе' . ($home !== null ? ' «' . $home['title'] . '»' : '')
                . ' — там его не будет видно на сайте. Сначала верните раздел, потом тег.',
                'bad'
            );
            $this->app->redirect($this->app->adminPath('tag?show=deleted'));
        }

        // Пока тег лежал убранным, его адрес мог занять раздел или товар.
        $taken = $this->pathTaken((string) $tag['path'], $id);
        if ($taken !== null) {
            $this->flash('Вернуть нельзя: адрес тега уже занят — ' . $taken . '.', 'bad');
            $this->app->redirect($this->app->adminPath('tag?show=deleted'));
        }

        $db->update('tag', ['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $db->delete('redirect', 'from_path = ? AND is_auto = 1', [$tag['path']]);

        (new Audit($db, $this->app->auth))->write('tag', $id, 'restore', [], ['deleted_at' => 'возвращён']);

        $this->flash('Тег возвращён и пока скрыт — включите его галочкой.');
        $this->app->redirect($this->app->adminPath('tag/' . $id));
    }

    /** Разделы для выбора «внутри раздела» — живые, деревом. */
    private function categories(): array
    {
        return $this->app->db->all(
            'SELECT id, title, path, is_published FROM category WHERE deleted_at IS NULL ORDER BY path'
        );
    }

    /**
     * Кто уже занял этот адрес — страница, раздел, товар или другой тег.
     * Адреса живут в одном пространстве, и совпадение молча прячет того,
     * кто разбирается позже (Resolver: страница, раздел, тег, товар).
     */
    private function pathTaken(string $path, int $exceptTagId = 0): ?string
    {
        $db = $this->app->db;

        $page = $db->one('SELECT title FROM page WHERE path = ? AND deleted_at IS NULL', [$path]);
        if ($page !== null) {
            return 'страница «' . $page['title'] . '»';
        }
        $category = $db->one('SELECT title FROM category WHERE path = ? AND deleted_at IS NULL', [$path]);
        if ($category !== null) {
            return 'раздел каталога «' . $category['title'] . '»';
        }
        $tag = $db->one(
            'SELECT title FROM tag WHERE path = ? AND id <> ? AND deleted_at IS NULL',
            [$path, $exceptTagId]
        );
        if ($tag !== null) {
            return 'тег «' . $tag['title'] . '»';
        }
        $product = $db->one(
            'SELECT title FROM product WHERE path = ? AND deleted_at IS NULL',
            [rtrim($path, '/')]
        );

        return $product !== null ? 'товар «' . $product['title'] . '»' : null;
    }

    /** Городские тексты тега — так же, как у раздела (CategoryController). */
    private function saveCityContent(int $id): void
    {
        $posted = $_POST['city'] ?? null;
        if (!is_array($posted)) {
            return;
        }

        $known = array_column(\Sklad\Model\City::all(), 'slug');
        $store = new \Sklad\Model\CityContent($this->app->db);
        $who = $this->app->auth->user()['email'] ?? null;
        $stored = $store->forEntity('tag', $id);

        foreach ($posted as $slug => $values) {
            $slug = (string) $slug;
            if (!is_array($values) || !in_array($slug, $known, true)) {
                continue;
            }
            // Разметку чистим только у изменённого и только не у администратора —
            // как в разделе: в форме приходят все 75 городов.
            if (($this->app->auth->user()['role'] ?? '') !== 'admin') {
                foreach (['intro', 'body'] as $f) {
                    if (isset($values[$f])
                        && trim((string) $values[$f]) !== trim((string) ($stored[$slug][$f] ?? ''))) {
                        $values[$f] = \Sklad\Model\Html::clean((string) $values[$f]);
                    }
                }
            }
            $store->save('tag', $id, $slug, $values, $who);
        }
    }

    /** @return array<string, mixed> набранное в форме — вернуть при отказе */
    private function postedFields(): array
    {
        $out = [];
        foreach (['title', 'slug', 'h1', 'meta_title', 'meta_description', 'intro', 'body', 'sort'] as $key) {
            if (array_key_exists($key, $_POST)) {
                $out[$key] = (string) $_POST[$key];
            }
        }
        if (array_key_exists('category_id', $_POST)) {
            $out['category_id'] = (int) $_POST['category_id'];
        }
        $out['is_published'] = empty($_POST['is_published']) ? 0 : 1;
        $out['is_indexable'] = empty($_POST['is_indexable']) ? 0 : 1;

        return $out;
    }
}
