<?php

declare(strict_types=1);

namespace Sklad\Site;

use Sklad\Model\Visible;

/**
 * Главная и обычные страницы.
 *
 * Какой шаблон рисовать, решает поле template у страницы, а не адрес.
 * Так менеджер может завести новую страницу в админке и выбрать ей вид
 * из списка, не трогая код.
 *
 * Страницы вроде корзины, сравнения и поиска — это разметка плюс скрипт,
 * работающий с памятью браузера. Сервер отдаёт их как есть и в индекс не
 * пускает: смотреть роботу там нечего, содержимое появляется только у
 * того, кто уже что-то отложил.
 */
final class PageController extends Page
{
    /** Известные виды страниц. Незнакомый — рисуем как текстовую. */
    private const TEMPLATES = ['page', 'cart', 'compare', 'favorites', 'search', 'contacts', 'catalog', 'promo'];

    public function home(): string
    {
        $db = $this->app->db;
        $page = $db->one("SELECT * FROM page WHERE path = '/' AND is_published = 1 AND deleted_at IS NULL");
        // Городские тексты («Тексты для городов» в карточке страницы) — как у
        // всех страниц. Главную Resolver отдаёт сюда раньше, чем накладывает
        // их на обычные страницы, поэтому здесь это надо сделать самим: без
        // этого заполненное для главной на поддоменах не показывалось.
        if ($page !== null) {
            $page = (new \Sklad\Model\CityContent($db))->apply($page, 'page', $this->app->city);
        }
        $this->pageScripts[] = 'home.js';

        // Условие видимости общее с каталогом: с облегчённым в «популярное»
        // выходил товар снятого с витрины подраздела, а карточка его не
        // открывает — клик с главной отдавал 404.
        $popular = $this->withSpecs($db->all(
            'SELECT p.*, (SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
            . '  WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo'
            . ' FROM product p WHERE ' . Visible::product()
            . ' ORDER BY p.sort, p.id LIMIT 4'
        ));

        return parent::show('home', [
            'page' => $page,
            'tiles' => $this->tiles(),
            'popular' => $popular,
            // Число моделей на главной — то же, что суммируют плитки: иначе
            // «в каталоге 247 моделей» не сходится с числами под плитками.
            'total' => (int) $db->value(
                'SELECT COUNT(*) FROM product p WHERE ' . Visible::product()
            ),
        ], [
            'title' => $page !== null && $page['meta_title'] !== ''
                ? $page['meta_title']
                : $this->app->settings->get('site_name'),
            'from_template' => $page === null || $page['meta_title'] === '',
            'description' => $page !== null ? $page['meta_description'] : '',
            'canonical' => $this->url('/'),
        ]);
    }

    /**
     * Скрипт, который оживляет страницу.
     *
     * Раньше эти скрипты лежали прямо в тексте страницы. Так их нельзя
     * было отделить от содержимого: менеджер правил абзац и мог снести
     * работающую часть. И строгая политика безопасности такие вставки
     * запрещает целиком — а без неё чужой скрипт, если он однажды
     * пролезет, выполнится беспрепятственно.
     */
    private const SCRIPTS = [
        'cart' => 'cart-page.js',
        'compare' => 'compare-page.js',
        'favorites' => 'favorites-page.js',
        'search' => 'search-page.js',
    ];

    public function render(array $page): string
    {
        $template = in_array($page['template'], self::TEMPLATES, true) ? $page['template'] : 'page';

        if (isset(self::SCRIPTS[$template])) {
            $this->pageScripts[] = self::SCRIPTS[$template];
        }

        $this->crumbs = [
            ['name' => 'Главная', 'url' => '/'],
            ['name' => $page['title']],
        ];

        $extra = [];
        if ($template === 'catalog') {
            $extra = ['tiles' => $this->tiles(), 'total' => (int) $this->app->db->value(
                'SELECT COUNT(*) FROM product p WHERE ' . Visible::product()
            )];
        }
        if ($template === 'promo') {
            // Витрина акций — тот же каталог, и условие видимости у неё
            // общее: товар снятого с витрины подраздела попадал в акции, а
            // карточка его не открывает.
            $extra = ['products' => $this->withSpecs($this->app->db->all(
                'SELECT p.*, (SELECT m.file FROM product_image pi JOIN media m ON m.id = pi.media_id'
                . '  WHERE pi.product_id = p.id ORDER BY pi.sort LIMIT 1) AS photo'
                . ' FROM product p WHERE ' . Visible::product()
                . ' AND p.old_price > p.price AND p.price > 0'
                . ' AND (p.promo_until IS NULL OR p.promo_until >= CURDATE())'
                . ' ORDER BY (p.old_price - p.price) DESC, p.sort'
            ))];
        }
        return parent::show($template, ['page' => $page] + $extra, [
            'title' => $page['meta_title'] !== '' ? $page['meta_title'] : $page['title'],
            'from_template' => $page['meta_title'] === '',
            'description' => $page['meta_description'],
            'canonical' => $this->url($page['path']),
            'noindex' => !$page['is_indexable'],
        ]);
    }

    /**
     * Плитки разделов с настоящим числом моделей.
     *
     * Счётчик считается запросом, а не вписан в текст. Вписанный устареет
     * на первом же завозе, и заметит это покупатель, а не менеджер.
     */
    private function tiles(): array
    {
        $db = $this->app->db;

        // Счёт вынесен во вложенный запрос, а не в HAVING: HAVING без
        // GROUP BY MySQL понимает, но на строгом режиме это не
        // гарантировано, а настройки боевого хостинга мы не выбираем.
        //
        // Разделы, чей адрес занят страницей, из плиток убираем: адрес
        // разбирается сверху вниз — страница, раздел, товар, — и перейти
        // в такой раздел всё равно некуда. Ссылка, ведущая не туда, хуже
        // отсутствующей.
        // Порядок — заданный в админке, а не по числу моделей. По числу
        // вперёд выходил самый крупный раздел, и витрина открывалась не с
        // того, с чего продавец хочет её открыть. Число моделей остаётся
        // на плитке, но очередь им больше не правит.
        // Раздел запчастей показываем даже пустым. Деталей в каталоге нет и
        // не будет — их не выбирают из плиток, а запрашивают по технике, и
        // сам раздел открывается формой запроса. Под общее правило «ноль
        // моделей — не показывать» он попадал вместе с недозаполненными
        // разделами, и попасть в него со страницы каталога было нельзя.
        $parts = trim((string) $this->app->settings->get('parts_path', ''));

        $tiles = $db->all(
            'SELECT * FROM ('
            . '  SELECT c.id, c.title, c.path, c.image, c.sort,'
            // Условие видимости общее со счётчиками подразделов ниже и со
            // страницей раздела: с облегчённым число на плитке не сходилось
            // ни с суммой чисел под ней, ни с числом в шапке раздела.
            . "   (SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(c.path, '%')"
            . '     AND ' . Visible::product() . ') AS goods'
            . '  FROM category c WHERE c.parent_id IS NULL AND c.is_published = 1 AND c.deleted_at IS NULL'
            . '   AND NOT EXISTS (SELECT 1 FROM page WHERE page.path = c.path AND page.is_published = 1 AND page.deleted_at IS NULL)'
            . ') AS t WHERE goods > 0 OR path = ? ORDER BY sort, title',
            [$parts]
        );

        foreach ($tiles as &$tile) {
            // Отдаём все подразделы, а сколько показать — решает шаблон.
            // С LIMIT 3 в запросе главная считала общее число категорий по
            // урезанному списку и писала «Все 21 категорию» вместо 48, а
            // каталог-обзор молча показывал три из четырнадцати, хотя по
            // своему назначению служит точкой входа для того, кто не знает
            // названий.
            $tile['children'] = $db->all(
                'SELECT * FROM ('
                . '  SELECT c.title, c.path, c.sort,'
                . "   (SELECT COUNT(*) FROM product p WHERE p.path LIKE CONCAT(c.path, '%')"
                . '     AND ' . Visible::product() . ') AS goods'
                . '  FROM category c WHERE c.parent_id = ? AND c.is_published = 1 AND c.deleted_at IS NULL'
                . ') AS t WHERE goods > 0 ORDER BY sort, title',
                [$tile['id']]
            );
            // Снимок раздела — первый снимок любого товара в нём, если
            // своего не задали: пустая плитка читается как «раздела нет».
            if (trim((string) $tile['image']) === '') {
                $tile['image'] = (string) ($db->value(
                    'SELECT m.file FROM product p'
                    . ' JOIN product_image pi ON pi.product_id = p.id'
                    . ' JOIN media m ON m.id = pi.media_id'
                    . " WHERE p.path LIKE CONCAT(?, '%') AND " . Visible::product()
                    . ' ORDER BY p.sort, pi.sort LIMIT 1',
                    [$tile['path']]
                ) ?? '');
            }
        }

        return $tiles;
    }
}
