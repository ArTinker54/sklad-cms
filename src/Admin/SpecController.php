<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;

/**
 * Порядок характеристик.
 *
 * Тем же порядком они идут и в плитке каталога, и в карточке товара, и в
 * таблице характеристик: витрина сортирует по `option.sort`. Раньше этот
 * порядок правился только в базе, а на витрине группы шли алфавитом — и
 * первой характеристикой товара оказывались «Габариты» вместо
 * грузоподъёмности.
 *
 * Заводить и удалять характеристики здесь нельзя: их набор приходит с
 * каталогом, а лишняя строка в этой таблице сломала бы подбор. Экран
 * только про порядок — это то, что правят руками.
 *
 * Права — общие с каталогом: характеристики его часть.
 */
final class SpecController extends Controller
{
    protected string $section = 'catalog';

    public function index(array $params = []): string
    {
        $db = $this->app->db;

        return $this->render('admin/specs', [
            'title' => 'Характеристики',
            'rows' => $db->all(
                'SELECT o.id, o.key, o.title, o.unit, o.group, o.sort,'
                . '  (SELECT COUNT(*) FROM product_option po'
                . "    WHERE po.option_id = o.id AND po.value <> '') AS filled"
                . ' FROM `option` o ORDER BY o.sort, o.id'
            ),
            // Какая характеристика закреплена первой на витрине.
            'pinned' => trim((string) $this->app->settings->get('specs_pinned', 'capacity')),
        ]);
    }

    /** Сохранить порядок. Приходит номер на каждую характеристику. */
    public function save(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $order = (array) ($_POST['sort'] ?? []);
        $changed = 0;

        foreach ($order as $id => $value) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $sort = max(0, (int) $value);
            // Пишем только изменившиеся: незачем трогать всю таблицу и
            // засорять журнал сотней одинаковых записей.
            $before = $db->value('SELECT sort FROM `option` WHERE id = ?', [$id]);
            if ($before === null || (int) $before === $sort) {
                continue;
            }
            $db->update('option', ['sort' => $sort], 'id = ?', [$id]);
            $changed++;
        }

        // Какую характеристику держать первой всегда — отдельной настройкой:
        // в узком разделе она у всех одинаковая и по «различимости» вылетала
        // бы из плитки, хотя это главная цифра техники.
        //
        // Регистр приводим, а не вырезаем: прежний фильтр выбрасывал заглавные
        // буквы, и «Capacity» превращался в «apacity». Витрина сверяет ключ на
        // точное равенство — закрепление после этого молча не работало.
        $pinned = (string) preg_replace(
            '/[^a-z0-9_]/',
            '',
            mb_strtolower(trim((string) ($_POST['pinned'] ?? '')))
        );
        // Ключа, которого нет в таблице, витрина тоже не найдёт, а экран
        // отрапортовал бы «Порядок сохранён». Такой ввод не пишем вовсе.
        // Пустое значение — законное «ничего не закреплять».
        $known = $pinned === ''
            || $db->value('SELECT id FROM `option` WHERE `key` = ?', [$pinned]) !== null;
        if ($known && $pinned !== (string) $this->app->settings->get('specs_pinned', 'capacity')) {
            $db->run(
                'INSERT INTO setting (`key`, value, title, hint, `group`, type, sort)'
                . " VALUES ('specs_pinned', ?, 'Первая характеристика',"
                . " 'Ключ характеристики, которая всегда идёт первой на плитке и в карточке', 'Каталог', 'text', 90)"
                . ' ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$pinned]
            );
            $changed++;
        }

        if ($changed > 0) {
            (new Audit($db, $this->app->auth))
                ->write('option', 0, 'reorder', [], ['changed' => $changed]);
        }

        if (!$known) {
            $this->flash('Характеристики с ключом «' . $pinned . '» нет — закрепление осталось прежним.'
                . ' Ключ возьмите из колонки «Ключ» в таблице ниже.', 'bad');
        } else {
            $this->flash($changed > 0
                ? 'Порядок сохранён. На витрине он применится сразу.'
                : 'Ничего не изменилось.');
        }
        $this->app->redirect($this->app->adminPath('specs'));
    }
}
