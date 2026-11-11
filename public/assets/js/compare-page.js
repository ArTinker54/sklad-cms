'use strict';
/* Сравнение: таблица характеристик по отложенным моделям.

   Вынесено из текста страницы в файл. Пока код лежал внутри
   содержимого, его нельзя было отделить от текста: менеджер правил
   абзац и мог снести работающую часть, а строгая политика
   безопасности такие вставки запрещает целиком. */

document.addEventListener("DOMContentLoaded",function(){
(function () {
  var nf = new Intl.NumberFormat('ru-RU');
  var view = document.getElementById('cmp-view');
  var empty = document.getElementById('cmp-empty');
  var body = document.getElementById('cmp-body');
  var table = document.getElementById('cmp-table');
  var data = null;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function render() {
    var ids = window.Compare.all().filter(function (id) { return data.products[id]; });
    var items = ids.map(function (id) { return { id: id, p: data.products[id] }; });

    view.hidden = !items.length;
    empty.hidden = !!items.length;
    document.getElementById('cmp-count').textContent =
      items.length ? items.length + ' из ' + window.Compare.LIMIT : '';
    if (!items.length) return;

    var rows = [];

    // шапка: фото, название, цена, действия
    rows.push('<tr class="cmp-head">' +
      '<th scope="row">Модель</th>' +
      items.map(function (it) {
        return '<td>' +
          // не lazy: колонок максимум четыре и все на первом экране
          '<a href="' + esc(it.p.file) + '"><img src="' + esc(it.p.img) + '" alt="' + esc(it.p.name) + '"></a>' +
          '<a class="cmp-head__name" href="' + esc(it.p.file) + '">' + esc(it.p.name) + '</a>' +
          '<span class="cmp-head__price">' +
            (it.p.price ? nf.format(it.p.price) + ' ₽' : 'По запросу') +
            '<small>' + esc(it.p.section) + '</small>' +
          '</span>' +
          '<button class="btn btn--primary" type="button" data-add="' + esc(it.id) + '">В корзину</button>' +
          '<button class="cmp-head__del" type="button" data-del="' + esc(it.id) + '">убрать</button>' +
        '</td>';
      }).join('') + '</tr>');

    // характеристики: только те, что заполнены хотя бы у одного
    var diffCount = 0;
    data.fields.forEach(function (f) {
      var vals = items.map(function (it) { return it.p.specs[f.key] || null; });
      if (!vals.some(Boolean)) return;

      var present = vals.filter(Boolean);
      var isDiff = present.length !== vals.length ||
                   present.some(function (v) { return v !== present[0]; });
      if (isDiff) diffCount++;

      rows.push('<tr data-diff="' + (isDiff ? 1 : 0) + '">' +
        '<th scope="row">' + esc(f.title) + (f.unit ? ', ' + esc(f.unit) : '') + '</th>' +
        vals.map(function (v) { return '<td>' + (v ? esc(v) : '—') + '</td>'; }).join('') +
      '</tr>');
    });

    body.innerHTML = rows.join('');
    document.getElementById('diff-hint').textContent =
      diffCount ? 'различий: ' + diffCount : 'характеристики совпадают полностью';
  }

  body.addEventListener('click', function (e) {
    var del = e.target.closest('[data-del]');
    if (del) { window.Compare.remove(del.dataset.del); return; }

    var add = e.target.closest('[data-add]');
    if (add) {
      var id = add.dataset.add, p = data.products[id];
      window.Cart.add({ id: id, name: p.name, url: '' + p.file, img: p.img, price: p.price, specs: p.section });
      window.Cart.goal('add_to_cart', { name: p.name, source: 'compare' });
      var was = add.textContent;
      add.textContent = 'Добавлено ✓';
      add.disabled = true;
      window.setTimeout(function () { add.textContent = was; add.disabled = false; }, 1400);
    }
  });

  document.getElementById('only-diff').addEventListener('change', function (e) {
    table.classList.toggle('only-diff', e.target.checked);
  });

  document.getElementById('cmp-clear').addEventListener('click', function () { window.Compare.clear(); });

  fetch('/products.json')
    .then(function (r) { return r.json(); })
    .then(function (d) {
      data = d;
      // Каталог уже здесь — заодно чистим все три списка от того, чего в
      // нём нет (см. Compare.prune): иначе в шапке «4», а сравнивать нечего.
      [window.Cart, window.Compare, window.Favorites].forEach(function (m) { if (m && m.prune) m.prune(d.products); });
      window.Compare.onChange(render);
    })
    .catch(function () {
      empty.hidden = false;
      empty.querySelector('h2').textContent = 'Не удалось загрузить каталог';
      empty.querySelector('p').textContent = 'Обновите страницу — если не поможет, напишите нам.';
    });
})();
});
