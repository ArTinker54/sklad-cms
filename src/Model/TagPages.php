<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Теги — страницы-подборки внутри раздела (05.10.2026).
 *
 * Не путать с Tags: там «Популярные запросы» — строки «подпись | адрес»,
 * которые ведут на уже существующие страницы. Тег — сама страница: свой
 * адрес под разделом, свои тексты и отобранные руками товары. Товар при
 * этом остаётся в своём разделе — в тег он «дублируется», как сказал
 * заказчик, а не переезжает.
 */
final class TagPages
{
    public function __construct(private Db $db)
    {
    }

    /**
     * Условие «в теге есть что показать» — хотя бы один видимый товар.
     *
     * Пустая подборка в «Популярных запросах» и в карте сайта — это ссылка
     * на страницу «пока пусто»: человек уходит ни с чем, поисковик видит
     * тонкую страницу.
     */
    public static function hasGoods(string $alias = 't'): string
    {
        return 'EXISTS (SELECT 1 FROM tag_product htp JOIN product p ON p.id = htp.product_id'
            . " WHERE htp.tag_id = {$alias}.id AND " . Visible::product('p') . ')';
    }

    /**
     * Полные названия разделов для списков админки: «Тележки › С весами».
     *
     * По одному «С весами» не понять, тележки это или штабелёры: подразделы
     * у разных веток называются одинаково.
     *
     * @return array<string, string> адрес раздела => название с предками
     */
    public function sectionTitles(): array
    {
        $titles = $this->db->pairs('SELECT path, title FROM category WHERE deleted_at IS NULL');
        $out = [];
        foreach ($titles as $path => $title) {
            $chain = [];
            $walked = '';
            foreach (array_filter(explode('/', trim((string) $path, '/'))) as $segment) {
                $walked .= '/' . $segment;
                if (isset($titles[$walked . '/'])) {
                    $chain[] = (string) $titles[$walked . '/'];
                }
            }
            $out[(string) $path] = implode(' › ', $chain ?: [(string) $title]);
        }

        return $out;
    }

    /**
     * Ссылки на теги для «Популярных запросов» раздела.
     *
     * Свои теги раздела, а если своих нет — ближайшего раздела выше по
     * ветке: подборку «Рохли 2 тонны» заводят у «Тележек», а нужна она и в
     * их подразделах. Так же наследуется и список «Популярных запросов».
     *
     * @param string $path    адрес раздела, для которого собираем
     * @param string $exclude адрес открытой страницы — на себя не ссылаемся
     * @return list<array{title: string, url: string}>
     */
    public function chips(string $path, string $exclude = ''): array
    {
        return $this->chipsWithLevel($path, $exclude)[1];
    }

    /**
     * То же, что chips(), и вместе с ним — адрес раздела, чьи это теги:
     * свой уровень или унаследованный. Витрине это нужно, чтобы поставить
     * свой список «Популярных запросов» подраздела раньше тегов, пришедших
     * сверху (ревью 05.10.2026).
     *
     * @return array{0: ?string, 1: list<array{title: string, url: string}>}
     */
    public function chipsWithLevel(string $path, string $exclude = ''): array
    {
        $branch = [];
        $walked = '';
        foreach (array_filter(explode('/', trim($path, '/'))) as $segment) {
            $walked .= '/' . $segment;
            $branch[] = $walked . '/';
        }
        if (!$branch) {
            return [null, []];
        }

        $rows = $this->db->all(
            'SELECT t.title, t.path, c.path AS category_path FROM tag t'
            . ' JOIN category c ON c.id = t.category_id'
            . ' WHERE c.path IN (' . implode(',', array_fill(0, count($branch), '?')) . ')'
            . ' AND ' . Visible::tag('t') . ' AND ' . self::hasGoods('t')
            . ' ORDER BY LENGTH(c.path) DESC, t.sort, t.title',
            $branch
        );

        // Ближайший к разделу уровень, у которого теги есть, — только его.
        $level = null;
        $out = [];
        foreach ($rows as $row) {
            $level ??= $row['category_path'];
            if ($row['category_path'] !== $level) {
                break;
            }
            if ($row['path'] === $exclude) {
                continue;
            }
            $out[] = ['title' => (string) $row['title'], 'url' => (string) $row['path']];
        }

        return [$level, $out];
    }

    /**
     * Состав тега из карточки тега.
     *
     * Снимаем только то, что человек видел в списке и сам снял. Целиком
     * состав из формы не пишем (ревью 05.10.2026): пока карточка тега была
     * открыта, товар могли добавить в тег из его карточки — устаревшая форма
     * молча выкинула бы его обратно. Убранные товары в списке не видны, и
     * их строки тоже остаются: вернут товар — он снова в своих тегах.
     *
     * @param list<int>       $shown   товары, которые были в списке формы
     * @param list<int>       $checked из них оставленные (отмеченные)
     * @param array<int, int> $sorts   место показанных: id => число
     * @param list<int>       $add     добавленные из списка всех товаров — встают в конец
     * @return array{before: list<int>, after: list<int>}
     */
    public function setProducts(int $tagId, array $shown, array $checked, array $sorts, array $add): array
    {
        return $this->db->transaction(function (Db $db) use ($tagId, $shown, $checked, $sorts, $add): array {
            $ints = static fn (array $ids): array => array_values(array_unique(array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )));
            $before = array_map('intval', $db->column(
                'SELECT product_id FROM tag_product WHERE tag_id = ? ORDER BY sort, product_id',
                [$tagId]
            ));
            $shown = $ints($shown);
            $checked = $ints($checked);

            // Что было в списке и снято — уходит; остальное остаётся как есть.
            $removed = array_diff($shown, $checked);
            $kept = array_values(array_diff($before, $removed));

            // Порядок: показанные — по числу «место» (при равных — как стояли),
            // за ними то, чего в форме не было (добавили, пока она была открыта),
            // в конце — отмеченные в списке всех товаров, как отмечены.
            $position = array_flip($before);
            $seen = array_flip($shown);
            $visible = array_values(array_filter($kept, static fn (int $id): bool => isset($seen[$id])));
            $others = array_values(array_filter($kept, static fn (int $id): bool => !isset($seen[$id])));
            usort($visible, static function (int $a, int $b) use ($sorts, $position): int {
                return [(int) ($sorts[$a] ?? 0), $position[$a] ?? PHP_INT_MAX, $a]
                    <=> [(int) ($sorts[$b] ?? 0), $position[$b] ?? PHP_INT_MAX, $b];
            });
            $order = array_merge($visible, $others);

            // Добавлять только существующие и не убранные: id из формы можно подменить.
            $add = array_values(array_diff($ints($add), $order));
            if ($add) {
                $alive = array_flip(array_map('intval', $db->column(
                    'SELECT id FROM product WHERE deleted_at IS NULL AND id IN (' . implode(',', $add) . ')'
                )));
                foreach ($add as $id) {
                    if (isset($alive[$id])) {
                        $order[] = $id;
                    }
                }
            }

            $db->delete('tag_product', 'tag_id = ?', [$tagId]);
            foreach ($order as $n => $id) {
                $db->run(
                    'INSERT INTO tag_product (tag_id, product_id, sort) VALUES (?, ?, ?)',
                    [$tagId, $id, ($n + 1) * 10]
                );
            }

            return ['before' => $before, 'after' => $order];
        });
    }

    /**
     * Теги товара из карточки товара: отмеченные — в тег (в конец списка),
     * снятые — из тега. Порядок остальных товаров в теге не трогаем.
     *
     * Снимаем только те, что были отмечены, когда карточку открыли, и
     * теперь сняты (ревью 05.10.2026): пока карточка товара была открыта,
     * товар могли добавить в тег из карточки тега, и устаревшая форма
     * вынула бы его обратно. Убранные теги в карточке не показываются — их
     * не трогаем вовсе: вернут тег — товар в нём на месте.
     *
     * @param list<int> $checked отмеченные сейчас
     * @param list<int> $was     отмеченные, когда карточку открыли
     * @return array{added: list<int>, removed: list<int>}
     */
    public function setTagsForProduct(int $productId, array $checked, array $was): array
    {
        return $this->db->transaction(function (Db $db) use ($productId, $checked, $was): array {
            $ints = static fn (array $ids): array => array_values(array_unique(array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )));
            // Только живые теги: убранные в форме не показаны и не трогаются.
            $before = array_map('intval', $db->column(
                'SELECT tp.tag_id FROM tag_product tp JOIN tag t ON t.id = tp.tag_id'
                . ' WHERE tp.product_id = ? AND t.deleted_at IS NULL',
                [$productId]
            ));
            $wanted = $ints($checked);
            if ($wanted) {
                $wanted = array_map('intval', $db->column(
                    'SELECT id FROM tag WHERE deleted_at IS NULL AND id IN (' . implode(',', $wanted) . ')'
                ));
            }

            $removed = array_values(array_intersect(array_diff($ints($was), $wanted), $before));
            $added = array_values(array_diff($wanted, $before));

            if ($removed) {
                $db->run(
                    'DELETE FROM tag_product WHERE product_id = ? AND tag_id IN (' . implode(',', $removed) . ')',
                    [$productId]
                );
            }
            foreach ($added as $tagId) {
                $last = (int) $db->value('SELECT COALESCE(MAX(sort), 0) FROM tag_product WHERE tag_id = ?', [$tagId]);
                $db->run(
                    'INSERT IGNORE INTO tag_product (tag_id, product_id, sort) VALUES (?, ?, ?)',
                    [$tagId, $productId, $last + 10]
                );
            }

            return ['added' => $added, 'removed' => $removed];
        });
    }
}
