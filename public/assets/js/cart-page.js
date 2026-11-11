'use strict';
/* Корзина-заявка: список отложенного, количество, отправка заявки.

   Вынесено из текста страницы в файл. Пока код лежал внутри
   содержимого, его нельзя было отделить от текста: менеджер правил
   абзац и мог снести работающую часть, а строгая политика
   безопасности такие вставки запрещает целиком. */

document.addEventListener("DOMContentLoaded",function(){
(function () {
  var nf = new Intl.NumberFormat('ru-RU');
  var list = document.getElementById('list');
  var view = document.getElementById('cartview');
  var empty = document.getElementById('empty');
  var done = document.getElementById('done');
  var form = document.getElementById('form');

  /* Экранирование того, что уходит в разметку строкой.

     В позицию заявки приходят название товара, его адрес, путь к снимку
     и короткие характеристики. Заводит их админка, но лежат они в памяти
     браузера — а туда посетитель может записать что угодно сам. Дальше
     всё это склеивается в innerHTML.

     Страница при этом та самая, где вводят имя, телефон, почту и ИНН:
     чужая разметка здесь дороже, чем где бы то ни было на сайте. */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function plural(n, one, few, many) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return one;
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few;
    return many;
  }

  /* ---------- отрисовка ----------

     Строку, которая уже на экране, не пересобираем, а правим на месте.

     Раньше список на каждое изменение собирался заново — и из-за этого
     удаление срабатывало через раз. Порядок был такой: человек правил
     количество, а потом нажимал «удалить». Нажатие мыши уводило фокус из
     поля количества, поле отдавало change, количество сохранялось — и
     список перерисовывался прямо между нажатием и отпусканием кнопки.
     Кнопки, на которой началось нажатие, к этому моменту в документе уже
     не было, а click браузер выдаёт, только когда нажали и отпустили на
     одном узле. Первое нажатие пропадало целиком, второе — когда
     сохранять было уже нечего — срабатывало.

     Теперь узел строки переживает перерисовку, и пропадать нечему. Заодно
     не сбивается фокус: «+» и «−» можно нажимать с клавиатуры подряд. */

  /* Подпись строки — всё, что нарисовано в ней намертво. Пока подпись та
     же, строку можно оставить: количество и сумму проставит fill().
     Картинку и адрес позиции дозаполняет backfillImages уже после первой
     отрисовки — ради них подпись и нужна, иначе дорисованное фото так бы
     и не появилось. Держим её на самом узле, а не в data-атрибуте: это
     служебная пометка для кода, в разметке ей делать нечего. */
  function sign(i) {
    return JSON.stringify([i.img, i.url, i.name, i.specs, i.price]);
  }

  function fill(li, i) {
    var input = li.querySelector('.qty input');
    var qty = String(parseInt(i.qty, 10) || 1);
    // Поле трогаем, только если в нём и правда другое число: лишняя
    // запись сбрасывала бы курсор тому, кто как раз его набирает.
    if (input.value !== qty) input.value = qty;
    li.querySelector('.cartitem__sum').textContent = i.price ? nf.format(i.price * i.qty) + ' ₽' : '—';
  }

  function build(i) {
    var li = document.createElement('li');
    li.className = 'cartitem';
    li.dataset.id = i.id;
    li.cartSign = sign(i);
    li.innerHTML =
      // Без loading="lazy": позиций мало и они сразу на экране,
      // отложенная загрузка тут только тормозит показ.
      '<div class="cartitem__media">' +
        '<img src="' + esc(i.img || '/assets/img/placeholder.svg') + '" alt="" width="92" height="69"' +
        '></div>' +
      '<div>' +
        '<p class="cartitem__name">' + (i.url ? '<a href="' + esc(i.url) + '">' + esc(i.name) + '</a>' : esc(i.name)) + '</p>' +
        (i.specs ? '<p class="cartitem__specs">' + esc(i.specs) + '</p>' : '') +
        (i.price ? '<p class="cartitem__unit">' + nf.format(i.price) + ' ₽ за штуку</p>' : '<p class="cartitem__unit">Цену уточнит менеджер</p>') +
      '</div>' +
      '<div class="cartitem__side">' +
        '<span class="qty">' +
          '<button type="button" data-step="-1" aria-label="Уменьшить количество">−</button>' +
          '<input type="number" min="1" max="999" aria-label="Количество">' +
          '<button type="button" data-step="1" aria-label="Увеличить количество">+</button>' +
        '</span>' +
        '<span class="cartitem__sum"></span>' +
        '<button type="button" class="cartitem__del">удалить</button>' +
      '</div>';
    fill(li, i);
    return li;
  }

  /* Счётчик, «Итого» и приписка под ним.

     Вынесено из render отдельной функцией не ради порядка: три эти строки
     стояли в конце render, а выше, на пустом списке, стоял ранний выход —
     и опустевшая корзина оставляла на экране числа от прошлого состояния
     («Итого 3 позиции», сумма за уже удалённое). Пока корзина честно
     пряталась, этого не было видно; как только hidden переставал работать
     (см. [hidden] в main.css), человек читал сумму за пустую заявку.
     Теперь считаем при каждой отрисовке, в том числе на нуле.

     Считаем по тому самому списку, который рисуем, а не спрашиваем
     window.Cart заново: иначе числа и строки берутся из двух разных
     чтений хранилища и могут разойтись. */
  function totals(items) {
    var n = items.reduce(function (s, i) { return s + i.qty; }, 0);
    document.getElementById('cnt').textContent =
      n + ' ' + plural(n, 'позиция', 'позиции', 'позиций');

    var withPrices = items.every(function (i) {
      return typeof i.price === 'number' && i.price > 0;
    });
    var sum = items.reduce(function (s, i) { return s + (i.price || 0) * i.qty; }, 0);

    document.getElementById('total').textContent = withPrices ? nf.format(sum) + ' ₽' : 'по запросу';
    document.getElementById('totalnote').textContent = withPrices
      ? 'Цены с НДС. Стоимость доставки рассчитает менеджер — она зависит от габаритов и адреса.'
      : 'На часть позиций цена уточняется. Менеджер пришлёт полный расчёт со счётом.';
  }

  function render(items) {
    if (!done.hidden) return;                    // после отправки корзину не показываем

    var has = items.length > 0;
    view.hidden = !has;
    empty.hidden = has;
    totals(items);

    // Пустой список чистим: иначе в спрятанной корзине оставались строки
    // уже удалённых позиций и всплывали при следующем добавлении.
    if (!has) { list.innerHTML = ''; return; }

    /* Один проход по списку: совпавшую строку оставляем, чужую убираем,
       недостающую вставляем. Позиции идут в порядке добавления и меняются
       только удалением — большего здесь не нужно. */
    var node = list.firstElementChild;
    items.forEach(function (i) {
      while (node && node.dataset.id !== i.id) {
        var gone = node;
        node = node.nextElementSibling;
        list.removeChild(gone);
      }
      if (node && node.cartSign === sign(i)) {
        fill(node, i);
        node = node.nextElementSibling;
        return;
      }
      var fresh = build(i);
      if (node) {                                // позиция та же, а данные изменились
        var stale = node;
        node = node.nextElementSibling;
        list.replaceChild(fresh, stale);
      } else {
        list.appendChild(fresh);
      }
    });
    while (node) {                               // хвост от прежней отрисовки
      var tail = node;
      node = node.nextElementSibling;
      list.removeChild(tail);
    }
  }

  list.addEventListener('click', function (e) {
    var item = e.target.closest('.cartitem');
    if (!item) return;
    var id = item.dataset.id;
    if (e.target.closest('.cartitem__del')) { window.Cart.remove(id); return; }
    var step = e.target.dataset.step;
    if (step) {
      var input = item.querySelector('.qty input');
      window.Cart.setQty(id, parseInt(input.value, 10) + parseInt(step, 10));
    }
  });

  list.addEventListener('change', function (e) {
    if (e.target.matches('.qty input')) {
      window.Cart.setQty(e.target.closest('.cartitem').dataset.id, e.target.value);
    }
  });

  /* ---------- проверка формы ---------- */
  var RULES = {
    name:    function (v) { return v.trim().length >= 2; },
    phone:   function (v) { return (v.replace(/\D/g, '').length >= 10); },
    email:   function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()); },
    city:    function (v) { return v.trim().length >= 2; },
    company: function (v) { return v.trim().length >= 2; },
    inn:     function (v) { return v === '' || /^(\d{10}|\d{12})$/.test(v.trim()); }
  };

  function check(el) {
    var rule = RULES[el.name];
    var ok = rule ? rule(el.value) : true;
    el.setAttribute('aria-invalid', ok ? 'false' : 'true');
    return ok;
  }

  form.addEventListener('input', function (e) {
    if (e.target.getAttribute('aria-invalid') === 'true') check(e.target);
  });
  form.addEventListener('blur', function (e) {
    if (RULES[e.target.name] && e.target.value !== '') check(e.target);
  }, true);

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Ловушка сработала — молча делаем вид, что всё хорошо.
    var trap = form.elements['ref_code'];
    if (trap && trap.value !== '') { return; }

    var bad = null;
    Object.keys(RULES).forEach(function (n) {
      if (!check(form[n]) && !bad) bad = form[n];
    });

    var consent = form.consent;
    consent.setAttribute('aria-invalid', consent.checked ? 'false' : 'true');
    if (!consent.checked && !bad) bad = consent;

    if (bad) { bad.focus(); return; }

    var btn = document.getElementById('submit');
    btn.disabled = true;
    btn.textContent = 'Отправляем…';

    var payload = {
      contact: {
        name: form.name.value.trim(), phone: form.phone.value.trim(),
        email: form.email.value.trim(), city: form.city.value.trim(),
        company: form.company.value.trim(), inn: form.inn.value.trim(),
        comment: form.comment.value.trim()
      },
      type: 'cart',
      items: window.Cart.all(),
      total: window.Cart.hasPrices() ? window.Cart.total() : null,
      // Доказательство согласия по 152-ФЗ. На сервере дополняется IP,
      // временем, формулировкой и версией политики.
      consent: { given: true, at: new Date().toISOString(), text: 'Политика конфиденциальности' },
      page: location.href
    };

    fetch('/zayavka/', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (res) {
        if (!res.ok) throw new Error(res.error || 'Не удалось отправить заявку');
        window.Cart.goal('lead_submit', { items: payload.items.length, total: payload.total });
        document.getElementById('ticket').textContent = res.number;
        view.hidden = true;
        empty.hidden = true;
        done.hidden = false;
        window.Cart.clear();
        done.scrollIntoView({ block: 'start' });
      })
      .catch(function (err) {
        // Корзину не чистим и форму не прячем: человек собирал заявку,
        // терять её из-за сбоя сети нельзя.
        btn.disabled = false;
        btn.textContent = 'Отправить заявку';
        var warn = document.getElementById('senderror');
        if (!warn) {
          warn = document.createElement('p');
          warn.id = 'senderror';
          warn.className = 'form__error';
          btn.parentNode.insertBefore(warn, btn);
        }
        warn.textContent = err.message + '. Позвоните нам — так быстрее.';
      });
  });

  /* ---------- сверка с каталогом ----------
     Позиции, которых в каталоге нет, выбрасываем (см. Compare.prune):
     товар ушёл, адрес сменился, а на стенде, где раньше жил другой сайт,
     в памяти браузера лежат его товары — чужая тележка с битой картинкой
     уходила бы с заявкой менеджеру. Оставшимся дописываем фото, если
     позиция сохранена до того, как появилось это поле.

     Раньше каталог грузился, только когда у кого-то не было фото, а
     позицию с чужим id подбирали по коду модели из названия («старый
     демо-набор»). Демо-набор — те самые чужие товары с выдуманными
     ценами (см. ниже): место им не в заявке, а подбор по коду выдавал
     их за товары сайта. */
  function syncWithCatalog(done) {
    if (!window.Cart.all().length) return done();

    fetch('/products.json')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        [window.Cart, window.Compare, window.Favorites].forEach(function (m) { if (m && m.prune) m.prune(d.products); });
        window.Cart.all().forEach(function (i) {
          var p = d.products[i.id];
          if (!i.img && p) window.Cart.patch(i.id, { img: p.img, url: '' + p.file });
        });
        done();
      })
      .catch(done);
  }

  /* Демо-наполнение корзины здесь было, пока страницу собирали в
     прототипе. На работающем сайте оно подсовывало бы покупателю два
     чужих товара с выдуманными ценами — убрано. */

  /* Показываем сразу, снимки дорисовываем следом.
     Раньше подписка стояла внутри ответа каталога: список ждал сетевого
     запроса, а у позиции, сохранённой до появления поля с фото, в рамке
     до первого касания висела заглушка. */
  window.Cart.onChange(render);
  syncWithCatalog(function () { render(window.Cart.all()); });

  document.getElementById('cart-clear').addEventListener('click', function () {
    window.Cart.clear();
  });
})();
});
