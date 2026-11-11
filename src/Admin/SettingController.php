<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;

/**
 * Настройки сайта.
 *
 * Телефон, почта, куда падают заявки, номер счётчика, формулировка
 * согласия. Список полей задаётся не кодом, а таблицей — новую
 * настройку добавляет миграция, и форма подхватывает её сама.
 */
final class SettingController extends Controller
{
    protected string $section = 'settings';

    public function index(array $params = []): string
    {
        return $this->render('admin/settings', [
            'title' => 'Настройки',
            'groups' => $this->app->settings->grouped(),
        ]);
    }

    public function save(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $before = $db->pairs('SELECT `key`, value FROM setting');
        $given = (array) ($_POST['setting'] ?? []);
        $after = [];

        foreach ($db->all('SELECT `key`, type FROM setting') as $row) {
            $key = $row['key'];
            // Снятая галочка в форму не приходит вовсе — для неё пустое
            // значение означает «выключено», а не «не трогали».
            $value = $row['type'] === 'flag'
                ? (empty($given[$key]) ? '' : '1')
                : trim((string) ($given[$key] ?? ''));

            if ((string) ($before[$key] ?? '') !== $value) {
                $db->update('setting', ['value' => $value], '`key` = ?', [$key]);
                $after[$key] = $value;
            }
        }

        (new Audit($db, $this->app->auth))->write('setting', null, 'update', $before, $after);

        $this->flash($after ? 'Настройки сохранены' : 'Менять было нечего');
        $this->app->redirect($this->app->adminPath('setting'));
    }
}
