<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Адресная часть названия.
 *
 * Таблица перевода подобрана под то, как уже устроены адреса на сайтах
 * сети: х → h, ц → c, щ → sch, ъ и ь выбрасываются. Отсюда
 * «трехопорные» → trehopornye и «подъемные» → podemnye. Взять другую,
 * более привычную таблицу нельзя: новые товары получили бы адреса,
 * непохожие на соседние, и каталог стал бы разнобойным.
 */
final class Slug
{
    private const LETTERS = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function make(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, self::LETTERS);

        // Разделители в дефис, всё прочее прочь. Точку в размерах
        // («1,5 тонны») тоже теряем — в адресе она только мешает.
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
        $text = trim($text, '-');

        // Слишком длинный адрес неудобен и в выдаче обрезается. Режем по
        // границе слова, чтобы не оставлять обрубок посреди слова.
        if (strlen($text) > 90) {
            $text = substr($text, 0, 90);
            $cut = strrpos($text, '-');
            if ($cut !== false && $cut > 40) {
                $text = substr($text, 0, $cut);
            }
        }

        return $text;
    }

    /**
     * Свободный адрес в пределах одного родителя.
     *
     * Занятый дополняется числом: cddk20, cddk20-2 и так далее. Это
     * лучше молчаливого отказа сохранить — менеджер заводит товар, а не
     * решает задачу про уникальность.
     */
    public static function unique(Db $db, string $table, string $slug, ?int $parentId, ?int $exceptId = null): string
    {
        $slug = $slug !== '' ? $slug : 'tovar';

        $parentColumn = $table === 'category' ? 'parent_id' : 'category_id';
        $where = $db->quoteName($parentColumn) . ' <=> ? AND slug = ?';
        $params = [$parentId];

        // Занятость смотрим и в соседней таблице.
        //
        // У одного родителя адрес товара и адрес раздела различаются только
        // косой на конце: /shtabelery/cddk20 и /shtabelery/cddk20/. UNIQUE
        // видит каждый в своей таблице, обе записи заводились молча, а
        // дальше запрос адреса товара находил раздел и отдавал на него 301 —
        // товар становился недостижим навсегда, хотя в админке числился
        // опубликованным. Родитель у обоих один и тот же раздел, поэтому
        // хватает пары «родитель + адресная часть»: собирать пути незачем.
        $neighbour = $table === 'category' ? 'product' : 'category';
        $neighbourColumn = $table === 'category' ? 'category_id' : 'parent_id';
        $neighbourWhere = $db->quoteName($neighbourColumn) . ' <=> ? AND slug = ?';

        $candidate = $slug;
        for ($n = 2; $n < 200; $n++) {
            $sql = 'SELECT id FROM ' . $db->quoteName($table) . ' WHERE ' . $where
                . ($exceptId ? ' AND id <> ' . (int) $exceptId : '') . ' LIMIT 1';
            $taken = $db->value($sql, array_merge($params, [$candidate]));
            if ($taken === null) {
                // Правимую запись исключаем только из своей таблицы: в
                // соседней те же числа принадлежат чужим записям, и
                // пропустив по ним, мы бы разрешили ровно ту пару адресов,
                // от которой здесь и защищаемся.
                $taken = $db->value(
                    'SELECT id FROM ' . $db->quoteName($neighbour) . ' WHERE ' . $neighbourWhere . ' LIMIT 1',
                    array_merge($params, [$candidate])
                );
            }
            if ($taken === null) {
                return $candidate;
            }
            $candidate = $slug . '-' . $n;
        }

        return $slug . '-' . substr(md5((string) mt_rand()), 0, 6);
    }
}
