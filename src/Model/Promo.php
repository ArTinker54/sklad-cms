<?php

declare(strict_types=1);

namespace Sklad\Model;

/**
 * Действует ли скидка прямо сейчас.
 *
 * Одного «старая цена выше текущей» мало: у скидки бывает срок, и он
 * проходит. Пока проверяли только цены, истёкшая акция продолжала висеть
 * на плитках и в карточке — с датой в прошлом. Покупатель видит «Акция до
 * 12.05», сегодня июль, и понимает про сайт всё.
 *
 * Цену при этом система не трогает: снять скидку — решение продавца, а
 * не следствие календаря. Пропадает только метка.
 */
final class Promo
{
    public static function active(array $product): bool
    {
        $price = (float) ($product['price'] ?? 0);
        $old = (float) ($product['old_price'] ?? 0);

        if ($price <= 0 || $old <= $price) {
            return false;
        }

        $until = trim((string) ($product['promo_until'] ?? ''));
        if ($until === '' || str_starts_with($until, '0000')) {
            return true;                     // бессрочная
        }

        $time = strtotime($until);

        // Дата указана до сегодняшнего дня включительно: акция «до 29-го»
        // работает весь 29-й, а не заканчивается в полночь на его входе.
        return $time !== false && $time >= strtotime('today');
    }
}
