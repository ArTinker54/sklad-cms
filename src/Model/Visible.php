<?php

declare(strict_types=1);

namespace Sklad\Model;

/**
 * Условие «товар правда виден покупателю».
 *
 * Одного `is_published` у товара мало. Товар лежит в разделе, раздел — в
 * другом разделе, и снять с витрины могли любого из них. Пока условие
 * проверяло только сам товар, снятый подраздел приводил вот к чему:
 * товары из него оставались в списке родителя, в products.json и в карте
 * сайта, а путь в шапке карточки вёл на адрес, который отдаёт 404.
 *
 * Условие собрано строкой, а не методом с параметрами, потому что
 * вставляется в десяток разных запросов — в подсчёты, выборки и
 * вложенные запросы. Подстановок в нём нет: только имена таблиц.
 */
final class Visible
{
    /**
     * Товар виден: сам опубликован, не убран, и ни один раздел над ним
     * не снят с витрины и не убран.
     *
     * @param string $alias под каким именем таблица product в запросе
     */
    public static function product(string $alias = 'p'): string
    {
        return $alias . '.is_published = 1 AND ' . $alias . '.deleted_at IS NULL'
            . ' AND NOT EXISTS ('
            . '   SELECT 1 FROM category vc'
            . '   WHERE (vc.is_published = 0 OR vc.deleted_at IS NOT NULL)'
            . "     AND {$alias}.path LIKE CONCAT(vc.path, '%')"
            . ' )';
    }

    /** Раздел виден: опубликован, не убран, и все разделы над ним тоже. */
    public static function category(string $alias = 'c'): string
    {
        return $alias . '.is_published = 1 AND ' . $alias . '.deleted_at IS NULL'
            . ' AND NOT EXISTS ('
            . '   SELECT 1 FROM category vp'
            . '   WHERE (vp.is_published = 0 OR vp.deleted_at IS NOT NULL)'
            . "     AND vp.id <> {$alias}.id"
            . "     AND {$alias}.path LIKE CONCAT(vp.path, '%')"
            . ' )';
    }
}
