<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;

/**
 * Адреса: переадресации и обращения в никуда.
 *
 * Самый недооценённый раздел. На старых адресах висят ссылки из выдачи,
 * из рекламы и из чужих статей, и каждая четырёхсотка — это потерянный
 * посетитель, который уже искал именно вас.
 *
 * Часть переадресаций система заводит сама при переименовании, часть
 * добавляют руками при переезде со старого сайта. Различать их важно:
 * автоматические можно чистить оптом, ручные — ни в коем случае.
 */
final class RedirectController extends Controller
{
    protected string $section = 'redirects';

    private const PER_PAGE = 50;

    public function index(array $params = []): string
    {
        $db = $this->app->db;
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $where = '1';
        $args = [];
        if ($query !== '') {
            $where = '(from_path LIKE ? OR to_path LIKE ?)';
            $args = ['%' . $query . '%', '%' . $query . '%'];
        }

        $total = (int) $db->value('SELECT COUNT(*) FROM redirect WHERE ' . $where, $args);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return $this->render('admin/redirects', [
            'title' => 'Адреса',
            'rows' => $db->all(
                'SELECT * FROM redirect WHERE ' . $where
                . ' ORDER BY hits DESC, id DESC LIMIT ' . self::PER_PAGE
                . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
                $args
            ),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'query' => $query,
            // Куда заходят и не находят. Это готовый список того, что
            // стоит закрыть переадресацией.
            'misses' => $db->all('SELECT * FROM not_found ORDER BY hits DESC LIMIT 20'),
        ]);
    }

    public function add(array $params = []): void
    {
        $this->guard();

        $from = '/' . trim($this->input('from_path', 500), '/');
        $to = '/' . trim($this->input('to_path', 500), '/');

        if ($from === '/' || $to === '/' || $from === $to) {
            $this->flash('Нужны разные адреса — откуда и куда', 'bad');
            $this->app->redirect($this->app->adminPath('redirect'));
        }

        $db = $this->app->db;

        // Цепочки: если на новый адрес уже кто-то указывал, переводим
        // сразу на конечный. Иначе посетитель пойдёт по двум прыжкам, а
        // поисковик такие цепочки не любит.
        $db->run('UPDATE redirect SET to_path = ? WHERE to_path = ?', [$to, $from]);
        $db->run(
            'INSERT INTO redirect (from_path, to_path, code, is_auto, created_at) VALUES (?, ?, 301, 0, ?)'
            . ' ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), is_auto = 0',
            [$from, $to, date('Y-m-d H:i:s')]
        );

        // Свернув цепочку, можно получить правило, ведущее само на себя:
        // было «/а → /б», добавили «/б → /а» — и первое превратилось в
        // «/а → /а». Браузер по такому адресу крутится, пока не сдастся,
        // и страница пропадает совсем. Такие правила убираем сразу.
        $looped = $db->delete('redirect', 'from_path = to_path');
        if ($looped > 0) {
            $this->flash('Переадресация добавлена. Правил, замкнувшихся на себя, убрано: ' . $looped);
            $db->delete('not_found', 'path = ?', [$from]);
            $this->app->redirect($this->app->adminPath('redirect'));
        }
        $db->delete('not_found', 'path = ?', [$from]);

        (new Audit($db, $this->app->auth))->write('redirect', null, 'create', [], ['from' => $from, 'to' => $to]);

        $this->flash('Переадресация добавлена');
        $this->app->redirect($this->app->adminPath('redirect'));
    }

    public function remove(array $params): void
    {
        $this->guard();
        $id = (int) ($params['id'] ?? 0);

        $row = $this->app->db->one('SELECT * FROM redirect WHERE id = ?', [$id]);
        if ($row !== null) {
            $this->app->db->delete('redirect', 'id = ?', [$id]);
            (new Audit($this->app->db, $this->app->auth))
                ->write('redirect', $id, 'delete', $row, []);
            $this->flash('Переадресация удалена. Старый адрес снова отдаёт «не найдено».');
        }

        $this->app->redirect($this->app->adminPath('redirect'));
    }

    /** Забыть промах: адрес был мусорным и чинить его незачем. */
    public function forget(array $params = []): void
    {
        $this->guard();
        $this->app->db->delete('not_found', 'path = ?', [$this->input('path', 500)]);
        $this->app->redirect($this->app->adminPath('redirect'));
    }
}
