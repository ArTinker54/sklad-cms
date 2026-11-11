<?php

declare(strict_types=1);

namespace Sklad\Site;

use Sklad\Core\App;
use Sklad\Model\CityContent;
use Sklad\Model\Visible;

/**
 * Ищет, что лежит по запрошенному адресу.
 *
 * Адрес хранится в базе целиком — у раздела, товара и страницы. Это
 * позволяет менеджеру переносить товар между разделами, не думая о том,
 * как соберётся ссылка, а системе — заводить редирект на старый адрес.
 *
 * Порядок поиска: страница, раздел, товар. Страницы первыми, потому что
 * их мало и они лежат в корне — так каталог не перехватит /kontakty/.
 */
final class Resolver
{
    public function __construct(private App $app)
    {
    }

    public function resolve(string $path): ?string
    {
        $clean = '/' . trim($path, '/');
        $withSlash = $clean === '/' ? '/' : $clean . '/';

        // Пришли не в том виде — переадресуем, а не отдаём содержимое.
        //
        // Раньше эта проверка стояла в самом конце и была недостижима:
        // выборки выше сами приводили адрес к нужному виду, и /shtabelery
        // без косой отдавал раздел кодом 200 наравне с /shtabelery/. Два
        // адреса с одной страницей — это дубли, и какой из них держать в
        // индексе, поисковик решал сам.
        $hasSlash = $path !== '/' && str_ends_with($path, '/');

        if ($clean !== '/') {
            // Раздел и страница живут с косой, товар — без.
            $needsSlash = $this->app->db->value(
                'SELECT 1 FROM page WHERE path = ? AND is_published = 1 AND deleted_at IS NULL',
                [$withSlash]
            ) ?? $this->app->db->value(
                'SELECT 1 FROM category WHERE path = ? AND is_published = 1 AND deleted_at IS NULL',
                [$withSlash]
            );

            if ($needsSlash !== null && !$hasSlash) {
                $this->app->redirect($withSlash, 301);
            }
            if ($needsSlash === null && $hasSlash && $this->app->db->value(
                'SELECT 1 FROM product WHERE path = ? AND is_published = 1 AND deleted_at IS NULL',
                [$clean]
            ) !== null) {
                $this->app->redirect($clean, 301);
            }
        }

        // Главная — отдельная страница с собственным шаблоном.
        if ($clean === '/') {
            return (new PageController($this->app))->home();
        }

        $page = $this->app->db->one(
            'SELECT * FROM page WHERE path = ? AND is_published = 1 AND deleted_at IS NULL',
            [$withSlash]
        );
        if ($page !== null) {
            $page = (new CityContent($this->app->db))
                ->apply($page, 'page', $this->app->city);

            return (new PageController($this->app))->render($page);
        }

        // Условие видимости общее с витриной. Со своим, облегчённым, сюда
        // проходили раздел и товар, спрятанные вместе с родительским
        // разделом: в списках их уже не было, а по прямой ссылке страница
        // открывалась — и оставалась в поисковой выдаче.
        $category = $this->app->db->one(
            'SELECT c.* FROM category c WHERE c.path = ? AND ' . Visible::category('c'),
            [$withSlash]
        );
        if ($category !== null) {
            // Городской текст накладываем ЗДЕСЬ, а не в шаблонах: дальше
            // раздел расходится по мете, заголовку, вступлению и тексту внизу,
            // и подменять пришлось бы в четырёх местах — четвёртое однажды
            // забыли бы. Пустые поля городской записи не перетирают общие.
            $category = (new CityContent($this->app->db))
                ->apply($category, 'category', $this->app->city);

            return (new CatalogController($this->app))->category($category);
        }

        // У товара косой на конце нет: так адреса отличаются от разделов
        // и не спорят за одну и ту же строку.
        $product = $this->app->db->one(
            'SELECT p.* FROM product p WHERE p.path = ? AND ' . Visible::product(),
            [$clean]
        );
        if ($product !== null) {
            return (new CatalogController($this->app))->product($product);
        }

        // Приведение адреса к единственно верному виду сделано выше, до
        // выборок: там оно и должно стоять. Здесь уже некому помогать —
        // адрес не нашёлся ни в одном виде, и дальше им занимается
        // таблица переадресаций со старого сайта.
        return null;
    }
}
