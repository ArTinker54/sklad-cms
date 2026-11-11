/* Картинки главной: интерактивный предпросмотр с текстом первого экрана (04.11.2026, обновлено 10.11.2026).
 *
 * Позволяет видеть, как слайд встанет на сайт до его загрузки:
 * - накладывает реальный текст, плашку дилера, кнопки и градиент затемнения;
 * - при выборе точки кадрирования (середина, левый/правый край, верх/низ)
 *   картинка сдвигается под текстом в реальном времени;
 * - кнопка «На весь экран» открывает крупный предпросмотр для ПК (1920×600)
 *   и смартфона (390×580).
 */
(function () {
  'use strict';

  var POS = {
    center: 'center center',
    top: 'center top',
    bottom: 'center bottom',
    left: 'left center',
    right: 'right center'
  };

  var modal = document.getElementById('hero-modal');
  var stage = document.getElementById('hero-modal-stage');
  var focusBox = document.getElementById('hero-modal-focus');
  var toggleMock = document.getElementById('hero-toggle-mock');
  var currentForm = null;

  // 1. Переключатель наложения текста (сохраняется в localStorage)
  if (toggleMock) {
    try {
      var saved = localStorage.getItem('hero_mock_enabled');
      if (saved === '0') {
        toggleMock.checked = false;
        document.body.classList.add('hero-hide-mock');
      }
    } catch (e) {}

    toggleMock.addEventListener('change', function () {
      document.body.classList.toggle('hero-hide-mock', !toggleMock.checked);
      try {
        localStorage.setItem('hero_mock_enabled', toggleMock.checked ? '1' : '0');
      } catch (e) {}
    });
  }

  // 2. Обновление предпросмотра в форме
  function update(form) {
    var box = form.querySelector('[data-hero-preview]');
    var url = form.getAttribute('data-hero-url');
    if (!box || !url) return;
    var focus = form.querySelector('input[name="focus"]:checked');
    var posKey = focus ? focus.value : 'center';
    box.style.backgroundImage = 'url("' + url + '")';
    box.style.backgroundPosition = POS[posKey] || 'center center';
    box.hidden = false;

    // Если открыт крупный просмотр для этой же формы — синхронизируем
    if (modal && modal.open && currentForm === form && stage) {
      stage.style.backgroundImage = 'url("' + url + '")';
      stage.style.backgroundPosition = POS[posKey] || 'center center';
      syncFocusButtons(posKey);
    }
  }

  function syncFocusButtons(activeVal) {
    if (!focusBox) return;
    var btns = focusBox.querySelectorAll('[data-focus]');
    for (var i = 0; i < btns.length; i++) {
      btns[i].classList.toggle('is-active', btns[i].getAttribute('data-focus') === activeVal);
    }
  }

  // 3. Открытие модального окна крупного просмотра
  function openZoom(media) {
    if (!modal || !stage) return;
    var url = '';
    var pos = 'center center';
    var isUpload = false;
    var form = media.closest('form.heroform');

    if (media.hasAttribute('data-hero-preview') && form) {
      url = form.getAttribute('data-hero-url');
      if (!url) return;
      var checked = form.querySelector('input[name="focus"]:checked');
      var val = checked ? checked.value : 'center';
      pos = POS[val] || 'center center';
      isUpload = true;
      currentForm = form;
      syncFocusButtons(val);
    } else {
      var img = media.querySelector('img');
      url = img ? img.currentSrc || img.src : media.getAttribute('data-src');
      currentForm = null;
    }

    if (!url) return;

    stage.style.backgroundImage = 'url("' + url + '")';
    stage.style.backgroundPosition = pos;

    // Показываем переключатель кадрирования только для загружаемого файла
    if (focusBox) {
      focusBox.hidden = !isUpload;
    }

    // Сброс на вид для ПК
    switchTab('pc');

    if (typeof modal.showModal === 'function') {
      modal.showModal();
    } else {
      modal.setAttribute('open', '');
    }
  }

  function switchTab(view) {
    if (!stage || !modal) return;
    stage.classList.toggle('is-pc', view === 'pc');
    stage.classList.toggle('is-ipad', view === 'ipad');
    stage.classList.toggle('is-mob', view === 'mob');
    var tabs = modal.querySelectorAll('.heromodal__tab');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].classList.toggle('is-active', tabs[i].getAttribute('data-view') === view);
    }
  }

  // 4. Обработчики событий документа
  document.addEventListener('change', function (e) {
    var form = e.target.closest && e.target.closest('form.heroform');
    if (!form) return;

    if (e.target.matches('[data-hero-file]')) {
      var file = e.target.files && e.target.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function () {
        form.setAttribute('data-hero-url', reader.result);
        update(form);
      };
      reader.readAsDataURL(file);
    } else if (e.target.name === 'focus') {
      update(form);
    }
  });

  document.addEventListener('click', function (e) {
    // Кнопка «На весь экран»
    var zoomBtn = e.target.closest && e.target.closest('[data-hero-zoom]');
    if (zoomBtn) {
      e.preventDefault();
      var media = zoomBtn.closest('.heroslide__media');
      if (media) openZoom(media);
      return;
    }

    // Табы ПК / Телефон в модальном окне
    var tabBtn = e.target.closest && e.target.closest('.heromodal__tab');
    if (tabBtn) {
      e.preventDefault();
      switchTab(tabBtn.getAttribute('data-view') || 'pc');
      return;
    }

    // Кнопки кадрирования внутри модального окна
    var focusBtn = e.target.closest && e.target.closest('[data-focus]');
    if (focusBtn && currentForm) {
      e.preventDefault();
      var val = focusBtn.getAttribute('data-focus');
      var radio = currentForm.querySelector('input[name="focus"][value="' + val + '"]');
      if (radio) {
        radio.checked = true;
        update(currentForm);
      }
      return;
    }

    // Закрытие модального окна
    if (e.target.matches('[data-close]') || (e.target.closest && e.target.closest('[data-close]'))) {
      e.preventDefault();
      if (modal) modal.close();
      return;
    }

    // Клик по подложке модалки
    if (modal && e.target === modal) {
      modal.close();
    }
  });

  if (modal) {
    modal.addEventListener('close', function () {
      currentForm = null;
    });
  }
})();
