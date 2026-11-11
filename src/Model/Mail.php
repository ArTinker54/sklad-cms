<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Отправка писем.
 *
 * Через встроенную mail(): на дешёвом российском хостинге она настроена
 * и работает, а тянуть composer и PHPMailer ради одного письма в день —
 * лишняя зависимость на каждой из десяти установок.
 *
 * Каждая попытка пишется в журнал. Если письма перестанут уходить —
 * а на shared-хостинге это случается молча, — узнать об этом надо из
 * админки, а не от клиента через неделю.
 */
final class Mail
{
    public function __construct(private Db $db)
    {
    }

    /**
     * @param string $to один или несколько адресов через запятую
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $to = trim($to);
        if ($to === '') {
            return false;
        }

        $from = trim((string) ($options['from'] ?? ''));
        $fromName = trim((string) ($options['from_name'] ?? ''));
        $replyTo = trim((string) ($options['reply_to'] ?? ''));

        $headers = ['MIME-Version: 1.0'];
        if ($from !== '') {
            $headers[] = 'From: ' . ($fromName !== ''
                ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>'
                : $from);
        }
        // Ответить клиенту менеджер должен одной кнопкой, а не копируя
        // адрес из тела письма.
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        [$contentType, $body] = $this->compose(
            $body,
            trim((string) ($options['html'] ?? '')),
            is_array($options['files'] ?? null) ? $options['files'] : []
        );
        $headers[] = 'Content-Type: ' . $contentType;

        $encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $sent = @mail($to, $encoded, $body, implode("\r\n", $headers));

        $this->db->insert('mail_log', [
            'recipient' => mb_substr($to, 0, 500),
            'subject' => mb_substr($subject, 0, 500),
            'request_id' => $options['request_id'] ?? null,
            'status' => $sent ? 'sent' : 'failed',
            'error' => $sent ? null : (error_get_last()['message'] ?? 'mail() вернула false'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $sent;
    }

    /**
     * Собираем тело письма и заголовок его типа.
     *
     * Три случая, от простого к сложному:
     *   • только текст            — text/plain, как было раньше;
     *   • текст и разметка        — multipart/alternative;
     *   • ещё и файлы            — multipart/mixed, внутри которого лежит
     *                               та же пара «текст + разметка».
     *
     * Текстовую часть отправляем ВСЕГДА, даже когда есть разметка. Письмо
     * без неё почтовые службы охотнее считают спамом, а в списке писем
     * показывают начало именно текстовой части — без неё там пусто.
     *
     * Всё кодируем base64. Кириллица в quoted-printable распухает втрое, а
     * восьмибитную отправку часть промежуточных серверов до сих пор рвёт по
     * длинным строкам — на кириллице это разрезает букву пополам.
     *
     * @param array<int, array{name?: string, type?: string, data?: string}> $files
     * @return array{0: string, 1: string}
     */
    private function compose(string $text, string $html, array $files): array
    {
        $b64 = static fn (string $s): string => chunk_split(base64_encode($s), 76, "\r\n");

        if ($html === '' && $files === []) {
            return ['text/plain; charset=utf-8', $text];
        }

        // Тело — либо голый текст, либо пара «текст + разметка».
        if ($html === '') {
            $inner = "Content-Type: text/plain; charset=utf-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n" . $b64($text);
            $innerType = null;
        } else {
            $alt = '=_alt_' . bin2hex(random_bytes(12));
            $inner = "--$alt\r\n"
                . "Content-Type: text/plain; charset=utf-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n" . $b64($text) . "\r\n"
                . "--$alt\r\n"
                . "Content-Type: text/html; charset=utf-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n" . $b64($html) . "\r\n"
                . "--$alt--\r\n";
            $innerType = "multipart/alternative; boundary=\"$alt\"";
        }

        if ($files === []) {
            return [$innerType ?? 'text/plain; charset=utf-8', $inner];
        }

        $mix = '=_mix_' . bin2hex(random_bytes(12));
        $out = "--$mix\r\n";
        $out .= $innerType !== null
            ? "Content-Type: $innerType\r\n\r\n" . $inner
            : $inner;

        foreach ($files as $f) {
            $data = (string) ($f['data'] ?? '');
            if ($data === '') {
                continue;
            }
            // Имя кодируем, только если в нём есть не-латиница. Кодировать
            // всегда — значит без нужды раздувать заголовок втрое: строка
            // уходит за 78 знаков, а её полагается переносить, и не каждый
            // почтовый сервер делает это аккуратно.
            $name = (string) ($f['name'] ?? 'file');
            if (preg_match('/[^\x20-\x7e]/', $name) === 1) {
                $name = '=?UTF-8?B?' . base64_encode($name) . '?=';
            }
            $type = (string) ($f['type'] ?? 'application/octet-stream');

            $out .= "\r\n--$mix\r\n"
                . "Content-Type: $type; name=\"$name\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n"
                . $b64($data);
        }
        $out .= "\r\n--$mix--\r\n";

        return ["multipart/mixed; boundary=\"$mix\"", $out];
    }
}
