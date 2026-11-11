<?php

/**
 * Title и Description — общий блок карточек страницы, раздела и товара.
 *
 * Поля назывались «Заголовок окна» и «Описание для выдачи», а заказчик ищет
 * «тайтл» и «дискрипшн» — и не находил (18.09 Роман спрашивал, где писать
 * описание главной, хотя поле было). Здесь подписи — теми словами, которыми
 * говорят о выдаче, счётчик длины и предпросмотр строки поиска. Счётчик и
 * предпросмотр меняются на ходу — public/assets/admin/seo.js.
 *
 * Подсказка «что уйдёт в поиск, если поле пустое» считается так же, как на
 * витрине (CatalogController, PageController, Meta::forProduct), — иначе
 * человек видел бы в админке одно, а в выдаче другое.
 *
 * @var array  $row             meta_title и meta_description записи
 * @var string $autoTitle       Title, если поле пустое
 * @var string $autoDescription Description, если поле пустое ('' — соберёт поисковик)
 * @var string $url             адрес страницы, для предпросмотра
 * @var string $emptyNote       чем обернётся пустое Description именно здесь
 */

use Sklad\Core\View;
use Sklad\Model\City;
use Sklad\Model\Meta;

$e = [View::class, 'e'];
$titleLimit = Meta::TITLE_LIMIT;
$descLimit = Meta::DESCRIPTION_LIMIT;
$title = (string) $row['meta_title'];
$desc = (string) $row['meta_description'];
$emptyNote = $emptyNote ?? 'Пусто — поисковик соберёт текст под ссылкой сам, со страницы.';
$emptyShow = 'Пусто — Яндекс подставит сюда свой текст со страницы';

// Предпросмотр — как на основном адресе: метки города дают Москву.
$shownTitle = City::fill($title !== '' ? $title : $autoTitle, null);
$shownDesc = City::fill($desc !== '' ? $desc : $autoDescription, null);
?>
<div class="seo" data-seo>
  <div>
    <label class="lbl" for="meta_title">Title — заголовок в поиске и на вкладке браузера
      <span class="counter<?= mb_strlen($title) > $titleLimit ? ' counter--over' : '' ?>"
            data-seo-count="meta_title" data-limit="<?= (int) $titleLimit ?>"><?= mb_strlen($title) ?> из <?= (int) $titleLimit ?></span>
    </label>
    <input class="val" id="meta_title" name="meta_title" maxlength="255"
           value="<?= $e($title) ?>" placeholder="<?= $e($autoTitle) ?>">
    <?php if (trim($autoTitle) !== ''): ?>
      <p class="note">Пусто — возьмётся «<?= $e($autoTitle) ?>».</p>
    <?php else: ?>
      <?php // Так было у Brand: название сайта в настройках пустое, и стёртый
            // Title главной оставил бы в поиске пустой заголовок. ?>
      <p class="note note--bad">Подставить нечего: название сайта в настройках не заполнено.
         Если оставить Title пустым, заголовок в поиске будет пустым.</p>
    <?php endif; ?>
  </div>

  <div>
    <label class="lbl" for="meta_description">Description — текст под ссылкой в поиске
      <span class="counter<?= mb_strlen($desc) > $descLimit ? ' counter--over' : '' ?>"
            data-seo-count="meta_description" data-limit="<?= (int) $descLimit ?>"><?= mb_strlen($desc) ?> из <?= (int) $descLimit ?></span>
    </label>
    <textarea class="val" id="meta_description" name="meta_description" rows="3" maxlength="500"
              placeholder="<?= $e($autoDescription) ?>"><?= $e($desc) ?></textarea>
    <p class="note"><?= $e($autoDescription !== '' ? 'Пусто — возьмётся текст из подсказки в поле.' : $emptyNote) ?></p>
  </div>

  <figure class="seo__preview">
    <figcaption>Так строка будет выглядеть в поиске</figcaption>
    <span class="seo__title" data-seo-show="meta_title" data-auto="<?= $e($autoTitle) ?>"><?= $e($shownTitle) ?></span>
    <span class="seo__url"><?= $e($url) ?></span>
    <span class="seo__desc<?= $shownDesc === '' ? ' seo__desc--empty' : '' ?>" data-seo-show="meta_description"
          data-auto="<?= $e($autoDescription) ?>" data-empty="<?= $e($emptyShow) ?>"><?= $e($shownDesc !== '' ? $shownDesc : $emptyShow) ?></span>
  </figure>

  <p class="note">Метки <span class="art">{город}</span>, <span class="art">{городе}</span>,
     <span class="art">{города}</span> подставят город поддомена, на основном адресе — Москву.
     На поддомене движок сам допишет к Title «в Калуге», а к Description — «Доставка в Калуге»,
     если город в тексте не назван.</p>
</div>
