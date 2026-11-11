<?php

use Sklad\Core\View;

/**
 * Заявка на технику — то, что в других магазинах называют корзиной.
 *
 * Онлайн-оплаты нет: человек собирает список моделей и отправляет его
 * менеджеру. Поэтому «Итого» — справочное, а обязательные поля здесь про
 * организацию, а не про доставку.
 *
 * Список и форму ведёт cart-page.js. Разметка — договор с ним: он ищет
 * cartview, list, empty, done, form, cnt, total, totalnote, submit, ticket
 * и cart-clear, а поля берёт по именам — name, phone, email, city, company,
 * inn, comment. Поле ref_code — ловушка для ботов, consent — согласие по
 * 152-ФЗ. Переименуете что-то одно — отправка молча перестанет работать.
 *
 * Блок senderror скрипт создаёт сам, если его нет, — здесь он не нужен.
 *
 * @var array $page
 */
$e = [View::class, 'e'];
$s = $settings;
?>
<?php
// Разметка корзины может лежать в базе, в тексте страницы: так заведён
// Brand — у него этот шаблон долго был заглушкой, выводившей текст
// страницы как есть. Если в тексте уже есть рабочая разметка (узнаём по
// главному элементу, #cartview), выводим только её, как заглушка, — иначе
// на странице окажутся две копии с одинаковыми id и скрипт запутается.
// Новые сайты заводятся с пустым текстом — им разметку даёт шаблон, а
// текст из базы, если он есть, идёт вступлением над ней.
$legacy = str_contains((string) $page['body'], 'id="cartview"');
?>
<main id="main" class="container">
<?php if ($legacy): ?>
  <div class="cathead"><h1><?= $e($page['title']) ?></h1></div>
  <?= $page['body'] ?>
<?php else: ?>
  <div class="cathead"><h1><?= $e($page['title'] !== '' ? $page['title'] : 'Заявка на технику') ?></h1></div>

  <?php if (trim((string) $page['body']) !== ''): ?>
    <div class="prose"><?= $page['body'] ?></div>
  <?php endif; ?>

  <div class="cart" id="cartview" hidden>
    <div>
      <ul class="cartlist" id="list"></ul>

      <div class="carttotal">
        <span class="carttotal__label">Итого <span id="cnt"></span></span>
        <span class="carttotal__value" id="total"></span>
      </div>
      <p class="carttotal__note" id="totalnote"></p>

      <p><button class="cmp-clear" type="button" id="cart-clear">Очистить заявку</button></p>

      <?php // Что будет дальше — сразу под списком. Без этого «отправить
            // заявку» читается как «купить», и человек ждёт счёт. ?>
      <div class="next">
        <h3>Что дальше</h3>
        <ol>
          <li>Менеджер проверит наличие и подберёт замену, если модели нет на складе.</li>
          <li>Пришлём коммерческое предложение с ценой и сроком поставки.</li>
          <li>После согласования выставим счёт — цена фиксируется счётом.</li>
        </ol>
      </div>
    </div>

    <form class="form" id="form" novalidate>
      <div class="form__head">
        <h2>Ваши контакты</h2>
        <p>Ответим в рабочее время: <?= $e($s->get('work_hours') ?: 'Пн–Пт, 9:00–18:00') ?></p>
      </div>

      <div class="form__body">
        <div class="field">
          <label for="f-name">Имя <span aria-hidden="true">*</span></label>
          <input type="text" id="f-name" name="name" autocomplete="name" required>
          <p class="field__error">Напишите, как к вам обращаться</p>
        </div>

        <div class="field__row">
          <div class="field">
            <label for="f-phone">Телефон <span aria-hidden="true">*</span></label>
            <input type="tel" id="f-phone" name="phone" autocomplete="tel" required>
            <p class="field__error">Нужен номер из десяти цифр</p>
          </div>
          <div class="field">
            <label for="f-email">Почта <span aria-hidden="true">*</span></label>
            <input type="email" id="f-email" name="email" autocomplete="email" required>
            <p class="field__error">Проверьте адрес — на него уйдёт предложение</p>
          </div>
        </div>

        <div class="field__row">
          <div class="field">
            <label for="f-city">Город <span aria-hidden="true">*</span></label>
            <input type="text" id="f-city" name="city" autocomplete="address-level2" required
                   value="<?= $e($city['name'] ?? '') ?>">
            <p class="field__error">Укажите город — от него зависит доставка</p>
          </div>
          <div class="field">
            <label for="f-company">Организация <span aria-hidden="true">*</span></label>
            <input type="text" id="f-company" name="company" autocomplete="organization" required>
            <p class="field__error">Работаем с организациями и ИП</p>
          </div>
        </div>

        <?php // ИНН необязателен: на первом запросе его часто не помнят, а
              // требовать — значит терять заявку. Если ввели, проверяем длину. ?>
        <div class="field">
          <label for="f-inn">ИНН <span class="cmp-toolbar__hint">— необязательно, ускорит выставление счёта</span></label>
          <input type="text" id="f-inn" name="inn" inputmode="numeric">
          <p class="field__error">ИНН — это 10 или 12 цифр</p>
        </div>

        <div class="field">
          <label for="f-comment">Комментарий</label>
          <textarea id="f-comment" name="comment" rows="3"
                    placeholder="Сроки, условия доставки, нужна ли выгрузка"></textarea>
        </div>

        <?php // Ловушка для ботов: спрятана сдвигом, а не display:none —
              // часть спам-скриптов такие поля пропускает. ?>
        <div class="trap" aria-hidden="true">
          <label for="f-ref">Не заполняйте это поле</label>
          <input type="text" id="f-ref" name="ref_code" tabindex="-1" autocomplete="off">
        </div>

        <?php // Текст согласия — один на весь сайт: Consent::html берёт его из
              // настройки consent_wording, ту же строку сервер кладёт в журнал
              // согласий при отправке. Здесь стояла своя фраза, и человек в
              // корзине соглашался с одним текстом, а в журнал уходил другой.
              // Название политики ссылкой делает сам помощник — дописывать
              // ссылку следом нельзя, название напечаталось бы дважды. ?>
        <?php // required и звёздочка — как в трёх остальных местах сети
              // (главная базовой темы, корзина и главная темы tor) и в окнах
              // заявок (consentHtml в request.js). Здесь их не было: форма с
              // novalidate, и проверку делает cart-page.js, — но звёздочка
              // единственная говорит человеку, что галочка обязательна, до
              // того, как он нажмёт «Отправить», а required оставляет поле
              // обязательным и для тех, у кого скрипт не выполнился. ?>
        <div class="consent">
          <input type="checkbox" id="f-consent" name="consent" required>
          <label for="f-consent"><?= \Sklad\Model\Consent::html($s) ?>
            <span class="req" aria-hidden="true">*</span></label>
        </div>

        <button class="btn btn--primary" type="submit" id="submit">Отправить заявку</button>
      </div>
    </form>
  </div>

  <div class="cartempty" id="empty">
    <h2>В заявке пока пусто</h2>
    <p>Добавьте технику из каталога — соберём список и отправим менеджеру одним запросом.</p>
    <a class="btn btn--primary" href="/katalog/">Перейти в каталог</a>
  </div>

  <div class="done" id="done" hidden>
    <div class="done__icon" aria-hidden="true">✓</div>
    <h2>Заявка отправлена</h2>
    <p>Номер заявки: <span class="done__num" id="ticket"></span></p>
    <p>Менеджер свяжется с вами в рабочее время. Если вопрос срочный —
       позвоните: <a href="tel:<?= $e($s->get('phone_href')) ?>"><?= $e($s->get('phone')) ?></a></p>
  </div>
<?php endif; ?>
</main>
