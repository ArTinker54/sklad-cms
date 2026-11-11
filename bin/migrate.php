<?php

declare(strict_types=1);

// Только из командной строки.
//
// Если веб-корень окажется не в public/, а в корне проекта — на дешёвом
// хостинге так бывает, — эта папка станет доступна из браузера. Тогда
// обычный запрос к migrate.php --fresh снесёт базу, а smoke.php выдаст
// зашедшему сессию администратора: он для проверки экранов подставляет
// её сам.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}


/**
 * Накат миграций из командной строки.
 *
 *   php bin/migrate.php            накатить недостающее
 *   php bin/migrate.php --status   показать, чего не хватает
 *   php bin/migrate.php --admin почта пароль [имя]
 *
 * Нужен там, где веб-установщик не годится: выкладка на сервер,
 * контур разработки, обновление сразу нескольких сайтов.
 */

$root = dirname(__DIR__);
require $root . '/src/Core/App.php';
Sklad\Core\App::registerAutoload($root);

use Sklad\Core\Db;
use Sklad\Core\Migrator;

$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Нет config/config.php — сначала установка.\n");
    exit(1);
}

$config = require $configFile;
$db = new Db($config['db']);
$migrator = new Migrator($db, $root . '/migrations');

$args = array_slice($argv, 1);
$command = $args[0] ?? '';

if ($command === '--status') {
    $pending = $migrator->pending();
    echo $pending
        ? "Не накачено: " . implode(', ', $pending) . "\n"
        : "Всё накачено.\n";
    exit(0);
}

if ($command === '--admin') {
    $email = mb_strtolower(trim($args[1] ?? ''));
    $password = (string) ($args[2] ?? '');
    $name = trim($args[3] ?? 'Администратор');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
        fwrite(STDERR, "Нужны почта и пароль не короче 12 знаков.\n");
        exit(1);
    }

    $db->run(
        'INSERT INTO user (email, password_hash, name, role, is_active, created_at)'
        . " VALUES (?, ?, ?, 'admin', 1, ?)"
        . ' ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role), is_active = 1',
        [$email, password_hash($password, PASSWORD_DEFAULT), $name, date('Y-m-d H:i:s')]
    );
    echo "Администратор {$email} заведён.\n";
    exit(0);
}

if ($command === '--fresh') {
    // Только для контура разработки: сносит все таблицы и накатывает
    // схему заново. Нужен, когда миграция упала на середине и половина
    // таблиц уже создана — на боевом такого делать нельзя, поэтому
    // команда отдельная и с явным именем.
    $tables = $db->column('SHOW TABLES');
    $db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $db->pdo()->exec('DROP TABLE IF EXISTS ' . $db->quoteName((string) $table));
    }
    $db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo 'Снесено таблиц: ' . count($tables) . "\n";
}

$applied = $migrator->migrate();
echo $applied
    ? "Накачено: " . implode(', ', $applied) . "\n"
    : "Новых миграций нет.\n";
