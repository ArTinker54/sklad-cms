<?php

use Sklad\Core\View;
use Sklad\Model\Photo;
use Sklad\Model\Promo;

/**
 * Карточка товара.
 *
 * Ключ для скриптов — адрес, а не номер: по нему собран products.json,
 * из которого сравнение, избранное и поиск достают характеристики.
 * Адрес уникален, slug — нет: четыре модели исходного каталога числятся
 * сразу в нескольких разделах.
 *
 * @var array $product
 * @var array $category
 * @var array $effective итоговые заголовок, описание, H1
 * @var array $images
 * @var array $related
 * @var array $specs характеристики по группам
 */
$e = [View::class, 'e'];
$s = $settings;
$price = (float) $product['price'];
$old = (float) $product['old_price'];
$first = $images[0]['file'] ?? '';

// Ключевые характеристики приходят готовыми — тем же набором и порядком,
// что в плитке раздела. Если раздела нет (товар вне каталога), берём
// начало общей таблицы: лучше три строки, чем пустое место.
$keySpecs = array_slice(
    $keySpecs ?: array_merge(...array_values($specs ?: [[]])),
    0,
    3
);
?>
<main id="main" class="container">
  <article itemscope itemtype="https://schema.org/Product"
           data-product-id="<?= $e($product['path']) ?>" data-url="<?= $e($product['path']) ?>"
           data-image="<?= $e(Photo::card($first)) ?>" data-name="<?= $e($product['title']) ?>"
           <?= $price > 0 ? 'data-price="' . (int) $price . '"' : '' ?>
           data-specs="<?= $e(implode(' · ', array_map(
                static fn (array $s): string => trim($s['value'] . ' ' . $s['unit']),
                $keySpecs
           ))) ?>">

    <div class="product">
      <div class="product__main">
        <div class="product__head">
          <?php // Избранное и сравнение — двумя значками у заголовка, как на плитке. ?>
          <div class="product__head-tools">
            <button class="btn-fav" type="button" data-action="favorite" aria-pressed="false"
                    aria-label="Отложить в избранное" title="В избранное">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5 4.4 13a4.6 4.6 0 0 1 6.5-6.5l1.1 1.1 1.1-1.1A4.6 4.6 0 0 1 19.6 13Z"/></svg>
            </button>
            <button class="btn-fav btn-fav--cmp" type="button" data-action="compare" aria-pressed="false"
                    aria-label="Добавить к сравнению" title="Сравнить">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V9M12 20V4M19 20v-7"/></svg>
            </button>
          </div>
          <h1 itemprop="name"><?= $e($effective['h1']) ?></h1>
        </div>

        <figure class="gallery" style="margin:0">
          <div class="gallery__main">
            <picture>
              <?php if (!Photo::isEmpty($first)): ?>
                <source srcset="<?= $e(Photo::fullWebp($first)) ?>" type="image/webp">
              <?php endif; ?>
              <?php // Главное изображение карточки — то, что поисковик
                    // замеряет как самое крупное на экране. Просим браузер
                    // взять его первым, вперёд шрифтов и скриптов. ?>
              <img src="<?= $e(Photo::full($first)) ?>" alt="<?= $e($product['title']) ?>"
                   width="800" height="600" fetchpriority="high">
            </picture>
          </div>

          <?php // Ленту миниатюр показываем только когда снимков правда несколько. ?>
          <?php if (count($images) > 1): ?>
            <ul class="gallery__thumbs">
              <?php foreach ($images as $i => $image): ?>
                <li>
                  <?php // aria-current — то, по чему стили красят рамку выбранной
                        // миниатюры, и то, что объявляет выбор читалке. Класс
                        // оставлен: по нему product.js узнаёт, как состояние
                        // называется в теме. ?>
                  <button type="button" class="gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>"
                          <?= $i === 0 ? 'aria-current="true"' : '' ?>"
                          data-full="<?= $e(Photo::full($image['file'])) ?>"
                          data-webp="<?= $e(Photo::fullWebp($image['file'])) ?>"
                          aria-label="Снимок <?= $i + 1 ?>">
                    <img src="<?= $e(Photo::card($image['file'])) ?>" alt="" width="80" height="60" loading="lazy">
                  </button>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </figure>
        <?php if (!Photo::isEmpty($first)): ?>
          <?php // Адрес снимка — от текущего города, как и канонический:
                // на поддомене разметка не должна уводить на основной домен. ?>
          <link itemprop="image" href="<?= $e(rtrim($app->cityUrl(), '/') . Photo::full($first)) ?>">
        <?php endif; ?>
      </div>

      <?php // Блок цены и правая колонка — одной дорожкой: так они всегда
            // одной высоты и заканчиваются на одной линии. Порознь каждая
            // тянулась по своему содержимому, и нижние края расходились. ?>
      <div class="product__rail">
      <?php // Предложение объявляем только когда есть цена. Без неё Offer
            // выходит неполным: price — обязательное поле, и поисковик
            // помечает разметку ошибочной. У нас цена стоит у десятой части
            // каталога, так что иначе ошибка была бы почти на всех карточках.
            // Адрес берём тот же, что и канонический: на городском поддомене
            // он должен указывать на страницу этого поддомена, а не на
            // основной домен. ?>
      <aside class="buybox"<?= $price > 0
        ? ' itemprop="offers" itemscope itemtype="https://schema.org/Offer"' : '' ?>>
        <?php if ($price > 0): ?>
          <link itemprop="url" href="<?= $e(rtrim($app->cityUrl(), '/') . $product['path']) ?>">
          <meta itemprop="priceCurrency" content="RUB">
          <meta itemprop="itemCondition" content="https://schema.org/NewCondition">
          <meta itemprop="price" content="<?= number_format($price, 2, '.', '') ?>">
        <?php endif; ?>

        <?php // Ключевые характеристики у цены — как на b2b-store.example.ru:
              // главные цифры видно сразу, до таблицы прокручивать не надо. ?>
        <?php if ($keySpecs): ?>
          <div class="buybox__key">
            <p class="buybox__key-title">Технические характеристики</p>
            <ul>
              <?php foreach ($keySpecs as $ks): ?>
                <li><?= $e($ks['title']) ?>: <b><?= $e(trim($ks['value'] . ' ' . $ks['unit'])) ?></b></li>
              <?php endforeach; ?>
            </ul>
            <a class="buybox__key-all" href="#p-s">Смотреть все характеристики</a>
          </div>
        <?php endif; ?>

        <div class="buybox__price">
          <p class="buybox__label"><?= $price > 0 ? 'Цена (с НДС)' : 'Цена' ?></p>
          <?php if ($price > 0): ?>
            <?php // Зачёркнутая цена и срок — только пока скидка идёт.
                  // Истёкшая акция с датой в прошлом говорит о сайте
                  // больше, чем любая скидка. ?>
            <?php if (Promo::active($product)): ?>
              <p class="buybox__old"><s><?= $e(number_format($old, 0, ',', ' ')) ?>&nbsp;руб.</s>
                <span class="buybox__save">−<?= $e(number_format($old - $price, 0, ',', ' ')) ?>&nbsp;руб.</span></p>
            <?php endif; ?>
            <?php // Цена — крупно и жирно, единицу помельче: главное здесь она.
                  // Склад не называем: их несколько, «со склада в Москве» врёт. ?>
            <p class="buybox__value"><?= $e(number_format($price, 0, ',', ' ')) ?><span class="buybox__rub">руб.</span></p>
            <?php if (Promo::active($product) && !empty($product['promo_until'])): ?>
              <p class="buybox__until">Цена действует до <?= $e(View::date($product['promo_until'])) ?></p>
            <?php endif; ?>
          <?php else: ?>
            <?php // Ноль в рублях покупатель читает как «бесплатно». Пишем словами. ?>
            <p class="buybox__value buybox__value--ask">Цена по запросу</p>
            <p class="buybox__note">Пришлём счёт в течение двух часов</p>
          <?php endif; ?>
        </div>

        <div class="buybox__body">
          <?php // Остатков в реальном времени нет, поэтому availability в микроразметке
                // не заявляем: ложный InStock ловится модерацией Яндекса. ?>
          <p class="stock stock--order"><?= $e(match ($product['availability']) {
            'in_stock' => 'В наличии на складе',
            'out' => 'Под заказ, срок уточняйте',
            default => 'Наличие уточняйте у менеджера',
          }) ?></p>

          <p style="margin:var(--s-4) 0 0">
            <button class="btn btn--primary" type="button" data-action="request">Оставить заявку</button>
            <button class="btn btn--secondary" type="button" data-action="add-to-cart">В корзину</button>
          </p>

          <?php if (!empty($blocks['price_disclaimer'])): ?>
            <p class="disclaimer"><?= $e($blocks['price_disclaimer']) ?></p>
          <?php endif; ?>
        </div>
      </aside>

      <?php // Правая колонка — паспорт модели и тёплый лид, как на b2b-store.example.ru:
            // артикул, бренд, страна сборки, короткие гарантии и запрос скидки.
            // Страна берётся из настроек, по умолчанию Китай (завод Brand). ?>
      <aside class="product__aside">
        <ul class="product__facts">
          <?php if ($product['article'] !== ''): ?>
            <li><span>Артикул</span> <b class="mono" itemprop="sku"><?= $e($product['article']) ?></b></li>
          <?php endif; ?>
          <li><span>Бренд</span>
            <?php // itemprop нельзя вешать на саму ссылку: у элементов a, area
                  // и link значение берётся из href, и маркой оказывался адрес
                  // страницы о бренде. Название переносим внутрь. ?>
            <span itemprop="brand" itemscope itemtype="https://schema.org/Brand">
              <a href="/o-brende/"><span itemprop="name"><?= $e($product['vendor'] !== '' ? $product['vendor'] : $s->get('brand', 'Brand')) ?></span></a>
            </span>
          </li>
          <?php // Пусто — строку не показываем: пустое поле в паспорте
                // товара читается как недоделка, а страна у каждого сайта
                // сети своя и живёт в настройках. ?>
          <?php if (trim((string) $s->get('country')) !== ''): ?>
            <li><span>Производство</span> <b><?= $e($s->get('country')) ?></b></li>
          <?php endif; ?>
        </ul>

        <ul class="product__trust">
          <li><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 2.6v5.2c0 4.3-3 7.4-7 8.7-4-1.3-7-4.4-7-8.7V5.6z"/><path d="M9 12l2 2 4-4.2"/></svg></span>Гарантия качества</li>
          <li><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h10.5v8H3z"/><path d="M13.5 9h3.8L21 12.3v2.2h-7.5z"/><circle cx="7" cy="17.2" r="1.7"/><circle cx="17" cy="17.2" r="1.7"/></svg></span>Доставка по РФ</li>
          <li><span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9.5 9.5l5 5"/><circle cx="9.5" cy="9.5" r=".7"/><circle cx="14.5" cy="14.5" r=".7"/></svg></span>Техника в лизинг</li>
        </ul>

        <div class="promo">
          <span class="promo__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 17.5 17.5 6.5"/><circle cx="8" cy="8" r="2.1"/><circle cx="16" cy="16" r="2.1"/></svg></span>
          <p class="promo__title">Хотите индивидуальное предложение?</p>
          <p class="promo__sub">Оставьте заявку — подготовим КП</p>
          <button class="btn btn--primary" type="button" data-action="discount">Запросить КП</button>
        </div>
      </aside>
      </div>
    </div>

    <div class="tabs">
      <h2 class="visually-hidden">Подробная информация</h2>
      <ul class="tabs__list" role="tablist">
        <li role="presentation"><button class="tabs__btn" role="tab" id="t-s" aria-controls="p-s" aria-selected="true">Характеристики</button></li>
        <li role="presentation"><button class="tabs__btn" role="tab" id="t-d" aria-controls="p-d" aria-selected="false" tabindex="-1">Описание</button></li>
      </ul>

      <section class="tabs__panel" role="tabpanel" id="p-s" aria-labelledby="t-s">
        <?php if ($specs): ?>
          <?php foreach ($specs as $group => $rows): ?>
            <h3 class="specs__group"><?= $e($group) ?></h3>
            <table class="specs">
              <tbody>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <th scope="row"><?= $e($row['title']) ?></th>
                    <td><?= $e(trim($row['value'] . ' ' . $row['unit'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Характеристики уточняются. Напишите или позвоните — пришлём паспорт модели.</p>
        <?php endif; ?>
      </section>

      <section class="tabs__panel prose" role="tabpanel" id="p-d" aria-labelledby="t-d" hidden>
        <div itemprop="description"><?= trim((string) $product['body']) !== ''
          ? $product['body']
          : '<p>' . $e($effective['description']) . '</p>' ?></div>
      </section>

    </div>

    <?php if (!empty($related)): ?>
      <section class="related">
        <h2>Похожие модели</h2>
        <?php // Обёртка обязательна: плитка печатает itemprop="itemListElement",
              // и без списка эти свойства цепляются к товару страницы — выходит,
              // что у него есть «элементы списка», которых у типа Product нет.
              // Список без itemprop начинает отдельный объект, поэтому чужие
              // товары к текущему больше не приписываются. ?>
        <ul class="cards" itemscope itemtype="https://schema.org/ItemList">
          <?php foreach ($related as $i => $item): ?>
            <?= $view->render('site/' . $theme . '/_card', ['product' => $item, 'position' => $i + 1]) ?>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

  </article>
</main>

<?php // Липкая панель на телефоне: до цены и корзины иначе прокручивать
      // мимо всей фотографии — почти восемьсот точек. Стили под неё были
      // с самого начала, а разметки не было, и внизу каждой страницы
      // просто стоял пустой отступ в её высоту. ?>
<div class="stickybar" data-product-id="<?= $e($product['path']) ?>"
     data-url="<?= $e($product['path']) ?>" data-name="<?= $e($product['title']) ?>"
     data-image="<?= $e(Photo::card($first)) ?>"
     <?= $price > 0 ? 'data-price="' . (int) $price . '"' : '' ?>>
  <span class="stickybar__price">
    <?= $price > 0 ? $e(number_format($price, 0, ',', ' ')) . '&nbsp;руб.' : 'Цена по запросу' ?>
  </span>
  <button class="btn btn--primary" type="button" data-action="request">Оставить заявку</button>
</div>
