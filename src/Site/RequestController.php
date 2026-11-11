<?php

declare(strict_types=1);

namespace Sklad\Site;

use Sklad\Core\App;
use Sklad\Model\Mail;
use Throwable;

/**
 * Приём заявок.
 *
 * Одна точка на все формы: модальные окна («Оставить заявку», «Купить в
 * 1 клик», «Запросить счёт», «Заказать звонок») и корзина. Отличаются
 * только набором полей и тем, что уходит менеджеру.
 *
 * Заявка сохраняется в базу до попытки отправить письмо. Порядок именно
 * такой: почта на дешёвом хостинге отваливается регулярно, и терять
 * из-за этого обращение нельзя — человек своё дело сделал.
 */
final class RequestController
{
    /** Поля, которые вообще принимаем. Всё остальное из запроса игнорируется. */
    private const FIELDS = ['name', 'phone', 'email', 'company', 'inn', 'city', 'comment'];

    private const TITLES = [
        'request' => 'Заявка с сайта',
        'one-click' => 'Заказ в один клик',
        'request-price' => 'Запрос счёта',
        'call' => 'Заказ звонка',
        'cart' => 'Заявка из корзины',
        'parts' => 'Запрос на запчасти',
    ];

    public function __construct(private App $app)
    {
    }

    public function store(array $params = []): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->respond(['ok' => false, 'error' => 'Ожидается POST'], 405);
        }

        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $this->respond(['ok' => false, 'error' => 'Не разобрать запрос'], 400);
        }

        // Ловушка для роботов: поле спрятано от человека, заполнить его
        // может только автозаполнение бота. Отвечаем успехом и ничего не
        // пишем — иначе бот подберёт форму по тексту ошибки.
        //
        // Имя ключа ровно то же, каким поле названо в формах, — ref_code.
        // Здесь сверялось прежнее surname (от него отказались: по нему
        // Chrome подставлял фамилию живому покупателю), и ветка не
        // срабатывала никогда — серверной защиты от роботов не было вовсе.
        if (!empty($data['ref_code'])) {
            $this->respond(['ok' => true, 'number' => $this->numberPrefix() . '-000000']);
        }

        $ip = $this->ip();
        $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        $limit = max(1, $this->app->settings->int('requests_per_hour', 20));
        // Порог считаем тем же временем, каким пишется created_at, — часами
        // PHP. Сравнение с NOW() брало время базы, а часовой пояс соединения
        // нигде не задаётся: при расхождении поясов час переставал быть
        // часом. Сдвиг в одну сторону превращал окно в четыре часа и
        // отсекал посетителя вчетверо раньше срока, в другую — предел не
        // срабатывал вовсе, и форму можно было заваливать без ограничений.
        $recent = (int) $this->app->db->value(
            'SELECT COUNT(*) FROM request WHERE ip = ? AND created_at > ?',
            [$ip, date('Y-m-d H:i:s', time() - 3600)]
        );
        if ($recent >= $limit) {
            $this->respond(
                ['ok' => false, 'error' => 'Слишком много заявок за час. Позвоните нам — так быстрее.'],
                429
            );
        }

        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        if (is_array($data['contact'] ?? null)) {
            $fields = array_merge($fields, $data['contact']);
        }
        $get = static function (string $key, int $max = 190) use ($fields): string {
            return mb_substr(trim((string) ($fields[$key] ?? '')), 0, $max);
        };

        $name = $get('name');
        if (mb_strlen($name) < 2) {
            $this->respond(['ok' => false, 'error' => 'Укажите, как к вам обращаться'], 422);
        }
        $phone = $get('phone', 64);
        if (strlen((string) preg_replace('/\D/', '', $phone)) < 10) {
            $this->respond(['ok' => false, 'error' => 'Введите телефон с кодом'], 422);
        }

        // Без согласия заявку принимать нельзя — это не формальность, а
        // условие законности самой обработки (ст. 6 152-ФЗ).
        if (empty($data['consent']['given'])) {
            $this->respond(
                ['ok' => false, 'error' => 'Без согласия на обработку данных заявку принять нельзя'],
                422
            );
        }

        $type = (string) preg_replace('/[^a-z\-]/', '', (string) ($data['type'] ?? 'request')) ?: 'request';
        $context = is_array($data['context'] ?? null) ? $data['context'] : [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $total = isset($data['total']) && $data['total'] !== null ? (float) $data['total'] : null;
        $now = date('Y-m-d H:i:s');

        // Фото из запроса на запчасти: проверяем, что это картинка, кладём в
        // загрузки и вписываем ссылку в комментарий — так менеджер видит её
        // прямо в заявке, а схему базы трогать не нужно.
        $photoUrl = $this->savePhoto($data['photo'] ?? null);
        $comment = $get('comment', 4000);
        if ($photoUrl !== null) {
            $comment = trim($comment . "\n\nФото: " . rtrim($this->app->config['site']['url'] ?? '', '/') . $photoUrl);
        }

        try {
            // Номер считается от id, а id известен только после вставки.
            // Класть до этого пустую строку нельзя: столбец number объявлен
            // UNIQUE. Две отправки в одну секунду давали Duplicate entry ''
            // и посетитель получал 500; а строка с пустым номером,
            // оставшаяся от оборванной отправки, занимала это значение
            // навсегда — после неё не вставлялась уже ни одна заявка, приём
            // вставал насовсем. Поэтому кладём заведомо уникальное временное
            // значение и следующим запросом заменяем его настоящим номером:
            // нумерация по-прежнему идёт по id, значит не скачет и остаётся
            // человекочитаемой, а недоделанная запись больше никого не
            // блокирует — она уникальна сама по себе.
            $requestId = $this->app->db->insert('request', [
                'number' => 'tmp-' . bin2hex(random_bytes(7)),
                'type' => $type,
                'name' => $name,
                'phone' => $phone,
                'email' => $get('email'),
                'company' => $get('company'),
                'inn' => $get('inn', 20),
                'city' => $get('city'),
                'comment' => $comment,
                'product_id' => $this->productId((string) ($context['id'] ?? '')),
                'items_total' => $total,
                'page_url' => mb_substr((string) ($data['page'] ?? ''), 0, 500),
                'ip' => $ip,
                'user_agent' => $agent,
                'status' => 'new',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            error_log('[sklad] заявка не сохранилась: ' . $e->getMessage());
            $this->respond(['ok' => false, 'error' => 'Не удалось сохранить заявку, позвоните нам'], 500);
        }

        // Префикс номера — из настройки: у каждого сайта сети своя марка, и
        // «XL» в заявке дилера TOR читается как чужой номер.
        $number = $this->numberPrefix() . '-' . str_pad((string) $requestId, 6, '0', STR_PAD_LEFT);
        $this->app->db->update('request', ['number' => $number], 'id = ?', [$requestId]);

        $lines = $this->saveItems($requestId, $items);
        $this->saveConsent($requestId, $now, $ip, $agent, $get);
        $this->notify($requestId, $number, $type, $get, $context, $lines, $total, $data, $ip, $now, $photoUrl);

        $this->respond(['ok' => true, 'number' => $number]);
    }

    /**
     * Позиции корзины.
     *
     * Название и цена копируются в позицию, а не берутся по ссылке на
     * товар: заявка должна остаться такой, какой её оформили, даже если
     * товар потом переименовали или подорожал.
     *
     * @return array<int, string> строки для письма
     */
    /**
     * Две латинские буквы для номера заявки: из марки, если она латиницей,
     * иначе прежние «XL». Номер попадает в письмо менеджеру и в разговор с
     * покупателем, поэтому он должен читаться как номер этого сайта.
     */
    private function numberPrefix(): string
    {
        // Префикс задаётся отдельной настройкой, а не выводится из марки.
        // Выведенный из марки, он менял нумерацию на уже работающем сайте:
        // у Brand заявки шли под «XL-», а из марки получалось «XI-», и в
        // переписке с покупателем номер переставал совпадать с прежними.
        $prefix = preg_replace('/[^A-Za-z]/', '', (string) $this->app->settings->get('request_prefix'));

        return $prefix !== '' ? strtoupper(substr($prefix, 0, 3)) : 'XL';
    }

    private function saveItems(int $requestId, array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = mb_substr((string) ($item['name'] ?? ''), 0, 255);
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $price = isset($item['price']) && $item['price'] !== null ? (float) $item['price'] : null;

            $this->app->db->insert('request_item', [
                'request_id' => $requestId,
                'product_id' => $this->productId((string) ($item['id'] ?? '')),
                'title' => $title,
                'url' => mb_substr((string) ($item['url'] ?? ''), 0, 500),
                'price' => $price,
                'qty' => $qty,
            ]);

            $lines[] = $title . ' — ' . $qty . ' шт.'
                . ($price !== null && $price > 0
                    ? ' × ' . number_format($price, 0, ',', ' ') . ' ₽'
                    : ' (цена по запросу)');
        }

        return $lines;
    }

    /**
     * Запись согласия.
     *
     * По ст. 9 152-ФЗ доказывать наличие согласия обязан оператор, и
     * доказательством считается зафиксированный факт: когда, с какого
     * адреса, под каким текстом и на какие именно данные. Формулировку
     * берём из настроек сайта, а не из того, что прислал браузер:
     * присланное клиентом доказательством быть не может.
     */
    private function saveConsent(int $requestId, string $now, string $ip, string $agent, callable $get): void
    {
        $subject = [];
        foreach (self::FIELDS as $key) {
            if ($key === 'comment') {
                continue;
            }
            $value = $get($key);
            if ($value !== '') {
                $subject[$key] = $value;
            }
        }

        $this->app->db->insert('consent', [
            'request_id' => $requestId,
            'created_at' => $now,
            'ip' => $ip,
            'user_agent' => $agent,
            'policy_url' => $this->app->settings->get('policy_url', '/politika-konfidencialnosti/'),
            'policy_version' => $this->app->settings->get('policy_version'),
            'wording' => $this->app->settings->get('consent_wording'),
            'subject' => json_encode($subject, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Письмо менеджеру.
     *
     * Заявка уже в базе, поэтому сбой почты не повод отвечать посетителю
     * ошибкой: он своё дело сделал, а разбираться с почтой — наше.
     */
    private function notify(
        int $requestId,
        string $number,
        string $type,
        callable $get,
        array $context,
        array $lines,
        ?float $total,
        array $data,
        string $ip,
        string $now,
        ?string $photoUrl = null
    ): void {
        $to = trim($this->app->settings->get('manager_email'));
        if ($to === '') {
            return;
        }

        $head = self::TITLES[$type] ?? 'Заявка с сайта';
        $site = rtrim($this->app->config['site']['url'] ?? '', '/');

        // Собираем заявку строками «подпись — значение» ОДИН раз, а дальше
        // раскладываем их и в текст, и в таблицу. Раньше письмо собиралось
        // только текстом, и стоило добавить поле в форму — его забывали
        // вписать сюда: менеджер узнавал о новом поле от клиента по телефону.
        $rows = [];
        $labels = ['Имя' => 'name', 'Телефон' => 'phone', 'Почта' => 'email',
                   'Компания' => 'company', 'ИНН' => 'inn', 'Город' => 'city'];
        foreach ($labels as $label => $key) {
            if ($get($key) !== '') {
                $rows[$label] = $get($key);
            }
        }
        if (!empty($context['name'])) {
            $rows['Товар'] = (string) $context['name'];
        }
        if ($get('comment', 4000) !== '') {
            $rows['Комментарий'] = $get('comment', 4000);
        }
        if ($lines) {
            $rows['Позиции'] = implode("\n", $lines);
            if ($total !== null) {
                $rows['Итого'] = number_format($total, 0, ',', ' ') . ' ₽';
            }
        }

        // Служебное — отдельной, приглушённой частью: менеджеру оно нужно
        // изредка, а мешает каждый раз.
        $tail = [
            'Страница' => (string) ($data['page'] ?? ''),
            'Адрес посетителя' => $ip,
            'Согласие зафиксировано' => $now,
        ];

        $body = $head . ' ' . $number . "\n\n";
        foreach ($rows + $tail as $label => $value) {
            $body .= $label . ': ' . $value . "\n";
        }

        (new Mail($this->app->db))->send($to, $head . ' ' . $number, $body, [
            'from' => $this->app->settings->get('email'),
            'from_name' => $this->app->settings->get('site_name'),
            'reply_to' => $get('email'),
            'request_id' => $requestId,
            'html' => $this->html($head . ' ' . $number, $rows, $tail, $site),
            // Фото прикладываем файлом, а не ссылкой: по ссылке менеджеру
            // надо открыть браузер и дождаться загрузки, а вложение видно
            // сразу и уезжает вместе с письмом, если его перешлют дальше.
            'files' => $this->files($photoUrl, $number),
        ]);
    }

    /**
     * Письмо таблицей.
     *
     * Стили только построчные: почтовые службы вырезают <style> из головы
     * письма, и вынесенное туда оформление просто пропадает. Раскладку
     * держим на таблицах — сетки и флексбокс в почте работают через раз.
     *
     * @param array<string, string> $rows
     * @param array<string, string> $tail
     */
    private function html(string $title, array $rows, array $tail, string $site): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $cell = 'padding:8px 12px;border-bottom:1px solid #e5e7eb;font:14px/1.5 Arial,sans-serif';

        $tr = static function (string $k, string $v, bool $quiet = false) use ($e, $cell): string {
            // Переносы строк в значении (позиции корзины, комментарий)
            // сохраняем: без этого весь список слипается в одну строку.
            $value = nl2br($e($v));
            $colour = $quiet ? ';color:#6b7280' : '';
            return '<tr><td style="' . $cell . ';color:#6b7280;white-space:nowrap;vertical-align:top">'
                . $e($k) . '</td><td style="' . $cell . $colour . '">' . $value . '</td></tr>';
        };

        $html = '<div style="font:14px/1.5 Arial,sans-serif;color:#111827">'
            . '<p style="font-size:16px;font-weight:bold;margin:0 0 12px">' . $e($title) . '</p>'
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px">';
        foreach ($rows as $k => $v) {
            $html .= $tr((string) $k, (string) $v);
        }
        foreach ($tail as $k => $v) {
            $html .= $tr((string) $k, (string) $v, true);
        }
        $html .= '</table>';
        if ($site !== '') {
            $html .= '<p style="margin:12px 0 0;font-size:12px;color:#9ca3af">'
                . $e(preg_replace('#^https?://#', '', $site) ?: $site) . '</p>';
        }

        return $html . '</div>';
    }

    /**
     * Фото заявки — вложением.
     *
     * Читаем с диска, куда его только что положил savePhoto(). Если файла
     * почему-то нет, письмо уходит без вложения: заявка с телефоном важнее
     * картинки, и терять её из-за пропавшего файла нельзя.
     *
     * @return array<int, array{name: string, type: string, data: string}>
     */
    private function files(?string $photoUrl, string $number): array
    {
        if ($photoUrl === null) {
            return [];
        }

        $path = $this->app->root() . '/public' . $photoUrl;
        $raw = is_file($path) ? @file_get_contents($path) : false;
        if ($raw === false || $raw === '') {
            return [];
        }

        $type = @mime_content_type($path) ?: 'application/octet-stream';

        return [[
            'name' => 'foto-' . $number . '.' . (pathinfo($photoUrl, PATHINFO_EXTENSION) ?: 'jpg'),
            'type' => $type,
            'data' => $raw,
        ]];
    }

    /**
     * Скрипты знают товар по адресу, база — по номеру.
     *
     * Не нашли — не беда: название и цена в заявке уже записаны, а
     * ссылка на карточку нужна менеджеру для удобства, не для учёта.
     */
    private function productId(string $path): ?int
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        $id = $this->app->db->value(
            'SELECT id FROM product WHERE path = ? AND deleted_at IS NULL',
            ['/' . ltrim($path, '/')]
        );

        return $id === null ? null : (int) $id;
    }

    /**
     * Фото из запроса на запчасти.
     *
     * Приходит data-URL'ом в JSON — браузер снимок уже уменьшил. Строго
     * проверяем по содержимому, что это картинка (заголовок data-URL
     * подделать ничего не стоит), и кладём под случайным именем в загрузки.
     * Любая осечка — возвращаем null: заявка важнее фото, терять её из-за
     * приложенного файла нельзя.
     *
     * Кладём в public/upload, а не в отдельный public/uploads: запрет на
     * исполнение лежит в public/upload/.htaccess, и своя папка рядом с ним
     * оказалась бы ничем не накрыта.
     */
    private function savePhoto(mixed $dataUrl): ?string
    {
        if (!is_string($dataUrl) || !preg_match('~^data:image/(jpeg|jpg|png|webp);base64,~', $dataUrl)) {
            return null;
        }
        $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($raw === false || strlen($raw) < 64 || strlen($raw) > 12 * 1024 * 1024) {
            return null;
        }
        $info = @getimagesizefromstring($raw);
        $ext = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        ][$info['mime'] ?? ''] ?? null;
        if ($ext === null) {
            return null;
        }

        $dir = $this->app->root() . '/public/upload/requests';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        try {
            $name = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (Throwable $e) {
            return null;
        }
        if (@file_put_contents($dir . '/' . $name, $raw) === false) {
            return null;
        }

        return '/upload/requests/' . $name;
    }

    /**
     * Адрес, по которому считаем заявки за час и который пишем в согласие.
     *
     * Только REMOTE_ADDR, как и в Core\Auth::ip(). Заголовок
     * X-Forwarded-For присылает сам посетитель: меняя его на каждом
     * запросе, предел в двадцать заявок обходят полностью — у каждого
     * «нового адреса» счётчик пустой. В журнале согласий подделанное
     * значение и вовсе бесполезно: доказательством оно быть не может.
     *
     * За обратным прокси REMOTE_ADDR — адрес прокси, один на всех, и
     * предел получается общим. Это неудобно, но считать ограничения по
     * присланному клиентом значению нельзя, а списка доверенных прокси в
     * проекте нет и не будет.
     */
    private function ip(): string
    {
        return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function respond(array $payload, int $code = 200): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8', true, $code);
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
