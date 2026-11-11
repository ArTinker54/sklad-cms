<?php

use Sklad\Core\View;
use Sklad\Model\Photo;

/**
 * Каталог целиком: плитки разделов.
 *
 * Число моделей в подписи считается запросом. Вписанное руками устареет
 * на первом же завозе, а заметит это покупатель, который зайдёт в раздел
 * «142 модели» и увидит там 130.
 *
 * @var array $page
 * @var array $tiles
 * @var int   $total
 */
$e = [View::class, 'e'];
// Адрес раздела запчастей — из настройки, той же, по которой раздел
// открывается формой запроса.
$partsPath = (string) $settings->get('parts_path', '');

$plural = static function (int $n, string $one, string $few, string $many): string {
    $mod100 = $n % 100;
    $mod10 = $n % 10;
    if ($mod100 >= 11 && $mod100 <= 14) {
        return $many;
    }

    return $mod10 === 1 ? $one : ($mod10 >= 2 && $mod10 <= 4 ? $few : $many);
};
?>
<main id="main">
  <div class="container">
    <div class="cathead">
      <h1><?= $e($page['title']) ?></h1>
      <p class="cathead__count"><?= $total ?> <?= $plural($total, 'модель', 'модели', 'моделей') ?>
        в <?= count($tiles) ?> <?= $plural(count($tiles), 'разделе', 'разделах', 'разделах') ?></p>
    </div>
  </div>

  <section class="section section--soft" style="padding-top:var(--s-6)">
    <div class="container">
      <ul class="tiles">
        <?php foreach ($tiles as $tile): ?>
          <li>
            <?php // Плитка перестала быть одной ссылкой на весь блок: подраздел
                  // внутри должен открываться сразу, а ссылку в ссылку вложить
                  // нельзя — браузер такую вложенность разрывает. Ссылкой стал
                  // заголовок, а стиль растягивает его на всю карточку: щелчок
                  // по пустому месту по-прежнему ведёт в раздел. ?>
            <div class="tile">
              <div class="tile__media">
                <picture>
                  <?php if (!Photo::isEmpty($tile['image'])): ?>
                    <source srcset="<?= $e(Photo::cardWebp($tile['image'])) ?>" type="image/webp">
                  <?php endif; ?>
                  <img src="<?= $e(Photo::card((string) $tile['image'])) ?>" alt="<?= $e($tile['title']) ?>"
                       width="320" height="200" loading="lazy">
                </picture>
              </div>
              <div class="tile__body">
                <a class="tile__name" href="<?= $e($tile['path']) ?>"><?= $e($tile['title']) ?></a>
                <?php // У раздела запчастей товаров нет и не будет: детали не
                      // выбирают из плиток, а запрашивают по технике. «0 моделей»
                      // на такой плитке читается как недозаполненный раздел. ?>
                <?php if ((string) $tile['path'] === $partsPath): ?>
                  <span class="tile__n">подберём по запросу</span>
                <?php else: ?>
                  <span class="tile__n"><b><?= (int) $tile['goods'] ?></b>
                    <?= $plural((int) $tile['goods'], 'модель', 'модели', 'моделей') ?></span>
                <?php endif; ?>
                <?php if (!empty($tile['children'])): ?>
                  <ul class="tile__sub">
                    <?php foreach ($tile['children'] as $child): ?>
                      <li><a href="<?= $e($child['path']) ?>"><?= $e($child['title']) ?></a><span><?= (int) $child['goods'] ?></span></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <span class="tile__more">Смотреть раздел →</span>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <?php if (trim((string) $page['body']) !== ''): ?>
    <div class="container"><div class="prose"><?= $page['body'] ?></div></div>
  <?php endif; ?>

  <?php // Кнопка заявки внизу страницы каталога — с 22.09.2026. Кнопка в
        // шапке есть, но к концу списка разделов она давно прокручена, а
        // своей у каталога не было вовсе. На главной есть форма #contacts,
        // на контактах — «Заказать звонок», в карточке и на плитках свои
        // кнопки, на акциях — «Запросить предложение». Липкую .stickybar
        // здесь не дублируем: её ставит только карточка товара. ?>
  <div class="container">
    <p class="pagecta"><button class="btn btn--primary" type="button" data-action="request">Оставить заявку</button></p>
  </div>
</main>
