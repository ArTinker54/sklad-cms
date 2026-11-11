<?php

declare(strict_types=1);

namespace Sklad\Admin;

/**
 * Заявки с сайта.
 *
 * Экран менеджера: список с отбором по состоянию, карточка с позициями
 * и запись о согласии на обработку данных.
 *
 * Выгрузка в Excel отдаётся файлом CSV с разделителем «точка с запятой»
 * и меткой порядка байтов: Excel открывает такой двойным щелчком и не
 * ломает кириллицу. Настоящий xlsx потребовал бы библиотеки, а её на
 * дешёвом хостинге может не оказаться.
 */
final class RequestController extends Controller
{
    protected string $section = 'requests';

    private const PER_PAGE = 30;

    private const STATES = [
        'new' => 'новые',
        'in_work' => 'в работе',
        'done' => 'завершённые',
        'spam' => 'спам',
    ];

    public function index(array $params = []): string
    {
        $db = $this->app->db;

        $status = (string) ($_GET['status'] ?? '');
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $where = ['1'];
        $args = [];

        if (isset(self::STATES[$status])) {
            $where[] = 'r.status = ?';
            $args[] = $status;
        }
        if ($query !== '') {
            $where[] = '(r.number LIKE ? OR r.name LIKE ? OR r.phone LIKE ? OR r.email LIKE ? OR r.company LIKE ?)';
            $like = '%' . $query . '%';
            array_push($args, $like, $like, $like, $like, $like);
        }

        $sql = ' FROM request r WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $args);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $rows = $db->all(
            'SELECT r.*, (SELECT COUNT(*) FROM request_item i WHERE i.request_id = r.id) AS items'
            . $sql . ' ORDER BY r.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $args
        );

        return $this->render('admin/requests', [
            'title' => 'Заявки',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'status' => $status,
            'query' => $query,
            'states' => self::STATES,
            'counts' => $db->pairs('SELECT status, COUNT(*) FROM request GROUP BY status'),
        ]);
    }

    public function show(array $params): string
    {
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $request = $db->one('SELECT * FROM request WHERE id = ?', [$id]);
        if ($request === null) {
            http_response_code(404);

            return $this->render('admin/requests', [
                'title' => 'Заявка не найдена',
                'rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1,
                'status' => '', 'query' => '', 'states' => self::STATES, 'counts' => [],
            ]);
        }

        return $this->render('admin/request', [
            'title' => 'Заявка ' . $request['number'],
            'request' => $request,
            'items' => $db->all('SELECT * FROM request_item WHERE request_id = ? ORDER BY id', [$id]),
            'consent' => $db->one('SELECT * FROM consent WHERE request_id = ? ORDER BY id LIMIT 1', [$id]),
            'states' => self::STATES,
        ]);
    }

    public function save(array $params): void
    {
        $this->guard();
        $id = (int) ($params['id'] ?? 0);

        $status = (string) ($_POST['status'] ?? '');
        $fields = ['note' => $this->input('note', 4000), 'updated_at' => date('Y-m-d H:i:s')];
        if (isset(self::STATES[$status])) {
            $fields['status'] = $status;
            $fields['manager_id'] = $this->app->auth->id();
        }

        $before = $this->app->db->one('SELECT status, note FROM request WHERE id = ?', [$id]);
        $this->app->db->update('request', $fields, 'id = ?', [$id]);

        // Кто и когда перевёл заявку — по этому потом разбираются, если
        // клиент говорит, что ему не перезвонили.
        $this->app->db->insert('audit', [
            'user_id' => $this->app->auth->id(),
            'entity' => 'request',
            'entity_id' => $id,
            'action' => 'update',
            'changes' => json_encode(['было' => $before, 'стало' => $fields], JSON_UNESCAPED_UNICODE),
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash('Заявка сохранена');
        $this->app->redirect($this->app->adminPath('request/' . $id));
    }

    /**
     * Выгрузка для Excel.
     *
     * Отдаём то, что отобрано на экране, а не всё подряд: менеджер
     * обычно выгружает месяц или новые, и молча подсунуть ему всю базу
     * было бы неожиданно.
     */
    /**
     * Обезвреживает ячейку выгрузки.
     *
     * Excel и LibreOffice считают формулой всё, что начинается с =, +, -
     * или @, и выполняют это при открытии файла. Имя и комментарий в
     * заявке пишет посетитель с улицы: достаточно представиться
     * «=HYPERLINK(...)», и менеджер, открыв выгрузку, получит в таблице
     * чужую ссылку — а в некоторых сборках и запуск внешней программы.
     *
     * Спасает не экранирование кавычек, а апостроф впереди: он и делает
     * содержимое текстом. В самой ячейке он не виден.
     */
    private static function safeCell($value): string
    {
        $text = (string) $value;
        // Табуляция и перевод строки в начале тоже сбивают разбор:
        // ведущий пробельный символ отбрасывается, и формула оживает.
        if ($text !== '' && preg_match('/^[\s]*[=+\-@]/', $text)) {
            return "'" . $text;
        }

        return $text;
    }

    public function export(array $params = []): void
    {
        // Отбор берётся тот же, что на экране, — включая строку поиска.
        // Раньше она терялась: менеджер искал заявки одной компании,
        // нажимал «Выгрузить» и получал всю базу с чужими персональными
        // данными. Экран при этом обещал выгрузку отобранного.
        $status = (string) ($_GET['status'] ?? '');
        $query = trim((string) ($_GET['q'] ?? ''));
        $args = [];
        $where = ['1'];

        if (isset(self::STATES[$status])) {
            $where[] = 'status = ?';
            $args[] = $status;
        }
        if ($query !== '') {
            $where[] = '(number LIKE ? OR name LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ?)';
            $like = '%' . $query . '%';
            array_push($args, $like, $like, $like, $like, $like);
        }

        $rows = $this->app->db->all(
            'SELECT number, created_at, status, name, phone, email, company, inn, city, comment, items_total, page_url'
            . ' FROM request WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC',
            $args
        );

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="zayavki-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'wb');
        // Метка порядка байтов: без неё Excel открывает файл в кодировке
        // системы и кириллица превращается в кашу.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Номер', 'Когда', 'Состояние', 'Имя', 'Телефон', 'Почта',
            'Компания', 'ИНН', 'Город', 'Комментарий', 'Сумма', 'Страница',
        ], ';');

        foreach ($rows as $row) {
            $row['status'] = self::STATES[$row['status']] ?? $row['status'];
            fputcsv($out, array_map([self::class, 'safeCell'], array_values($row)), ';');
        }
        fclose($out);
        exit;
    }
}
