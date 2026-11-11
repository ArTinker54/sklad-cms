'use strict';
/* Заглушка вместо не загрузившегося снимка.

   Раньше это делал атрибут onerror прямо на каждой картинке. Работало,
   но такие вставки запрещает строгая политика безопасности: браузер не
   отличает наш маленький обработчик от чужого, пролезшего в текст
   страницы. Правило одно на всех, поэтому обработчик переехал сюда.

   Слушаем на этапе перехвата: событие error у картинок не всплывает, и
   обычный обработчик на документе его не увидит. */

(function (window, document) {

  var PLACEHOLDER = '/assets/img/placeholder.svg';

  document.addEventListener('error', function (e) {
    var el = e.target;
    if (!el || el.tagName !== 'IMG') return;
    // Заглушка тоже может не загрузиться — второй раз не подставляем,
    // иначе получится бесконечный круг.
    if (el.dataset.fallbackDone) return;
    el.dataset.fallbackDone = '1';

    // Картинка внутри <picture>: пока жив <source>, браузер возьмёт его,
    // и подмена src ничего не даст. Убираем источники и только потом
    // ставим заглушку.
    var picture = el.parentNode;
    if (picture && picture.tagName === 'PICTURE') {
      var sources = picture.querySelectorAll('source');
      for (var i = 0; i < sources.length; i++) {
        sources[i].parentNode.removeChild(sources[i]);
      }
    }

    el.src = PLACEHOLDER;
  }, true);

})(window, document);
