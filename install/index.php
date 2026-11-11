<?php

declare(strict_types=1);

/**
 * Установщик.
 *
 * Ради него всё и затевалось: поставить систему на следующий сайт сети
 * должно быть делом получаса, а не дня. Поэтому один файл, никаких
 * зависимостей и проверка окружения до того, как человек начнёт
 * заполнять форму.
 *
 * После установки папку install надо удалить — установщик об этом
 * напоминает и сам отказывается работать, если конфиг уже есть.
 */

$root = dirname(__DIR__);
require $root . '/src/Core/App.php';
require $root . '/src/Core/Db.php';
require $root . '/src/Core/Migrator.php';

use Sklad\Core\Db;
use Sklad\Core\Migrator;

$configFile = $root . '/config/config.php';
$installed = is_file($configFile);

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Проверка окружения. Возвращает список [название, выполнено, пояснение]. */
$checks = static function (string $root): array {
    $list = [];
    $list[] = ['PHP 8.0 или новее', PHP_VERSION_ID >= 80000, 'Сейчас ' . PHP_VERSION];
    foreach (['pdo_mysql' => 'работа с базой', 'mbstring' => 'русский текст', 'json' => 'обмен данными'] as $ext => $why) {
        $list[] = ['Расширение ' . $ext, extension_loaded($ext), $why];
    }
    // Картинки пережимаются при загрузке, иначе менеджер зальёт снимок
    // с телефона на 6 мегабайт и страница будет грузиться минуту.
    $list[] = ['Расширение gd или imagick', extension_loaded('gd') || extension_loaded('imagick'), 'пережатие фотографий'];

    foreach (['/config' => 'настройки', '/storage' => 'кэш и журналы', '/public/upload' => 'загруженные фотографии'] as $dir => $why) {
        $path = $root . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $list[] = ['Папка ' . $dir . ' доступна на запись', is_dir($path) && is_writable($path), $why];
    }

    return $list;
};

$environment = $checks($root);
$ready = !in_array(false, array_column($environment, 1), true);

$errors = [];
$done = false;

if (!$installed && $ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $form = [
        'db_host' => trim($_POST['db_host'] ?? 'localhost'),
        'db_name' => trim($_POST['db_name'] ?? ''),
        'db_user' => trim($_POST['db_user'] ?? ''),
        'db_pass' => (string) ($_POST['db_pass'] ?? ''),
        'site_url' => rtrim(trim($_POST['site_url'] ?? ''), '/'),
        'theme' => preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['theme'] ?? 'default'))) ?: 'default',
        'admin_email' => mb_strtolower(trim($_POST['admin_email'] ?? '')),
        'admin_name' => trim($_POST['admin_name'] ?? ''),
        'admin_pass' => (string) ($_POST['admin_pass'] ?? ''),
    ];

    if ($form['db_name'] === '' || $form['db_user'] === '') {
        $errors[] = 'Заполните название базы и пользователя.';
    }
    if (!filter_var($form['site_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Адрес сайта должен быть полным: https://example.ru';
    }
    if (!filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Проверьте почту администратора.';
    }
    // Двенадцать знаков — не придирка: этот пароль открывает заявки с
    // персональными данными, и подбирать его будут роботы, а не люди.
    if (mb_strlen($form['admin_pass']) < 12) {
        $errors[] = 'Пароль администратора — не короче 12 знаков.';
    }

    $db = null;
    if (!$errors) {
        try {
            $db = new Db([
                'host' => $form['db_host'],
                'name' => $form['db_name'],
                'user' => $form['db_user'],
                'pass' => $form['db_pass'],
            ]);
        } catch (Throwable $ex) {
            $errors[] = 'База не отвечает: ' . $ex->getMessage();
        }
    }

    if (!$errors && $db !== null) {
        try {
            (new Migrator($db, $root . '/migrations'))->migrate();

            $db->run(
                'INSERT INTO user (email, password_hash, name, role, is_active, created_at)'
                . " VALUES (?, ?, ?, 'admin', 1, ?)"
                . ' ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)',
                [
                    $form['admin_email'],
                    password_hash($form['admin_pass'], PASSWORD_DEFAULT),
                    $form['admin_name'] !== '' ? $form['admin_name'] : 'Администратор',
                    date('Y-m-d H:i:s'),
                ]
            );

            // Часть настроек знает только установщик — проставляем сразу,
            // чтобы сайт открылся осмысленным, а не пустым.
            foreach (['site_name' => parse_url($form['site_url'], PHP_URL_HOST), 'manager_email' => $form['admin_email']] as $key => $value) {
                $db->run('UPDATE setting SET value = ? WHERE `key` = ? AND (value IS NULL OR value = \'\')', [(string) $value, $key]);
            }

            $sample = require $root . '/config/config.sample.php';
            $sample['db']['host'] = $form['db_host'];
            $sample['db']['name'] = $form['db_name'];
            $sample['db']['user'] = $form['db_user'];
            $sample['db']['pass'] = $form['db_pass'];
            $sample['site']['url'] = $form['site_url'];
            $sample['site']['theme'] = $form['theme'];

            $written = file_put_contents(
                $configFile,
                "<?php\n\n// Собран установщиком. Правится руками, в git не хранится.\n\nreturn "
                . var_export($sample, true) . ";\n"
            );
            if ($written === false) {
                $errors[] = 'Не удалось записать config/config.php — проверьте права на папку.';
            } else {
                $done = true;
            }
        } catch (Throwable $ex) {
            $errors[] = 'Установка прервалась: ' . $ex->getMessage();
        }
    }
}

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Установка</title>
<style>
  body { font: 16px/1.6 system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; color: #23272f;
         max-width: 46rem; margin: 0 auto; padding: 2rem 1rem 4rem; }
  h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
  .lead { color: #6b7280; margin: 0 0 2rem; }
  fieldset { border: 1px solid #dcdfe4; border-radius: 4px; margin: 0 0 1.5rem; padding: 1rem 1.25rem 1.25rem; }
  legend { font-weight: 700; padding: 0 .5rem; }
  label { display: block; margin: 0 0 .9rem; }
  label span { display: block; font-size: .875rem; color: #4b5563; margin-bottom: .25rem; }
  input { width: 100%; box-sizing: border-box; padding: .55rem .7rem; font: inherit;
          border: 1px solid #c9cdd4; border-radius: 3px; }
  button { font: inherit; font-weight: 700; padding: .7rem 1.6rem; border: 0; border-radius: 3px;
           background: #303849; color: #fff; cursor: pointer; }
  button[disabled] { background: #9aa1ad; cursor: not-allowed; }
  ul.checks { list-style: none; padding: 0; margin: 0 0 1.5rem; }
  ul.checks li { padding: .35rem 0; border-bottom: 1px solid #eef0f3; display: flex; gap: .6rem; }
  .ok { color: #1f7a3d; } .bad { color: #b3261e; }
  .note { color: #6b7280; font-size: .875rem; }
  .err { background: #fdecea; border-left: 3px solid #b3261e; padding: .75rem 1rem; margin: 0 0 1.5rem; }
  .done { background: #e8f4ec; border-left: 3px solid #1f7a3d; padding: 1rem 1.25rem; }
  code { background: #f2f4f7; padding: .1rem .35rem; border-radius: 3px; }
</style>
</head>
<body>

<h1>Установка</h1>
<p class="lead">Каталог складской техники — один сайт, одна база.</p>

<?php if ($installed): ?>
  <div class="done">
    <p><b>Система уже установлена.</b></p>
    <p>Файл <code>config/config.php</code> на месте. Если нужно поставить заново — удалите его,
       но помните, что база при этом останется прежней.</p>
    <p class="note">Папку <code>install</code> с боевого сайта надо удалить.</p>
  </div>
<?php elseif ($done): ?>
  <div class="done">
    <p><b>Готово.</b> База создана, администратор заведён.</p>
    <p>Дальше: удалите папку <code>install</code> и войдите в админку — <code>/admin</code>.</p>
  </div>
<?php else: ?>

  <ul class="checks">
    <?php foreach ($environment as [$title, $passed, $why]): ?>
      <li>
        <span class="<?= $passed ? 'ok' : 'bad' ?>"><?= $passed ? '✓' : '✕' ?></span>
        <span><?= $e($title) ?> <span class="note">— <?= $e($why) ?></span></span>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if (!$ready): ?>
    <div class="err">Пока не выполнены условия выше, установка не пойдёт. Обычно это решается
      в панели хостинга: версия PHP и расширения включаются там же.</div>
  <?php endif; ?>

  <?php foreach ($errors as $error): ?>
    <div class="err"><?= $e($error) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <fieldset>
      <legend>База данных</legend>
      <label><span>Сервер</span><input name="db_host" value="<?= $e($_POST['db_host'] ?? 'localhost') ?>"></label>
      <label><span>Название базы</span><input name="db_name" value="<?= $e($_POST['db_name'] ?? '') ?>" required></label>
      <label><span>Пользователь</span><input name="db_user" value="<?= $e($_POST['db_user'] ?? '') ?>" required></label>
      <label><span>Пароль</span><input type="password" name="db_pass" autocomplete="off"></label>
    </fieldset>

    <fieldset>
      <legend>Сайт</legend>
      <label><span>Адрес, полностью и без косой на конце</span>
        <input name="site_url" placeholder="https://equipment.example.ru" value="<?= $e($_POST['site_url'] ?? '') ?>" required></label>
      <label><span>Оформление — папка в templates/site</span>
        <input name="theme" value="<?= $e($_POST['theme'] ?? 'default') ?>"></label>
    </fieldset>

    <fieldset>
      <legend>Администратор</legend>
      <label><span>Имя</span><input name="admin_name" value="<?= $e($_POST['admin_name'] ?? '') ?>"></label>
      <label><span>Почта — она же логин</span>
        <input type="email" name="admin_email" value="<?= $e($_POST['admin_email'] ?? '') ?>" required></label>
      <label><span>Пароль — не короче 12 знаков</span>
        <input type="password" name="admin_pass" autocomplete="new-password" required></label>
    </fieldset>

    <button type="submit" <?= $ready ? '' : 'disabled' ?>>Установить</button>
  </form>

<?php endif; ?>

</body>
</html>
