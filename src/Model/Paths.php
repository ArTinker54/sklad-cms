<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Адреса разделов и товаров, и редиректы при их смене.
 *
 * Самое ответственное место во всей системе. Менеджер переименовывает
 * товар или переносит его в другой раздел, не думая про адреса, — а на
 * старом адресе висят ссылки из выдачи, из рекламы и из чужих статей.
 * Если старый адрес начнёт отдавать четырёхсотку, страница выпадет из
 * индекса, и вернуть её обратно будет стоить месяцев.
 *
 * Поэтому смена адреса и заведение редиректа — одна операция, в одной
 * транзакции. Половина этой работы хуже, чем ничего.
 */
final class Paths
{
    public function __construct(private Db $db)
    {
    }

    /** Адрес раздела: /shtabelery/shtabelery-soprovozhdaemye/ */
    public function categoryPath(?int $parentId, string $slug): string
    {
        $prefix = '/';
        if ($parentId !== null) {
            $parent = $this->db->value('SELECT path FROM category WHERE id = ?', [$parentId]);
            if ($parent !== null) {
                $prefix = (string) $parent;
            }
        }

        return $prefix . $slug . '/';
    }

    /** Адрес товара: /shtabelery/shtabelery-soprovozhdaemye/cddr15 — без косой на конце. */
    public function productPath(?int $categoryId, string $slug): string
    {
        $prefix = '/';
        if ($categoryId !== null) {
            $parent = $this->db->value('SELECT path FROM category WHERE id = ?', [$categoryId]);
            if ($parent !== null) {
                $prefix = (string) $parent;
            }
        }

        return $prefix . $slug;
    }

    /**
     * Меняет адрес и оставляет за собой редирект.
     *
     * Возвращает число заведённых редиректов: у раздела это он сам плюс
     * всё, что под ним.
     */
    public function moveCategory(int $id, string $newPath): int
    {
        return $this->db->transaction(function (Db $db) use ($id, $newPath): int {
            $oldPath = (string) $db->value('SELECT path FROM category WHERE id = ?', [$id]);
            if ($oldPath === '' || $oldPath === $newPath) {
                return 0;
            }

            $moved = 0;

            // Сам раздел
            $db->update('category', ['path' => $newPath], 'id = ?', [$id]);
            $moved += $this->remember($oldPath, $newPath);

            // Вложенные разделы: у них адрес начинается со старого.
            // Заменяем начало, хвост сохраняем как есть.
            $children = $db->all(
                'SELECT id, path FROM category WHERE path LIKE ? AND id <> ?',
                [$oldPath . '%', $id]
            );
            foreach ($children as $child) {
                $childPath = $newPath . substr($child['path'], strlen($oldPath));
                $db->update('category', ['path' => $childPath], 'id = ?', [$child['id']]);
                $moved += $this->remember($child['path'], $childPath);
            }

            // Товары во всём этом дереве
            $products = $db->all('SELECT id, path FROM product WHERE path LIKE ?', [$oldPath . '%']);
            foreach ($products as $product) {
                $productPath = $newPath . substr($product['path'], strlen($oldPath));
                $db->update('product', ['path' => $productPath], 'id = ?', [$product['id']]);
                $moved += $this->remember($product['path'], $productPath);
            }

            return $moved;
        });
    }

    public function moveProduct(int $id, string $newPath): int
    {
        return $this->db->transaction(function (Db $db) use ($id, $newPath): int {
            $oldPath = (string) $db->value('SELECT path FROM product WHERE id = ?', [$id]);
            if ($oldPath === '' || $oldPath === $newPath) {
                return 0;
            }
            $db->update('product', ['path' => $newPath], 'id = ?', [$id]);

            return $this->remember($oldPath, $newPath);
        });
    }

    /**
     * Заводит редирект со старого адреса на новый.
     *
     * Две тонкости, обе выяснены на живых сайтах:
     *
     * Первая — цепочки. Товар переименовали дважды: A → B, потом B → C.
     * Без правки первый редирект вёл бы на B, а B уже никуда не ведёт.
     * Поэтому все редиректы, указывавшие на старый адрес, переводим
     * сразу на новый.
     *
     * Вторая — возврат. Если менеджер вернул прежнее название, редирект
     * A → A стал бы петлёй. Такой удаляем.
     */
    private function remember(string $from, string $to): int
    {
        if ($from === $to) {
            return 0;
        }

        $this->db->run('UPDATE redirect SET to_path = ? WHERE to_path = ?', [$to, $from]);
        $this->db->run('DELETE FROM redirect WHERE from_path = to_path');
        $this->db->run('DELETE FROM redirect WHERE from_path = ?', [$to]);

        $this->db->run(
            'INSERT INTO redirect (from_path, to_path, code, is_auto, created_at) VALUES (?, ?, 301, 1, ?)'
            . ' ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)',
            [$from, $to, date('Y-m-d H:i:s')]
        );

        return 1;
    }
}
