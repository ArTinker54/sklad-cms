'use strict';
/* Подбор по параметрам в разделе каталога.

   Первые карточки приходят с сервера — они же попадают в индекс
   поисковика. Как только человек начинает подбирать или сортировать,
   скрипт забирает весь раздел из products.json и дальше рисует сетку
   сам: фильтр обязан работать по всему разделу, а не по тем двум
   десяткам, что успели прийти на первой странице.

   Раздел определяется по началу адреса: адреса повторяют дерево
   каталога, поэтому /shtabelery/ — это и сам раздел, и всё под ним. */

(function (window, document) {

  var root = document.querySelector('[data-catalog]');
  if (!root) return;

  var PATH = root.getAttribute('data-catalog');          // '/shtabelery/'
  var PAGE = parseInt(root.getAttribute('data-page-size'), 10) || 24;

  /* Какие характеристики показывать в плитке — говорит сервер.

     Раньше скрипт выбирал их сам: брал три самых заполненных по всему
     каталогу. Сервер же считает свои — по товарам этого раздела. Наборы
     расходились, и после первого движения фильтра плитки молча меняли
     подписи: у товара вместо «Высота подъёма» появлялась «Длина вил».
     Со стороны это выглядит как подмена товара. */
  var WANT = (root.getAttribute('data-fields') || '').split(',').filter(Boolean);

  var grid = document.getElementById('cards');
  var pager = document.querySelector('.pager');
  var found = document.getElementById('found');
  var empty = document.getElementById('nothing');
  var moreWrap = document.getElementById('more');
  var moreBtn = moreWrap ? moreWrap.querySelector('button') : null;
  var sort = document.getElementById('sort');
  var reset = document.getElementById('reset');
  var panel = document.getElementById('filters');
  if (!grid) return;

  var inputs = [].slice.call(document.querySelectorAll('[data-filter]'));
  var index = null;      // весь раздел, подгружается по первому действию
  var fields = [];
  var filtered = [];
  var shown = PAGE;
  var nf = new Intl.NumberFormat('ru-RU');

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function num(v) {
    if (v == null) return null;
    var n = parseFloat(String(v).replace(/\s| /g, '').replace(',', '.'));
    return isFinite(n) ? n : null;
  }

  function load() {
    if (index) return Promise.resolve(index);
    // Индекс собирается из базы на каждый запрос: правка цены в
    // админке должна доезжать и до подбора, а не только до карточки.
    return fetch('/products.json')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        fields = d.fields || [];
        // Оставляем ровно те, что назвал сервер, и в его порядке.
        if (WANT.length) {
          fields = WANT.map(function (k) {
            for (var i = 0; i < (d.fields || []).length; i++) {
              if (d.fields[i].key === k) return d.fields[i];
            }
            return null;
          }).filter(Boolean);
        }
        index = Object.keys(d.products)
          .filter(function (id) { return String(d.products[id].file).indexOf(PATH) === 0; })
          .map(function (id) { var p = d.products[id]; p.id = id; return p; });
        filtered = index.slice();
        return index;
      });
  }

  /* Разметка карточки повторяет чанк _card.php: скрипты корзины,
     сравнения и избранного цепляются к data-атрибутам, и расхождение
     сломало бы их молча. */
  function card(p, position) {
    var short = fields.map(function (f) {
      var v = p.specs[f.key];
      return v ? v + (f.unit ? ' ' + f.unit : '') : null;
    }).filter(Boolean).join(' · ');

    var specs = fields.map(function (f) {
      var v = p.specs[f.key];
      return v ? '<li>' + esc(f.title) + ': <b>' + esc(v) + (f.unit ? ' ' + esc(f.unit) : '') + '</b></li>' : '';
    }).join('');

    var jpg = String(p.img).replace(/\.webp$/, '.jpg');

    /* Акция. Серверная плитка _card.php рисует плашку и зачёркнутую
       прежнюю цену, а подбор — нет, и после первого движения фильтра
       скидка с карточек пропадала: цена оставалась верной, но выгода
       переставала быть видна.

       Поля берутся из products.json: promo — сервер уже посчитал
       Promo::active() (строка = дата окончания, «31.08»), old — цена до
       скидки. Пока сервер их не отдаёт, оба блока просто не рисуются, и
       плитка выглядит как прежде. */
    var onSale = !!p.promo && p.old > p.price;
    var badge = p.promo
      ? '<p class="card__badge">Акция' + (typeof p.promo === 'string' ? ' до ' + esc(p.promo) : '') + '</p>'
      : '';

    return '<li class="card" itemprop="itemListElement" itemscope itemtype="https://schema.org/Product"' +
      ' data-product-id="' + esc(p.id) + '" data-url="' + esc(p.file) + '"' +
      ' data-name="' + esc(p.name) + '" data-image="' + esc(jpg) + '"' +
      ' data-specs="' + esc(short) + '"' + (p.price ? ' data-price="' + p.price + '"' : '') + '>' +
      '<meta itemprop="position" content="' + position + '">' +
      badge +
      '<div class="card__media">' +
        '<div class="card__tools">' +
          '<button class="card__tool card__tool--fav" type="button" data-action="favorite" aria-pressed="false" title="Отложить в избранное" aria-label="Отложить в избранное">' +
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5 4.4 13a4.6 4.6 0 0 1 6.5-6.5l1.1 1.1 1.1-1.1A4.6 4.6 0 0 1 19.6 13Z"/></svg>' +
          '</button>' +
          '<button class="card__tool card__tool--cmp" type="button" data-action="compare" aria-pressed="false" title="Добавить к сравнению" aria-label="Добавить к сравнению">' +
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V9M12 20V4M19 20v-7"/></svg>' +
          '</button>' +
        '</div>' +
        '<a class="card__imglink" href="' + esc(p.file) + '" tabindex="-1" aria-hidden="true">' +
          '<picture>' +
            '<source srcset="' + esc(p.img) + '" type="image/webp">' +
            '<img src="' + esc(jpg) + '" alt="' + esc(p.name) + '" width="320" height="240" loading="lazy">' +
          '</picture>' +
        '</a>' +
      '</div>' +
      '<div class="card__body">' +
        '<h3 class="card__title"><a href="' + esc(p.file) + '" itemprop="url">' +
          '<span itemprop="name">' + esc(p.name) + '</span></a></h3>' +
        '<ul class="card__specs">' + specs + '</ul>' +
        '<p class="card__price">' + (p.price
          ? '<span class="card__price-cap">Цена <span>(с&nbsp;НДС)</span></span>'
            + '<span class="card__price-val' + (onSale ? ' card__price-val--promo' : '') + '">'
            + (onSale ? '<s>' + nf.format(p.old) + '&nbsp;руб.</s>' : '')
            + '<b>' + nf.format(p.price) + '&nbsp;руб.</b></span>'
          : '<span class="card__price-cap">Цена</span>'
            + '<span class="card__price-val card__price-val--ask">по запросу</span>') + '</p>' +
        '<div class="card__actions">' +
          '<button class="btn btn--primary" type="button" data-action="request">Оставить заявку</button>' +
          '<button class="btn btn--ghost" type="button" data-action="add-to-cart">В корзину</button>' +
        '</div>' +
      '</div>' +
    '</li>';
  }

  function render() {
    var slice = filtered.slice(0, shown);
    grid.innerHTML = slice.map(function (p, i) { return card(p, i + 1); }).join('');

    if (found) found.textContent = filtered.length;
    if (empty) empty.hidden = filtered.length > 0;
    if (moreWrap) {
      moreWrap.hidden = slice.length >= filtered.length;
      var left = moreWrap.querySelector('[data-more-count]');
      if (left) left.textContent = Math.min(PAGE, filtered.length - slice.length);
    }
    // Постраничная навигация с сервера относится к неотфильтрованному
    // разделу — с этого момента она врёт, убираем.
    if (pager) pager.hidden = true;

    // Отметки «в сравнении» и «в избранном» на дорисованных карточках.
    // Раньше здесь звали onChange с пустой функцией — она добавляла
    // слушателя и ничего не перерисовывала: кнопка на уже отложенном
    // товаре выглядела неотмеченной, и нажатие убирало его из списка.
    if (window.Compare && window.Compare.repaint) window.Compare.repaint();
    if (window.Favorites && window.Favorites.repaint) window.Favorites.repaint();
  }

  function collect() {
    var ranges = {}, sets = {}, used = 0;
    inputs.forEach(function (el) {
      var key = el.getAttribute('data-filter');
      if (el.type === 'checkbox') {
        if (!el.checked) return;
        (sets[key] = sets[key] || []).push(el.value);
        used++;
      } else if (el.value !== '') {
        (ranges[key] = ranges[key] || {})[el.getAttribute('data-bound')] = parseFloat(el.value);
        used++;
      }
    });
    return { ranges: ranges, sets: sets, used: used };
  }

  function sortInPlace(mode) {
    if (mode === 'price-asc') filtered.sort(function (a, b) { return (a.price || 1e12) - (b.price || 1e12); });
    if (mode === 'price-desc') filtered.sort(function (a, b) { return (b.price || 0) - (a.price || 0); });
    if (mode === 'name') filtered.sort(function (a, b) { return a.name.localeCompare(b.name, 'ru'); });
  }

  function apply() {
    load().then(function () {
      var f = collect();

      filtered = index.filter(function (p) {
        for (var key in f.ranges) {
          // Товар без значения в диапазон не попадает: иначе фильтр
          // «до 1000 кг» выдавал бы и то, у чего вес не указан.
          var v = num(p.specs[key]);
          if (v === null) return false;
          var r = f.ranges[key];
          if (r.min !== undefined && !(v >= r.min)) return false;
          if (r.max !== undefined && !(v <= r.max)) return false;
        }
        for (var k in f.sets) {
          if (f.sets[k].indexOf(p.specs[k]) === -1) return false;
        }
        return true;
      });

      sortInPlace(sort ? sort.value : 'default');
      shown = PAGE;
      if (reset) reset.disabled = f.used === 0;
      render();
    });
  }

  inputs.forEach(function (el) {
    el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', apply);
  });

  if (sort) sort.addEventListener('change', apply);

  if (reset) {
    reset.addEventListener('click', function () {
      inputs.forEach(function (el) {
        if (el.type === 'checkbox') el.checked = false; else el.value = '';
      });
      apply();
    });
  }

  if (moreBtn) {
    moreBtn.addEventListener('click', function () {
      shown += PAGE;
      render();
    });
  }

  var select = document.getElementById('section-select');
  if (select) {
    select.addEventListener('change', function () {
      if (select.value) window.location.href = select.value;
    });
  }

  // Панель на узком экране открывается кнопкой и закрывается крестиком
  var open = document.getElementById('open');
  var close = document.getElementById('close');
  if (open && panel) open.addEventListener('click', function () { panel.classList.add('filters--open'); });
  if (close && panel) close.addEventListener('click', function () { panel.classList.remove('filters--open'); });

})(window, document);
