'use strict';
/* Поиск по каталогу.
   Индекс — тот же products.json, что у сравнения и избранного.
   Грузится лениво: при первом открытии панели, а не на каждой странице. */

(function (window, document) {

  var LIMIT = 8;
  var index = null;
  var loading = null;

  /* Приводим к общему виду: регистр, ё/е и дефисы в кодах моделей.
     «CDDK15-III», «cddk15 iii» и «CDDK15III» должны находиться одинаково. */
  function norm(s) {
    return String(s || '').toLowerCase().replace(/ё/g, 'е').replace(/[\s\-_/]+/g, ' ').trim();
  }
  function squash(s) { return norm(s).replace(/ /g, ''); }

  function load() {
    if (index) return Promise.resolve(index);
    if (loading) return loading;
    loading = fetch('/products.json')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        index = Object.keys(d.products).map(function (id) {
          var p = d.products[id];
          return {
            id: id, name: p.name, file: p.file, img: p.img,
            price: p.price, section: p.section,
            n: norm(p.name + ' ' + p.section),
            s: squash(p.name)
          };
        });
        return index;
      })
      .catch(function () { index = []; return index; });
    return loading;
  }

  function search(q, limit) {
    if (!index) return [];
    var nq = norm(q), sq = squash(q);
    if (nq.length < 2) return [];

    var hits = [];
    for (var i = 0; i < index.length; i++) {
      var it = index[i], score = 0;

      if (it.s.indexOf(sq) === 0) score = 100;              // с начала названия
      else if (it.s.indexOf(sq) !== -1) score = 60;         // где-то внутри
      else {
        // все слова запроса должны найтись — иначе «тележка 2000» выдаст всё подряд
        var words = nq.split(' ').filter(Boolean);
        var all = words.every(function (w) { return it.n.indexOf(w) !== -1; });
        if (!all) continue;
        score = 30 + words.length;
      }
      if (it.price) score += 2;                             // с ценой показываем выше
      hits.push({ item: it, score: score });
    }

    hits.sort(function (a, b) { return b.score - a.score || a.item.name.localeCompare(b.item.name, 'ru'); });
    return hits.slice(0, limit || LIMIT).map(function (h) { return h.item; });
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* Подсветка совпавших слов — видно, почему товар нашёлся. */
  function highlight(name, q) {
    var words = norm(q).split(' ').filter(function (w) { return w.length > 1; });
    var out = esc(name);
    words.forEach(function (w) {
      var re = new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
      out = out.replace(re, '<mark>$1</mark>');
    });
    return out;
  }

  window.BrandSearch = { load: load, search: search, highlight: highlight, esc: esc, norm: norm };

  /* ---------- панель в шапке ---------- */
  function initBar() {
    var bar = document.querySelector('.searchbar');
    var toggle = document.querySelector('.header__search');
    if (!bar || !toggle) return;

    var input = bar.querySelector('input');
    var list = bar.querySelector('.suggest');
    var close = bar.querySelector('.searchbar__close');
    var active = -1;

    function open() {
      bar.dataset.open = 'true';
      load();
      input.focus();
    }
    function hide() {
      bar.dataset.open = 'false';
      list.dataset.open = 'false';
      active = -1;
    }

    function render(items, q) {
      if (!items.length) {
        list.innerHTML = q.length >= 2
          ? '<li><span class="suggest__all" style="display:block;padding:var(--s-4)">Ничего не найдено</span></li>'
          : '';
        list.dataset.open = q.length >= 2 ? 'true' : 'false';
        return;
      }
      list.innerHTML = items.map(function (it) {
        return '<li><a href="' + esc(it.file) + '">' +
          '<img src="' + esc(it.img) + '" alt="">' +
          '<span class="suggest__name">' + highlight(it.name, q) +
            '<span class="suggest__meta">' + esc(it.section) +
            (it.price ? ' · ' + new Intl.NumberFormat('ru-RU').format(it.price) + ' ₽' : ' · цена по запросу') +
            '</span></span></a></li>';
      }).join('') +
        '<li><a class="suggest__all" href="/poisk/?s=' + encodeURIComponent(q) + '">Показать все результаты</a></li>';
      list.dataset.open = 'true';
      active = -1;
    }

    var timer = null;
    input.addEventListener('input', function () {
      window.clearTimeout(timer);
      var q = input.value;
      timer = window.setTimeout(function () {
        load().then(function () { render(search(q), q); });
      }, 120);
    });

    input.addEventListener('keydown', function (e) {
      var items = Array.prototype.slice.call(list.querySelectorAll('li'));
      if (e.key === 'Escape') { hide(); toggle.focus(); return; }
      if (e.key === 'Enter' && active < 0) {
        if (input.value.trim().length >= 2) location.href = '/poisk/?s=' + encodeURIComponent(input.value.trim());
        return;
      }
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter') return;
      e.preventDefault();
      if (!items.length) return;

      if (e.key === 'Enter') { items[active].querySelector('a').click(); return; }
      active = e.key === 'ArrowDown'
        ? (active + 1) % items.length
        : (active - 1 + items.length) % items.length;
      items.forEach(function (li, n) { li.setAttribute('aria-selected', n === active); });
      items[active].scrollIntoView({ block: 'nearest' });
    });

    toggle.addEventListener('click', function () {
      bar.dataset.open === 'true' ? hide() : open();
    });
    close.addEventListener('click', function () { hide(); toggle.focus(); });

    document.addEventListener('click', function (e) {
      if (bar.dataset.open === 'true' && !bar.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) hide();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initBar);
  else initBar();

})(window, document);
