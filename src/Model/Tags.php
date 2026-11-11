<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Популярные запросы раздела — подпись и внутренний адрес.
 *
 * Лежат одним текстовым полем, по строке на запрос:
 *   Гидравлическая тележка 2500 | /telezhki/gidravlicheskie-2500/
 *
 * Разбор молчаливый: строку с опечаткой просто не показываем. Ругаться
 * некуда — поле правится в общей форме раздела, и ронять сохранение
 * из-за лишней строки нельзя. Чтобы администратор всё же видел, что
 * дошло до сайта, normalize() перед записью выбрасывает ровно то же,
 * что выбросил бы parse().
 */
final class Tags
{
    /**
     * Больше дюжины ссылок — уже не подсказка, а второе меню: блок
     * перерастает список товаров, ради которого страницу открыли.
     */
    public const LIMIT = 12;

    /** @return list<array{title: string, url: string}> */
    public static function parse(?string $raw): array
    {
        $out = [];

        // Перечисляем переводы строки поимённо. Короткое «\R» здесь брать
        // нельзя: без пометки о Юникоде оно считает переводом строки и
        // одиночный байт 0x85, а это второй байт буквы «х». «Рохля»
        // разрывалась пополам и сохранялась в базу как «ля».
        foreach (preg_split("/\r\n|\r|\n/", (string) $raw) ?: [] as $line) {
            $bar = strpos($line, '|');
            if ($bar === false) {
                continue;
            }

            // Ведущую решётку снимаем: её рисует шаблон, и с решёткой в
            // подписи на странице выходило бы «##Тележки».
            $title = ltrim(trim(substr($line, 0, $bar)), '# ');
            $url = trim(substr($line, $bar + 1));

            // Адрес из админки уходит прямо в href, поэтому пускаем только
            // свой: «javascript:...» выполнился бы в браузере посетителя, а
            // «//чужой-сайт/» увёл бы его с сайта под видом своей ссылки.
            //
            // Одной проверки на «//» мало. По правилам разбора адресов
            // обратная косая в этом месте равнозначна прямой, и «/\чужой/»
            // браузер понимает точно так же — как чужой узел. Поэтому
            // обратную косую не пускаем нигде, вместе с переводами строки
            // и табуляцией: ими адрес разрывают, чтобы обойти проверку.
            if ($title === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')
                || strpbrk($url, "\\\r\n\t\0") !== false) {
                continue;
            }

            $out[] = ['title' => $title, 'url' => $url];
            if (count($out) === self::LIMIT) {
                break;
            }
        }

        return $out;
    }

    /**
     * Значение для записи в базу.
     *
     * Собирается заново из разбора, а не чистится построчно: две отдельные
     * чистки рано или поздно разъезжаются, и в поле остаётся строка,
     * которой на сайте нет. Так администратор после сохранения видит
     * ровно тот список, который показывается посетителю.
     */
    public static function normalize(string $raw): string
    {
        $lines = [];
        // Столбец текстовый, но не бездонный: вставленная в поле простыня
        // на десятки тысяч знаков не влезла бы, и сохранение раздела
        // упало бы ошибкой базы — вместе со всеми остальными правками
        // формы. Разумного списка обрезка не касается: в нём дюжина строк.
        foreach (self::parse(mb_substr($raw, 0, 60000)) as $tag) {
            $lines[] = $tag['title'] . ' | ' . $tag['url'];
        }

        return implode("\n", $lines);
    }

    /**
     * Какие из адресов на сайте не открываются.
     *
     * Формат проверить мало: опечатка в адресе формату не противоречит, и
     * строка «/telezhki/gidravlicheskiye-2500/» выглядит совершенно
     * законной. Разбирать это надо по базе — здесь общее место и для
     * админки, которая предупреждает человека при сохранении, и для
     * витрины, которая мёртвую ссылку просто не рисует.
     *
     * Адрес может оказаться разделом, страницей, товаром или старым
     * адресом, с которого стоит переадресация, — берём всё это одним
     * запросом. У товара адрес хранится без косой на конце, поэтому его
     * сверяем в его же виде.
     *
     * @param list<string> $urls
     * @return list<string> в том же виде, в каком пришли
     */
    public static function missing(Db $db, array $urls): array
    {
        $urls = array_values(array_unique($urls));
        if (!$urls) {
            return [];
        }

        $marks = implode(',', array_fill(0, count($urls), '?'));
        $bare = array_map(static fn (string $url): string => rtrim($url, '/'), $urls);

        $found = $db->column(
            'SELECT c.path FROM category c WHERE c.path IN (' . $marks . ') AND ' . Visible::category('c')
            . ' UNION SELECT p.path FROM page p WHERE p.path IN (' . $marks . ')'
            . '   AND p.is_published = 1 AND p.deleted_at IS NULL'
            . ' UNION SELECT pr.path FROM product pr WHERE pr.path IN (' . $marks . ')'
            . '   AND ' . Visible::product('pr')
            . ' UNION SELECT r.from_path FROM redirect r WHERE r.from_path IN (' . $marks . ')',
            array_merge($urls, $urls, $bare, $urls)
        );

        $found = array_flip($found);

        return array_values(array_filter(
            $urls,
            static fn (string $url): bool => !isset($found[$url]) && !isset($found[rtrim($url, '/')])
        ));
    }
}
