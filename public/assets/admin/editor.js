'use strict';
/* Простой редактор текста для админки.

   Свой, а не готовая библиотека, по двум причинам. Первая: подключать
   чужой CDN нельзя — сайт живёт на российском хостинге и не должен
   зависеть от доступности чужого домена. Вторая: готовые редакторы
   тянут за собой мегабайт кода ради возможностей, которых здесь никто
   не ждёт, — таблиц с объединением ячеек, вставки видео, работы со
   шрифтами.

   Нужно ровно то, чем размечают текст «О компании»: заголовки, списки,
   выделение, ссылка, цитата. Всё остальное сервер всё равно вырежет при
   сохранении, и редактор, позволяющий больше, чем принимает сервер,
   только обманывал бы.

   Работает поверх обычного textarea: он остаётся в форме и уходит на
   сервер. Если скрипт не выполнится, останется поле с разметкой —
   неудобно, но не потеряно. */

(function (window, document) {

  var BUTTONS = [
    { cmd: 'bold', label: 'Ж', title: 'Полужирный (Ctrl+B)', style: 'font-weight:700' },
    { cmd: 'italic', label: 'К', title: 'Курсив (Ctrl+I)', style: 'font-style:italic' },
    { sep: true },
    { cmd: 'h2', label: 'Заголовок', title: 'Заголовок раздела' },
    { cmd: 'h3', label: 'Подзаголовок', title: 'Заголовок помельче' },
    { sep: true },
    { cmd: 'insertUnorderedList', label: '• Список', title: 'Список' },
    { cmd: 'insertOrderedList', label: '1. Список', title: 'Нумерованный список' },
    { cmd: 'blockquote', label: 'Цитата', title: 'Цитата' },
    { sep: true },
    { cmd: 'link', label: 'Ссылка', title: 'Вставить ссылку' },
    { cmd: 'unlink', label: 'Убрать ссылку', title: 'Убрать ссылку' },
    { sep: true },
    { cmd: 'removeFormat', label: 'Очистить', title: 'Снять оформление' },
    { cmd: 'source', label: '</>', title: 'Показать разметку' }
  ];

  function build(area) {
    var wrap = document.createElement('div');
    wrap.className = 'editor';

    var bar = document.createElement('div');
    bar.className = 'editor__bar';

    var body = document.createElement('div');
    body.className = 'editor__body';
    body.contentEditable = 'true';
    body.innerHTML = area.value;
    body.setAttribute('role', 'textbox');
    body.setAttribute('aria-multiline', 'true');
    body.setAttribute('aria-label', area.getAttribute('aria-label') || 'Текст страницы');

    area.parentNode.insertBefore(wrap, area);
    wrap.appendChild(bar);
    wrap.appendChild(body);
    wrap.appendChild(area);
    area.classList.add('editor__source');
    area.hidden = true;

    BUTTONS.forEach(function (item) {
      if (item.sep) {
        var sep = document.createElement('span');
        sep.className = 'editor__sep';
        bar.appendChild(sep);
        return;
      }
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'editor__btn';
      btn.textContent = item.label;
      btn.title = item.title;
      if (item.style) btn.setAttribute('style', item.style);
      btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
      btn.addEventListener('click', function () { run(item.cmd, body, area, wrap); });
      bar.appendChild(btn);
    });

    // Переносим содержимое обратно в поле формы: уходит на сервер именно
    // оно, а не то, что видно на экране.
    function sync() { if (!area.hidden) return; area.value = body.innerHTML; }
    body.addEventListener('input', sync);
    body.addEventListener('blur', sync);
    if (area.form) area.form.addEventListener('submit', sync);

    // Вставка из Word тащит за собой стили и разметку целыми абзацами.
    // Берём только текст: оформить его заново — три нажатия, вычистить
    // чужую разметку — полдня.
    body.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });

    return { body: body, area: area, wrap: wrap };
  }

  function run(cmd, body, area, wrap) {
    body.focus();

    if (cmd === 'source') {
      var showing = area.hidden;
      if (showing) area.value = body.innerHTML; else body.innerHTML = area.value;
      area.hidden = !showing;
      body.hidden = showing;
      wrap.classList.toggle('editor--source', showing);
      return;
    }
    if (cmd === 'h2' || cmd === 'h3') {
      // Повторное нажатие на том же заголовке возвращает обычный абзац.
      var block = document.queryCommandValue('formatBlock').toLowerCase();
      document.execCommand('formatBlock', false, block === cmd ? 'p' : cmd);
      return;
    }
    if (cmd === 'blockquote') {
      document.execCommand('formatBlock', false, 'blockquote');
      return;
    }
    if (cmd === 'link') {
      var url = window.prompt('Адрес ссылки', 'https://');
      if (url) document.execCommand('createLink', false, url);
      return;
    }
    document.execCommand(cmd, false, null);
  }

  function init() {
    var areas = document.querySelectorAll('textarea[data-editor]');
    Array.prototype.forEach.call(areas, build);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})(window, document);

/* Подтверждение необратимого действия.

   Раньше это был атрибут onsubmit прямо на форме. Строгая политика
   безопасности такие вставки запрещает: браузер не отличает наше
   подтверждение от чужого кода, пролезшего в разметку. Текст вопроса
   остался там же, где кнопка, — в атрибуте data-confirm. */
(function (document) {
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-confirm]');
    if (!form) return;
    if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
  });
})(document);

/* Тексты для городов: показываем один город за раз.

   Пятьдесят раскрытых блоков подряд — это стена полей, в которой не найти
   нужное. Скрытые остаются в форме и уходят на сервер вместе с ней, поэтому
   заполненное в другом городе не теряется: контроллер сохраняет только те
   города, что пришли в отправке, и пустые поля означают «взять общее». */
(function (document) {
  var pick = document.getElementById('city-pick');
  if (!pick) return;

  var boxes = Array.prototype.slice.call(document.querySelectorAll('.city-box'));

  function show() {
    var want = pick.value;
    boxes.forEach(function (b) {
      // Через hidden, а не style.display: в разметке уже стоит атрибут
      // hidden, и правка через стиль перебивалась бы им наполовину.
      b.hidden = b.getAttribute('data-city') !== want;
    });
  }

  pick.addEventListener('change', show);
  show();
})(document);
