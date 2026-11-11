<?php

use Sklad\Core\View;

/**
 * Картинки первого экрана главной (04.11.2026, «Ирине менять картинку на главной»).
 * Обновление 10.11.2026: интерактивный предпросмотр с реальным текстом главной,
 * затемнением, кадрированием и крупным просмотром для ПК и мобильной версии.
 *
 * Стили — здесь же, а не в admin.css: экран один, а admin.css у сайтов сети
 * разных лет, и правка в нём легла бы не на все.
 *
 * @var array $slides
 * @var array{0:int,1:int} $size
 * @var int $max
 * @var array|null $heroOverlay
 */
$e = [View::class, 'e'];
[$w, $h] = $size;
$focusNames = [
    'center' => 'середину',
    'top' => 'верх',
    'bottom' => 'низ',
    'left' => 'левый край',
    'right' => 'правый край',
];

$heroOverlay = $heroOverlay ?? [
    'eyebrow' => 'Официальный дилер в России',
    'title' => 'Складская техника',
    'brand' => '',
    'brandHeroUrl' => null,
    'lead' => 'Тележки, штабелёры, ричтраки, электротягачи и подъёмные столы. От производителя. Работаем с НДС. Отгрузка по всей территории РФ.',
    'cta2' => 'Подобрать запчасти',
    'figures' => [],
];

$renderMock = static function () use ($e, $heroOverlay): string {
    $out = '<div class="heromock" aria-hidden="true">'
        . '<div class="heromock__overlay"></div>'
        . '<div class="heromock__grid">'
        . '<div class="heromock__main">'
        . '<span class="heromock__eyebrow">' . $e($heroOverlay['eyebrow']) . '</span>'
        . '<div class="heromock__title">' . $e($heroOverlay['title']);
    if (!empty($heroOverlay['brandHeroUrl'])) {
        $out .= ' <img class="heromock__brand" src="' . $e($heroOverlay['brandHeroUrl']) . '" alt="' . $e($heroOverlay['brand']) . '">';
    }
    $out .= '</div>'
        . '<p class="heromock__lead">' . $e($heroOverlay['lead']) . '</p>'
        . '<div class="heromock__cta">'
        . '<span class="heromock__btn heromock__btn--primary">В каталог</span>'
        . '<span class="heromock__btn heromock__btn--ghost">' . $e($heroOverlay['cta2']) . '</span>'
        . '</div>'
        . '<div class="heromock__dots">'
        . '<span class="heromock__dot is-active"></span>'
        . '<span class="heromock__dot"></span>'
        . '<span class="heromock__dot"></span>'
        . '</div>'
        . '</div>';

    if (!empty($heroOverlay['figures'])) {
        $out .= '<div class="heromock__figures">';
        foreach ($heroOverlay['figures'] as $fig) {
            $out .= '<div class="heromock__fig"><b>' . $e($fig['num']) . '</b><span>' . $e($fig['label']) . '</span></div>';
        }
        $out .= '</div>';
    }

    $out .= '</div></div>';

    return $out;
};

$uploadForm = static function (int $n) use ($e, $adminPath, $token, $focusNames, $renderMock): string {
    $id = 'hero-file-' . $n;
    $html = '<form class="heroform" method="post" enctype="multipart/form-data" action="' . $e($adminPath('hero/upload')) . '">'
        . '<input type="hidden" name="_token" value="' . $e($token) . '">'
        . '<input type="hidden" name="n" value="' . $n . '">'
        . '<label class="lbl" for="' . $id . '">' . ($n ? 'Заменить этот слайд' : 'Файл нового слайда') . '</label>'
        . '<input class="val" id="' . $id . '" type="file" name="image" accept="image/jpeg,image/png,image/webp" required data-hero-file>'
        . '<fieldset class="herofocus"><legend class="lbl">Если снимок не той формы — что оставить</legend>';
    foreach ($focusNames as $value => $label) {
        $html .= '<label><input type="radio" name="focus" value="' . $value . '"' . ($value === 'center' ? ' checked' : '')
            . '> ' . $e($label) . '</label>';
    }

    return $html . '</fieldset>'
        . '<div class="heroslide__media heroform__preview" hidden data-hero-preview>'
        . '<span class="note">Так встанет на сайт</span>'
        . '<button type="button" class="heromedia__zoom" data-hero-zoom title="Крупный просмотр (ПК и телефон)">🔍 На весь экран</button>'
        . $renderMock()
        . '</div>'
        . '<button class="btn btn--primary" type="submit">' . ($n ? 'Заменить' : 'Добавить слайд') . '</button>'
        . '</form>';
};
?>
<style>
  .herobar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem;
             padding: .75rem 1rem; background: #f0f3f8; border: 1px solid #d9e0ea; border-radius: 8px; margin: 1rem 0; }
  .herobar__switch { display: inline-flex; align-items: center; gap: .5rem; cursor: pointer; font-size: .9375rem; font-weight: 600; user-select: none; }
  .herobar__switch input { width: 1.15rem; height: 1.15rem; cursor: pointer; }
  .herobar__hint { font-size: .8125rem; color: #5a6474; }
  .heroslides { display: grid; gap: 1.25rem; margin-top: .75rem; }
  .heroslide { display: grid; grid-template-columns: minmax(280px, 560px) minmax(240px, 1fr); gap: 1.25rem; align-items: start;
               padding: 1rem; border: 1px solid #e2e5ea; border-radius: 8px; background: #fff; }
  .heroslide__media { position: relative; aspect-ratio: <?= (int) $w ?> / <?= (int) $h ?>; background: #232a38 center / cover no-repeat;
                      border-radius: 6px; overflow: hidden; container-type: inline-size; }
  .heroslide__media img.heroslide__bg,
  .heroslide__media > img:not(.heromock__brand) { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
  .heroslide__media > .note { position: absolute; left: .5rem; bottom: .5rem; z-index: 5; padding: .15rem .45rem; border-radius: 4px;
                              background: rgba(0,0,0,.65); color: #fff; font-size: .75rem; }
  .heromedia__zoom { position: absolute; top: .5rem; right: .5rem; z-index: 5; padding: .25rem .55rem; font-size: .75rem;
                     font-weight: 600; border-radius: 4px; background: rgba(26,30,39,.82); color: #fff; border: 1px solid rgba(255,255,255,.2);
                     cursor: pointer; backdrop-filter: blur(4px); transition: background .15s, color .15s, transform .1s;
                     display: inline-flex; align-items: center; gap: .25rem; }
  .heromedia__zoom:hover { background: #ffcc00; color: #1e2430; border-color: #ffcc00; transform: scale(1.03); }
  .heroslide__info p { margin: 0 0 .5rem; }
  .heroslide__tools { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
  .heroslide__tools form { margin: 0; }
  .heroslide__tools button { margin-top: 0; }
  .herofocus { border: 0; padding: 0; margin: .5rem 0 0; display: flex; flex-wrap: wrap; gap: .25rem 1rem; }
  .herofocus legend { width: 100%; padding: 0; }
  .herofocus label { font-size: .875rem; display: inline-flex; gap: .3rem; align-items: center; }
  .heroform__preview { margin-top: .75rem; max-width: 560px; }

  /* Наложение текста главной (макет сайта) */
  .heromock { position: absolute; inset: 0; z-index: 2; pointer-events: none; overflow: hidden; }
  .heromock__overlay { position: absolute; inset: 0; z-index: 1; pointer-events: none;
                       background: linear-gradient(90deg, rgba(35,42,56,.90) 0%, rgba(35,42,56,.72) 48%, rgba(35,42,56,.28) 100%),
                                   linear-gradient(180deg, rgba(35,42,56,.32) 0%, rgba(35,42,56,.08) 40%, rgba(35,42,56,.40) 100%); }
  .heromock__grid { position: relative; z-index: 2; height: 100%; display: grid; grid-template-columns: minmax(0, 1fr) auto;
                    gap: 2cqw; align-items: center; padding: 2.2cqw 3.5cqw; box-sizing: border-box; color: #fff;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
  .heromock__main { min-width: 0; max-width: 65cqw; }
  .heromock__eyebrow { display: inline-block; padding: 0.2cqw 0.7cqw; margin-bottom: 0.6cqw; background: #ffcc00; color: #1e2430;
                       font-size: clamp(7px, 1.3cqw, 13px); line-height: 1.15; font-weight: 800; text-transform: uppercase;
                       letter-spacing: 0.05em; border-radius: 2px; }
  .heromock__title { color: #fff; font-size: clamp(9.5px, 2.7cqw, 28px); font-weight: 800; line-height: 1.15; margin: 0 0 0.6cqw;
                     text-shadow: 0 2px 6px rgba(0,0,0,.45); display: flex; align-items: baseline; flex-wrap: wrap; gap: 0.4cqw; }
  .heromock__brand { position: relative !important; inset: auto !important; height: 1.25em !important; width: auto !important;
                     max-width: 25cqw !important; display: inline-block !important; vertical-align: baseline !important;
                     top: .18em !important; object-fit: contain !important; filter: drop-shadow(0 2px 4px rgba(0,0,0,.4)); }
  .heromock__lead { font-size: clamp(6.5px, 1.25cqw, 13px); line-height: 1.28; color: #d3d7e0; max-width: 52ch; margin: 0 0 0.8cqw;
                    text-shadow: 0 1px 4px rgba(0,0,0,.4); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .heromock__cta { display: flex; flex-wrap: wrap; gap: 0.6cqw; align-items: center; margin-bottom: 0.6cqw; }
  .heromock__btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.3cqw 0.9cqw; border-radius: 3px;
                   font-size: clamp(6px, 1.1cqw, 12px); font-weight: 700; white-space: nowrap; line-height: 1.2; }
  .heromock__btn--primary { background: #ffcc00; color: #1e2430; }
  .heromock__btn--ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.45); }
  .heromock__dots { display: flex; gap: 0.4cqw; }
  .heromock__dot { width: 1.6cqw; height: 0.3cqw; min-width: 8px; min-height: 2px; background: rgba(255,255,255,.3); border-radius: 1px; }
  .heromock__dot.is-active { background: #ffcc00; }
  .heromock__figures { display: flex; flex-direction: column; gap: 0.6cqw; min-width: 8cqw; }
  .heromock__fig { padding: 0.35cqw 0.7cqw; background: rgba(255,255,255,.08); border-left: 2px solid #ffcc00; border-radius: 2px;
                   backdrop-filter: blur(2px); }
  .heromock__fig b { display: block; font-size: clamp(8px, 1.8cqw, 18px); color: #ffcc00; font-weight: 800; line-height: 1.1; }
  .heromock__fig span { display: block; font-size: clamp(5.5px, 0.95cqw, 10px); color: #b3bac7; line-height: 1.1; }

  /* Отключение показа текста */
  body.hero-hide-mock .heromock { display: none !important; }

  /* Модальное окно предпросмотра (ПК / Телефон) */
  .heromodal { border: 0; padding: 0; background: transparent; max-width: 96vw; width: 1180px; max-height: 94vh; margin: auto;
               border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.6); overflow: hidden; }
  .heromodal::backdrop { background: rgba(15,19,26,.82); backdrop-filter: blur(4px); }
  .heromodal__box { background: #1e2430; color: #fff; display: flex; flex-direction: column; max-height: 94vh; }
  .heromodal__head { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1.25rem;
                     border-bottom: 1px solid rgba(255,255,255,.1); background: #161b24; }
  .heromodal__tabs { display: flex; gap: .5rem; }
  .heromodal__tab { padding: .4rem .85rem; font-size: .875rem; font-weight: 600; border-radius: 6px;
                    border: 1px solid rgba(255,255,255,.15); background: transparent; color: #b3bac7; cursor: pointer; transition: all .15s; }
  .heromodal__tab:hover { color: #fff; border-color: rgba(255,255,255,.3); }
  .heromodal__tab.is-active { background: #ffcc00; color: #1e2430; border-color: #ffcc00; }
  .heromodal__close { background: transparent; border: 0; color: #9da6b5; font-size: 1.25rem; cursor: pointer; padding: .25rem .5rem;
                      line-height: 1; border-radius: 4px; }
  .heromodal__close:hover { color: #fff; background: rgba(255,255,255,.1); }
  .heromodal__body { padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px; }
  .heromodal__stage { position: relative; background: #232a38 center / cover no-repeat; overflow: hidden; transition: width .2s ease, height .2s ease; }
  .heromodal__stage.is-pc { width: 100%; max-width: 1100px; aspect-ratio: <?= (int) $w ?> / <?= (int) $h ?>; border-radius: 8px;
                            box-shadow: 0 8px 30px rgba(0,0,0,.4); container-type: inline-size; }
  .heromodal__stage.is-ipad { width: 940px; max-width: 90vw; aspect-ratio: 1194 / <?= (int) $h ?>; border-radius: 14px;
                              border: 6px solid #0f131a; box-shadow: 0 16px 40px rgba(0,0,0,.5); container-type: inline-size; }
  .heromodal__stage.is-ipad .heromock__grid { padding: 2cqw 3cqw; }
  .heromodal__stage.is-mob { width: 375px; max-width: 90vw; height: 560px; max-height: 65vh; border-radius: 26px;
                             border: 8px solid #0f131a; box-shadow: 0 16px 40px rgba(0,0,0,.5); container-type: inline-size; }
  .heromodal__stage.is-mob .heromock__overlay { background: linear-gradient(180deg, rgba(35,42,56,.8) 0%, rgba(35,42,56,.6) 45%, rgba(35,42,56,.9) 100%); }
  .heromodal__stage.is-mob .heromock__grid { display: flex; flex-direction: column; justify-content: center; align-items: flex-start;
                                             padding: 1.5rem 1.25rem; gap: .75rem; }
  .heromodal__stage.is-mob .heromock__main { max-width: 100%; }
  .heromodal__stage.is-mob .heromock__eyebrow { font-size: 10px; padding: 2px 7px; margin-bottom: 6px; }
  .heromodal__stage.is-mob .heromock__title { font-size: 20px; margin-bottom: 6px; }
  .heromodal__stage.is-mob .heromock__lead { font-size: 12px; line-height: 1.35; margin-bottom: 10px; -webkit-line-clamp: 4; }
  .heromodal__stage.is-mob .heromock__cta { width: 100%; flex-direction: column; gap: 6px; }
  .heromodal__stage.is-mob .heromock__btn { width: 100%; font-size: 12px; padding: 8px 12px; box-sizing: border-box; text-align: center; }
  .heromodal__stage.is-mob .heromock__figures { display: none; }
  .heromodal__stage.is-mob .heromock__dots { margin-top: 6px; }
  .heromodal__focus { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .5rem; margin-top: 1rem; width: 100%; }
  .heromodal__focus .lbl { color: #b3bac7; font-size: .875rem; margin-right: .25rem; }
  .heromodal__focus-btn { padding: .35rem .75rem; font-size: .8125rem; border-radius: 4px; border: 1px solid rgba(255,255,255,.2);
                          background: rgba(255,255,255,.05); color: #fff; cursor: pointer; transition: all .15s; }
  .heromodal__focus-btn:hover { background: rgba(255,255,255,.15); }
  .heromodal__focus-btn.is-active { background: #ffcc00; color: #1e2430; border-color: #ffcc00; font-weight: 700; }
  .heromodal__foot { padding: .75rem 1.25rem; background: #161b24; border-top: 1px solid rgba(255,255,255,.08); }
  .heromodal__foot .note { color: #9da6b5; font-size: .8125rem; }

  @media (max-width: 800px) { .heroslide { grid-template-columns: 1fr; } }
</style>

<div class="head">
  <h1>Картинки главной <span class="head__count"><?= count($slides) ?></span></h1>
  <a class="btn" href="/" target="_blank" rel="noopener">Открыть главную</a>
</div>

<div class="pane">
  <h2>Как это работает</h2>
  <p class="note">Это фон первого экрана главной: слайды сменяют друг друга, текст и кнопки сайт пишет поверх
     (сам текст правится в «Настройках»). Лучший размер — <b><?= (int) $w ?>×<?= (int) $h ?></b> точек, такой
     у слайдов этого сайта; снимок другой формы обрежется под него — что оставить, выбирается при загрузке,
     и до загрузки видно, как он встанет. Левая часть кадра уходит под текст и затемняется — технику лучше
     держать справа. JPG, PNG или WebP.</p>
  <p class="note">Заменённый или убранный слайд не пропадает: копия остаётся на сервере, её можно вернуть.</p>
</div>

<div class="herobar">
  <label class="herobar__switch">
    <input type="checkbox" id="hero-toggle-mock" checked>
    <span class="herobar__label">👁 Текст и затемнение как на сайте</span>
  </label>
  <span class="herobar__hint">💡 Текст и затемнение показывают реальный вид на сайте. Технику лучше держать справа, чтобы она не закрывалась текстом.</span>
</div>

<div class="heroslides">
  <?php foreach ($slides as $slide): ?>
    <section class="heroslide" id="slide-<?= (int) $slide['n'] ?>">
      <div class="heroslide__media" data-slide-media data-src="<?= $e($slide['url']) ?>">
        <img src="<?= $e($slide['url']) ?>" alt="Слайд <?= (int) $slide['n'] ?>">
        <button type="button" class="heromedia__zoom" data-hero-zoom title="Крупный просмотр (ПК и телефон)">🔍 На весь экран</button>
        <?= $renderMock() ?>
      </div>
      <div class="heroslide__info">
        <p><b>Слайд <?= (int) $slide['n'] ?></b><?= $slide['n'] === 1 ? ' — показывается первым' : '' ?></p>
        <p class="note"><?= (int) $slide['width'] ?>×<?= (int) $slide['height'] ?>,
           загружен <?= $e(date('d.m.Y H:i', $slide['time'])) ?></p>
        <?= $uploadForm((int) $slide['n']) ?>
        <div class="heroslide__tools">
          <?php if ($slide['n'] > 1): ?>
            <form method="post" action="<?= $e($adminPath('hero/' . $slide['n'] . '/move')) ?>">
              <input type="hidden" name="_token" value="<?= $e($token) ?>">
              <input type="hidden" name="dir" value="up">
              <button class="btn" type="submit">↑ Показывать раньше</button>
            </form>
          <?php endif; ?>
          <?php if ($slide['n'] < count($slides)): ?>
            <form method="post" action="<?= $e($adminPath('hero/' . $slide['n'] . '/move')) ?>">
              <input type="hidden" name="_token" value="<?= $e($token) ?>">
              <input type="hidden" name="dir" value="down">
              <button class="btn" type="submit">↓ Показывать позже</button>
            </form>
          <?php endif; ?>
          <?php if (count($slides) > 1): ?>
            <form method="post" action="<?= $e($adminPath('hero/' . $slide['n'] . '/remove')) ?>"
                  data-confirm="Убрать слайд <?= (int) $slide['n'] ?> с главной? Копия останется на сервере.">
              <input type="hidden" name="_token" value="<?= $e($token) ?>">
              <button class="btn" type="submit">Убрать слайд</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if (count($slides) < $max): ?>
    <section class="heroslide" id="slide-new">
      <div class="heroslide__media" data-slide-media>
        <span class="note">Новый слайд</span>
        <?= $renderMock() ?>
      </div>
      <div class="heroslide__info">
        <p><b>Добавить слайд</b> — встанет последним.</p>
        <?= $uploadForm(0) ?>
      </div>
    </section>
  <?php else: ?>
    <p class="note">Слайдов <?= (int) $max ?> — больше не нужно: замените один из них.</p>
  <?php endif; ?>
</div>

<dialog id="hero-modal" class="heromodal" aria-label="Предпросмотр слайда на сайте">
  <div class="heromodal__box">
    <div class="heromodal__head">
      <div class="heromodal__tabs">
        <button type="button" class="heromodal__tab is-active" data-view="pc">💻 Компьютер (<?= (int) $w ?>×<?= (int) $h ?>)</button>
        <button type="button" class="heromodal__tab" data-view="ipad">📟 Планшет / iPad (1194×<?= (int) $h ?>)</button>
        <button type="button" class="heromodal__tab" data-view="mob">📱 Телефон (390×580)</button>
      </div>
      <button type="button" class="heromodal__close" data-close aria-label="Закрыть">✕</button>
    </div>
    <div class="heromodal__body">
      <div class="heromodal__stage is-pc" id="hero-modal-stage">
        <?= $renderMock() ?>
      </div>
      <div class="heromodal__focus" id="hero-modal-focus" hidden>
        <span class="lbl">Кадрирование:</span>
        <?php foreach ($focusNames as $val => $txt): ?>
          <button type="button" class="heromodal__focus-btn" data-focus="<?= $val ?>"><?= $e($txt) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="heromodal__foot">
      <span class="note">💡 <b>Совет:</b> левая часть кадра уходит под текст и затемнение, поэтому технику лучше размещать справа («правый край» или «середину»).</span>
    </div>
  </div>
</dialog>

<script src="<?= $e($app->asset('/assets/admin/hero.js')) ?>"></script>
