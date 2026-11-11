<?php

use Sklad\Core\View;

/**
 * Акции.
 *
 * Список собирается из базы: акционным считается товар, у которого
 * старая цена выше текущей и срок ещё не вышел. Отдельного «списка
 * акций» нет намеренно — иначе скидку пришлось бы отмечать дважды, и
 * однажды она осталась бы только в одном месте.
 *
 * @var array $page
 * @var array $products
 * @var \Sklad\Core\Settings $settings
 */
$e = [View::class, 'e'];
?>
<main id="main" class="container">
  <div class="cathead">
    <h1><?= $e($page['title']) ?></h1>
    <?php if ($products): ?>
      <p class="cathead__count">Сейчас со скидкой: <?= count($products) ?></p>
    <?php endif; ?>
  </div>

  <?php if (trim((string) $page['body']) !== ''): ?>
    <div class="prose"><?= $page['body'] ?></div>
  <?php endif; ?>

  <?php if ($products): ?>
    <ul class="cards" itemscope itemtype="https://schema.org/ItemList">
      <?php foreach ($products as $i => $product): ?>
        <?= $view->render('site/' . $theme . '/_card', ['product' => $product, 'position' => $i + 1]) ?>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <?php // Пустой раздел объясняем словами: иначе он читается как поломка. ?>
    <?php // Текст — из настроек: у каждого сайта сети свой (миграция 015).
          // Пусто — нейтральный текст по умолчанию. ?>
    <p class="empty">
      <b><?= $e(trim((string) $settings->get('promo_empty_title')) ?: 'Сейчас действующих акций нет') ?></b>
      <?= $e(trim((string) $settings->get('promo_empty_text'))
          ?: 'Напишите или позвоните — под объём и под конкретную задачу цену всегда считаем отдельно.') ?>
    </p>
    <p><button class="btn btn--primary" type="button" data-action="request">Запросить предложение</button></p>
  <?php endif; ?>
</main>
