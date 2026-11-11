<?php

declare(strict_types=1);

namespace Sklad\Site;

use Sklad\Core\App;
use Sklad\Core\View;
use Sklad\Model\Photo;
use Sklad\Model\Promo;
use Sklad\Model\Visible;

/**
 * Индекс каталога для скриптов: поиск, сравнение, избранное, подбор.
 *
 * Собирается из базы на каждый запрос, а не лежит собранным файлом.
 * Файл означал бы, что цена живёт в двух местах: карточка берёт её из
 * базы, а поиск и подбор — из файла. Менеджер правит цену в админке, а в
 * результатах поиска остаётся прежняя — и никто этого не замечает
 * месяцами.
 *
 * Ключ товара — его адрес.
 *
 * Не номер: скрипты корзины и сравнения держат ключ в localStorage, а
 * номер после переезда базы ничего не значит. И не slug: в исходном
 * каталоге Brand четыре модели числятся сразу в двух-трёх разделах —
 * WF, HW, CBD20R-II и BF-III. Адреса у них разные, а slug один, и по
 * slug пять позиций из 247 просто затирали друг друга.
 */
final class ApiController
{
    public function __construct(private App $app)
    {
    }

    public function products(array $params = []): void
    {
        $db = $this->app->db;

        // Порядок — по заполненности: чаще заполненная характеристика
        // полезнее в плитке. Первые три показывает и сервер, и скрипт.
        $fields = [];
        foreach ($db->all(
            'SELECT o.key, o.title, o.unit, COUNT(po.product_id) AS filled FROM `option` o'
            . " JOIN product_option po ON po.option_id = o.id AND po.value <> ''"
            . ' GROUP BY o.id ORDER BY filled DESC, o.sort'
        ) as $row) {
            $fields[] = ['key' => $row['key'], 'title' => $row['title'], 'unit' => $row['unit']];
        }

        // Раздел верхнего уровня — подпись в подсказке поиска.
        $sections = $db->pairs(
            'SELECT path, title FROM category WHERE parent_id IS NULL AND is_published = 1 AND deleted_at IS NULL'
        );

        $rows = $db->all(
            // Код модели и наличие нужны плитке, которую подбор рисует сам:
            // без них после первого движения фильтра карточки теряли бы
            // строку с артикулом и статусом, и выглядит это как подмена
            // товара, а не как работа фильтра.
            'SELECT p.id, p.slug, p.path, p.title, p.price, p.old_price, p.promo_until, p.article, p.availability,'
            . ' (SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . '   WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo'
            . ' FROM product p WHERE ' . Visible::product()
        );

        $products = [];
        $byId = [];
        foreach ($rows as $row) {
            $section = '';
            foreach ($sections as $path => $title) {
                if (str_starts_with($row['path'], (string) $path)) {
                    $section = (string) $title;
                    break;
                }
            }

            $products[$row['path']] = [
                'name' => $row['title'],
                'file' => $row['path'],
                // Скрипты подставляют webp и сами меняют расширение на
                // jpeg, если браузер webp не понимает.
                'img' => Photo::isEmpty($row['photo'])
                    ? Photo::PLACEHOLDER
                    : Photo::cardWebp((string) $row['photo']),
                'price' => (float) $row['price'] > 0 ? (float) $row['price'] : null,
                // Скидка нужна и подбору: он перерисовывает плитки сам,
                // и без этих полей метка и зачёркнутая цена пропадали
                // после первого движения фильтра.
                'old' => (float) $row['old_price'] > 0 ? (float) $row['old_price'] : null,
                'promo' => Promo::active($row)
                    ? (trim((string) $row['promo_until']) !== '' && !str_starts_with((string) $row['promo_until'], '0000')
                        ? View::date($row['promo_until'], 'd.m')
                        : true)
                    : false,
                'article' => (string) $row['article'],
                'avail' => (string) $row['availability'],
                'section' => $section,
                'specs' => [],
            ];
            $byId[(int) $row['id']] = $row['path'];
        }

        if ($byId) {
            $ids = implode(',', array_map('intval', array_keys($byId)));
            foreach ($db->all(
                'SELECT po.product_id, o.key, po.value FROM product_option po'
                . " JOIN `option` o ON o.id = po.option_id WHERE po.product_id IN ({$ids}) AND po.value <> ''"
            ) as $row) {
                $key = $byId[(int) $row['product_id']] ?? null;
                if ($key !== null) {
                    $products[$key]['specs'][$row['key']] = $row['value'];
                }
            }
        }

        $this->json(['fields' => $fields, 'products' => (object) $products]);
    }

    private function json(array $data): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
