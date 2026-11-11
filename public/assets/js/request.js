'use strict';
/* Модальные формы заявок.
   Одна разметка на все случаи: «Оставить заявку», «Купить в 1 клик»,
   «Запросить стоимость», «Заказать звонок». Отличаются заголовком,
   набором полей и тем, что уходит менеджеру. */

(function (window, document) {

  // Все заявки — одного содержания, как в корзине и на b2b-store.example.ru:
  // имя, телефон, e-mail, город, компания, ИНН и комментарий. Так заявку
  // сразу можно пускать в счёт. Отличаются лишь заголовком, текстом
  // кнопки и целью в метрике.
  var COMMON = ['name', 'phone', 'email', 'city', 'company', 'inn', 'comment'];
  var LEAD = 'Оставьте заявку — менеджер свяжется с вами для уточнения деталей заказа';

  /* Пример в подсказке «Марка и модель техники» — модель этого сайта.
     Раньше здесь было вписано «например, Brand CDD15B», а скрипт один на
     всю сеть: тот же пример стоял в форме запчастей у Партнёра, Tisel и
     Hangcha, где такой техники нет. Строку готовит сервер (SearchHint), у
     него же марка сайта; пусто — обходимся без примера, оборванное
     «например,» в поле выглядит поломкой. */
  var ownModel = (document.body && document.body.getAttribute('data-model-hint')) || '';
  var MODEL_HINT = ownModel ? 'например, ' + ownModel : 'марка и модель техники';

  var TYPES = {
    request:         { title: 'Оставить заявку',     lead: LEAD, fields: COMMON, submit: 'Отправить заявку',   goal: 'lead_request' },
    'one-click':     { title: 'Быстрый заказ',       lead: LEAD, fields: COMMON, submit: 'Отправить заявку',   goal: 'lead_oneclick' },
    'request-price': { title: 'Запросить стоимость', lead: LEAD, fields: COMMON, submit: 'Запросить стоимость', goal: 'lead_price' },
    call:            { title: 'Заказать звонок',     lead: LEAD, fields: COMMON, submit: 'Жду звонка',          goal: 'lead_call' },
    discount:        { title: 'Запросить КП',         lead: LEAD, fields: COMMON, submit: 'Отправить заявку',    goal: 'lead_discount' },
    // Запрос на запчасти — свой набор полей: по технике подбирают деталь.
    // Доп-поля (техника, год, серийный, список) уходят в комментарий, ФИО
    // в имя — так заявка ложится в общий приём без правки схемы базы.
    parts: {
      title: 'Запрос на запчасти',
      lead: 'Заполните форму — так подбор и наш ответ будут быстрее',
      fields: ['brand_model', 'year', 'serial', 'company', 'phone', 'email', 'city', 'fio', 'parts_list', 'photo'],
      submit: 'Отправить запрос',
      goal: 'lead_parts'
    }
  };

  var FIELDS = {
    name:    { label: 'Имя', type: 'text', ac: 'name', required: true, ph: 'Введите ваше имя', err: 'Укажите, как к вам обращаться' },
    phone:   { label: 'Телефон', type: 'tel', ac: 'tel', required: true, ph: '+7 (___) ___-__-__', err: 'Введите телефон с кодом' },
    email:   { label: 'E-mail', type: 'email', ac: 'email', required: true, err: 'Проверьте адрес — на него придёт ответ' },
    city:    { label: 'Город', type: 'text', ac: 'address-level2', required: false, ph: 'Москва' },
    company: { label: 'Компания', type: 'text', ac: 'organization', required: true, err: 'Укажите организацию для счёта' },
    inn:     { label: 'ИНН', type: 'text', ac: 'off', required: false, hint: 'ускорит выставление счёта', err: 'ИНН состоит из 10 или 12 цифр' },
    comment: { label: 'Комментарий', type: 'textarea', required: false, ph: 'Опишите Ваш запрос в комментарии' },
    // Поля запроса на запчасти.
    brand_model: { label: 'Марка и модель техники', type: 'text', required: true, ph: MODEL_HINT, err: 'Укажите марку и модель' },
    // Год и серийный номер — по желанию: их смотрят на шильдике, а он
    // бывает затёрт или закрыт навесным. Требовать их значило терять
    // заявку от того, кто просто не может их прочитать.
    year:        { label: 'Год выпуска', type: 'text', ac: 'off', required: false, ph: 'например, 2021', err: 'Укажите год выпуска' },
    serial:      { label: 'Серийный номер техники', type: 'text', ac: 'off', required: false, err: 'Укажите серийный номер' },
    fio:         { label: 'Ф.И.О.', type: 'text', ac: 'name', required: true, err: 'Укажите Ф.И.О.' },
    parts_list:  { label: 'Список требуемых запчастей', type: 'textarea', required: true, ph: 'Наименования, артикулы, количество' },
    photo:       { label: 'Фото детали или шильдика', type: 'file', required: false, hint: 'по желанию, ускоряет подбор' }
  };

  var RULES = {
    name: function (v) { return v.trim().length >= 2; },
    phone: function (v) { return v.replace(/\D/g, '').length >= 10; },
    email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()); },
    company: function (v) { return v.trim().length >= 2; },
    inn: function (v) { return v === '' || /^(\d{10}|\d{12})$/.test(v.trim()); },
    comment: function () { return true; },
    brand_model: function (v) { return v.trim().length >= 2; },
    year: function (v) { return v.trim().length >= 2; },
    serial: function (v) { return v.trim().length >= 1; },
    fio: function (v) { return v.trim().length >= 2; },
    parts_list: function (v) { return v.trim().length >= 2; }
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // Уменьшаем снимок до 1600px по большей стороне и пережимаем в JPEG:
  // фото шильдика остаётся читаемым, а вес падает в разы. Не смогли —
  // вернём null, заявка тогда уйдёт без фото, а не потеряется.
  //
  // Ответ обязан прийти ровно один раз и при любом исходе. Обработчик
  // onload асинхронный, внешний try/catch его не накрывает: исключение в
  // drawImage уходило в никуда, cb не вызывался, и кнопка навсегда
  // оставалась в состоянии «Отправляем…» — заявка не уходила и человек
  // об этом не узнавал. Отсюда и внутренний try/catch, и страховочный
  // таймер, и защита от повторного вызова.
  function shrinkImage(file, cb) {
    var done = false;
    var timer = window.setTimeout(function () { finish(null); }, 15000);

    function finish(data) {
      if (done) return;
      done = true;
      window.clearTimeout(timer);
      cb(data);
    }

    try {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        try {
          var max = 1600, w = img.width, h = img.height;
          if (w > max || h > max) { var s = max / Math.max(w, h); w = Math.round(w * s); h = Math.round(h * s); }
          var c = document.createElement('canvas');
          c.width = w; c.height = h;
          c.getContext('2d').drawImage(img, 0, 0, w, h);
          finish(c.toDataURL('image/jpeg', 0.85));
        } catch (e) { finish(null); }
      };
      img.onerror = function () { URL.revokeObjectURL(url); finish(null); };
      img.src = url;
    } catch (e) { finish(null); }
  }

  var dialog = null, lastFocus = null;

  function build() {
    if (dialog) return dialog;
    dialog = document.createElement('div');
    dialog.className = 'modal';
    dialog.hidden = true;
    dialog.innerHTML =
      '<div class="modal__backdrop" data-close></div>' +
      '<div class="modal__win" role="dialog" aria-modal="true" aria-labelledby="modal-title">' +
        '<button class="modal__close" type="button" data-close aria-label="Закрыть">✕</button>' +
        '<div class="modal__body"></div>' +
      '</div>';
    document.body.appendChild(dialog);

    dialog.addEventListener('click', function (e) {
      if (e.target.closest('[data-close]')) close();
    });
    return dialog;
  }

  /* Поле формы. Префикс к id — чтобы окно и вшитая в страницу форма могли
     жить на одной странице: с одинаковыми id подпись цеплялась бы к чужому
     полю, и нажатие на неё ставило курсор не туда. */
  function fieldHtml(key, prefix) {
    var f = FIELDS[key];
    var id = prefix + key;
    var wide = f.type === 'textarea' || f.type === 'file' ? ' field--wide' : '';
    if (f.type === 'textarea') {
      return '<div class="field' + wide + '"><label for="' + id + '">' + f.label +
             (f.required ? ' <span class="req" aria-hidden="true">*</span>' : '') + '</label>' +
             '<textarea id="' + id + '" name="' + key + '" rows="3"' +
             (f.ph ? ' placeholder="' + esc(f.ph) + '"' : '') + '></textarea>' +
             '<p class="field__error">' + esc(f.err || 'Заполните поле') + '</p></div>';
    }
    if (f.type === 'file') {
      return '<div class="field' + wide + '"><label for="' + id + '">' + f.label +
             (f.hint ? ' <span style="color:var(--c-muted);font-weight:400">— ' + f.hint + '</span>' : '') +
             '</label>' +
             '<input type="file" id="' + id + '" name="' + key + '" accept="image/*">' +
             '<p class="field__error">' + esc(f.err || '') + '</p></div>';
    }
    return '<div class="field"><label for="' + id + '">' + f.label +
      (f.required ? ' <span class="req" aria-hidden="true">*</span>'
                  : (f.hint ? ' <span style="color:var(--c-muted);font-weight:400">— ' + f.hint + '</span>' : '')) +
      '</label>' +
      '<input type="' + f.type + '" id="' + id + '" name="' + key + '" autocomplete="' + (f.ac || 'off') + '"' +
      (f.ph ? ' placeholder="' + esc(f.ph) + '"' : '') + (f.required ? ' required' : '') + '>' +
      '<p class="field__error">' + esc(f.err || '') + '</p></div>';
  }

  /* Галочка согласия.

     Формулировку и адрес политики берём из настроек сайта — их шаблон
     кладёт в data-атрибуты <body>. Зашитыми в скрипт они расходились с
     админкой: владелец правил текст, на экране оставался прежний, а в
     журнал согласий сервер писал новый — и записью уже нельзя было
     подтвердить, под каким текстом человек поставил галочку.

     Название политики внутри формулировки делаем ссылкой, а не дописываем
     ссылку следом: приписанная, она показала бы название второй раз, и
     увиденный текст опять разошёлся бы с записанным. Не нашли названия
     (владелец переписал формулировку по-своему) — ставим ссылку отдельно:
     без неё человеку негде прочитать, на что он соглашается.

     Близнец этого кода на стороне сервера — Sklad\Model\Consent::html().
     Галочку в корзине и на главной рисует шаблон, и браузер до них не
     дотянется. Правятся парой: разойдутся — человек снова прочитает в
     корзине одно, а в модальном окне другое. Совпадение проверяется
     прогоном обеих на формулировках всех четырёх сайтов сети. */
  function consentHtml(prefix) {
    var body = document.body;
    var wording = (body.getAttribute('data-consent-wording') || '').trim();
    var url = body.getAttribute('data-policy-url') || '/politika-konfidencialnosti/';
    var link = '<a href="' + esc(url) + '" target="_blank" rel="noopener">';
    var at = wording.lastIndexOf('Политик');
    var text = at < 0
      ? (wording ? esc(wording) + ' ' : '') + link + 'Политика конфиденциальности</a>'
      : esc(wording.slice(0, at)) + link + esc(wording.slice(at)) + '</a>';

    return '<div class="consent"><input type="checkbox" id="' + prefix + 'consent" required>' +
      '<label for="' + prefix + 'consent">' + text +
      ' <span class="req" aria-hidden="true">*</span></label></div>';
  }

  /* Разметка самой формы — одна на оба случая. */
  function formHtml(cfg, prefix) {
    // Ловушка для роботов: поле уведено за край экрана, дешёвый бот
    // заполняет всё подряд и выдаёт себя. Живой человек попасть в него не
    // должен ни руками, ни автозаполнением, поэтому readonly (значения в
    // такие поля браузер не подставляет) и имя без смысла для
    // автозаполнения: по name="surname" Chrome узнавал фамилию и заполнял
    // ловушку у настоящего покупателя — заявка после этого не уходила.
    return '<form novalidate>' +
      '<div class="trap" aria-hidden="true"><label for="' + prefix + 'refcode">Не заполняйте</label>' +
        '<input type="text" id="' + prefix + 'refcode" name="ref_code" tabindex="-1" autocomplete="off" readonly></div>' +
      '<div class="fields">' +
        cfg.fields.map(function (k) { return fieldHtml(k, prefix); }).join('') +
      '</div>' +
      consentHtml(prefix) +
      '<button class="btn btn--primary" type="submit">' + esc(cfg.submit) + '</button>' +
    '</form>';
  }

  /* Проверка, сборка и отправка. Общая и для окна, и для вшитой формы:
     расходятся они только тем, что показать после успеха. */
  function wire(root, cfg, type, context, onSuccess) {
    var form = root.querySelector('form');

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var btn = form.querySelector('button[type="submit"]');

      function fail(msg) {
        btn.disabled = false;
        btn.textContent = cfg.submit;
        var warn = form.querySelector('.form__error');
        if (!warn) {
          warn = document.createElement('p');
          warn.className = 'form__error';
          form.insertBefore(warn, btn);
        }
        warn.textContent = msg;
      }

      // Ловушка сработала. Молчать при этом нельзя: если поле всё-таки
      // заполнил браузер, перед нами живой человек — раньше окно просто
      // закрывалось, а вшитая в страницу форма не отвечала вовсе, и
      // заявка исчезала без следа. Робот от этой строки ничего не
      // получает: какое именно поле его выдало, ему не сообщают.
      var trap = form.elements['ref_code'];
      if (trap && trap.value !== '') {
        fail('Не удалось отправить заявку. Позвоните нам — так быстрее.');
        return;
      }

      var bad = null;
      cfg.fields.forEach(function (k) {
        var el = form[k];
        // Необязательное пустое поле (город, ИНН, комментарий) пропускаем:
        // проверяем его, только если человек что-то ввёл.
        var ok = (!FIELDS[k].required && (el.value || '').trim() === '')
          ? true
          : (RULES[k] ? RULES[k](el.value) : true);
        el.setAttribute('aria-invalid', ok ? 'false' : 'true');
        if (!ok && !bad) bad = el;
      });
      var consent = form.querySelector('.consent input[type="checkbox"]');
      consent.setAttribute('aria-invalid', consent.checked ? 'false' : 'true');
      if (!consent.checked && !bad) bad = consent;
      if (bad) { bad.focus(); return; }

      // Собираем заявку. У запчастей поля другие — раскладываем их по
      // стандартным (ФИО → имя, техника/год/серийный/список → комментарий);
      // остальные формы шлют поля как есть.
      var payload = { type: type, context: context || null, fields: {} };
      payload.consent = { given: true, at: new Date().toISOString() };
      payload.page = location.href;
      // Значение ловушки уходит на сервер под тем же именем, каким поле
      // названо в форме. Без него серверной проверке нечего было смотреть,
      // и от робота, который шлёт запрос напрямую, минуя эту страницу,
      // защиты не оставалось вовсе. У живого человека здесь пустая строка.
      payload.ref_code = trap ? trap.value : '';

      if (type === 'parts') {
        payload.fields = {
          name: form.fio.value.trim(),
          phone: form.phone.value.trim(),
          email: form.email.value.trim(),
          city: form.city.value.trim(),
          company: form.company.value.trim(),
          comment: 'Техника: ' + form.brand_model.value.trim()
            + '\nГод выпуска: ' + form.year.value.trim()
            + '\nСерийный №: ' + form.serial.value.trim()
            + '\n\nТребуемые запчасти:\n' + form.parts_list.value.trim()
        };
      } else {
        cfg.fields.forEach(function (k) { payload.fields[k] = form[k].value.trim(); });
      }

      btn.disabled = true;
      btn.textContent = 'Отправляем…';

      // Фото не удалось обработать — заявку всё равно отправляем, но
      // говорим об этом человеку на экране успеха и менеджеру в тексте.
      var photoLost = false;

      function send() {
        fetch('/zayavka/', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
          .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
          .then(function (res) {
            if (!res.ok) throw new Error(res.error || 'Не удалось отправить');
            if (window.Cart) window.Cart.goal(cfg.goal, { type: type });
            onSuccess(res, photoLost);
          })
          .catch(function (err) {
            // Заявку терять нельзя: показываем причину и оставляем
            // заполненную форму, чтобы человек мог повторить отправку.
            fail(err.message + '. Позвоните нам — так быстрее.');
          });
      }

      // Фото (только у запчастей) уменьшаем в браузере и кладём в base64 —
      // так POST остаётся лёгким и не упирается в лимиты сервера.
      var file = form.photo && form.photo.files && form.photo.files[0];
      if (file) {
        if (file.size > 25 * 1024 * 1024) { fail('Фото слишком большое — приложите файл поменьше'); return; }
        shrinkImage(file, function (dataUrl) {
          if (dataUrl) {
            payload.photo = dataUrl;
          } else {
            // Снимок обработать не вышло. Отменять из-за этого всю заявку
            // нельзя — потерять запрос хуже, чем потерять фото. Поэтому
            // отправляем без него, но помечаем это в комментарии, чтобы
            // менеджер знал, что фото надо запросить отдельно.
            photoLost = true;
            payload.fields.comment = (payload.fields.comment || '')
              + '\n\n(Фото приложить не удалось — браузер не смог его обработать.)';
          }
          send();
        });
      } else {
        send();
      }
    });
  }

  function open(type, context) {
    var cfg = TYPES[type] || TYPES.request;
    build();

    var body = dialog.querySelector('.modal__body');
    body.innerHTML =
      '<h2 id="modal-title">' + esc(cfg.title) + '</h2>' +
      '<p class="modal__lead">' + esc(cfg.lead) + '</p>' +
      (context ? '<p class="modal__ctx">' + esc(context.name) + '</p>' : '') +
      formHtml(cfg, 'rq-');

    wire(body, cfg, type, context, function (res, photoLost) {
      body.innerHTML =
        '<h2 id="modal-title">Заявка отправлена</h2>' +
        '<p class="modal__lead">Номер обращения <b>' + esc(res.number) + '</b>.' +
        ' Свяжемся в рабочее время в течение двух часов.</p>' +
        (photoLost ? '<p class="modal__lead">Фото приложить не удалось — менеджер' +
          ' попросит его отдельно.</p>' : '') +
        '<button class="btn btn--primary" type="button" data-close>Понятно</button>';
      body.querySelector('[data-close]').focus();
    });

    lastFocus = document.activeElement;
    dialog.hidden = false;
    document.body.style.overflow = 'hidden';
    // Курсор в первое поле — только там, где есть мышь. На телефоне фокус
    // сразу поднимает клавиатуру поверх окна, а iPhone ещё и увеличивает
    // страницу к полю и оставляет её увеличенной после закрытия окна
    // («страница шире экрана», заказчик Tisel 27.09.2026). Фокус для
    // читалок и клавиатуры тогда получает само окно.
    var first = body.querySelector('input:not([tabindex="-1"])');
    var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    if (first && !touch) {
      first.focus();
    } else if (touch) {
      var win = body.closest('[role="dialog"]') || body;
      if (!win.hasAttribute('tabindex')) win.setAttribute('tabindex', '-1');
      win.focus({ preventScroll: true });
    }
  }

  /* Форма, вшитая в страницу, — раздел запчастей начинается с неё.
     Всплывающим окном она была хуже: человек его закрывал и больше не
     находил, а на странице форма стоит на месте и видна сразу. */
  function mount(host, type) {
    var cfg = TYPES[type] || TYPES.parts;
    host.innerHTML =
      '<div class="pageform__head">' +
        '<h2>' + esc(cfg.title) + '</h2>' +
        '<p>' + esc(cfg.lead) + '</p>' +
      '</div>' +
      formHtml(cfg, 'pf-');

    wire(host, cfg, type, null, function (res, photoLost) {
      host.innerHTML =
        '<div class="pageform__done">' +
          '<h2>Запрос отправлен</h2>' +
          '<p>Номер обращения <b>' + esc(res.number) + '</b>.' +
          ' Подберём запчасти и ответим в рабочее время в течение двух часов.</p>' +
          (photoLost ? '<p>Фото приложить не удалось — менеджер попросит его отдельно.</p>' : '') +
        '</div>';
      host.scrollIntoView({ block: 'center', behavior: 'smooth' });
    });
  }

  function close() {
    if (!dialog || dialog.hidden) return;
    dialog.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
    // Фокус не должен уходить за пределы окна, пока оно открыто.
    if (e.key === 'Tab' && dialog && !dialog.hidden) {
      var f = dialog.querySelectorAll('a[href], button, input, textarea, select');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action="request"],[data-action="one-click"],[data-action="request-price"],[data-action="call"],[data-action="discount"],[data-action="parts"]');
    if (!btn) return;
    e.preventDefault();

    var host = btn.closest('[data-product-id]');
    open(btn.dataset.action, host ? { id: host.dataset.productId, name: host.dataset.name } : null);
  });

  // Раздел запчастей начинается с формы, вшитой в страницу: шаблон
  // оставляет под неё место, а рисуем её здесь — набор полей и отправка
  // общие с окном, второй копии не заводим.
  function mountAll() {
    [].forEach.call(document.querySelectorAll('[data-parts-form]'), function (host) {
      mount(host, 'parts');
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAll);
  } else {
    mountAll();
  }

  window.RequestForm = { open: open, close: close, mount: mount };

})(window, document);
