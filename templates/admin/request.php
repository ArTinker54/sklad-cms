<?php

use Sklad\Core\View;

$e = [View::class, 'e'];
?>

<div class="head">
  <h1>Заявка <?= $e($request['number']) ?></h1>
  <a class="btn" href="<?= $e($adminPath('request')) ?>">Ко всем заявкам</a>
</div>

<div class="two">
  <div class="pane">
    <h2>Кто обратился</h2>
    <dl class="facts">
      <dt>Имя</dt><dd><?= $e($request['name']) ?></dd>
      <dt>Телефон</dt><dd><a href="tel:<?= $e($request['phone']) ?>"><?= $e($request['phone']) ?></a></dd>
      <?php if ($request['email'] !== ''): ?>
        <dt>Почта</dt><dd><a href="mailto:<?= $e($request['email']) ?>"><?= $e($request['email']) ?></a></dd>
      <?php endif; ?>
      <?php foreach (['company' => 'Компания', 'inn' => 'ИНН', 'city' => 'Город'] as $key => $label): ?>
        <?php if ($request[$key] !== ''): ?>
          <dt><?= $e($label) ?></dt><dd><?= $e($request[$key]) ?></dd>
        <?php endif; ?>
      <?php endforeach; ?>
      <dt>Когда</dt><dd><?= $e(View::date($request['created_at'], 'd.m.Y H:i')) ?></dd>
      <?php if ($request['page_url'] !== ''): ?>
        <dt>Со страницы</dt><dd><a href="<?= $e($request['page_url']) ?>"><?= $e($request['page_url']) ?></a></dd>
      <?php endif; ?>
    </dl>

    <?php if ($request['comment'] !== null && $request['comment'] !== ''): ?>
      <h2>Комментарий</h2>
      <p class="quote"><?= nl2br($e($request['comment'])) ?></p>
    <?php endif; ?>

    <?php if ($items): ?>
      <h2>Позиции</h2>
      <table class="grid">
        <thead><tr><th>Товар</th><th class="w-state">Кол-во</th><th class="w-price">Цена</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <?php if ($item['url'] !== ''): ?>
                  <a href="<?= $e($item['url']) ?>"><?= $e($item['title']) ?></a>
                <?php else: ?>
                  <?= $e($item['title']) ?>
                <?php endif; ?>
              </td>
              <td class="w-state num"><?= (int) $item['qty'] ?></td>
              <td class="w-price num">
                <?= $item['price'] === null ? '<span class="muted">по запросу</span>' : $e(View::price($item['price'])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($request['items_total'] !== null): ?>
        <p class="total">Итого <b><?= $e(View::price($request['items_total'])) ?></b></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="pane">
    <h2>Работа с заявкой</h2>
    <form method="post" action="<?= $e($adminPath('request/' . $request['id'])) ?>">
      <input type="hidden" name="_token" value="<?= $e($token) ?>">

      <label class="lbl" for="status">Состояние</label>
      <select id="status" name="status" class="val">
        <?php foreach ($states as $key => $label): ?>
          <option value="<?= $e($key) ?>"<?= $request['status'] === $key ? ' selected' : '' ?>><?= $e($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="lbl" for="note">Заметка — клиент её не видит</label>
      <textarea id="note" name="note" class="val" rows="5"><?= $e($request['note'] ?? '') ?></textarea>

      <button class="btn btn--primary" type="submit">Сохранить</button>
    </form>

    <h2>Согласие на обработку данных</h2>
    <?php if ($consent === null): ?>
      <p class="warn">Согласие не зафиксировано. Обрабатывать эти данные нельзя —
         заявка попала в базу в обход формы.</p>
    <?php else: ?>
      <div class="consent">
        <p class="consent__text"><?= $e($consent['wording']) ?></p>
        <dl class="facts facts--tight">
          <dt>Дано</dt><dd><?= $e(View::date($consent['created_at'], 'd.m.Y H:i:s')) ?></dd>
          <dt>Адрес</dt><dd><?= $e($consent['ip']) ?></dd>
          <dt>Политика</dt>
          <dd><a href="<?= $e($consent['policy_url']) ?>"><?= $e($consent['policy_url']) ?></a><?php
            if ($consent['policy_version'] !== '') { echo ', редакция ' . $e($consent['policy_version']); }
          ?></dd>
          <dt>Данные</dt><dd><?= $e($consent['subject']) ?></dd>
          <dt>Браузер</dt><dd class="tiny"><?= $e($consent['user_agent']) ?></dd>
        </dl>
      </div>
      <p class="note">Это и есть доказательство правомерности обработки, если о нём
         спросят. По ст. 9 152-ФЗ доказывать обязан продавец.</p>
    <?php endif; ?>
  </div>
</div>
