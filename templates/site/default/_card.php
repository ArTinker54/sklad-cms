<?php

use Sklad\Core\View;
use Sklad\Model\Photo;
use Sklad\Model\Promo;

/**
 * Плитка товара.
 *
 * Разметка обязана совпадать с той, что рисует catalog.js: корзина,
 * сравнение и избранное цепляются к data-атрибутам, и расхождение
 * сломает их молча — кнопка нажимается, а в корзину ничего не кладётся.
 *
 * Ключ товара — адрес: он уникален, а slug нет. Четыре модели исходного
 * каталога числятся сразу в нескольких разделах.
 *
 * @var array $product строка товара плюс specs и photo
 * @var int   $position номер в списке, для микроразметки
 */
$e = [View::class, 'e'];
$photo = (string) ($product['photo'] ?? '');
$price = (float) ($product['price'] ?? 0);
$short = implode(' · ', array_map(
    static fn (array $s): string => trim($s['value'] . ' ' . $s['unit']),
    $product['specs'] ?? []
));
?>
<li class="card" itemprop="itemListElement" itemscope itemtype="https://schema.org/Product"
    data-product-id="<?= $e($product['path']) ?>" data-url="<?= $e($product['path']) ?>"
    data-name="<?= $e($product['title']) ?>" data-image="<?= $e(Photo::card($photo)) ?>"
    data-specs="<?= $e($short) ?>"<?= $price > 0 ? ' data-price="' . (int) $price . '"' : '' ?>>
  <meta itemprop="position" content="<?= (int) ($position ?? 1) ?>">

  <?php if (Promo::active($product)): ?>
    <p class="card__badge">Акция<?= !empty($product['promo_until'])
      ? ' до ' . View::date($product['promo_until'], 'd.m') : '' ?></p>
  <?php endif; ?>

  <div class="card__media">
    <?php // Избранное и сравнение — иконками поверх фото, рядом. Сравнение
          // переехало сюда из тела карточки: три полоски у сердечка. ?>
    <div class="card__tools">
      <button class="card__tool card__tool--fav" type="button" data-action="favorite" aria-pressed="false"
              title="Отложить в избранное" aria-label="Отложить в избранное">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5 4.4 13a4.6 4.6 0 0 1 6.5-6.5l1.1 1.1 1.1-1.1A4.6 4.6 0 0 1 19.6 13Z"/></svg>
      </button>
      <button class="card__tool card__tool--cmp" type="button" data-action="compare" aria-pressed="false"
              title="Добавить к сравнению" aria-label="Добавить к сравнению">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V9M12 20V4M19 20v-7"/></svg>
      </button>
    </div>
    <?php // В карточку можно зайти и по картинке, не только по названию.
          // Ссылка спрятана от читалок: заголовок ведёт туда же. ?>
    <a class="card__imglink" href="<?= $e($product['path']) ?>" tabindex="-1" aria-hidden="true">
      <picture>
        <?php if (!Photo::isEmpty($photo)): ?>
          <source srcset="<?= $e(Photo::cardWebp($photo)) ?>" type="image/webp">
        <?php endif; ?>
        <?php // Первый ряд грузим сразу, остальное отложенно. Отложенная
              // загрузка у всех подряд била по самой странице раздела: именно
              // снимок первого ряда поисковик замеряет как главное изображение
              // экрана, и откладывать его — значит своими руками портить
              // измерение скорости на страницах, куда и приходят из выдачи. ?>
        <img src="<?= $e(Photo::card($photo)) ?>" alt="<?= $e($product['title']) ?>"
             width="320" height="240"<?= (int) ($position ?? 99) <= 4 ? '' : ' loading="lazy"' ?>>
      </picture>
    </a>
  </div>

  <div class="card__body">
    <h3 class="card__title">
      <a href="<?= $e($product['path']) ?>" itemprop="url"><span itemprop="name"><?= $e($product['title']) ?></span></a>
    </h3>
    <ul class="card__specs">
      <?php foreach ($product['specs'] ?? [] as $spec): ?>
        <li><?= $e($spec['title']) ?>: <b><?= $e(trim($spec['value'] . ' ' . $spec['unit'])) ?></b></li>
      <?php endforeach; ?>
    </ul>

    <?php // Цены нет — так и пишем. Ноль в рублях покупатель читает как «бесплатно». ?>
    <p class="card__price">
      <?php if ($price > 0): ?>
        <span class="card__price-cap">Цена <span>(с&nbsp;НДС)</span></span>
        <span class="card__price-val<?= Promo::active($product) ? ' card__price-val--promo' : '' ?>">
          <?php if (Promo::active($product)): ?><s><?= $e(number_format((float) $product['old_price'], 0, ',', ' ')) ?>&nbsp;руб.</s><?php endif; ?>
          <b><?= $e(number_format($price, 0, ',', ' ')) ?>&nbsp;руб.</b>
        </span>
      <?php else: ?>
        <span class="card__price-cap">Цена</span>
        <span class="card__price-val card__price-val--ask">по запросу</span>
      <?php endif; ?>
    </p>

    <div class="card__actions">
      <button class="btn btn--primary" type="button" data-action="request">Оставить заявку</button>
      <button class="btn btn--ghost" type="button" data-action="add-to-cart">В корзину</button>
    </div>
  </div>
</li>
