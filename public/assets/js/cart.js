'use strict';
/* Корзина-заявка Brand.
   Хранится в localStorage: онлайн-оплаты нет, серверная сессия не нужна.
   Все страницы подключают этот файл — счётчик в шапке синхронизируется сам. */

(function (window, document) {

  var KEY = 'sklad.cart.v1';
  var listeners = [];

  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      var items = raw ? JSON.parse(raw) : [];
      return Array.isArray(items) ? items.filter(valid) : [];
    } catch (e) {
      // Приватный режим или переполнение — корзина просто не запоминается.
      return [];
    }
  }

  function write(items) {
    try { window.localStorage.setItem(KEY, JSON.stringify(items)); } catch (e) {}
    listeners.forEach(function (fn) { fn(items); });
    document.dispatchEvent(new CustomEvent('cart:change', { detail: { items: items } }));
  }

  function valid(i) {
    return i && typeof i.id === 'string' && i.id && typeof i.qty === 'number' && i.qty > 0;
  }

  var Cart = {

    all: read,

    count: function () {
      return read().reduce(function (n, i) { return n + i.qty; }, 0);
    },

    total: function () {
      return read().reduce(function (s, i) { return s + (i.price || 0) * i.qty; }, 0);
    },

    /* Цены может не быть — тогда заявка уходит без суммы, менеджер считает сам. */
    hasPrices: function () {
      return read().every(function (i) { return typeof i.price === 'number' && i.price > 0; });
    },

    add: function (item, qty) {
      qty = qty || 1;
      var items = read();
      var found = items.filter(function (i) { return i.id === item.id; })[0];
      if (found) {
        found.qty += qty;
      } else {
        items.push({
          id: String(item.id),
          name: item.name || '',
          url: item.url || '',
          img: item.img || '',
          price: typeof item.price === 'number' ? item.price : null,
          specs: item.specs || '',
          qty: qty
        });
      }
      write(items);
      return this;
    },

    setQty: function (id, qty) {
      qty = Math.max(1, Math.min(999, parseInt(qty, 10) || 1));
      var items = read();
      items.forEach(function (i) { if (i.id === id) i.qty = qty; });
      write(items);
      return this;
    },

    remove: function (id) {
      write(read().filter(function (i) { return i.id !== id; }));
      return this;
    },

    clear: function () { write([]); return this; },

    /* Дописать поля уже лежащим позициям.
       Нужно, когда в корзине остались записи, сохранённые старой версией
       кода: у них нет картинки, и перекладывать товар заново пользователь
       не обязан. */
    patch: function (id, fields) {
      var items = read(), touched = false;
      items.forEach(function (i) {
        if (i.id !== id) return;
        Object.keys(fields).forEach(function (k) {
          if (fields[k] && !i[k]) { i[k] = fields[k]; touched = true; }
        });
      });
      if (touched) write(items);
      return touched;
    },

    /* Позиции, которых нет в каталоге, — из заявки вон (см. Compare.prune).
       Иначе в корзине висит чужая тележка с битой картинкой и ссылкой в
       никуда, и с заявкой она уходит менеджеру. */
    prune: function (known) {
      if (!known || !Object.keys(known).length) return false;
      var items = read();
      var kept = items.filter(function (i) { return Object.prototype.hasOwnProperty.call(known, i.id); });
      if (kept.length === items.length) return false;
      write(kept);
      return true;
    },

    onChange: function (fn) { listeners.push(fn); fn(read()); return this; }
  };

  /* ---------- цель Яндекс.Метрики ---------- */
  Cart.goal = function (name, params) {
    if (typeof window.ym === 'function' && window.SKLAD_METRIKA_ID) {
      window.ym(window.SKLAD_METRIKA_ID, 'reachGoal', name, params || {});
    }
  };

  /* ---------- счётчик в шапке ---------- */
  function paintCounter(items) {
    var n = items.reduce(function (s, i) { return s + i.qty; }, 0);
    [].forEach.call(document.querySelectorAll('[data-cart-count]'), function (el) {
      el.textContent = n;
      el.hidden = n === 0;
    });
  }

  /* ---------- кнопки «В корзину» ---------- */
  function bindButtons() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action="add-to-cart"]');
      if (!btn) return;

      var host = btn.closest('[data-product-id]') || btn.closest('.card') || document.querySelector('[data-product-id]');
      if (!host) return;

      var price = parseInt(host.dataset.price, 10);
      // Картинку берём из data-image, а если его нет — из первого <img> карточки.
      var pic = host.dataset.image;
      if (!pic) {
        var el = host.querySelector('.card__media img, .gallery__main img');
        if (el) pic = el.getAttribute('src');
      }

      Cart.add({
        id: host.dataset.productId || host.dataset.name || host.querySelector('.card__title, h1').textContent.trim(),
        name: (host.dataset.name || host.querySelector('.card__title, h1').textContent).trim(),
        url: host.dataset.url || (host.querySelector('a') || {}).getAttribute && host.querySelector('a').getAttribute('href') || '',
        img: pic || '',
        price: isNaN(price) ? null : price,
        specs: host.dataset.specs || ''
      });

      Cart.goal('add_to_cart', { name: host.dataset.name });

      // Подтверждение прямо на кнопке — модалка тут только мешает.
      var was = btn.textContent;
      btn.textContent = 'Добавлено ✓';
      btn.disabled = true;
      window.setTimeout(function () { btn.textContent = was; btn.disabled = false; }, 1400);
    });
  }

  Cart.onChange(paintCounter);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindButtons);
  } else {
    bindButtons();
  }

  window.Cart = Cart;

})(window, document);
