<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Подраздел, показанный ещё и в других разделах (27.10.2026).
 *
 * Заказчик (Партнёр): «чтоб подкатегория жила в одном месте, а показывалась в
 * разных категориях». Подраздел остаётся там, где лежит: адрес, хлебные
 * крошки и товары у него одни, правится он в одном месте. В другом разделе
 * он встаёт плиткой, а его товары — в сетку, подбор и счётчики того
 * раздела, как будто лежат в нём. Связи — таблица category_also
 * (миграция 025): parent_id — где ещё показать, category_id — что.
 *
 * Связей единицы, поэтому весь их набор читается одним запросом, а
 * остальное считается здесь, в PHP. Где связей нет (у сайтов, которые ими
 * не пользуются), запросы витрины остаются прежними: пересчитывается только
 * то, чего связи касаются.
 *
 * Цепочки разбираются: подраздел А показан в Б, а Б — в В, значит, в В
 * видны и товары А. Петли (А в Б, Б в А) админка завести не даёт, а здесь
 * их на всякий случай обходим, не уходя в бесконечный круг.
 *
 * Дубль раздела (whole, миграция 026): раздел, показанный целиком, не встаёт
 * одной плиткой, а раскрывается — плитками идут его подразделы, товары все
 * его. Партнёр: «Рохля» — дубль «Гидравлических тележек» со своими текстами и
 * Title по городам.
 *
 * Товар в нескольких разделах (product_also, миграция 027): галочки
 * «показывать также в разделах» в карточке товара, как дополнительные
 * категории на b2b-store.example.ru и в OpenCart. Товар встаёт в сетку, подбор и
 * счётчики отмеченного раздела, разделов над ним и тех, где этот раздел
 * показан связями выше. Адрес у товара по-прежнему один.
 */
final class Also
{
    /** @var list<array{parent_id: int, parent_path: string, category_id: int, category_path: string, whole: bool}>|null */
    private ?array $links = null;

    /** @var array<int, true>|null */
    private ?array $touched = null;

    /** @var array<int, list<string>>|null товар => адреса разделов, где он показан ещё */
    private ?array $productLinks = null;

    /**
     * @param bool $visibleOnly только связи между видимыми на сайте разделами
     *                          (витрина); админке для проверки петель нужны все
     */
    public function __construct(private Db $db, private bool $visibleOnly = true)
    {
    }

    /** @return list<array{parent_id: int, parent_path: string, category_id: int, category_path: string, whole: bool}> */
    public function links(): array
    {
        if ($this->links === null) {
            $rows = $this->db->all(
                'SELECT al.parent_id, ap.path AS parent_path, al.category_id, ac.path AS category_path, al.whole'
                . ' FROM category_also al'
                . ' JOIN category ap ON ap.id = al.parent_id'
                . ' JOIN category ac ON ac.id = al.category_id'
                . ($this->visibleOnly
                    ? ' WHERE ' . Visible::category('ap') . ' AND ' . Visible::category('ac')
                    : ' WHERE ap.deleted_at IS NULL')
                . ' ORDER BY al.sort, ac.title, ac.id'
            );
            $this->links = array_map(static fn (array $row): array => [
                'parent_id' => (int) $row['parent_id'],
                'parent_path' => (string) $row['parent_path'],
                'category_id' => (int) $row['category_id'],
                'category_path' => (string) $row['category_path'],
                'whole' => (bool) $row['whole'],
            ], $rows);
        }

        return $this->links;
    }

    /**
     * Отметки «показывать также в разделах»: id товара => адреса отмеченных
     * разделов. Таблицы может ещё не быть (код выложен раньше миграции 027) —
     * тогда отметок просто нет, а не ошибка на всём каталоге.
     *
     * @return array<int, list<string>>
     */
    public function productLinks(): array
    {
        if ($this->productLinks === null) {
            $this->productLinks = [];
            try {
                $rows = $this->db->all(
                    'SELECT pa.product_id, c.path FROM product_also pa'
                    . ' JOIN category c ON c.id = pa.category_id'
                    . ' WHERE ' . ($this->visibleOnly ? Visible::category('c') : 'c.deleted_at IS NULL')
                    . ' ORDER BY pa.product_id, c.path'
                );
            } catch (\PDOException) {
                $rows = [];
            }
            foreach ($rows as $row) {
                $this->productLinks[(int) $row['product_id']][] = (string) $row['path'];
            }
        }

        return $this->productLinks;
    }

    /**
     * Товары из других мест, отмеченные в разделе $path, в любом его
     * подразделе или в показанных в нём ($prefixes из prefixes()). Свои,
     * лежащие под $path, сюда тоже могут попасть — условие «или» в scope()
     * от этого не меняется.
     *
     * @param list<string> $prefixes
     * @return list<int>
     */
    public function ticked(string $path, array $prefixes): array
    {
        $roots = array_merge([$path], $prefixes);
        $ids = [];
        foreach ($this->productLinks() as $id => $paths) {
            foreach ($paths as $marked) {
                foreach ($roots as $root) {
                    if (str_starts_with($marked, $root)) {
                        $ids[] = $id;
                        continue 3;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Разделы, где лежат товары $ids: подбор берёт их отметки «в подборе» —
     * иначе в новом разделе, наполненном одними галочками, подбора не было бы.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public function homes(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        return array_map('intval', $this->db->column(
            'SELECT DISTINCT category_id FROM product WHERE category_id IS NOT NULL'
            . ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')'
        ));
    }

    /**
     * Адреса чужих подразделов, чьи товары считаются товарами раздела $path:
     * показанные в нём самом или в любом его подразделе — и дальше по
     * цепочке. Лежащие внутри $path и вложенные друг в друга отброшены:
     * условие «адрес начинается с…» их и так накрывает.
     *
     * @return list<string>
     */
    public function prefixes(string $path): array
    {
        if (!$this->links()) {
            return [];
        }

        $found = [];
        $queue = [$path];
        $seen = [$path => true];
        while ($queue) {
            $root = array_shift($queue);
            foreach ($this->links as $link) {
                if (str_starts_with($link['parent_path'], $root) && !isset($seen[$link['category_path']])) {
                    $seen[$link['category_path']] = true;
                    $found[] = $link['category_path'];
                    $queue[] = $link['category_path'];
                }
            }
        }

        // Короче — раньше: адрес, вложенный в уже взятый, лишний.
        sort($found, SORT_STRING);
        $out = [];
        foreach ($found as $candidate) {
            if (str_starts_with($candidate, $path)) {
                continue;
            }
            foreach ($out as $kept) {
                if (str_starts_with($candidate, $kept)) {
                    continue 2;
                }
            }
            $out[] = $candidate;
        }

        return $out;
    }

    /**
     * Условие «товар в разделе $path или в показанных в нём»: [SQL, подстановки].
     * Без показанных — ровно прежнее «$alias.path LIKE '/razdel/%'».
     *
     * @param list<string> $prefixes из prefixes()
     * @param list<int>    $ids      ещё и эти строки по id: отмеченные товары из
     *                               ticked() (или их разделы из homes() — для
     *                               условия на разделы)
     * @return array{0: string, 1: list<string>}
     */
    public static function scope(string $path, array $prefixes, string $alias = 'p', array $ids = []): array
    {
        $parts = [];
        $args = [];
        foreach (array_merge([$path], $prefixes) as $prefix) {
            $parts[] = $alias . '.path LIKE ?';
            $args[] = $prefix . '%';
        }
        if ($ids) {
            $parts[] = $alias . '.id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }

        return [count($parts) > 1 ? '(' . implode(' OR ', $parts) . ')' : $parts[0], $args];
    }

    /**
     * Разделы, чей счёт меняют связи: те, что сами показывают чужие
     * подразделы, и все разделы над ними.
     *
     * @return array<int, true>
     */
    public function touched(): array
    {
        if ($this->touched === null) {
            $this->touched = [];
            $hosts = array_column($this->links(), 'parent_path');
            // Отмеченные товары (027) меняют счёт отмеченного раздела, разделов
            // над ним и тех, где он показан связями, — по цепочке.
            $marked = [];
            foreach ($this->productLinks() as $paths) {
                foreach ($paths as $path) {
                    $marked[$path] = true;
                }
            }
            $queue = array_keys($marked);
            while ($queue) {
                $path = array_shift($queue);
                foreach ($this->links() as $link) {
                    if (str_starts_with($path, $link['category_path']) && !isset($marked[$link['parent_path']])) {
                        $marked[$link['parent_path']] = true;
                        $queue[] = $link['parent_path'];
                    }
                }
            }
            $hosts = array_values(array_unique(array_merge($hosts, array_keys($marked))));
            if ($hosts) {
                foreach ($this->db->all('SELECT id, path FROM category WHERE deleted_at IS NULL') as $row) {
                    foreach ($hosts as $host) {
                        if (str_starts_with($host, (string) $row['path'])) {
                            $this->touched[(int) $row['id']] = true;
                            break;
                        }
                    }
                }
            }
        }

        return $this->touched;
    }

    /** Видимых товаров в разделе вместе с показанными в нём. */
    public function count(string $path): int
    {
        $prefixes = $this->prefixes($path);
        [$where, $args] = self::scope($path, $prefixes, 'p', $this->ticked($path, $prefixes));

        return (int) $this->db->value(
            'SELECT COUNT(*) FROM product p WHERE ' . $where . ' AND ' . Visible::product(),
            $args
        );
    }

    /**
     * Пересчитать счётчик у строк-разделов, которых касаются связи. У строк
     * нужны id и path; остальные строки не трогаем — их счёт уже верный.
     */
    public function recount(array $rows, string $key = 'goods'): array
    {
        $touched = $this->touched();
        if (!$touched) {
            return $rows;
        }
        foreach ($rows as &$row) {
            if (isset($touched[(int) ($row['id'] ?? 0)])) {
                $row[$key] = $this->count((string) $row['path']);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Чужие подразделы, показанные прямо в разделе $parentId: строки
     * category (выбранные $columns, всегда с id и path) со счётом goods, в
     * порядке связей. Раздел, показанный целиком (дубль), раскрывается: на
     * его месте — его подразделы и то, что показано в нём самом.
     */
    public function childrenOf(int $parentId, string $columns = 'c.*'): array
    {
        $ids = $this->childIds($parentId, [$parentId => true]);
        if (!$ids) {
            return [];
        }

        $byId = [];
        foreach ($this->db->all(
            'SELECT ' . $columns . ', c.id, c.path,'
            . "  (SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(c.path, '%')"
            . '    AND ' . Visible::product() . ') AS goods'
            . ' FROM category c WHERE c.id IN (' . implode(',', $ids) . ')'
        ) as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }

        return $this->recount($out);
    }

    /**
     * id плиток, которые связи добавляют разделу $parentId, по порядку.
     *
     * @param array<int, true> $seen уже раскрытые разделы — от петель
     * @return list<int>
     */
    private function childIds(int $parentId, array $seen): array
    {
        $ids = [];
        foreach ($this->links() as $link) {
            if ($link['parent_id'] !== $parentId || isset($seen[$link['category_id']])) {
                continue;
            }
            if (!$link['whole']) {
                $ids[] = $link['category_id'];
                continue;
            }
            // Дубль: подразделы показанного раздела — его собственные, затем
            // показанные в нём самом.
            foreach ($this->db->column(
                'SELECT c.id FROM category c WHERE c.parent_id = ? AND ' . Visible::category('c')
                . ' ORDER BY c.sort, c.title',
                [$link['category_id']]
            ) as $id) {
                $ids[] = (int) $id;
            }
            foreach ($this->childIds($link['category_id'], $seen + [$link['category_id'] => true]) as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Снимок для плитки раздела, у которого своих товаров с фото нет, — из показанных и отмеченных в нём. */
    public function photo(string $path): string
    {
        $prefixes = $this->prefixes($path);
        $ticked = $this->ticked($path, $prefixes);
        if (!$prefixes && !$ticked) {
            return '';
        }
        [$where, $args] = self::scope($path, $prefixes, 'p', $ticked);

        return (string) ($this->db->value(
            'SELECT m.file FROM product p'
            . ' JOIN product_image pi ON pi.product_id = p.id'
            . ' JOIN media m ON m.id = pi.media_id'
            . ' WHERE ' . $where . ' AND ' . Visible::product()
            . ' ORDER BY p.sort, pi.sort LIMIT 1',
            $args
        ) ?? '');
    }
}
