<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Model\City;
use Sklad\Model\CityContent;
use Sklad\Model\Html;

/**
 * Предпросмотр страницы или раздела в выбранном городе — до сохранения.
 *
 * Заказчик 03.10.2026: «когда города заполняешь, должен быть предпросмотр
 * страницы». Городской текст пишут вслепую: что получится на поддомене —
 * какой заголовок, вступление, текст внизу, — было видно только после
 * «Сохранить» и захода на сам поддомен. Здесь та же форма карточки уходит
 * кнопкой «Предпросмотр» в новую вкладку, и витрина собирается так, будто
 * черновик уже сохранён и посетитель пришёл на поддомен этого города.
 *
 * Ничего не пишется: ни в базу, ни в журнал. Страница помечена как
 * предпросмотр — плашкой сверху, noindex и без счётчика Метрики.
 */
final class PreviewController extends Controller
{
    /** Что можно смотреть и каким разделом прав это закрыто. */
    private const ENTITIES = [
        'page' => ['page', 'content'],
        'category' => ['category', 'catalog'],
        // Тег — страница-подборка под разделом (05.10.2026), права те же, что у каталога.
        'tag' => ['tag', 'catalog'],
        // Карточка товара (28.10.2026): свои тексты товара для города.
        'product' => ['product', 'catalog'],
    ];

    public function show(array $params = []): string
    {
        $this->guard();

        $entity = (string) ($params['entity'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        if (!isset(self::ENTITIES[$entity]) || $id <= 0) {
            http_response_code(404);
            return 'Нечего показывать.';
        }
        [$table, $section] = self::ENTITIES[$entity];
        if (!$this->app->auth->can($section)) {
            http_response_code(403);
            return $this->render('admin/denied', ['section' => $section]);
        }

        $row = $this->app->db->one(
            'SELECT * FROM `' . $table . '` WHERE id = ? AND deleted_at IS NULL',
            [$id]
        );
        if ($row === null) {
            http_response_code(404);
            return 'Запись не найдена.';
        }
        // Служебные страницы (корзина, контакты, каталог…) правит только
        // администратор — ContentController::savePage отказывает остальным, и
        // показывать им предпросмотр того, что не сохранится, нельзя.
        if ($entity === 'page' && !$this->isAdmin()
            && in_array((string) ($row['template'] ?? ''), ContentController::SERVICE, true)) {
            return $this->notice('Эту страницу правит только администратор — её тексты, в том числе городские, сохранить не получится.');
        }

        $slug = (string) ($_POST['preview_city'] ?? '');
        $city = City::bySlug($slug);
        if ($city === null) {
            http_response_code(400);
            return 'Город не выбран.';
        }

        // Общие поля карточки — как их сохранит «Сохранить»: только присланные,
        // разметка — той же чисткой, что при сохранении (body()).
        $base = [];
        foreach (CityContent::FIELDS as $f) {
            if (array_key_exists($f, $_POST)) {
                $base[$f] = in_array($f, ['intro', 'body'], true) ? $this->body($f) : $this->input($f, 500);
            }
        }

        // Поля города. Разметку чистим так же, как общий текст: черновик
        // контент-менеджера не должен выполнить скрипт в окне предпросмотра.
        $posted = $_POST['city'][$slug] ?? [];
        $values = [];
        foreach (CityContent::FIELDS as $f) {
            $v = is_array($posted) ? (string) ($posted[$f] ?? '') : '';
            $values[$f] = in_array($f, ['intro', 'body'], true) && !$this->isAdmin() ? Html::clean($v) : $v;
        }

        // Город основного адреса (обычно Москва) — без поддомена: городские
        // тексты там на витрине не накладываются (App::$city пуст), показываем так же.
        $onMain = $slug === City::home()['slug'];
        $this->app->city = $onMain ? null : $city;
        $this->app->view->share('city', $this->app->city);
        $this->app->preview = true;
        CityContent::preview($entity, $id, $slug, $base, $values);

        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');

        if ((int) $row['is_published'] !== 1) {
            return $this->notice('Страница снята с витрины — предпросмотр появится, когда её опубликуют.');
        }

        $html = (new \Sklad\Site\Resolver($this->app))->resolve((string) $row['path']);
        if ($html === null) {
            return $this->notice('Не удалось собрать страницу для предпросмотра.');
        }

        return $this->banner($html, $city, $onMain);
    }

    private function isAdmin(): bool
    {
        return ($this->app->auth->user()['role'] ?? '') === 'admin';
    }

    /** Плашка «это предпросмотр» поверх страницы. */
    private function banner(string $html, array $city, bool $onMain): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $where = $onMain
            ? 'основной адрес (Москва) — городские тексты здесь не применяются, видны общие'
            : $e($city['name']) . ' — ' . $e($this->app->cityUrl('/'));
        $bar = '<div role="note" style="position:sticky;top:0;z-index:9999;padding:8px 16px;'
            . 'background:#fff3cd;color:#5c4400;border-bottom:1px solid #e0c46c;'
            . 'font:600 14px/1.4 system-ui,sans-serif;text-align:center">'
            . 'Предпросмотр: ' . $where . '. Видны набранные Title, Description, вступление и текст — ещё не сохранены;'
            . ' название, H1 и «Показывать на сайте» — как сохранено. Вернитесь в карточку и нажмите «Сохранить».'
            . '</div>';

        $out = preg_replace('/<body\b[^>]*>/i', '$0' . $bar, $html, 1);

        return is_string($out) ? $out : $bar . $html;
    }

    private function notice(string $text): string
    {
        $e = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><meta charset="utf-8"><title>Предпросмотр</title>'
            . '<p style="font:16px/1.5 system-ui,sans-serif;margin:40px">' . $e . '</p>';
    }
}
