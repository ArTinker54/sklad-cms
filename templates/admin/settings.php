<?php
use Sklad\Core\View;
$e = [View::class, 'e'];
?>
<div class="head"><h1>Настройки</h1></div>

<form method="post" action="<?= $e($adminPath('setting')) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <?php foreach ($groups as $group => $rows): ?>
    <div class="pane" style="margin-bottom:1.25rem">
      <h2><?= $e($group) ?></h2>
      <?php foreach ($rows as $row): ?>
        <div>
          <?php if ($row['type'] === 'flag'): ?>
            <label class="check">
              <input type="checkbox" name="setting[<?= $e($row['key']) ?>]" value="1"<?= $row['value'] ? ' checked' : '' ?>>
              <span><?= $e($row['title'] ?: $row['key']) ?></span>
            </label>
          <?php else: ?>
            <label class="lbl" for="s-<?= $e($row['key']) ?>"><?= $e($row['title'] ?: $row['key']) ?></label>
            <?php if ($row['type'] === 'textarea'): ?>
              <textarea class="val" id="s-<?= $e($row['key']) ?>" name="setting[<?= $e($row['key']) ?>]" rows="3"><?= $e($row['value']) ?></textarea>
            <?php else: ?>
              <input class="val<?= $row['type'] === 'number' ? ' num' : '' ?>" id="s-<?= $e($row['key']) ?>"
                     name="setting[<?= $e($row['key']) ?>]" value="<?= $e($row['value']) ?>">
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($row['hint'] !== ''): ?><p class="note"><?= $e($row['hint']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <div class="save-bar"><button class="btn btn--primary" type="submit">Сохранить</button></div>
</form>