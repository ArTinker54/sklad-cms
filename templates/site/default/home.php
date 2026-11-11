<?php

use Sklad\Core\View;
use Sklad\Model\Photo;

/**
 * Главная.
 *
 * Первый экран, разделы, популярные модели и форма подбора — в шаблоне;
 * тексты «О бренде» и «Почему у нас» приходят из админки телом страницы.
 * Разделение по одному признаку: то, что считается из каталога, считает
 * шаблон, а то, что пишет маркетолог, лежит в базе и правится без нас.
 *
 * @var array|null $page
 * @var array $tiles
 * @var array $popular
 * @var int   $total
 */
$e = [View::class, 'e'];
$s = $settings;

$plural = static function (int $n, string $one, string $few, string $many): string {
    $mod100 = $n % 100;
    $mod10 = $n % 10;
    if ($mod100 >= 11 && $mod100 <= 14) {
        return $many;
    }

    return $mod10 === 1 ? $one : ($mod10 >= 2 && $mod10 <= 4 ? $few : $many);
};

// Слайдов столько, сколько лежит файлов. Раньше стояла пятёрка числом, и
// тема одна на всю сеть: у сайта с тремя снимками два слайда оставались
// пустыми — точки под ними были, а фон не менялся, и человек тыкал в них
// впустую. Считаем подряд от первого до первого недостающего: дыра в
// нумерации означала бы пустой слайд ровно так же.
$slides = 0;
while (is_file($app->root() . sprintf('/public/assets/img/hero/slide%02d.jpg', $slides + 1))) {
    $slides++;
}

// Размеры знака марки берём у файла, а не пишем числами: у Brand он вытянутый,
// у TOR почти квадратный, и с чужими числами место под него резервировалось бы
// неверно — заголовок дёргался бы при загрузке.
$heroBrandPath = $app->root() . '/public/assets/img/brand-hero.png';
$heroBrandSize = is_file($heroBrandPath) ? @getimagesize($heroBrandPath) : false;
?>
<main id="main">

  <?php // Первый слайд отдаём сразу, остальные подгружаются после загрузки
        // страницы: пять фотографий по 200–280 КБ в начальный вес не нужны. ?>
  <section class="hero">
    <div class="hero__bg" id="hero-bg" aria-hidden="true">
      <?php for ($i = 1; $i <= $slides; $i++): ?>
        <?php $src = sprintf('/assets/img/hero/slide%02d.jpg', $i); ?>
        <div class="hero__slide<?= $i === 1 ? ' is-active' : '' ?>" data-src="<?= $src ?>"
             <?= $i === 1 ? 'style="background-image:url(\'' . $src . '\')"' : '' ?>></div>
      <?php endfor; ?>
    </div>

    <div class="container hero__grid">
      <div>
        <?php // Тексты первого экрана — из настроек. Раньше стояли прямо
              // здесь, а тема одна на всю сеть: на сайте TOR читалось «Тележки,
              // штабелёры, ричтраки, электротягачи» — перечень чужой марки,
              // причём без грузоподъёмного оборудования, которого у TOR почти
              // пятьсот позиций. Настройки завела миграция 011, но подключить
              // их тогда забыли, и они полгода лежали без дела.
              //
              // Запасные значения — прежние: у сайтов, где настройку не
              // заполнили, ничего не изменится. ?>
        <p class="hero__eyebrow"><?= $e($s->get('hero_eyebrow') ?: 'Официальный дилер в России') ?></p>
        <?php // Знак марки лежит по общему имени brand-hero.png, а не по
              // «hero-old»: тема одна на всю сеть, и на сайте TOR путь с
              // чужой маркой в имени вёл бы в никуда. У каждого сайта свой
              // файл по этому адресу. ?>
        <h1><?= $e($s->get('hero_title') ?: 'Складская техника') ?> <img class="hero__brand"
             src="<?= $e($app->asset('/assets/img/brand-hero.png')) ?>"
             alt="<?= $e($s->get('brand')) ?>"<?= $heroBrandSize ? ' width="' . (int) $heroBrandSize[0]
             . '" height="' . (int) $heroBrandSize[1] . '"' : '' ?>></h1>
        <?php // В подзаголовке «%d» заменяем на число моделей — так обещает
              // подсказка к настройке. Склонение слова «модель» считаем от
              // того же числа, вручную его не напишешь: у сайтов сети оно
              // разное и меняется при каждой загрузке каталога.
              $lead = $s->get('hero_lead')
                  ?: 'Тележки, штабелёры, ричтраки, электротягачи и подъёмные столы.'
                     . ' %d от производителя. Работаем с НДС.'
                     . ' Отгрузка по всей территории РФ.';
              $lead = str_replace(
                  '%d',
                  $total . ' ' . $plural($total, 'модель', 'модели', 'моделей'),
                  $lead
              ); ?>
        <p class="hero__lead"><?= $e($lead) ?></p>
        <p class="hero__cta">
          <a class="btn btn--primary" href="/katalog/">Перейти в каталог</a>
          <button class="btn btn--ghost" type="button" data-action="parts"
             style="color:#fff;border-color:rgba(255,255,255,.35)">Подобрать запчасти</button>
        </p>

        <ul class="hero__dots" id="hero-dots">
          <?php for ($i = 0; $i < $slides; $i++): ?>
            <li><button class="hero__dot" type="button" data-slide="<?= $i ?>"
                        <?= $i === 0 ? 'aria-current="true"' : '' ?>
                        aria-label="Слайд <?= $i + 1 ?> из <?= $slides ?>"></button></li>
          <?php endfor; ?>
        </ul>
      </div>
      <?php // Цифры берём из настройки: тема общая для сети, а «1985 — год
            // основания завода» это факт про завод Brand, и на сайте другой
            // марки он оказывался неправдой. По строке на цифру, значение и
            // подпись через вертикальную черту. Пусто — блока нет вовсе:
            // пустое место незаметно, а чужой год подрывает доверие.
            //
            // Строки режем поимённо, а не через «\R»: без пометки о Юникоде
            // тот считает переводом строки байт 0x85, а это второй байт
            // буквы «х» — слова рвались пополам.
            $figures = [];
            foreach (preg_split("/\r\n|\r|\n/", (string) $s->get('hero_figures')) ?: [] as $line) {
                $parts = explode('|', $line, 2);
                $value = trim($parts[0]);
                if ($value === '') { continue; }
                $figures[] = [$value, trim($parts[1] ?? '')];
            } ?>
      <?php if ($figures): ?>
        <div class="hero__figures">
          <?php foreach ($figures as [$value, $label]): ?>
            <p class="hero__fig"><b><?= $e($value) ?></b><span><?= $e($label) ?></span></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php // Секция на светло-сером: белые карточки на белом фоне не читались. ?>
  <section class="section section--soft">
    <div class="container">
      <div class="section__head">
        <h2>Каталог техники</h2>
        <a href="/katalog/">Смотреть всё →</a>
      </div>
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
                <span class="tile__n"><b><?= (int) $tile['goods'] ?></b>
                  <?= $plural((int) $tile['goods'], 'модель', 'модели', 'моделей') ?></span>
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

  <?php if ($popular): ?>
    <section class="section">
      <div class="container">
        <div class="section__head">
          <h2>Популярные модели</h2>
          <a href="/akcii/">Товары со скидкой →</a>
        </div>
        <ul class="cards" itemscope itemtype="https://schema.org/ItemList">
          <?php foreach ($popular as $i => $product): ?>
            <?= $view->render('site/' . $theme . '/_card', ['product' => $product, 'position' => $i + 1]) ?>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  <?php endif; ?>

  <?php // Тексты о бренде и преимуществах правятся в админке.
        //
        // У <main> главной своих полей нет — их держат секции, каждая со
        // своим контейнером. Поэтому тело страницы выводилось сюда как
        // есть, в расчёте на то, что оно принесёт секцию с собой. Текст,
        // перенесённый со старого сайта, приходит голыми абзацами, и на
        // стенде Hangcha нижний блок главной шёл от края до края окна:
        // поля нет, строка во весь монитор.
        //
        // Своя вёрстка (Brand, Партнёр, Tisel) секцию несёт — её оставляем как
        // есть. Вторая обёртка удвоила бы отступы, а серую подложку
        // section--soft оторвала бы от краёв окна, ради которых она и
        // делалась во всю ширину. Признак — начинается ли тело с секции;
        // в page.php по тому же соображению смотрят на «prose».
        $body = $page !== null ? (string) $page['body'] : '';
        $wrap = $body !== '' && !str_starts_with(ltrim($body), '<section'); ?>
  <?php if ($wrap): ?>
    <section class="section">
      <div class="container">
        <?php // prose держит читаемую длину строки: на широком мониторе
              // контейнер в 1240 точек дал бы строку, которую глаз теряет
              // при переносе. ?>
        <div class="prose"><?= $body ?></div>
      </div>
    </section>
  <?php else: ?>
    <?= $body ?>
  <?php endif; ?>

  <section class="section" id="contacts">
    <div class="container cta">
      <div>
        <h2>Внимание! На территории пропускной режим</h2>
        <p>Для осмотра либо получения оплаченной техники необходимо заблаговременно
           согласовать визит с менеджером.</p>
        <p>Оформление пропуска на территорию занимает не менее 3 часов. Просим заранее
           направить данные на посетителя и автомобиль (марка, модель, госномер).</p>
        <ul class="contacts">
          <?php if ($s->get('phone') !== ''): ?>
            <li><b>Телефон</b><a href="tel:<?= $e($s->get('phone_href')) ?>"><?= $e($s->get('phone')) ?></a></li>
          <?php endif; ?>
          <?php if ($s->get('email') !== ''): ?>
            <li><b>Почта</b><a href="mailto:<?= $e($s->get('email')) ?>"><?= $e($s->get('email')) ?></a></li>
          <?php endif; ?>
          <?php if ($s->get('work_hours') !== ''): ?>
            <li><b>Время работы</b><span><?= $e($s->get('work_hours')) ?></span></li>
          <?php endif; ?>
        </ul>
      </div>

      <form class="form" id="quick" novalidate>
        <div class="form__head">
          <h2 style="font-size:var(--fs-lg)">Заявка на подбор</h2>
          <p>Перезвоним в течение двух часов</p>
        </div>
        <div class="form__body">
          <div class="trap" aria-hidden="true">
            <label for="q-surname">Не заполняйте это поле</label>
            <input type="text" id="q-surname" name="ref_code" tabindex="-1" autocomplete="off" readonly>
          </div>
          <div class="field">
            <label for="q-name">Имя <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="q-name" name="name" autocomplete="name" required>
            <p class="field__error">Укажите, как к вам обращаться</p>
          </div>
          <div class="field">
            <label for="q-phone">Телефон <span class="req" aria-hidden="true">*</span></label>
            <input type="tel" id="q-phone" name="phone" autocomplete="tel" inputmode="tel"
                   placeholder="+7 (___) ___-__-__" required>
            <p class="field__error">Введите телефон с кодом города или оператора</p>
          </div>
          <div class="field">
            <label for="q-comment">Задача</label>
            <textarea id="q-comment" name="comment" rows="3"
                      placeholder="Например: паллеты до 1,5 т, подъём на 3 метра, проход 2,6 м"></textarea>
          </div>
          <?php // Текст согласия — общий помощник, см. Consent. Ссылка стояла
                // приписанной следом, и на формулировке, которая сама кончается
                // словами «Политики конфиденциальности», название печаталось
                // дважды. ?>
          <div class="consent">
            <input type="checkbox" id="q-consent" name="consent" required>
            <label for="q-consent"><?= \Sklad\Model\Consent::html($s) ?>
              <span class="req" aria-hidden="true">*</span></label>
          </div>
          <button class="btn btn--primary" type="submit">Отправить заявку</button>
        </div>
      </form>
    </div>
  </section>
</main>

<?php // Карточка организации для поисковиков — из настроек, а не вписана
      // в разметку: телефон меняется в одном поле и разъезжаться ему негде. ?>
<script type="application/ld+json" nonce="<?= $e($app->nonce()) ?>">
<?= json_encode(array_filter([
  '@context' => 'https://schema.org',
  '@type' => 'Organization',
  'name' => $s->get('company') ?: $s->get('site_name'),
  'legalName' => $s->get('company_full'),
  // Марка, под которой компанию ищут: продаёт «СкладТех», а спрашивают
  // Brand. Без этого поисковик не свяжет одно с другим.
  'alternateName' => $s->get('brand'),
  // По ИНН поисковик сводит сайт с записью в ЕГРЮЛ.
  'taxID' => $s->get('inn'),
  'vatID' => $s->get('inn'),
  'url' => rtrim($app->config['site']['url'] ?? '', '/') . '/',
  'telephone' => $s->get('phone'),
  'email' => $s->get('email'),
  'address' => $s->get('address') !== ''
    ? ['@type' => 'PostalAddress', 'addressCountry' => 'RU', 'streetAddress' => $s->get('address')]
    : null,
  // Часы работы поисковик показывает прямо в выдаче — если понимает
  // формат. Собираются из той же настройки, что видит человек.
  'openingHours' => View::schemaHours($s->get('work_hours')) ?: null,
  // Косые оставляем экранированными намеренно: настройку правит человек,
  // и строка «/script» в угловых скобках в названии компании оборвала бы
  // блок разметки посреди страницы. Поисковику экранированная косая не
  // мешает.
]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
