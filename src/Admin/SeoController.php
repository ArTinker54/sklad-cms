<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Model\Meta;

/**
 * Раздел «Поиск»: то, что видит поисковик.
 *
 * Собран вокруг проверок, а не полей. Поля правятся в карточках товара
 * и раздела; сюда приходят, чтобы увидеть, где не заполнено, где
 * повторяется и где длина такая, что в выдаче обрежется.
 */
final class SeoController extends Controller
{
    protected string $section = 'content';

    public function index(array $params = []): string
    {
        $db = $this->app->db;
        $meta = new Meta($db);

        return $this->render('admin/seo', [
            'title' => 'Поиск',
            'counts' => [
                'без заголовка' => (int) $db->value(
                    "SELECT COUNT(*) FROM product WHERE deleted_at IS NULL AND is_published = 1 AND meta_title = ''"
                ),
                'без описания' => (int) $db->value(
                    "SELECT COUNT(*) FROM product WHERE deleted_at IS NULL AND is_published = 1 AND meta_description = ''"
                ),
                'заголовок длиннее ' . Meta::TITLE_LIMIT => (int) $db->value(
                    'SELECT COUNT(*) FROM product WHERE deleted_at IS NULL AND is_published = 1'
                    . ' AND CHAR_LENGTH(meta_title) > ' . Meta::TITLE_LIMIT
                ),
                'описание длиннее ' . Meta::DESCRIPTION_LIMIT => (int) $db->value(
                    'SELECT COUNT(*) FROM product WHERE deleted_at IS NULL AND is_published = 1'
                    . ' AND CHAR_LENGTH(meta_description) > ' . Meta::DESCRIPTION_LIMIT
                ),
                'закрыто от поиска' => (int) $db->value(
                    'SELECT COUNT(*) FROM product WHERE deleted_at IS NULL AND is_published = 1 AND is_indexable = 0'
                ),
            ],
            'duplicateMeta' => $meta->duplicates(),
            'duplicateTitles' => $meta->duplicateTitles(),
            // Разделы без шаблона — там, где мета собирается из названия
            // и потому почти наверняка слабая.
            'templateless' => $db->all(
                'SELECT c.id, c.title, c.path,'
                . ' (SELECT COUNT(*) FROM product p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS goods'
                . " FROM category c WHERE c.product_title_tpl = ''"
                . ' HAVING goods > 0 ORDER BY goods DESC'
            ),
            'limits' => ['title' => Meta::TITLE_LIMIT, 'description' => Meta::DESCRIPTION_LIMIT],
        ]);
    }
}
