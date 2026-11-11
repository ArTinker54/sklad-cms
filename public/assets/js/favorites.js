'use strict';
/* Избранное. Устроено как сравнение, но без лимита:
   это «отложить, чтобы вернуться», а не «сопоставить рядом». */

(function (window, document) {

  var KEY = 'sklad.favorites.v1';
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
    document.dispatchEvent(new CustomEvent('favorites:change', { detail: { ids: ids } }));
  }

  var Favorites = {
    all: read,
    count: function () { return read().length; },
    has: function (id) { return read().indexOf(id) !== -1; },

    add: function (id) {
      var ids = read();
      if (ids.indexOf(id) === -1) { ids.push(id); write(ids); }
      return this;
    },

    remove: function (id) {
      write(read().filter(function (i) { return i !== id; }));
      return this;
    },

    toggle: function (id) {
      if (this.has(id)) { this.remove(id); return false; }
      this.add(id);
      return true;
    },

    clear: function () { write([]); return this; },

    /* Выбросить то, чего нет в каталоге, — см. Compare.prune. */
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

  function paintCounter(ids) {
    [].forEach.call(document.querySelectorAll('[data-fav-count]'), function (el) {
      el.textContent = ids.length;
      el.hidden = ids.length === 0;
    });
    [].forEach.call(document.querySelectorAll('[data-fav-link]'), function (el) {
      el.hidden = ids.length === 0;
    });
  }

  function paintButtons(ids) {
    [].forEach.call(document.querySelectorAll('[data-action="favorite"]'), function (btn) {
      var host = btn.closest('[data-product-id]');
      if (!host) return;
      var on = ids.indexOf(host.dataset.productId) !== -1;
      btn.setAttribute('aria-pressed', on);
      // Кнопка без текста — подпись нужна и для screen reader, и для подсказки.
      var label = on ? 'Убрать из избранного' : 'Отложить в избранное';
      btn.setAttribute('aria-label', label);
      btn.title = label;
    });
  }

  function bind() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action="favorite"]');
      if (!btn) return;
      e.preventDefault();
      var host = btn.closest('[data-product-id]');
      if (host) Favorites.toggle(host.dataset.productId);
    });
  }

  function paint(ids) { paintCounter(ids); paintButtons(ids); }
  Favorites.onChange(paint);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  window.Favorites = Favorites;

})(window, document);
