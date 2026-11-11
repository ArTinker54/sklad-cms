'use strict';
/* Карточка товара: вкладки и переключение снимков.

   Вкладки — не декорация: характеристики, описание и условия доставки
   на одном экране не помещаются, а прокручивать до нужного покупателю
   приходится каждый раз. Разметка при этом остаётся целиком в странице,
   поэтому поисковик видит всё три раздела, а не только открытый. */

(function (window, document) {

  /* ---------- вкладки ---------- */
  function initTabs() {
    var list = document.querySelector('.tabs__list');
    if (!list) return;

    var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));

    function select(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on);
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus !== false) tab.focus();
    }

    list.addEventListener('click', function (e) {
      var t = e.target.closest('[role="tab"]');
      if (t) select(t);
    });

    list.addEventListener('keydown', function (e) {
      var i = tabs.indexOf(document.activeElement);
      if (i < 0) return;
      if (e.key === 'ArrowRight') { e.preventDefault(); select(tabs[(i + 1) % tabs.length]); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); select(tabs[(i - 1 + tabs.length) % tabs.length]); }
    });

    /* Ссылка на панель («Смотреть все характеристики») сначала открывает
       её вкладку. Закрытая панель скрыта, и переход по якорю молча не
       срабатывал: человек нажимал, а страница стояла на месте. Фокус не
       забираем и прокрутку не трогаем — её делает браузер, плавно по CSS. */
    document.addEventListener('click', function (e) {
      var link = e.target.closest('a[href^="#"]');
      if (!link) return;
      var id = link.getAttribute('href').slice(1);
      if (!id) return;
      for (var i = 0; i < tabs.length; i++) {
        if (tabs[i].getAttribute('aria-controls') === id) {
          select(tabs[i], false);
          return;
        }
      }
    });
  }

  /* ---------- снимки ----------

     Скрипт один на обе темы, а разметка у них своя: там .gallery и
     .gallery__thumb, тут .shot и .shot__thumb. Раньше искали только
     первые имена — и в теме TOR лента миниатюр была рядом кнопок,
     которые не делали ничего: снимок не менялся, и покупатель видел
     товар с одного ракурса.

     Поэтому цепляемся не за имена классов, а за то, что у кнопки есть
     адрес полного снимка. Такая кнопка на странице ровно одного рода,
     и новая тема заработает без правки скрипта. */
  function initGallery() {
    var thumbs = Array.prototype.slice.call(document.querySelectorAll('figure [data-full]'));
    if (!thumbs.length) return;

    var figure = thumbs[0].closest('figure');
    // Крупный снимок — единственный <picture> вне ленты миниатюр.
    var picture = figure.querySelector('picture');
    var img = picture ? picture.querySelector('img') : null;
    var source = picture ? picture.querySelector('source') : null;
    if (!img) return;

    // Отметка выбранной миниатюры называется в темах по-разному.
    // Берём ту, что уже стоит на первой, — так тема сама решает, как
    // называется её состояние, и скрипт не хранит их список.
    var ON = /(^|\s)(is-[\w-]+)/.exec(thumbs[0].className);
    ON = ON ? ON[2] : 'is-active';

    thumbs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        img.src = btn.getAttribute('data-full');
        // Источник webp меняем вместе с картинкой: иначе браузер,
        // понимающий webp, покажет прежний снимок и переключение
        // выглядит как поломка.
        if (source) source.srcset = btn.getAttribute('data-webp');
        thumbs.forEach(function (t) {
          t.classList.toggle(ON, t === btn);
          // Рамку выбранной миниатюры стили красят по aria-current, а
          // разметка и скрипт до сих пор ставили только класс — отметка
          // не загоралась ни на одном сайте сети. Ставим оба: класс
          // оставляем теме (у tor он свой), aria-current заодно
          // объявляет выбор читалке.
          if (t === btn) { t.setAttribute('aria-current', 'true'); }
          else { t.removeAttribute('aria-current'); }
        });
      });
    });
  }

  /* ---------- нижняя панель с заявкой ---------- */
  /* Панель внизу экрана нужна, когда кнопки заявки ушли из вида — пока
     листаешь характеристики. Если же на экране уже есть своя «Оставить
     заявку» (в шапке, в блоке цены, в карточках ниже) или «Запросить КП»
     из блока скидки, панель её только дублирует: две жёлтые кнопки подряд. Поэтому прячем панель, пока
     видна хоть одна другая кнопка заявки. Стиль скрытия — .stickybar--away
     в main.css. Без IntersectionObserver панель просто остаётся как была. */
  function initStickybar() {
    var bar = document.querySelector('.stickybar');
    if (!bar || !('IntersectionObserver' in window)) return;

    var others = Array.prototype.filter.call(
      document.querySelectorAll('[data-action="request"], [data-action="discount"]'),
      function (b) { return !bar.contains(b); }
    );
    if (!others.length) return;

    var seen = new Set();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) seen.add(e.target); else seen.delete(e.target);
      });
      bar.classList.toggle('stickybar--away', seen.size > 0);
    });
    others.forEach(function (b) { io.observe(b); });
  }

  function init() { initTabs(); initGallery(); initStickybar(); }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})(window, document);
