'use strict';
/* Title и Description в карточках админки: счётчик длины и предпросмотр
   строки поиска меняются на ходу, пока человек печатает.

   Разметка и серверная часть того же самого — templates/admin/_seo.php.
   Без скрипта блок работает: счётчик и предпросмотр просто показывают
   значения на момент открытия карточки. */

(function (document) {
  // Предпросмотр — как на основном адресе, где город — Москва.
  var CITY = { '{город}': 'Москва', '{городе}': 'Москве', '{города}': 'Москвы' };

  function fill(text) {
    return text.replace(/\{город[еа]?\}/g, function (m) { return CITY[m] || m; });
  }

  function bind(box, name) {
    var field = box.querySelector('[name="' + name + '"]');
    var count = box.querySelector('[data-seo-count="' + name + '"]');
    var show = box.querySelector('[data-seo-show="' + name + '"]');
    if (!field) return;

    function update() {
      var value = field.value.trim();
      if (count) {
        var limit = Number(count.getAttribute('data-limit')) || 0;
        count.textContent = value.length + ' из ' + limit;
        count.classList.toggle('counter--over', limit > 0 && value.length > limit);
      }
      if (show) {
        var text = value || show.getAttribute('data-auto') || '';
        show.textContent = text ? fill(text) : (show.getAttribute('data-empty') || '');
        show.classList.toggle('seo__desc--empty', !text && name === 'meta_description');
      }
    }

    field.addEventListener('input', update);
    update();
  }

  var boxes = document.querySelectorAll('[data-seo]');
  for (var i = 0; i < boxes.length; i++) {
    bind(boxes[i], 'meta_title');
    bind(boxes[i], 'meta_description');
  }
})(document);
