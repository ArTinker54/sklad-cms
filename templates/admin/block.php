<?php
use Sklad\Core\View;
$e = [View::class, 'e'];
?>
<div class="head">
  <h1><?= $e($block['title']) ?></h1>
  <a class="btn" href="<?= $e($adminPath('page')) ?>">К списку</a>
</div>

<form class="pane" method="post" action="<?= $e($adminPath('block/' . $block['key'])) ?>">
  <input type="hidden" name="_token" value="<?= $e($token) ?>">
  <?php if ($block['hint'] !== ''): ?><p class="note"><?= $e($block['hint']) ?></p><?php endif; ?>
  <textarea class="val" name="body" rows="10" data-editor><?= $e($block['body']) ?></textarea>
  <div><button class="btn btn--primary" type="submit">Сохранить</button></div>
</form>