<?php

declare(strict_types=1);

namespace Sklad\Admin;

/**
 * Библиотека снимков.
 *
 * Нужна потому, что один и тот же снимок часто идёт нескольким товарам:
 * модели одной линейки отличаются высотой подъёма, а фотография у них
 * общая. Без библиотеки менеджер заливает файл заново на каждую позицию
 * — лишнее действие и лишний повод ошибиться размером.
 *
 * Экран обычный, серверный, без всплывающих окон. Прикрепление идёт
 * возвратом на карточку товара: так видно, куда именно снимок ушёл, и
 * не нужно гадать, сохранилось ли.
 */
final class MediaController extends Controller
{
    protected string $section = 'catalog';

    private const PER_PAGE = 60;

    public function index(array $params = []): string
    {
        $db = $this->app->db;

        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        // Номер товара, для которого выбираем. Пусто — просто смотрим
        // библиотеку.
        $forId = (int) ($_GET['for'] ?? 0);

        $where = ['1'];
        $args = [];
        if ($query !== '') {
            $where[] = '(m.original_name LIKE ? OR m.file LIKE ?)';
            $like = '%' . $query . '%';
            array_push($args, $like, $like);
        }
        if (($_GET['free'] ?? '') === '1') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM product_image pi WHERE pi.media_id = m.id)';
        }

        $sql = ' FROM media m WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $sql, $args);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        // В режиме выбора снимки этого товара идут первыми. Иначе они
        // теряются среди двух с лишним сотен чужих, и менеджер вслепую
        // прикрепляет то, что у товара уже есть.
        $order = 'm.id DESC';
        if ($forId > 0) {
            $order = '(SELECT COUNT(*) FROM product_image pi'
                . '   WHERE pi.media_id = m.id AND pi.product_id = ' . $forId . ') DESC, m.id DESC';
        }

        $rows = $db->all(
            'SELECT m.*,'
            . ' (SELECT COUNT(*) FROM product_image pi WHERE pi.media_id = m.id) AS used'
            . $sql . ' ORDER BY ' . $order . ' LIMIT ' . self::PER_PAGE
            . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $args
        );

        $product = null;
        if ($forId > 0) {
            $product = $db->one('SELECT id, title, path FROM product WHERE id = ? AND deleted_at IS NULL', [$forId]);
        }

        return $this->render('admin/media', [
            'title' => 'Фотографии',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'query' => $query,
            'free' => ($_GET['free'] ?? '') === '1',
            'product' => $product,
            // Что уже прикреплено к этому товару — чтобы не предлагать
            // прикрепить второй раз.
            'attached' => $product !== null
                ? $db->column('SELECT media_id FROM product_image WHERE product_id = ?', [$forId])
                : [],
        ]);
    }

    /** Прикрепить уже загруженный снимок к товару. */
    public function attach(array $params): void
    {
        $this->guard();
        $db = $this->app->db;

        $productId = (int) ($params['id'] ?? 0);
        $mediaId = (int) ($_POST['media_id'] ?? 0);

        $product = $db->one('SELECT id FROM product WHERE id = ? AND deleted_at IS NULL', [$productId]);
        $media = $db->value('SELECT id FROM media WHERE id = ?', [$mediaId]);
        if ($product === null || $media === null) {
            $this->app->redirect($this->app->adminPath('catalog'));
        }

        // INSERT IGNORE, а не проверка перед вставкой: пара
        // товар-снимок уникальна на уровне базы, и повторное нажатие
        // ничего не сломает.
        $db->run(
            'INSERT IGNORE INTO product_image (product_id, media_id, alt, sort)'
            . ' VALUES (?, ?, \'\', (SELECT COALESCE(MAX(s.sort), -1) + 1 FROM'
            . '   (SELECT sort FROM product_image WHERE product_id = ?) AS s))',
            [$productId, $mediaId, $productId]
        );

        $this->flash('Фотография прикреплена');
        $this->app->redirect($this->app->adminPath('catalog/' . $productId) . '#photos');
    }

    /**
     * Удалить снимок совсем — вместе с файлами.
     *
     * Только ничей: пока снимок стоит хоть у одного товара, удаление
     * оставило бы у него битую рамку. Открепить от товара и удалить
     * файл — разные действия, и путать их нельзя.
     *
     * Здесь удаление настоящее, без пометки. Причина простая: у файла
     * нет адреса в выдаче, терять нечего, а место он занимает. Тем и
     * отличается от товара и раздела.
     */
    public function delete(array $params): void
    {
        $this->guard();
        $db = $this->app->db;
        $id = (int) ($params['id'] ?? 0);

        $media = $db->one('SELECT * FROM media WHERE id = ?', [$id]);
        if ($media === null) {
            $this->app->redirect($this->app->adminPath('media'));
        }

        $used = (int) $db->value('SELECT COUNT(*) FROM product_image WHERE media_id = ?', [$id]);
        if ($used > 0) {
            $this->flash(
                'Снимок стоит у ' . $used . ' ' . ($used === 1 ? 'товара' : 'товаров')
                . '. Сначала открепите его там.',
                'bad'
            );
            $this->app->redirect($this->app->adminPath('media'));
        }

        $this->removeFiles((string) $media['file']);
        $db->delete('media', 'id = ?', [$id]);

        (new \Sklad\Core\Audit($db, $this->app->auth))
            ->write('media', $id, 'delete', [], ['file' => $media['file']]);

        $this->flash('Снимок удалён вместе с файлами');
        $this->app->redirect($this->app->adminPath('media'));
    }

    /**
     * Файлы снимка на диске.
     *
     * Их четыре: большой и плиточный, каждый в своём формате и webp. Имя
     * в базе одно, остальные достраиваются — так же, как их достраивает
     * витрина.
     */
    private function removeFiles(string $file): void
    {
        $root = $this->app->root() . '/public';
        $dot = strrpos($file, '.');
        if ($dot === false) {
            return;
        }
        $base = substr($file, 0, $dot);

        // Расширение берём из самой записи, а не из жёсткого списка.
        // Импорт каталога заводит снимки с исходным расширением, и .png
        // в библиотеке не редкость: перебор одних лишь jpg и webp стирал
        // у такого снимка только webp-пару — витрина оставалась с битым
        // <picture>, сам png лежал на диске, а админка рапортовала, что
        // удалила вместе с файлами.
        //
        // webp кладут рядом и загрузка, и импорт, поэтому его сносим
        // всегда; у самого webp-снимка пара совпадает с оригиналом,
        // и повторное имя из списка убираем.
        $extensions = array_unique([substr($file, $dot), '.webp']);

        // Папку загрузок разворачиваем один раз: если её нет, удалять
        // нечего и сверять путь не с чем.
        $uploadRoot = realpath($root . '/upload');
        if ($uploadRoot === false) {
            return;
        }

        foreach (['', '-card'] as $size) {
            foreach ($extensions as $ext) {
                $path = $root . $base . $size . $ext;
                // Проверяем, что путь не увёл за пределы папки загрузок:
                // имя приходит из базы, но полагаться на это не стоит.
                // Разделитель в конце обязателен: без него проверку
                // прошёл бы и соседний каталог вроде upload-old.
                $real = realpath($path);
                if ($real !== false && str_starts_with($real, $uploadRoot . DIRECTORY_SEPARATOR)) {
                    @unlink($real);
                }
            }
        }
    }
}
