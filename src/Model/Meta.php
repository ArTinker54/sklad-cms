<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Мета-теги: что в итоге увидит поисковик.
 *
 * Правило одно и то же везде: собственное значение товара сильнее
 * шаблона раздела. Заполнили руками — работает ваше; не заполнили —
 * собирается по шаблону.
 *
 * Так 247 позиций получают осмысленные заголовки сразу, а править
 * приходится только те, где шаблон промахнулся. Обратный порядок —
 * «сначала заполните всё руками» — на практике означает, что не
 * заполнят ничего.
 */
final class Meta
{
    /** Сколько влезает в выдачу, дальше обрезается многоточием. */
    public const TITLE_LIMIT = 70;
    public const DESCRIPTION_LIMIT = 160;

    public function __construct(private Db $db)
    {
    }

    /**
     * Итоговые заголовок и описание товара.
     *
     * @return array{title:string, description:string, h1:string, from_template:bool}
     */
    public function forProduct(array $product, ?array $category = null): array
    {
        if ($category === null && !empty($product['category_id'])) {
            $category = $this->db->one('SELECT * FROM category WHERE id = ?', [$product['category_id']]);
        }

        $values = $this->placeholders($product, $category);

        $title = trim((string) ($product['meta_title'] ?? ''));
        $fromTemplate = false;
        if ($title === '' && $category !== null && trim((string) $category['product_title_tpl']) !== '') {
            $title = $this->fill((string) $category['product_title_tpl'], $values);
            $fromTemplate = true;
        }
        if ($title === '') {
            $title = (string) $product['title'];
            $fromTemplate = true;
        }

        $description = trim((string) ($product['meta_description'] ?? ''));
        if ($description === '' && $category !== null && trim((string) $category['product_description_tpl']) !== '') {
            $description = $this->fill((string) $category['product_description_tpl'], $values);
        }
        if ($description === '') {
            $description = trim((string) ($product['intro'] ?? ''));
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1' => trim((string) ($product['h1'] ?? '')) !== ''
                ? (string) $product['h1']
                : (string) $product['title'],
            'from_template' => $fromTemplate,
        ];
    }

    /**
     * Значения для подстановок.
     *
     * Кроме полей товара доступна любая характеристика по её ключу:
     * {capacity} даст «2000», а {capacity_unit} — «кг». Без единицы
     * шаблон «грузоподъёмность {capacity}» читался бы обрубком.
     */
    private function placeholders(array $product, ?array $category): array
    {
        $values = [
            'название' => (string) $product['title'],
            'артикул' => (string) ($product['article'] ?? ''),
            'раздел' => $category !== null ? (string) $category['title'] : '',
            'цена' => (float) ($product['price'] ?? 0) > 0
                ? number_format((float) $product['price'], 0, ',', ' ')
                : '',
        ];

        if (!empty($product['id'])) {
            $rows = $this->db->all(
                'SELECT o.key, o.unit, po.value FROM product_option po'
                . ' JOIN `option` o ON o.id = po.option_id WHERE po.product_id = ?',
                [$product['id']]
            );
            foreach ($rows as $row) {
                $values[$row['key']] = (string) $row['value'];
                $values[$row['key'] . '_unit'] = trim($row['value'] . ' ' . $row['unit']);
            }
        }

        return $values;
    }

    private function fill(string $template, array $values): string
    {
        $result = preg_replace_callback(
            '/\{([a-zа-яё_]+)\}/ui',
            static fn (array $m): string => (string) ($values[mb_strtolower($m[1])] ?? ''),
            $template
        ) ?? '';

        // Незаполненная подстановка оставляет двойные пробелы и висящие
        // запятые — «Штабелёр , подъём  мм» выглядит как поломка.
        $result = preg_replace('/\s{2,}/u', ' ', $result) ?? $result;
        $result = preg_replace('/\s*,\s*(?=,|$)/u', '', $result) ?? $result;

        return trim($result, " \t\n\r,—-");
    }

    /**
     * Повторяющиеся заголовки.
     *
     * Десяток страниц с одинаковым title ослабляют друг друга: поисковик
     * не понимает, какую показывать. На переносе Brand таких было 124 из
     * 247, и заметил я это случайно — поэтому проверка живёт в админке.
     */
    public function duplicates(int $limit = 20): array
    {
        return $this->db->all(
            'SELECT meta_title AS value, COUNT(*) AS goods,'
            . ' GROUP_CONCAT(id ORDER BY id SEPARATOR \',\') AS ids'
            . " FROM product WHERE deleted_at IS NULL AND is_published = 1 AND meta_title <> ''"
            . ' GROUP BY meta_title HAVING goods > 1 ORDER BY goods DESC LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Заголовок окна с припиской из настроек — например, « — Бренд».
     *
     * Зовётся только для заголовков, собранных нами. Написанные руками
     * перенесены со старого сайта слово в слово: их показывает выдача
     * строкой ссылки, и менять их при переезде нельзя.
     */
    public static function withSuffix(string $title, string $suffix): string
    {
        $suffix = rtrim($suffix);
        if (trim($suffix) === '') {
            return $title;
        }

        // Сравниваем без обрамления: приписку пишут вместе с тире и
        // пробелами, а искать надо само название.
        $core = (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $suffix);

        // Название уже в заголовке — второй раз не нужно. Иначе главная
        // «TOR — официальный дилер» становится «…дилерTOR».
        if ($core === '' || mb_stripos($title, $core) !== false) {
            return $title;
        }

        // Пишут по-разному: « — Бренд», «— Бренд», «Бренд». Если начали
        // сразу с названия, отделяем пробелом — иначе слипнется с
        // заголовком, а поправить это в настройке никто не догадается.
        if (preg_match('/^[\p{L}\p{N}]/u', $suffix) === 1) {
            $suffix = ' ' . $suffix;
        }

        // В выдачу влезает ограниченное число знаков. Если с припиской
        // не влезает — режем сам заголовок, а не приписку: она короткая,
        // и в ней название компании.
        $room = self::TITLE_LIMIT - mb_strlen($suffix);
        if ($room > 0 && mb_strlen($title) > $room) {
            $title = rtrim(mb_substr($title, 0, $room - 1)) . '…';
        }

        return $title . $suffix;
    }

    /** То же по названиям: они же идут в H1, если своего нет. */
    public function duplicateTitles(int $limit = 20): array
    {
        return $this->db->all(
            'SELECT title AS value, COUNT(*) AS goods,'
            . ' GROUP_CONCAT(id ORDER BY id SEPARATOR \',\') AS ids'
            . ' FROM product WHERE deleted_at IS NULL AND is_published = 1'
            . ' GROUP BY title HAVING goods > 1 ORDER BY goods DESC LIMIT ' . max(1, $limit)
        );
    }
}
