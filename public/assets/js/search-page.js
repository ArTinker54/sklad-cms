'use strict';
/* Страница поиска: выдача по индексу products.json.

   Вынесено из текста страницы в файл. Пока код лежал внутри
   содержимого, его нельзя было отделить от текста: менеджер правил
   абзац и мог снести работающую часть, а строгая политика
   безопасности такие вставки запрещает целиком. */

document.addEventListener("DOMContentLoaded",function(){
(function () {
  var nf = new Intl.NumberFormat('ru-RU');
  var form = document.getElementById('sform');
  var input = document.getElementById('s');
  var results = document.getElementById('results');
  var nothing = document.getElementById('nothing');
  var note = document.getElementById('found-note');

  function plural(n, one, few, many) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return one;
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few;
    return many;
  }

  function run(q) {
    var S = window.BrandSearch;
    var items = S.search(q, 60);

    note.textContent = q ? 'по запросу «' + q + '» — ' + items.length + ' ' +
      plural(items.length, 'модель', 'модели', 'моделей') : '';
    nothing.hidden = !(q && !items.length);

    results.innerHTML = items.map(function (it) {
      return '<li class="card" data-product-id="' + S.esc(it.id) + '" data-name="' + S.esc(it.name) + '"' +
             ' data-url="' + S.esc(it.file) + '" data-image="' + S.esc(it.img) + '"' +
             ' data-specs="' + S.esc(it.section) + '"' +
             (it.price ? ' data-price="' + it.price + '"' : '') + '>' +
        '<div class="card__media">' +
          '<a href="' + S.esc(it.file) + '"><img src="' + S.esc(it.img) + '" alt="' + S.esc(it.name) + '" width="320" height="240" loading="lazy"></a>' +
        '</div>' +
        '<div class="card__body">' +
          '<h3 class="card__title"><a href="' + S.esc(it.file) + '">' + S.highlight(it.name, q) + '</a></h3>' +
          '<ul class="card__specs"><li>' + S.esc(it.section) + '</li></ul>' +
          // Разметка цены — та же, что у серверной плитки _card.php и у
          // catalog.js: вся типографика висит на card__price-cap и
          // card__price-val, у самого card__price её нет.
          '<p class="card__price">' + (it.price
            ? '<span class="card__price-cap">Цена <span>(с&nbsp;НДС)</span></span>'
              + '<span class="card__price-val"><b>' + nf.format(it.price) + '&nbsp;руб.</b></span>'
            : '<span class="card__price-cap">Цена</span>'
              + '<span class="card__price-val card__price-val--ask">по запросу</span>') + '</p>' +
          '<button class="btn btn--primary card__btn" type="button" data-action="add-to-cart">В корзину</button>' +
          '<button class="btn-cmp" type="button" data-action="compare" aria-pressed="false"><span data-compare-label>Сравнить</span></button>' +
        '</div>' +
      '</li>';
    }).join('');

    // Отметки «в сравнении» и «в избранном» на дорисованных карточках:
    // в разметке выше aria-pressed зашит как "false", и без перерисовки
    // нажатие на уже отложенном товаре УБИРАЛО его из списка.
    if (window.Compare && window.Compare.repaint) window.Compare.repaint();
    if (window.Favorites && window.Favorites.repaint) window.Favorites.repaint();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var q = input.value.trim();
    history.replaceState(null, '', q ? '?s=' + encodeURIComponent(q) : location.pathname);
    run(q);
  });

  var q = new URLSearchParams(location.search).get('s') || '';
  input.value = q;
  window.BrandSearch.load().then(function () { if (q) run(q); input.focus(); });
})();
});
