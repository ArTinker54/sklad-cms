<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;
use Sklad\Core\Settings;

/**
 * Подсказка в строке поиска.
 *
 * Пример модели был вписан прямо в шаблон — «например CDDK20», — а шаблон
 * один на всю сеть. Поэтому чужую модель Brand видели и у Партнёра, и у Tisel,
 * и у Hangcha, где такой техники нет вовсе. Заказчик это и заметил.
 *
 * Пример берём из каталога самого сайта, а не просим вписать руками в
 * настройках: вписанный однажды, он переживёт и снятие модели с
 * производства, и смену модельного ряда — и снова станет примером того,
 * чего на сайте нет. Из каталога он не может оказаться чужим.
 *
 * Годится не всякий код модели. Поиск (public/assets/js/search.js) ищет по
 * названию товара и разделу, артикул как отдельное поле он не смотрит.
 * Поэтому берём только тот артикул, который в названии и написан, — иначе
 * подсказка предлагала бы запрос, на который сайт ничего не находит.
 * Порядок тот же, в каком витрина показывает «популярное»: первой окажется
 * модель, которую продавец сам поставил в каталоге первой.
 *
 * Если своё слово всё же нужно — настройка `search_example` его перебивает.
 */
final class SearchHint
{
    /** За отрисовку страницы подсказку спрашивают дважды: шапка и форма на 404. */
    private static ?string $found = null;

    public static function example(Db $db, Settings $settings): string
    {
        $own = trim($settings->get('search_example'));
        if ($own !== '') {
            return $own;
        }

        if (self::$found === null) {
            self::$found = (string) ($db->value(
                'SELECT p.article FROM product p'
                . " WHERE p.article <> '' AND p.title LIKE CONCAT('%', p.article, '%')"
                . ' AND ' . Visible::product()
                . ' ORDER BY p.sort, p.id LIMIT 1'
            ) ?? '');
        }

        return self::$found;
    }

    /**
     * Подсказка целиком.
     *
     * Каталог может оказаться пустым или без единого кода модели в
     * названиях — тогда остаётся одна общая часть. Оборванное «— например»
     * в поле ввода выглядит поломкой, поэтому приписку либо ставим
     * целиком, либо не ставим вовсе.
     */
    public static function text(Db $db, Settings $settings, string $base): string
    {
        $example = self::example($db, $settings);

        return $example === '' ? $base : $base . ' — например ' . $example;
    }
}
