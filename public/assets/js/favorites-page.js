'use strict';
/* Избранное: список отложенного.

   Вынесено из текста страницы в файл. Пока код лежал внутри
   содержимого, его нельзя было отделить от текста: менеджер правил
   абзац и мог снести работающую часть, а строгая политика
   безопасности такие вставки запрещает целиком. */

document.addEventListener("DOMContentLoaded",function(){
(function () {
  var nf = new Intl.NumberFormat('ru-RU');
  var view = document.getElementById('fav-view');
  var empty = document.getElementById('fav-empty');
  var grid = document.getElementById('fav-grid');
  var data = null;

  function plural(n, one, few, many) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return one;
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few;
    return many;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function render() {
    var ids = window.Favorites.all().filter(function (id) { return data.products[id]; });

    view.hidden = !ids.length;
    empty.hidden = !!ids.length;
    // Было «1 моделей»: число подставлялось к одной форме слова.
    document.getElementById('fav-count').textContent = ids.length
      ? ids.length + ' ' + plural(ids.length, 'модель', 'модели', 'моделей')
      : '';
    if (!ids.length) { grid.innerHTML = ''; return; }
    document.getElementById('fav-n').textContent = ids.length;

    grid.innerHTML = ids.map(function (id) {
      var p = data.products[id];
      // три первых заполненных характеристики — как в каталоге
      var specs = data.fields.filter(function (f) { return p.specs[f.key]; }).slice(0, 3)
        .map(function (f) {
          return '<li>' + esc(f.title) + ': <b>' + esc(p.specs[f.key]) + '</b>' + (f.unit ? ' ' + esc(f.unit) : '') + '</li>';
        }).join('');
      // Раздел — строкой характеристик, а не внутри цены: .card__price
      // размечен как «подпись слева, сумма справа», третий элемент в нём
      // ломает раскладку.
      var section = p.section ? '<li>' + esc(p.section) + '</li>' : '';

      return '<li class="card" data-product-id="' + esc(id) + '" data-name="' + esc(p.name) + '"' +
             ' data-url="' + esc(p.file) + '" data-image="' + esc(p.img) + '"' +
             ' data-specs="' + esc(p.section) + '"' +
             (p.price ? ' data-price="' + p.price + '"' : '') + '>' +
        '<div class="card__media">' +
          '<button class="btn-fav" type="button" data-action="favorite" aria-pressed="true" aria-label="Убрать из избранного">' +
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5 4.4 13a4.6 4.6 0 0 1 6.5-6.5l1.1 1.1 1.1-1.1A4.6 4.6 0 0 1 19.6 13Z"/></svg>' +
          '</button>' +
          '<a href="' + esc(p.file) + '"><img src="' + esc(p.img) + '" alt="' + esc(p.name) + '" width="320" height="240" loading="lazy"></a>' +
        '</div>' +
        '<div class="card__body">' +
          '<h3 class="card__title"><a href="' + esc(p.file) + '">' + esc(p.name) + '</a></h3>' +
          '<ul class="card__specs">' + section + specs + '</ul>' +
          // Разметка цены — та же, что у серверной плитки _card.php и у
          // catalog.js: вся типографика висит на card__price-cap и
          // card__price-val, у самого card__price её нет.
          '<p class="card__price">' + (p.price
            ? '<span class="card__price-cap">Цена <span>(с&nbsp;НДС)</span></span>'
              + '<span class="card__price-val"><b>' + nf.format(p.price) + '&nbsp;руб.</b></span>'
            : '<span class="card__price-cap">Цена</span>'
              + '<span class="card__price-val card__price-val--ask">по запросу</span>') + '</p>' +
          '<button class="btn btn--primary card__btn" type="button" data-action="add-to-cart">В корзину</button>' +
          '<button class="btn-cmp" type="button" data-action="compare" aria-pressed="false">' +
            '<span data-compare-label>Сравнить</span></button>' +
        '</div>' +
      '</li>';
    }).join('');

    // Отметки «в сравнении» на дорисованных карточках. Раньше здесь звали
    // onChange с пустой функцией — она лишь добавляла ещё одного слушателя
    // и ничего не перерисовывала: кнопка у товара, уже отложенного в
    // сравнение, оставалась подписана «Сравнить», и нажатие его УБИРАЛО.
    if (window.Compare && window.Compare.repaint) window.Compare.repaint();
  }

  document.getElementById('fav-clear').addEventListener('click', function () { window.Favorites.clear(); });

  fetch('/products.json')
    .then(function (r) { return r.json(); })
    .then(function (d) {
      data = d;
      // Заодно чистим все три списка от того, чего в каталоге нет (см. Compare.prune).
      [window.Cart, window.Compare, window.Favorites].forEach(function (m) { if (m && m.prune) m.prune(d.products); });
      window.Favorites.onChange(render);
    })
    .catch(function () {
      empty.hidden = false;
      empty.querySelector('h2').textContent = 'Не удалось загрузить каталог';
      empty.querySelector('p').textContent = 'Обновите страницу — если не поможет, напишите нам.';
    });
})();
});
