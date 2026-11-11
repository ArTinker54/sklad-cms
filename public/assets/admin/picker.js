/* Выбор товаров в тег (05.10.2026): поиск по названию и артикулу и счётчик
   отмеченных. Список — весь каталог по разделам, у Партнёра это больше тысячи
   строк: без поиска нужную модель не найти.

   Без скрипта список тоже работает: разделы раскрываются, галочки
   отправляются формой. */
(function (document) {
  var box = document.querySelector('[data-picker]');
  if (!box) return;

  var search = box.querySelector('[data-picker-search]');
  var count = box.querySelector('[data-picker-count]');
  var items = Array.prototype.slice.call(box.querySelectorAll('[data-picker-item]'));
  var groups = Array.prototype.slice.call(box.querySelectorAll('.tagpick__group'));
  var opened = groups.map(function (g) { return g.open; });
  var hint = count ? count.textContent : '';

  function recount() {
    if (!count) return;
    var n = box.querySelectorAll('[data-picker-item] input:checked').length;
    count.textContent = n
      ? 'Отмечено: ' + n + '. Встанут в конец списка после «Сохранить».'
      : hint;
  }

  function filter() {
    var q = (search.value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    items.forEach(function (item) {
      item.hidden = q !== '' && (item.getAttribute('data-text') || '').indexOf(q) === -1;
    });
    groups.forEach(function (g, i) {
      var any = g.querySelector('[data-picker-item]:not([hidden])');
      // С запросом раскрываем всё, где нашлось, и прячем пустые разделы;
      // без запроса — как было до поиска.
      g.hidden = q !== '' && !any;
      g.open = q !== '' ? !!any : opened[i];
    });
  }

  if (search) {
    search.addEventListener('input', filter);
    // Enter в поиске не должен отправлять всю карточку тега.
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
    });
  }
  box.addEventListener('change', recount);
  recount();
})(document);
