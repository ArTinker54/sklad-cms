<?php

use Sklad\Core\View;
use Sklad\Model\Photo;

/**
 * Раздел каталога.
 *
 * Первая страница приходит с сервера — её видит поисковик и тот, у кого
 * не выполнился скрипт. Как только человек трогает подбор, catalog.js
 * забирает раздел целиком и дальше рисует сетку сам.
 *
 * @var array $category
 * @var array $products
 * @var array $filters
 * @var array $sections
 * @var array $tags     список вида [['title' => …, 'url' => …], …], бывает пустым
 * @var int   $total, $page, $pages
 */
$e = [View::class, 'e'];
// Запчасти — не обычный раздел: их не выбирают из плиток, а запрашивают
// по технике. Поэтому раздел открывается формой запроса, а каталог
// деталей идёт под ней. Адрес раздела — из настройки, считаем и подразделы.
//
// Запасным значением здесь стоял адрес Brand. Шаблон один на всю сеть, и
// у остальных сайтов такого адреса нет: на стенде Tisel раздел запчастей
// открывался без формы запроса — то есть без того единственного, ради
// чего он заведён. Пусто значит «раздел запчастей не назначен», и
// плитка каталога читает настройку так же (catalog.php).
$partsPath = (string) $settings->get('parts_path', '');
// Форма только на самом разделе запчастей. В подразделе человек уже
// выбрал вид детали и смотрит товары — форма там перекрывала бы их
// собой на весь экран, хотя запрос он уже мог отправить выше.
$openParts = $partsPath !== '' && (string) $category['path'] === $partsPath;
?>
<main id="main" class="container" data-catalog="<?= $e($category['path']) ?>"
      data-page-size="<?= (int) $settings->int('per_page', 24) ?>"
      data-fields="<?= $e(implode(',', $shownFields ?? [])) ?>">

  <div class="cathead">
    <?php // Город дописываем только в заголовок раздела. В карточку товара
          // его переносить нельзя: там h1 помечен itemprop="name", и это
          // название товара для поисковика — «Электротягач QDD30 в Туле»
          // ушло бы в выдачу как имя модели. Хлебные крошки берут ту же
          // строку из базы и остаются без города — в них он лишний. ?>
    <h1><?= $e(\Sklad\Model\City::heading(
      $category['h1'] !== '' ? $category['h1'] : $category['title'],
      $city
    )) ?></h1>
    <?php if (trim((string) $category['intro']) !== ''): ?>
      <div class="prose"><?= $category['intro'] ?></div>
    <?php endif; ?>
  </div>

  <?php // Форму рисует request.js — набор полей и отправка общие с окном
        // заявки. Здесь только место под неё и запасной текст, если скрипт
        // не выполнился: телефон человек увидит в любом случае. ?>
  <?php if ($openParts): ?>
    <section class="pageform" data-parts-form>
      <div class="pageform__head">
        <h2>Запрос на запчасти</h2>
        <p>Заполните форму — так подбор и наш ответ будут быстрее.
           Или позвоните: <a href="tel:<?= $e($settings->get('phone_href')) ?>"><?= $e($settings->get('phone')) ?></a></p>
      </div>
    </section>
  <?php endif; ?>

  <?php // Подразделы — плитками со снимком, как на b2b-store.example.ru: по
        // названию «С весами» и «С короткими вилами» на глаз не различить,
        // а по картинке видно сразу. Пустой блок читается как поломка,
        // поэтому показываем только там, где подразделы есть. ?>
  <?php if (!empty($children)): ?>
    <nav class="subtiles" aria-label="Подразделы">
      <?php foreach ($children as $child): ?>
        <a class="subtile" href="<?= $e($child['path']) ?>">
          <span class="subtile__pic">
            <?php if (!Photo::isEmpty((string) ($child['image'] ?? ''))): ?>
              <img src="<?= $e(Photo::card((string) $child['image'])) ?>" alt=""
                   width="70" height="70" loading="lazy">
            <?php endif; ?>
          </span>
          <span class="subtile__name"><?= $e($child['title']) ?></span>
          <?php // Счётчик — только там, где есть что считать. Ноль ничего не
                // сообщает: человек и так увидит пустой подраздел, открыв его,
                // а «0» на плитке читается как поломка счётчика. В разделе
                // запчастей Hangcha «0» стоял на всех четырёх плитках. ?>
          <?php if ((int) ($child['goods'] ?? 0) > 0): ?>
            <span class="subtile__n"><?= (int) $child['goods'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php // Популярные запросы — под плитками и над подбором, как на
        // b2b-store.example.ru: человек сначала видит устройство раздела, потом
        // короткие пути к тому, что спрашивают чаще всего. Список приходит
        // готовым и уже урезанным; пустой блок с одним заголовком читается
        // как поломка, поэтому при пустом $tags не рисуем ничего. ?>
  <?php if (!empty($tags)): ?>
    <nav class="tagcloud" aria-label="Популярные запросы">
      <h2 class="tagcloud__head">Популярные запросы</h2>
      <ul class="tagcloud__list">
        <?php foreach ($tags as $tag): ?>
          <li class="tagcloud__item"><a class="tagcloud__link" href="<?= $e($tag['url']) ?>">#<?= $e($tag['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
  <?php endif; ?>

  <?php // Подбор и сортировка — только там, где есть что подбирать.
        //
        // В разделе запчастей Hangcha товаров нет вовсе, а страница всё равно
        // рисовала «Подбор по параметрам», «Найдено: 0» и список сортировки,
        // который нечего сортировать. Раздел от этого выглядел сломанным, а
        // не пустым. Ни один из этих органов управления ничего не делает при
        // нулевом числе товаров: подбирать не из чего, сортировать нечего.
        //
        // Мера — $total, число товаров всего поддерева раздела тем же
        // условием видимости, что и сетка: там, где товары есть, страница
        // остаётся прежней до последней строки.
        //
        // Без боковой колонки в сетке .catalog остаётся один ребёнок, и он
        // встал бы в первую колонку шириной 268 точек — отсюда модификатор.
        // Считаем его по самой колонке, а не по $hasGoods: колонки может не
        // быть и у раздела с товарами — когда подбирать не по чему и список
        // разделов пуст. Случай редкий, но раскладка ломалась бы так же.
        $hasGoods = $total > 0;
        $showFilters = $hasGoods && (!empty($filters) || !empty($sections)); ?>
  <div class="catalog<?= $showFilters ? '' : ' catalog--wide' ?>">

    <?php if ($showFilters): ?>
      <aside class="filters" id="filters" aria-label="Подбор по параметрам">
        <div class="filters__head">
          <h2 class="filters__title">Подбор</h2>
          <button class="filters__reset" type="button" id="reset" disabled>Сбросить</button>
          <button class="filters__close" type="button" id="close" aria-label="Закрыть фильтры">✕</button>
        </div>
        <div class="filters__body">

          <?php if (!empty($sections)): ?>
            <?php // Отмечаем один пункт — самый длинный подходящий адрес.
                  // Иначе в подразделе отмеченными оказывались и он, и
                  // раздел над ним, а какой покажет браузер — как повезёт. ?>
            <?php
            $activePath = '';
            foreach ($sections as $section) {
                if (str_starts_with($category['path'], $section['path'])
                    && strlen($section['path']) > strlen($activePath)) {
                    $activePath = $section['path'];
                }
            }
            ?>
            <div class="fsections">
              <label class="fgroup__legend" for="section-select">Раздел</label>
              <select id="section-select" class="fselect">
                <?php foreach ($sections as $i => $section): ?>
                  <?php // Первая строка — раздел целиком, остальные его
                        // подразделы: отбиваем их, чтобы вложенность читалась. ?>
                  <option value="<?= $e($section['path']) ?>"
                    <?= $section['path'] === $activePath ? ' selected' : '' ?>>
                    <?= $i > 0 && !empty($sections[0]['is_hub']) ? '　— ' : '' ?><?= $e($section['title']) ?> — <?= (int) $section['goods'] ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php // Без скрипта выпадающий список никуда не ведёт — оставляем ссылки. ?>
              <noscript>
                <ul style="list-style:none;margin:var(--s-2) 0 0;padding:0">
                  <?php foreach ($sections as $section): ?>
                    <li><a href="<?= $e($section['path']) ?>"><?= $e($section['title']) ?></a></li>
                  <?php endforeach; ?>
                </ul>
              </noscript>
            </div>
          <?php endif; ?>

          <?php foreach ($filters as $filter): ?>
            <div class="fgroup"><fieldset>
              <legend class="fgroup__legend"><?= $e($filter['title']) ?><?= $filter['unit'] !== ''
                ? ', ' . $e($filter['unit']) : '' ?></legend>

              <?php if ($filter['type'] === 'range'): ?>
                <div class="frange">
                  <input type="number" inputmode="numeric" data-filter="<?= $e($filter['key']) ?>" data-bound="min"
                         placeholder="<?= $e(number_format($filter['min'], 0, ',', ' ')) ?>"
                         aria-label="<?= $e($filter['title']) ?> от">
                  <span>—</span>
                  <input type="number" inputmode="numeric" data-filter="<?= $e($filter['key']) ?>" data-bound="max"
                         placeholder="<?= $e(number_format($filter['max'], 0, ',', ' ')) ?>"
                         aria-label="<?= $e($filter['title']) ?> до">
                </div>
                <p class="fhint">от <?= $e(number_format($filter['min'], 0, ',', ' ')) ?>
                   до <?= $e(number_format($filter['max'], 0, ',', ' ')) ?> <?= $e($filter['unit']) ?></p>
              <?php else: ?>
                <?php foreach ($filter['options'] as $value => $count): ?>
                  <label class="fcheck">
                    <input type="checkbox" data-filter="<?= $e($filter['key']) ?>" value="<?= $e($value) ?>">
                    <span><?= $e($value) ?></span><span class="fcheck__n"><?= (int) $count ?></span>
                  </label>
                <?php endforeach; ?>
              <?php endif; ?>

            </fieldset></div>
          <?php endforeach; ?>

        </div>
      </aside>
    <?php endif; ?>

    <div>
      <?php if ($hasGoods): ?>
        <div class="toolbar">
          <button class="btn btn--ghost filters__open" type="button" id="open">Подбор по параметрам</button>
          <p class="toolbar__found">Найдено: <b id="found"><?= (int) $total ?></b></p>
          <label>Сортировать
            <select id="sort">
              <option value="default">по популярности</option>
              <option value="price-asc">сначала дешевле</option>
              <option value="price-desc">сначала дороже</option>
              <option value="name">по названию</option>
            </select>
          </label>
        </div>
      <?php endif; ?>

      <h2 class="visually-hidden">Товары раздела</h2>

      <ul class="cards" id="cards" itemscope itemtype="https://schema.org/ItemList">
        <?php foreach ($products as $i => $product): ?>
          <?= $view->render('site/' . $theme . '/_card', [
                'product' => $product,
                'position' => ($page - 1) * $settings->int('per_page', 24) + $i + 1,
              ]) ?>
        <?php endforeach; ?>
      </ul>

      <?php if (!$products): ?>
        <p class="empty"><b>В этом разделе пока пусто</b>Напишите нам — подберём модель под задачу.</p>
      <?php endif; ?>

      <p class="empty" id="nothing" hidden><b>Под эти параметры ничего не нашлось</b>Смягчите условия или напишите нам — подберём модель под задачу.</p>

      <p class="more" id="more" hidden>
        <button class="btn btn--ghost" type="button">Показать ещё <span data-more-count><?= (int) $settings->int('per_page', 24) ?></span></button>
      </p>

      <?php // Постранично: в «Штабелёрах» 142 модели, одной страницей это почти 400 КБ. ?>
      <?php if ($pages > 1): ?>
        <div class="pager">
          <?php if ($page > 1): ?>
            <a class="pager__item" rel="prev"
               href="<?= $e($category['path'] . ($page - 1 > 1 ? '?page=' . ($page - 1) : '')) ?>">Назад</a>
          <?php endif; ?>
          <?php for ($n = 1; $n <= $pages; $n++): ?>
            <?php if ($n === $page): ?>
              <span class="pager__item pager__item--active" aria-current="page"><?= $n ?></span>
            <?php else: ?>
              <a class="pager__item" href="<?= $e($category['path'] . ($n > 1 ? '?page=' . $n : '')) ?>"><?= $n ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $pages): ?>
            <a class="pager__item" rel="next" href="<?= $e($category['path'] . '?page=' . ($page + 1)) ?>">Вперёд</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (trim((string) $category['body']) !== ''): ?>
    <div class="prose" style="margin-top:var(--s-8)"><?= $category['body'] ?></div>
  <?php endif; ?>
</main>
