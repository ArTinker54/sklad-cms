<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;
use Sklad\Model\Paths;
use Sklad\Model\Slug;

/**
 * Страницы и блоки текста.
 *
 * Блоки — то, что живёт вне страниц: подпись в подвале, реквизиты,
 * оговорка про оферту, короткий текст о доставке в карточке товара.
 * Меняются они чаще всего, и требовать ради них разработчика было бы
 * издевательством.
 */
final class ContentController extends Controller
{
    protected string $section = 'content';

    /**
     * Виды страниц, которые нельзя убрать.
     *
     * Это не тексты, а части сайта: на корзину ведёт кнопка из шапки, на
     * сравнение и избранное — значки, на поиск — строка. Убери такую
     * страницу, и кнопка поведёт в никуда, а заметит это покупатель.
     */
    private const SERVICE = ['cart', 'compare', 'favorites', 'search', 'contacts', 'catalog', 'promo'];

    public function pages(array $params = []): string
    {
        return $this->render('admin/pages', [
            'title' => 'Страницы',
            'rows' => $this->app->db->all(
                'SELECT * FROM page WHERE deleted_at IS '
                . (($_GET['show'] ?? '') === 'deleted' ? 'NOT NULL' : 'NULL')
                . ' ORDER BY sort, id'
            ),
            'deleted' => ($_GET['show'] ?? '') === 'deleted',
            'deletedCount' => (int) $this->app->db->value(
                'SELECT COUNT(*) FROM page WHERE deleted_at IS NOT NULL'
            ),
            'blocks' => $this->app->db->all('SELECT * FROM block ORDER BY `key`'),
        ]);
    }

    /**
     * Кто уже занял этот адрес.
     *
     * Смотрим по всем трём таблицам, а не только по своей: адреса живут
     * в одном пространстве, и первым его разбирает Resolver — страница,
     * раздел, товар. Совпадение не даёт ошибки, оно молча прячет то, что
     * идёт следом.
     *
     * @return string|null человеческое название занявшего, или null
     */
    private function pathTaken(string $path, int $exceptPageId = 0): ?string
    {
        $db = $this->app->db;

        $page = $db->one(
            'SELECT title FROM page WHERE path = ? AND id <> ? AND deleted_at IS NULL',
            [$path, $exceptPageId]
        );
        if ($page !== null) {
            return 'страница «' . $page['title'] . '»';
        }

        $category = $db->one('SELECT title FROM category WHERE path = ? AND deleted_at IS NULL', [$path]);
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

    public function editPage(array $params): string
    {
        $id = (int) ($params['id'] ?? 0);
        $page = $this->app->db->one('SELECT * FROM page WHERE id = ?', [$id]);
        if ($page === null) {
            $this->flash('Страница не найдена', 'bad');
            $this->app->redirect($this->app->adminPath('page'));
        }

        // Набранное перед отказом — поверх сохранённого. Адрес, вид и
        // пометку об удалении берём только из базы: их форма не шлёт, а
        // от них зависит, что вообще можно делать со страницей.
        $page = $this->oldInput() + $page;

        return $this->render('admin/page', [
            'title' => $page['title'],
            'page' => $page,
            // Можно ли убрать — решает контроллер, а не шаблон. Иначе
            // список служебных видов лежал бы в двух местах, и однажды
            // кнопка появилась бы там, где действие её отбивает.
            'canDelete' => $page['path'] !== '/' && !in_array($page['template'], self::SERVICE, true),
            // Городские тексты — тем же блоком, что у разделов. Страницы вроде
            // «Доставки» и «О компании» для городской выдачи важнее каталога:
            // именно там пишут про сроки и условия в конкретный город.
            'cities' => \Sklad\Model\City::all(),
            'cityContent' => (new \Sklad\Model\CityContent($this->app->db))
                ->forEntity('page', $id),
        ]);
    }

    /**
     * Городские тексты страницы.
     *
     * Слаг сверяем со списком: без сверки в таблицу попал бы любой набор
     * символов из подделанной формы и лежал бы там мёртвым грузом — на
     * витрине он не совпал бы ни с одним поддоменом.
     */
    private function savePageCityContent(int $id): void
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
            $store->save('page', $id, $slug, $values, $who);
        }
    }

    /**
     * Новая страница.
     *
     * Заводится обычной текстовой: остальные виды — корзина, сравнение,
     * поиск — это разметка со скриптом, и создавать их из админки
     * незачем. Снята с витрины, пока не заполнили.
     */
    public function createPage(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $title = $this->input('title', 255);
        if ($title === '') {
            $this->flash('Название новой страницы не может быть пустым', 'bad');
            $this->app->redirect($this->app->adminPath('page'));
        }

        // Название из одних знаков препинания даёт пустой адрес, а
        // страница по адресу «//» недостижима. Подставляем запасной.
        $slug = Slug::make($title) ?: 'stranica';
        $path = '/' . $slug . '/';

        // Занятость смотрим и по разделам каталога.
        //
        // Адрес разбирается сверху вниз — сначала страницы, потом разделы.
        // Страница с адресом раздела не «спорит» с ним, а молча его
        // заслоняет: раздел пропадает с сайта, оставаясь в админке
        // показанным, и вместе с ним из выдачи уходит страница, по
        // которой сайт стоял по запросу «купить штабелёр».
        if ($this->pathTaken($path) !== null) {
            $this->flash('Такой адрес уже занят: ' . $this->pathTaken($path), 'bad');
            $this->app->redirect($this->app->adminPath('page'));
        }

        $now = date('Y-m-d H:i:s');
        $id = $db->insert('page', [
            'slug' => $slug,
            'path' => $path,
            'title' => $title,
            'template' => 'page',
            'is_published' => 0,
            'is_indexable' => 1,
            'sort' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->flash('Страница заведена. Заполните и поставьте галочку «Показывать на сайте».');
        $this->app->redirect($this->app->adminPath('page/' . $id));
    }

    /**
     * Убрать страницу.
     *
     * Служебные — корзину, сравнение, поиск, контакты — убрать нельзя:
     * на них ведут кнопки из шапки и корзины, и без них сайт сломается
     * молча. Главную тоже: её адрес это адрес сайта.
     */
    public function deletePage(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $page = $db->one('SELECT * FROM page WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($page === null) {
            $this->app->redirect($this->app->adminPath('page'));
        }
        if ($page['path'] === '/' || in_array($page['template'], self::SERVICE, true)) {
            $this->flash('Эта страница служебная: на неё ведут кнопки сайта. Её можно только скрыть.', 'bad');
            $this->app->redirect($this->app->adminPath('page/' . $id));
        }

        $now = date('Y-m-d H:i:s');
        $db->update('page', ['deleted_at' => $now, 'is_published' => 0, 'updated_at' => $now], 'id = ?', [$id]);

        // Ведём на главную: у текстовой страницы нет естественного
        // родителя, а «страница не найдена» на месте бывшей «Доставки»
        // хуже, чем главная.
        $db->run(
            'INSERT INTO redirect (from_path, to_path, code, is_auto, hits, created_at)'
            . ' VALUES (?, ?, 301, 1, 0, ?) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)',
            [$page['path'], '/', $now]
        );
        $db->delete('redirect', 'from_path = to_path');

        (new Audit($db, $this->app->auth))->write('page', $id, 'delete', $page, ['deleted_at' => 'убрана']);

        $this->flash('Страница убрана. Со старого адреса поставлена переадресация на главную.');
        $this->app->redirect($this->app->adminPath('page'));
    }

    public function restorePage(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $page = $db->one('SELECT * FROM page WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($page === null) {
            $this->app->redirect($this->app->adminPath('page?show=deleted'));
        }

        $db->update('page', ['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $db->delete('redirect', 'from_path = ? AND is_auto = 1', [$page['path']]);

        (new Audit($db, $this->app->auth))->write('page', $id, 'restore', [], ['deleted_at' => 'возвращена']);

        $this->flash('Страница возвращена и пока скрыта — включите её галочкой.');
        $this->app->redirect($this->app->adminPath('page/' . $id));
    }

    public function savePage(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $before = $db->one('SELECT * FROM page WHERE id = ?', [$id]);
        if ($before === null) {
            $this->app->redirect($this->app->adminPath('page'));
        }

        // Служебные страницы правит только администратор.
        //
        // Не из недоверия, а потому что это не текст, а работающая часть
        // сайта: в теле корзины лежит форма заявки с полями и метками, за
        // которые цепляется скрипт. Очистка разметки, которая применяется
        // ко всем кроме администратора, вынесет оттуда форму, поля и все
        // метки — и корзина перестанет работать, ничего об этом не
        // сказав.
        if (in_array($before['template'], self::SERVICE, true)
            && ($this->app->auth->user()['role'] ?? '') !== 'admin') {
            $this->flash(
                'Эта страница — работающая часть сайта, а не текст. Её правит только администратор.',
                'bad'
            );
            $this->app->redirect($this->app->adminPath('page/' . $id));
        }

        $title = $this->input('title', 255);
        if ($title === '') {
            $this->flash('Без названия страницу не сохранить', 'bad');
            $this->app->redirect($this->app->adminPath('page/' . $id));
        }

        // Главная живёт по адресу «/», и адреса у неё нет — это адрес
        // самого сайта. Собирать его из названия нельзя: нажатие
        // «Сохранить» уводило главную на /glavnaya/, сайт оставался без
        // главной, а вернуть её через админку было уже нечем.
        if ($before['path'] === '/') {
            $newPath = '/';
            $slug = '';
        } else {
            $slug = Slug::make($this->input('slug', 190) ?: $title);
            $newPath = '/' . $slug . '/';
        }

        // Адрес занят другой страницей — молча перетереть нельзя,
        // потеряется чужая.
        $taken = $newPath !== $before['path'] ? $this->pathTaken($newPath, $id) : null;
        if ($taken !== null) {
            // Набранное кладём рядом с сообщением: после переадресации
            // форма перерисовывается из базы, и десяток переписанных
            // абзацев пропадал вместе с занятым адресом, из-за которого
            // отказ и вышел. Поправить нужно одно поле, а не всё заново.
            $this->keepInput($this->onlyGiven([
                'meta_title' => $this->input('meta_title', 255),
                'meta_description' => $this->input('meta_description', 500),
                'body' => $this->body(),
                'sort' => max(0, (int) ($_POST['sort'] ?? 0)),
            ]) + [
                'slug' => $this->input('slug', 190),
                'title' => $title,
                'is_published' => empty($_POST['is_published']) ? 0 : 1,
                'is_indexable' => empty($_POST['is_indexable']) ? 0 : 1,
            ]);
            $this->flash('Такой адрес уже занят: ' . $taken, 'bad');
            $this->app->redirect($this->app->adminPath('page/' . $id));
        }

        // Галочки шлются только когда отмечены — их узнаём по признаку
        // «форма вообще была», а не по наличию ключа.
        $fields = $this->onlyGiven([
            'meta_title' => $this->input('meta_title', 255),
            'meta_description' => $this->input('meta_description', 500),
            'body' => $this->body(),
            'sort' => max(0, (int) ($_POST['sort'] ?? 0)),
        ]) + [
            'slug' => $slug,
            'title' => $title,
            'is_published' => empty($_POST['is_published']) ? 0 : 1,
            'is_indexable' => empty($_POST['is_indexable']) ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->update('page', $fields, 'id = ?', [$id]);

        $moved = 0;
        if ($before['path'] !== $newPath) {
            $db->update('page', ['path' => $newPath], 'id = ?', [$id]);
            $db->run(
                'INSERT INTO redirect (from_path, to_path, code, is_auto, created_at) VALUES (?, ?, 301, 1, ?)'
                . ' ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)',
                [rtrim($before['path'], '/'), rtrim($newPath, '/'), date('Y-m-d H:i:s')]
            );
            $moved = 1;
        }

        (new Audit($db, $this->app->auth))->write('page', $id, 'update', $before, $fields);

        $this->savePageCityContent($id);

        $this->flash($moved ? 'Сохранено. Со старого адреса поставлена переадресация.' : 'Сохранено.');
        $this->app->redirect($this->app->adminPath('page/' . $id));
    }

    public function editBlock(array $params): string
    {
        $key = (string) ($params['key'] ?? '');
        $block = $this->app->db->one('SELECT * FROM block WHERE `key` = ?', [$key]);
        if ($block === null) {
            $this->flash('Блок не найден', 'bad');
            $this->app->redirect($this->app->adminPath('page'));
        }

        return $this->render('admin/block', ['title' => $block['title'], 'block' => $block]);
    }

    public function saveBlock(array $params): void
    {
        $this->guard();
        $key = (string) ($params['key'] ?? '');

        $before = $this->app->db->one('SELECT * FROM block WHERE `key` = ?', [$key]);
        if ($before === null) {
            $this->app->redirect($this->app->adminPath('page'));
        }

        $fields = ['body' => $this->body(), 'updated_at' => date('Y-m-d H:i:s')];
        $this->app->db->update('block', $fields, '`key` = ?', [$key]);

        (new Audit($this->app->db, $this->app->auth))->write('block', null, 'update', $before, $fields);

        $this->flash('Блок сохранён');
        $this->app->redirect($this->app->adminPath('block/' . $key));
    }
}
