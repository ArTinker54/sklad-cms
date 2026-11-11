'use strict';
/* Меню на телефоне: три полоски в шапке и панель во весь экран.

   Разметка панели лежит в конце страницы (layout.php) и без скрипта
   работает как обычный блок: полоски — ссылка «#mobmenu», и по ней человек
   попадает к разделам каталога и поиску. Здесь эта же ссылка становится
   раскрывашкой.

   Замок прокрутки держим классом на <html>, а не записью в
   document.body.style.overflow. Ту же запись правят окно заявки
   (request.js) и окно городов (inline-скрипт в layout.php), а заявка
   открывается ИЗ панели: кто закрылся вторым, тот и снимал общий замок —
   страница под открытым окном снова прокручивалась. Класс и инлайновый
   стиль живут порознь и друг друга не затирают. */

(function (window, document) {

  /* Та же граница, что и у стилей шапки в main.css, и спрашиваем её у
     самого браузера, а не сравниваем innerWidth с числом. Два повода:
     innerWidth бывает дробным (масштаб страницы, эмуляция устройства) и
     тогда расходится с медиазапросом на полпикселя — полоски есть, панели
     нет; и событие resize приходит не всегда, а change у медиазапроса
     приходит ровно тогда, когда переключились стили. */
  // 900, а не 767: полоски видны везде, где спрятано верхнее меню разделов
  // (main.css, блок «до 900»), — в том числе на телефоне горизонтально.
  var PHONE = window.matchMedia
    ? window.matchMedia('(max-width: 900px)')
    : { matches: true, addListener: function () {} };

  function init() {
    var panel = document.getElementById('mobmenu');
    var burger = document.querySelector('.header__burger');
    if (!panel || !burger) return;

    var win = panel.querySelector('.mobmenu__win') || panel;
    var closeBtn = panel.querySelector('.mobmenu__close');
    var lastFocus = null;

    // Диалогом панель объявляем отсюда, а не в разметке: без скрипта она
    // не диалог, а кусок страницы, и врать об этом диктору нельзя.
    win.setAttribute('role', 'dialog');
    win.setAttribute('aria-modal', 'true');
    win.setAttribute('aria-label', 'Меню сайта');
    burger.setAttribute('aria-haspopup', 'dialog');

    function isOpen() { return panel.getAttribute('data-open') === 'true'; }

    /* Что умеет принять фокус прямо сейчас. Перебираем каждый раз заново:
       избранное и сравнение появляются в списке только когда в них что-то
       положили (атрибут hidden), а список, снятый один раз при загрузке,
       увёл бы Tab на спрятанную ссылку. */
    function focusable() {
      var all = win.querySelectorAll('a[href], button:not([disabled]), input, select, textarea');
      var out = [];
      for (var i = 0; i < all.length; i++) {
        if (all[i].offsetWidth || all[i].offsetHeight || all[i].getClientRects().length) out.push(all[i]);
      }
      return out;
    }

    function open() {
      lastFocus = document.activeElement;
      // Панель открывают заново, а не продолжают с того места, где
      // закрыли: у Tisel список разделов длиннее экрана, и тот, кто
      // долистал до конца, во второй раз попадал бы в середину списка —
      // без «Оставить заявку» и без заголовка «Каталог» на экране.
      var body = panel.querySelector('.mobmenu__body');
      if (body) body.scrollTop = 0;
      panel.setAttribute('data-open', 'true');
      burger.setAttribute('aria-expanded', 'true');
      document.documentElement.classList.add('is-menu-open');
      var first = closeBtn || focusable()[0];
      if (first) first.focus();
    }

    /* returnFocus = false, когда панель закрывается ради другого окна
       (окно заявки): фокус туда уже перевёл тот, кто это окно
       открыл, и возврат на полоски выдернул бы человека обратно в шапку. */
    function close(returnFocus) {
      if (!isOpen()) return;
      panel.setAttribute('data-open', 'false');
      burger.setAttribute('aria-expanded', 'false');
      document.documentElement.classList.remove('is-menu-open');
      if (returnFocus !== false && lastFocus && lastFocus.focus) lastFocus.focus();
    }

    burger.addEventListener('click', function (e) {
      // На компьютере полосок нет вовсе, но правило одно: если стили шапки
      // сейчас не мобильные, отдаём ссылке её обычное поведение — переход к
      // панели внизу страницы. Молча не сработавшая ссылка хуже.
      if (!PHONE.matches) return;
      e.preventDefault();
      if (isOpen()) close(); else open();
    });

    panel.addEventListener('click', function (e) {
      if (!isOpen()) return;

      // Крестик и подложка. Подложка нажимается только там, где панель не
      // во весь экран: на узком телефоне она целиком под панелью.
      if (e.target.closest('[data-menu-close]')) { close(); return; }

      // Кнопки, которые открывают своё окно поверх панели: заявка и выбор
      // города (город вернулся в панель 25.09 — она теперь как у storefront).
      if (e.target.closest('[data-city-open], [data-action="request"]')) {
        // Окно заявки запоминает, куда вернуть фокус, по
        // document.activeElement — но к тому мигу нажатая кнопка уже в
        // спрятанной панели, и возвращаться ему некуда: фокус уезжает в
        // начало страницы. Переводим его на полоски заранее, пока панель
        // ещё на экране.
        if (burger && burger.focus) burger.focus({ preventScroll: true });
        close(false);
        return;
      }

      // Переход по ссылке. Страница перезагрузится сама, но до перезагрузки
      // проходит секунда-другая, и всё это время открытая панель выглядит
      // как «нажатие не сработало». Якорь на этой же странице не
      // перезагружает ничего — там закрыть панель обязательно.
      if (e.target.closest('a[href]')) close(false);
    });

    document.addEventListener('keydown', function (e) {
      if (!isOpen()) return;
      if (e.key === 'Escape') { close(); return; }
      if (e.key !== 'Tab') return;
      // Пока панель открыта, Tab ходит по кругу внутри неё — так же, как в
      // окне городов и в окне заявки.
      var f = focusable();
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    /* Сторож на поворот экрана и на растягивание окна. Панель показывает
       только мобильный блок стилей: перешагнув 768, она пропала бы с
       экрана, а замок прокрутки и перехваченный фокус остались бы —
       страница на компьютере встала бы намертво. Слушаем сам медиазапрос:
       он срабатывает ровно в тот миг, когда переключились стили.
       addListener — для Safari до 14, там addEventListener у медиазапроса
       ещё не было. */
    var guard = function () { if (!PHONE.matches) close(false); };
    if (PHONE.addEventListener) PHONE.addEventListener('change', guard);
    else if (PHONE.addListener) PHONE.addListener(guard);
    // И по resize с поворотом заодно: событие change у медиазапроса
    // приходит не в каждой оболочке (в нашем же смотровом окне не пришло
    // ни одно из трёх). Замок прокрутки от этого не зависит — он снимается
    // самим медиазапросом в main.css, — но панель лучше закрыть честно.
    window.addEventListener('resize', guard);
    window.addEventListener('orientationchange', guard);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})(window, document);
