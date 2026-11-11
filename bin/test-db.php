<?php

declare(strict_types=1);

// Только из командной строки.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Проверки того, что без базы не проверишь.
 *
 *   php bin/test-db.php
 *
 * Переносы разделов и товаров, переадресация со старых адресов, сборка
 * мета-тегов по шаблону, приём заявки с журналом согласия. Это самая
 * дорогая часть системы: ошибка здесь не видна на экране, а всплывает
 * потерянными позициями в выдаче или пропавшей заявкой.
 *
 * Всё делается в откатываемой сделке: каждая проверка заводит свои
 * записи и в конце откатывает их целиком. Настоящий каталог не
 * затрагивается — гонять можно и на боевом, ничего не оставив.
 */

$root = dirname(__DIR__);
require $root . '/src/Core/App.php';
Sklad\Core\App::registerAutoload($root);

use Sklad\Core\App;
use Sklad\Model\Meta;
use Sklad\Model\Paths;
use Sklad\Model\Slug;

$app = new App($root);
$db = $app->db;

$passed = 0;
$failed = [];
$group = '';

function group(string $name): void
{
    global $group;
    $group = $name;
}

function is_it(string $what, $actual, $expected): void
{
    global $passed, $failed, $group;
    if ($actual === $expected) {
        $passed++;

        return;
    }
    $failed[] = sprintf(
        "%s — %s\n      ждали:  %s\n      вышло:  %s",
        $group,
        $what,
        var_export($expected, true),
        var_export($actual, true)
    );
}

function ok(string $what, bool $condition): void
{
    is_it($what, $condition, true);
}

$now = date('Y-m-d H:i:s');
$mark = 'proverka-' . substr(sha1((string) getmypid() . $now), 0, 8);

// Всё внутри сделки: в конце откатим и следов не останется.
$db->pdo()->beginTransaction();

try {
    // -----------------------------------------------------------------------
    group('перенос раздела');

    $parentId = $db->insert('category', [
        'slug' => $mark, 'path' => '/' . $mark . '/', 'title' => 'Проверка',
        'is_published' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $childId = $db->insert('category', [
        'parent_id' => $parentId, 'slug' => 'vnutri', 'path' => '/' . $mark . '/vnutri/',
        'title' => 'Внутри', 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $goodsId = $db->insert('product', [
        'category_id' => $childId, 'slug' => 'tovar', 'path' => '/' . $mark . '/vnutri/tovar',
        'title' => 'Товар', 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $paths = new Paths($db);
    is_it('адрес раздела собирается', $paths->categoryPath($parentId, 'novyy'), '/' . $mark . '/novyy/');
    is_it('адрес товара собирается', $paths->productPath($childId, 'novyy'), '/' . $mark . '/vnutri/novyy');

    // Переименовываем верхний раздел — за ним должны переехать и
    // подраздел, и товар, а на старые адреса встать переадресация.
    $moved = $paths->moveCategory($parentId, '/' . $mark . '-new/');
    is_it('переадресаций поставлено', $moved >= 3, true);
    is_it(
        'подраздел переехал',
        $db->value('SELECT path FROM category WHERE id = ?', [$childId]),
        '/' . $mark . '-new/vnutri/'
    );
    is_it(
        'товар внутри переехал',
        $db->value('SELECT path FROM product WHERE id = ?', [$goodsId]),
        '/' . $mark . '-new/vnutri/tovar'
    );
    is_it(
        'старый адрес товара ведёт на новый',
        $db->value('SELECT to_path FROM redirect WHERE from_path = ?', ['/' . $mark . '/vnutri/tovar']),
        '/' . $mark . '-new/vnutri/tovar'
    );

    // Переносим обратно: цепочка должна свернуться, а не удлиниться.
    $paths->moveCategory($parentId, '/' . $mark . '/');
    $chain = $db->value('SELECT to_path FROM redirect WHERE from_path = ?', ['/' . $mark . '/vnutri/tovar']);
    ok('цепочка не выросла', $chain === null || $chain === '/' . $mark . '/vnutri/tovar');
    is_it(
        'правил, ведущих на себя, нет',
        (int) $db->value('SELECT COUNT(*) FROM redirect WHERE from_path = to_path'),
        0
    );

    // -----------------------------------------------------------------------
    group('перенос товара');

    $paths->moveProduct($goodsId, '/' . $mark . '/vnutri/drugoy');
    is_it(
        'адрес товара сменился',
        $db->value('SELECT path FROM product WHERE id = ?', [$goodsId]),
        '/' . $mark . '/vnutri/drugoy'
    );
    ok(
        'со старого встала переадресация',
        $db->value('SELECT to_path FROM redirect WHERE from_path = ?', ['/' . $mark . '/vnutri/tovar'])
            === '/' . $mark . '/vnutri/drugoy'
    );

    // -----------------------------------------------------------------------
    group('уникальность адреса');

    $db->insert('product', [
        'category_id' => $childId, 'slug' => 'sosed', 'path' => '/' . $mark . '/vnutri/sosed',
        'title' => 'Сосед', 'is_published' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    is_it('занятый адрес получает номер', Slug::unique($db, 'product', 'sosed', $childId), 'sosed-2');
    is_it('свободный остаётся как есть', Slug::unique($db, 'product', 'svobodnyy', $childId), 'svobodnyy');
    is_it('свой адрес не считается занятым', Slug::unique($db, 'product', 'drugoy', $childId, $goodsId), 'drugoy');

    // -----------------------------------------------------------------------
    group('мета-теги по шаблону');

    $db->update('category', [
        'product_title_tpl' => '{название} — купить, {раздел}',
        'product_description_tpl' => '{название}: грузоподъёмность {capacity_unit}.',
    ], 'id = ?', [$childId]);

    $optionId = $db->value('SELECT id FROM `option` WHERE `key` = ?', ['capacity']);
    if ($optionId !== null) {
        $db->run(
            'INSERT INTO product_option (product_id, option_id, value, value_num) VALUES (?, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$goodsId, $optionId, '2000', 2000]
        );
    }

    $product = $db->one('SELECT * FROM product WHERE id = ?', [$goodsId]);
    $category = $db->one('SELECT * FROM category WHERE id = ?', [$childId]);
    $meta = (new Meta($db))->forProduct($product, $category);

    is_it('заголовок собран по шаблону', $meta['title'], 'Товар — купить, Внутри');
    ok('шаблон отмечен как шаблон', $meta['from_template']);
    if ($optionId !== null) {
        is_it('характеристика с единицей', $meta['description'], 'Товар: грузоподъёмность 2000 кг.');
    }

    // Своё значение сильнее шаблона — иначе правка руками не имела бы смысла.
    $db->update('product', ['meta_title' => 'Своё название'], 'id = ?', [$goodsId]);
    $own = (new Meta($db))->forProduct($db->one('SELECT * FROM product WHERE id = ?', [$goodsId]), $category);
    is_it('своё значение сильнее шаблона', $own['title'], 'Своё название');
    ok('своё не помечено шаблоном', !$own['from_template']);

    // -----------------------------------------------------------------------
    group('заявка и согласие');

    $requestId = $db->insert('request', [
        'number' => '', 'type' => 'cart', 'name' => 'Проверка', 'phone' => '+70000000000',
        'status' => 'new', 'ip' => '127.0.0.1', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $db->insert('request_item', [
        'request_id' => $requestId, 'product_id' => $goodsId,
        'title' => 'Товар на момент заказа', 'price' => 100, 'qty' => 2,
    ]);
    $db->insert('consent', [
        'request_id' => $requestId, 'created_at' => $now, 'ip' => '127.0.0.1',
        'policy_url' => '/politika/', 'wording' => 'Согласие', 'subject' => '{}',
    ]);

    // Товар переименовали и убрали — позиция заявки обязана остаться
    // такой, какой её оформили.
    $db->update('product', ['title' => 'Переименован', 'deleted_at' => $now], 'id = ?', [$goodsId]);
    $item = $db->one('SELECT * FROM request_item WHERE request_id = ?', [$requestId]);
    is_it('название в заявке не поехало', $item['title'], 'Товар на момент заказа');
    is_it('цена в заявке не поехала', (float) $item['price'], 100.0);

    // Согласие не удаляется вместе с заявкой: сроки хранения разные.
    $db->delete('request', 'id = ?', [$requestId]);
    is_it(
        'согласие пережило удаление заявки',
        (int) $db->value('SELECT COUNT(*) FROM consent WHERE request_id = ?', [$requestId]),
        1
    );
    is_it(
        'позиции ушли вместе с заявкой',
        (int) $db->value('SELECT COUNT(*) FROM request_item WHERE request_id = ?', [$requestId]),
        0
    );
} catch (Throwable $e) {
    $failed[] = 'сорвалось: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
}

// Откат: ни одна проверочная запись в базе не остаётся.
$db->pdo()->rollBack();

$left = (int) $db->value('SELECT COUNT(*) FROM category WHERE slug LIKE ?', [$mark . '%']);
if ($left > 0) {
    $failed[] = 'откат не сработал: проверочных разделов осталось ' . $left;
}

if ($failed) {
    echo "\n[test-db] НЕ СОШЛОСЬ:\n";
    foreach ($failed as $line) {
        echo '  · ' . $line . "\n";
    }
}
printf("[test-db] проверок %d, из них не сошлось %d\n", $passed + count($failed), count($failed));

exit($failed ? 1 : 0);
