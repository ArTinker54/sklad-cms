'use strict';
/* Сравнение товаров.
   Как и корзина — на localStorage: список id, данные подтягиваются
   из products.json уже на странице сравнения. */

(function (window, document) {

  var KEY = 'sklad.compare.v1';
  var LIMIT = 4;          // больше четырёх колонок не влезает даже на десктопе
  var listeners = [];

  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      var ids = raw ? JSON.parse(raw) : [];
      return Array.isArray(ids) ? ids.filter(function (i) { return typeof i === 'string' && i; }) : [];
    } catch (e) { return []; }
  }

  function write(ids) {
    try { window.localStorage.setItem(KEY, JSON.stringify(ids)); } catch (e) {}
    listeners.forEach(function (fn) { fn(ids); });
    document.dispatchEvent(new CustomEvent('compare:change', { detail: { ids: ids } }));
  }

  var Compare = {
    LIMIT: LIMIT,
    all: read,
    count: function () { return read().length; },
    has: function (id) { return read().indexOf(id) !== -1; },
    isFull: function () { return read().length >= LIMIT; },

    add: function (id) {
      var ids = read();
      if (ids.indexOf(id) !== -1) return { ok: true, already: true };
      if (ids.length >= LIMIT) return { ok: false, full: true };
      ids.push(id);
      write(ids);
      return { ok: true };
    },

    remove: function (id) {
      write(read().filter(function (i) { return i !== id; }));
      return this;
    },

    toggle: function (id) {
      return this.has(id) ? (this.remove(id), { ok: true, removed: true }) : this.add(id);
    },

    clear: function () { write([]); return this; },

    /* Выбросить то, чего нет в каталоге этого сайта (known — products из
       products.json). В памяти браузера лежат адреса товаров, а товар
       уходит из каталога, адрес меняется, и на стенде, где раньше жил
       другой сайт, остаются его товары: tor.staging.local был Партнёром, стал
       Tisel. Такие записи не показать, но место они занимают — в шапке
       «4», страница сравнения пуста, а добавить нельзя: «не больше
       четырёх», и кнопка краснеет. Пустой каталог (не пришёл, идёт
       загрузка) — не повод выбрасывать всё. Возвращает true, если было что. */
    prune: function (known) {
      if (!known || !Object.keys(known).length) return false;
      var ids = read();
      var kept = ids.filter(function (id) { return Object.prototype.hasOwnProperty.call(known, id); });
      if (kept.length === ids.length) return false;
      write(kept);
      return true;
    },

    onChange: function (fn) { listeners.push(fn); fn(read()); return this; },

    /* Перерисовать отметки на карточках, дорисованных подбором.
       Раньше для этого звали onChange с пустой функцией — она лишь
       добавляла слушателя, а кнопки оставались неотмеченными: нажатие
       на уже отложенном товаре убирало его из списка. */
    repaint: function () { paint(read()); return this; }
  };

  /* ---------- счётчик в шапке ---------- */
  function paintCounter(ids) {
    [].forEach.call(document.querySelectorAll('[data-compare-count]'), function (el) {
      el.textContent = ids.length;
      el.hidden = ids.length === 0;
    });
    // ссылка на сравнение прячется, пока сравнивать нечего
    [].forEach.call(document.querySelectorAll('[data-compare-link]'), function (el) {
      el.hidden = ids.length === 0;
    });
  }

  /* ---------- состояние кнопок на карточках ---------- */
  function paintButtons(ids) {
    [].forEach.call(document.querySelectorAll('[data-action="compare"]'), function (btn) {
      var host = btn.closest('[data-product-id]');
      if (!host) return;
      var on = ids.indexOf(host.dataset.productId) !== -1;
      btn.setAttribute('aria-pressed', on);
      btn.title = on ? 'Убрать из сравнения' : 'Добавить к сравнению';
      var label = btn.querySelector('[data-compare-label]');
      if (label) label.textContent = on ? 'В сравнении' : 'Сравнить';
    });
  }

  /* ---------- список полон ---------- */

  /* Каталог — только когда список полон, и один раз за страницу: файл
     весит сотни килобайт, на каждой странице он ни к чему. Неудача не
     запоминается — следующее нажатие попробует снова. */
  var catalog = null;
  function loadCatalog() {
    if (!catalog) {
      catalog = fetch('/products.json')
        .then(function (r) { return r.json(); })
        .then(function (d) { return d.products; });
      catalog.catch(function () { catalog = null; });
    }
    return catalog;
  }

  /* Сообщение внизу экрана. Кнопка-значок раньше только краснела и
     дёргалась, а объяснение жило в title — на телефоне его не видно
     вовсе, и красная рамка читалась как поломка. Область role="status"
     заводим пустой и пишем в неё следом: вставленную сразу с текстом
     читалка может и не объявить. */
  var hintTimer;
  function limitHint() {
    var el = document.getElementById('cmp-limit');
    if (!el) {
      el = document.createElement('p');
      el.id = 'cmp-limit';
      el.className = 'cmp-limit';
      el.setAttribute('role', 'status');
      el.hidden = true;
      document.body.appendChild(el);
    }
    window.setTimeout(function () {
      el.innerHTML = 'Сравнить можно не больше ' + LIMIT + ' моделей сразу. ' +
        '<a href="/sravnenie/">Открыть сравнение</a>';
      el.hidden = false;
    }, 50);
    window.clearTimeout(hintTimer);
    hintTimer = window.setTimeout(function () { el.hidden = true; }, 5000);
  }

  function showLimit(btn) {
    // Тихо игнорировать нельзя — человек нажал и ждёт реакции. Но
    // btn.textContent затирает содержимое: у кнопки-иконки это стёрло
    // бы значок. Поэтому текст — в подпись, если она есть, иначе
    // мигаем кнопкой; объясняет сообщение внизу экрана.
    var label = btn.querySelector('[data-compare-label]');
    if (label) {
      var was = label.textContent;
      label.textContent = 'Максимум ' + LIMIT;
      window.setTimeout(function () { label.textContent = was; paintButtons(Compare.all()); }, 1600);
    } else {
      var prevTitle = btn.title;
      btn.title = 'В сравнении не больше ' + LIMIT;
      btn.classList.add('is-limit');
      window.setTimeout(function () {
        btn.title = prevTitle; btn.classList.remove('is-limit'); paintButtons(Compare.all());
      }, 1600);
    }
    limitHint();
  }

  function bind() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action="compare"]');
      if (!btn) return;
      e.preventDefault();

      var host = btn.closest('[data-product-id]');
      if (!host) return;

      var id = host.dataset.productId;
      var res = Compare.toggle(id);
      if (res.full) {
        // Прежде чем сказать «больше нельзя», сверяемся с каталогом:
        // места могут занимать товары, которых на сайте нет (см. prune).
        loadCatalog()
          .then(function (known) {
            if (Compare.prune(known) && Compare.add(id).ok) return;
            showLimit(btn);
          })
          .catch(function () { showLimit(btn); });
      }
    });
  }

  function paint(ids) { paintCounter(ids); paintButtons(ids); }
  Compare.onChange(paint);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  window.Compare = Compare;

})(window, document);
