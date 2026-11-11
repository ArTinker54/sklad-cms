<?php

use Sklad\Core\View;

/**
 * Люди и права.
 *
 * @var array      $rows
 * @var array      $roles
 * @var array|null $editing кого правим, если правим
 */
$e = [View::class, 'e'];
$u = $editing;
?>
<div class="head"><h1>Люди <span class="head__count"><?= count($rows) ?></span></h1></div>

<div class="two">
  <div class="pane">
    <h2>Кто есть</h2>
    <table class="grid">
      <thead><tr><th>Имя</th><th>Почта</th><th class="w-cat">Роль</th><th class="w-price">Заходил</th><th class="w-state"></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr<?= $row['is_active'] ? '' : ' class="is-hidden"' ?>>
            <td><span class="grid__title"><?= $e($row['name']) ?></span>
              <?php if (!$row['is_active']): ?><span class="tag tag--off">отключён</span><?php endif; ?></td>
            <td class="art"><?= $e($row['email']) ?></td>
            <td class="w-cat sub"><?= $e(explode(' —', $roles[$row['role']] ?? $row['role'])[0]) ?></td>
            <td class="w-price num"><?= $e(View::date($row['last_login_at'], 'd.m.Y H:i')) ?: '<span class="muted">ни разу</span>' ?></td>
            <td class="w-state">
              <a class="btn" href="<?= $e($adminPath('user')) ?>?edit=<?= (int) $row['id'] ?>">Изменить</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="note">Учётку не удаляют, а отключают: за человеком остаётся след в
       журнале изменений и в заявках, которые он вёл.</p>
  </div>

  <div class="pane">
    <h2><?= $u !== null ? 'Правим: ' . $e($u['name'] ?: $u['email']) : 'Новый человек' ?></h2>

    <?php if ($u !== null): ?>
      <p class="note"><a class="btn" href="<?= $e($adminPath('user')) ?>">Завести нового вместо этого</a></p>
    <?php endif; ?>

    <form method="post" action="<?= $e($adminPath('user')) ?>">
      <input type="hidden" name="_token" value="<?= $e($token) ?>">
      <input type="hidden" name="id" value="<?= (int) ($u['id'] ?? 0) ?>">

      <div><label class="lbl" for="u-name">Имя</label>
        <input class="val" id="u-name" name="name" value="<?= $e($u['name'] ?? '') ?>"></div>

      <div><label class="lbl" for="u-email">Почта — она же логин</label>
        <input class="val" type="email" id="u-email" name="email" required
               value="<?= $e($u['email'] ?? '') ?>"></div>

      <div><label class="lbl" for="u-pass">Пароль</label>
        <input class="val" type="password" id="u-pass" name="password" autocomplete="new-password">
        <p class="note">Не короче 12 знаков.<?= $u !== null
          ? ' Оставьте пустым, чтобы не менять.' : '' ?></p></div>

      <?php // Роль подставляется текущая. Пока её не подставляли, браузер
            // выбирал первый вариант списка — «Администратор», — и заход
            // поправить опечатку в имени молча выдавал полные права. ?>
      <div><label class="lbl" for="u-role">Роль</label>
        <select class="val" id="u-role" name="role">
          <?php foreach ($roles as $key => $label): ?>
            <option value="<?= $e($key) ?>"<?= ($u['role'] ?? 'content') === $key ? ' selected' : '' ?>>
              <?= $e($label) ?>
            </option>
          <?php endforeach; ?>
        </select></div>

      <label class="check">
        <input type="checkbox" name="is_active" value="1"<?= ($u === null || $u['is_active']) ? ' checked' : '' ?>>
        <span>Пускать в админку</span>
      </label>

      <div><button class="btn btn--primary" type="submit">Сохранить</button></div>
    </form>
  </div>
</div>
