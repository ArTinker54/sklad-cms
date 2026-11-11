<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;

/**
 * Акции.
 *
 * Отдельного «флажка акции» у товара нет и не нужно: товар в акции, пока
 * старая цена выше текущей и срок не прошёл (см. Model\Promo, тот же
 * признак читает витрина /akcii/). Этот экран — удобная витрина того же
 * признака: показать всё, что сейчас со скидкой, и позволить поставить
 * или снять акцию, не открывая всю карточку товара.
 *
 * Права — общие с каталогом: акция это цена товара, отдельного раздела
 * прав она не заслуживает. Но смотреть и менять — разное: обе кнопки
 * правят цену, и они закрыты тем, кому цены не подчиняются (guardPrice
 * ниже).
 */
final class PromoController extends Controller
{
    protected string $section = 'catalog';

    public function index(array $params = []): string
    {
        $db = $this->app->db;

        // В акции — старая цена выше текущей. Просроченные по дате
        // показываем тоже: метка на витрине у них уже погасла, но старая
        // цена висит — акцию надо снять или продлить ей срок.
        $active = $db->all(
            'SELECT p.id, p.title, p.path, p.price, p.old_price, p.promo_until,'
            . ' c.title AS category_title,'
            . ' (SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . '  WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo'
            . ' FROM product p LEFT JOIN category c ON c.id = p.category_id'
            . ' WHERE p.deleted_at IS NULL AND p.price > 0 AND p.old_price > p.price'
            . ' ORDER BY (p.old_price - p.price) DESC, p.sort, p.id'
        );

        // Поставить в акцию можно только товар с ценой: без неё скидку не
        // от чего считать. Уже участвующих (из списка выше) не предлагаем —
        // их правят прямо в таблице кнопкой «Снять» или в карточке.
        $candidates = $db->all(
            'SELECT p.id, p.title, p.price, c.title AS category_title'
            . ' FROM product p LEFT JOIN category c ON c.id = p.category_id'
            . ' WHERE p.deleted_at IS NULL AND p.price > 0'
            . '   AND (p.old_price IS NULL OR p.old_price <= p.price)'
            . ' ORDER BY c.path, p.sort, p.id'
        );

        return $this->render('admin/promos', [
            'title' => 'Акции',
            'active' => $active,
            'candidates' => $candidates,
            'today' => date('Y-m-d'),
        ]);
    }

    /** Поставить товар в акцию: старая цена и необязательный срок. */
    public function assign(array $params = []): void
    {
        $this->guard();
        $this->guardPrice();
        $db = $this->app->db;

        $id = (int) ($_POST['product_id'] ?? 0);
        $product = $db->one('SELECT * FROM product WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($product === null) {
            $this->flash('Товар не найден', 'bad');
            $this->app->redirect($this->app->adminPath('promos'));
        }

        // За обычную цену берём «старую», если она выше текущей: товар уже
        // может быть в акции. Список кандидатов в соседней вкладке устаревает
        // за минуту, и взяв за обычную уже акционную цену, вторая акция
        // затирала настоящую цену навсегда: 500 000 → 450 000 → 430 000.
        $price = (float) $product['old_price'] > (float) $product['price']
            ? (float) $product['old_price']        // обычная цена — уйдёт в «старую»
            : (float) $product['price'];
        $salePrice = $this->money('sale_price');   // цена по акции — ниже обычной
        $until = $this->promoDate('promo_until');

        // Скидка — это цена НИЖЕ обычной. Без обычной цены её не от чего
        // отсчитывать; цена по акции не ниже обычной — это не скидка.
        if ($price <= 0) {
            $this->flash('У товара нет цены — сначала проставьте обычную в карточке. Без неё скидку не от чего считать.', 'bad');
            $this->app->redirect($this->app->adminPath('promos'));
        }
        if ($salePrice <= 0 || $salePrice >= $price) {
            $this->flash('Цена по акции должна быть ниже обычной (' . number_format($price, 0, ',', ' ') . ' ₽) — тогда это скидка.', 'bad');
            $this->app->redirect($this->app->adminPath('promos'));
        }

        // Обычная цена становится зачёркнутой «старой», цена по акции — новой.
        $fields = [
            'old_price' => $price,
            'price' => $salePrice,
            'promo_until' => $until,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->update('product', $fields, 'id = ?', [$id]);

        (new Audit($db, $this->app->auth))->write('product', $id, 'promo_on', $product, $fields);

        $this->flash('Акция: «' . $product['title'] . '» — '
            . number_format($salePrice, 0, ',', ' ') . ' ₽ вместо '
            . number_format($price, 0, ',', ' ') . ' ₽.');
        $this->app->redirect($this->app->adminPath('promos'));
    }

    /**
     * Снять акцию: вернуть обычную цену, старую обнулить, срок убрать.
     * Обычная цена лежит именно в «старой» — не вернув её, товар остался
     * бы продаваться по акционной цене, но уже без метки и без срока.
     */
    public function remove(array $params): void
    {
        $this->guard();
        $this->guardPrice();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $product = $db->one('SELECT * FROM product WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($product === null) {
            $this->app->redirect($this->app->adminPath('promos'));
        }

        // Снять акцию — вернуть обычную цену: она лежит в «старой». Метку
        // и срок убираем. Если акции по сути не было (старая не выше) —
        // цену не трогаем.
        $price = (float) $product['price'];
        $old = (float) $product['old_price'];
        $fields = [
            'price' => $old > $price ? $old : $price,
            'old_price' => 0,
            'promo_until' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db->update('product', $fields, 'id = ?', [$id]);

        (new Audit($db, $this->app->auth))->write('product', $id, 'promo_off', $product, $fields);

        $this->flash('Акция снята: «' . $product['title'] . '». Вернули обычную цену.');
        $this->app->redirect($this->app->adminPath('promos'));
    }

    /**
     * Ставить и снимать акции может тот, кому подчиняются цены.
     *
     * Смотреть на этот экран менеджеру можно и нужно — он видит, что
     * сейчас со скидкой, — а обе кнопки меняют именно цену товара, и это
     * не его дело (см. Auth::canPrice). Отказываем вслух: молчаливое
     * «ничего не произошло» человек прочтёт как «скидка назначена» и будет
     * ждать её на витрине, а на деле цена осталась прежней.
     */
    private function guardPrice(): void
    {
        if ($this->app->auth->canPrice()) {
            return;
        }

        $this->flash('Цены и акции ставит администратор или контент-менеджер —'
            . ' у вашей роли правка цен закрыта. Напишите им, какую скидку'
            . ' поставить или снять.', 'bad');
        $this->app->redirect($this->app->adminPath('promos'));
    }

    /** Цена из формы: менеджер пишет «1 100 000» и «1100000,50». */
    private function money(string $key): float
    {
        $raw = str_replace([' ', "\xc2\xa0"], '', (string) ($_POST[$key] ?? ''));
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? max(0, (float) $raw) : 0.0;
    }

    private function promoDate(string $key): ?string
    {
        $raw = trim((string) ($_POST[$key] ?? ''));
        if ($raw === '') {
            return null;
        }
        $time = strtotime($raw);

        return $time === false ? null : date('Y-m-d', $time);
    }
}
