<?php

use Sklad\Core\View;

/**
 * Контакты.
 *
 * Телефон, почта и реквизиты берутся из настроек и блоков, а не из
 * текста страницы: при смене телефона менеджер правит одно поле, и оно
 * меняется разом и в шапке, и здесь, и в письмах.
 *
 * @var array $page
 */
$e = [View::class, 'e'];
$s = $settings;
?>
<main id="main" class="container">
  <div class="cathead"><h1><?= $e($page['title']) ?></h1></div>

  <div class="contacts" itemscope itemtype="https://schema.org/Organization">
    <meta itemprop="name" content="<?= $e($s->get('company') ?: $s->get('site_name')) ?>">

    <ul class="contacts__list">
      <?php if ($s->get('phone') !== ''): ?>
        <li><span class="contacts__label">Телефон</span>
          <a class="contacts__value" href="tel:<?= $e($s->get('phone_href')) ?>"
             itemprop="telephone"><?= $e($s->get('phone')) ?></a></li>
      <?php endif; ?>
      <?php if ($s->get('email') !== ''): ?>
        <li><span class="contacts__label">Почта</span>
          <a class="contacts__value" href="mailto:<?= $e($s->get('email')) ?>"
             itemprop="email"><?= $e($s->get('email')) ?></a></li>
      <?php endif; ?>
      <?php // Подпись — из настройки. По умолчанию «Пункт выдачи»: у части
            // сайтов сети юрлицо зарегистрировано в другом регионе, и назвать
            // склад адресом компании там значит разойтись с ЕГРЮЛ. Где такой
            // разницы нет, в настройке пишут «Адрес». Тема одна на всю сеть,
            // и вшитое слово развело бы шаблоны по сайтам. ?>
      <?php // В городе со своим пунктом выдачи показываем его адрес, иначе
            // основной: адрес человек читает как «сюда можно приехать». ?>
      <?php $point = !empty($city['address'])
        ? $city['name'] . ', ' . $city['address']
        : $s->get('address'); ?>
      <?php if ($point !== ''): ?>
        <li><span class="contacts__label"><?= $e($s->get('address_label', 'Пункт выдачи')) ?></span>
          <span class="contacts__value" itemprop="address"><?= $e($point) ?></span></li>
      <?php endif; ?>
      <?php if ($s->get('work_hours') !== ''): ?>
        <li><span class="contacts__label">Время работы</span>
          <span class="contacts__value"><?= $e($s->get('work_hours')) ?>
            <meta itemprop="openingHours" content="<?= $e(View::schemaHours($s->get('work_hours'))) ?>">
          </span></li>
      <?php endif; ?>
      <?php if ($s->get('messenger_url') !== ''): ?>
        <li><span class="contacts__label"><?= $e($s->get('messenger_title')) ?></span>
          <a class="contacts__value contacts__max" href="<?= $e($s->get('messenger_url')) ?>"
             target="_blank" rel="noopener">
            <img src="/assets/img/max.svg" alt="" width="20" height="20">Написать нам</a></li>
      <?php endif; ?>
    </ul>

    <?php // Город регионального поддомена: человек должен видеть, куда
          // попал, и уметь переключиться. Список — обычными ссылками:
          // по ним поисковик и обходит городские поддомены. ?>
    <?php // Сам выбор города живёт в шапке — здесь только показываем, куда
          // считается доставка, и даём открыть тот же список. ?>
    <p class="cities__now">
      Ваш город: <b><?= $e($city['name'] ?? 'Москва') ?></b>.
      <button class="linklike" type="button" data-city-open>Выбрать другой город</button>
    </p>

    <?php // Пропускной режим на складе — под адресом, заметным блоком: без
          // согласованного заранее визита человека не пустят на территорию. ?>
    <div class="visit-note">
      <p class="visit-note__head">Внимание! На территории пропускной режим</p>
      <p>Для осмотра либо получения оплаченной техники необходимо заблаговременно согласовать визит с менеджером.</p>
      <p>Оформление пропуска на территорию занимает не менее 3 часов. Просим заранее направить данные на посетителя и автомобиль (марка, модель, госномер).</p>
    </div>

    <p class="contacts__call"><button class="btn btn--primary" type="button" data-action="call">Заказать звонок</button></p>
  </div>

  <?php if (trim((string) $page['body']) !== ''): ?>
    <div class="prose"><?= $page['body'] ?></div>
  <?php endif; ?>

  <?php if (!empty($blocks['requisites'])): ?>
    <div class="prose reqs" style="margin-top:var(--s-8)">
      <h2>Реквизиты</h2>
      <?= $blocks['requisites'] ?>
    </div>
  <?php endif; ?>
</main>
