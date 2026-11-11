<?php

declare(strict_types=1);

namespace Sklad\Site;

use Sklad\Core\App;
use Sklad\Model\Visible;

/**
 * Файлы для поисковиков: robots.txt, карта сайта, выгрузка для Яндекса.
 *
 * Отдаются страницами, а не лежат файлами на диске: каталог меняется в
 * админке каждый день, и файл пришлось бы пересобирать руками. Отдать
 * их запросом — дешевле, чем помнить про пересборку.
 *
 * Все три собираются из одних и тех же правил: что закрыто от поиска в
 * админке, того нет ни в карте, ни в выгрузке.
 */
final class FeedController
{
    public function __construct(private App $app)
    {
    }

    public function robots(array $params = []): void
    {
        // Контур разработки закрываем целиком. Это копия будущего сайта
        // с тем же каталогом и теми же текстами: попав в индекс, она
        // начнёт спорить с настоящим сайтом за те же запросы.
        if (!$this->app->isPublic()) {
            $this->send('text/plain; charset=utf-8', implode("\n", [
                '# Контур разработки, а не сайт. Индексировать нечего.',
                'User-agent: *',
                'Disallow: /',
                '',
            ]));
        }

        $custom = trim($this->app->settings->get('robots_txt'));
        // На городском поддомене адреса ведут на него же: карта сайта с
        // чужого домена бесполезна, а сама страница объявлена своей
        // канонической — расхождение поисковик читает как ошибку.
        $base = rtrim($this->app->cityUrl(), '/');

        // Служебные адреса закрываем всегда, даже если правило заменили
        // своим: забыть про /admin в robots легко, а последствия — вход
        // в админку в поисковой выдаче.
        $text = $custom !== '' ? $custom : implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /korzina/',
            'Disallow: /sravnenie/',
            'Disallow: /izbrannoe/',
            'Disallow: /poisk',
            '',
            '# Параметры сортировки и постраничной навигации плодят дубли',
            'Disallow: /*?sort=',
            'Disallow: /*?page=',
            'Disallow: /*?utm_',
        ]);

        // Папку с фотографиями не закрываем: снимки техники приводят людей
        // из поиска по картинкам, а от заливки чужого файла robots.txt всё
        // равно не защищает — это делает запрет исполнения в самой папке.
        $admin = rtrim($this->app->config['admin_path'] ?? '/admin', '/');
        if ($admin !== '' && !str_contains($text, $admin)) {
            $text .= "\nDisallow: " . $admin;
        }

        if ($this->app->settings->flag('sitemap_enabled')) {
            $text .= "\n\nSitemap: " . $base . '/sitemap.xml';
        }

        $this->send('text/plain; charset=utf-8', $text . "\n");
    }

    public function sitemap(array $params = []): void
    {
        if (!$this->app->settings->flag('sitemap_enabled')) {
            http_response_code(404);
            exit;
        }

        // На городском поддомене адреса ведут на него же: карта сайта с
        // чужого домена бесполезна, а сама страница объявлена своей
        // канонической — расхождение поисковик читает как ошибку.
        $base = rtrim($this->app->cityUrl(), '/');
        $db = $this->app->db;
        $urls = [];

        // Страницы, разделы, товары — в порядке важности для поисковика.
        // Всё, что закрыто от индексации или имеет свой основной адрес,
        // в карту не идёт: карта должна вести только на то, что мы
        // хотим видеть в выдаче.
        foreach ($db->all(
            "SELECT path, updated_at FROM page WHERE is_published = 1 AND is_indexable = 1 AND deleted_at IS NULL ORDER BY sort"
        ) as $row) {
            $urls[] = [$row['path'], $row['updated_at'], $row['path'] === '/' ? '1.0' : '0.5'];
        }

        // Один адрес — одна запись. Адрес разбирается в порядке «страница,
        // раздел, товар», и если страница и раздел завелись на одном пути,
        // открывается страница, а раздел недостижим. В карту же попадали
        // оба, и поисковик получал один и тот же адрес дважды. Первым
        // выигрывает тот, кто и откроется, — порядок обходов ниже такой же,
        // как в разборе адреса.
        $seen = [];
        foreach ($urls as [$path]) {
            $seen[$path] = true;
        }

        foreach ($db->all(
            'SELECT c.path, c.updated_at, c.canonical FROM category c'
            . ' WHERE c.is_indexable = 1 AND ' . Visible::category() . ' ORDER BY c.path'
        ) as $row) {
            if (trim((string) $row['canonical']) === '' && !isset($seen[$row['path']])) {
                $seen[$row['path']] = true;
                $urls[] = [$row['path'], $row['updated_at'], '0.8'];
            }
        }

        foreach ($db->all(
            'SELECT p.path, p.updated_at, p.canonical FROM product p'
            . ' WHERE p.is_indexable = 1 AND ' . Visible::product() . ' ORDER BY p.path'
        ) as $row) {
            if (trim((string) $row['canonical']) === '' && !isset($seen[$row['path']])) {
                $seen[$row['path']] = true;
                $urls[] = [$row['path'], $row['updated_at'], '0.7'];
            }
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as [$path, $updated, $priority]) {
            $body .= '  <url>' . "\n"
                . '    <loc>' . $this->esc($base . $path) . '</loc>' . "\n"
                . '    <lastmod>' . date('Y-m-d', strtotime((string) $updated) ?: time()) . '</lastmod>' . "\n"
                . '    <priority>' . $priority . '</priority>' . "\n"
                . '  </url>' . "\n";
        }

        $this->send('application/xml; charset=utf-8', $body . '</urlset>' . "\n");
    }

    /**
     * Выгрузка для Директа и Маркета.
     *
     * Цена в YML обязательна: предложение без неё Яндекс отклоняет
     * целиком. Поэтому товары без цены в выгрузку не идут, а сколько их
     * осталось за бортом — написано комментарием в начале файла. Молча
     * отдавать неполный каталог нельзя: со стороны он выглядит как
     * полный.
     */
    public function yml(array $params = []): void
    {
        if (!$this->app->settings->flag('yml_enabled')) {
            http_response_code(404);
            exit;
        }

        $db = $this->app->db;
        // На городском поддомене адреса ведут на него же: карта сайта с
        // чужого домена бесполезна, а сама страница объявлена своей
        // канонической — расхождение поисковик читает как ошибку.
        $base = rtrim($this->app->cityUrl(), '/');
        $settings = $this->app->settings;

        // Разделы перечисляем все живые, включая закрытые от индексации:
        // в выгрузке они не адреса, а дерево, на которое ссылаются
        // предложения. Пропусти один — и Яндекс отклонит все товары под
        // ним, потому что categoryId ссылается в пустоту.
        $categories = '';
        $known = [];
        foreach ($db->all(
            'SELECT c.id, c.parent_id, c.title FROM category c WHERE ' . Visible::category() . ' ORDER BY c.path'
        ) as $row) {
            $known[(int) $row['id']] = true;
            // Родитель, не попавший в список, ссылается в пустоту так же,
            // как и сам раздел: у верхнего уровня parentId не пишем вовсе.
            $parent = $row['parent_id'] !== null && isset($known[(int) $row['parent_id']])
                ? ' parentId="' . (int) $row['parent_id'] . '"'
                : '';
            $categories .= '      <category id="' . (int) $row['id'] . '"' . $parent
                . '>' . $this->esc($row['title']) . '</category>' . "\n";
        }

        $offers = '';
        $withPrice = 0;
        $withoutPrice = (int) $db->value(
            'SELECT COUNT(*) FROM product p WHERE p.price <= 0 AND ' . Visible::product()
        );

        $rows = $db->all(
            'SELECT p.*, (SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . '  WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo'
            // is_indexable здесь тоже: закрытому от поиска товару нечего
            // делать и в выгрузке для Директа — иначе объявление ведёт на
            // страницу, которую мы сами из индекса убрали.
            . ' FROM product p WHERE p.price > 0 AND p.is_indexable = 1 AND ' . Visible::product()
            . ' ORDER BY p.id'
        );

        $orphans = 0;
        foreach ($rows as $row) {
            // Предложение без раздела из списка Яндекс отклоняет. Такое
            // бывает у товара, оставшегося без раздела вовсе, — молча
            // выгружать его нельзя, но и ронять из-за него всю выгрузку
            // незачем: пропускаем и пишем сколько, комментарием в файле.
            if (!isset($known[(int) $row['category_id']])) {
                $orphans++;
                continue;
            }

            $withPrice++;
            $text = trim(strip_tags((string) ($row['intro'] !== '' ? $row['intro'] : $row['body'])));

            $offers .= '      <offer id="' . (int) $row['id'] . '" available="'
                . ($row['availability'] === 'out' ? 'false' : 'true') . '">' . "\n"
                . '        <url>' . $this->esc($base . $row['path']) . '</url>' . "\n"
                . '        <price>' . number_format((float) $row['price'], 0, '.', '') . '</price>' . "\n"
                . '        <currencyId>RUR</currencyId>' . "\n"
                . '        <categoryId>' . (int) $row['category_id'] . '</categoryId>' . "\n"
                . ($row['photo'] ? '        <picture>' . $this->esc($base . $row['photo']) . '</picture>' . "\n" : '')
                . ($row['vendor'] !== '' ? '        <vendor>' . $this->esc($row['vendor']) . '</vendor>' . "\n" : '')
                . ($row['article'] !== '' ? '        <vendorCode>' . $this->esc($row['article']) . '</vendorCode>' . "\n" : '')
                . '        <name>' . $this->esc($row['title']) . '</name>' . "\n"
                . ($text !== '' ? '        <description>' . $this->esc(mb_substr($text, 0, 3000)) . '</description>' . "\n" : '')
                . '      </offer>' . "\n";
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">' . "\n"
            . '<!-- Товаров с ценой: ' . $withPrice . '. Без цены и потому не выгружено: '
            . $withoutPrice . '. Цена в YML обязательна.'
            . ($orphans > 0 ? ' Без раздела и потому пропущено: ' . $orphans . '.' : '')
            . ' -->' . "\n"
            . '<yml_catalog date="' . date('Y-m-d H:i') . '">' . "\n"
            . '  <shop>' . "\n"
            . '    <name>' . $this->esc($settings->get('site_name')) . '</name>' . "\n"
            . '    <company>' . $this->esc($settings->get('company') ?: $settings->get('site_name')) . '</company>' . "\n"
            . '    <url>' . $this->esc($base . '/') . '</url>' . "\n"
            . '    <currencies>' . "\n      <currency id=\"RUR\" rate=\"1\"/>\n" . '    </currencies>' . "\n"
            . '    <categories>' . "\n" . $categories . '    </categories>' . "\n"
            . '    <offers>' . "\n" . $offers . '    </offers>' . "\n"
            . '  </shop>' . "\n"
            . '</yml_catalog>' . "\n";

        $this->send('application/xml; charset=utf-8', $body);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function send(string $type, string $body): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: ' . $type);
        header('Cache-Control: public, max-age=3600');
        echo $body;
        exit;
    }
}
