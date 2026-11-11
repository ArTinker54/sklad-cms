'use strict';
/* Главная: слайд-шоу на первом экране и форма подбора. */

(function (window, document) {

  /* ---------- слайд-шоу ---------- */
  function initHero() {
    var bg = document.getElementById('hero-bg');
    if (!bg) return;

    var slides = Array.prototype.slice.call(bg.querySelectorAll('.hero__slide'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('.hero__dot'));
    if (slides.length < 2) return;

    var current = 0;
    var timer = null;
    var DELAY = 6000;
    var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Остальные картинки грузим после отрисовки страницы, а не вместе с ней.
    function preload() {
      slides.slice(1).forEach(function (el) {
        var src = el.dataset.src;
        if (!src || el.style.backgroundImage) return;
        var img = new Image();
        img.onload = function () { el.style.backgroundImage = 'url("' + src + '")'; };
        img.src = src;
      });
    }

    function show(i) {
      current = (i + slides.length) % slides.length;
      slides.forEach(function (el, n) { el.classList.toggle('is-active', n === current); });
      dots.forEach(function (d, n) {
        if (n === current) d.setAttribute('aria-current', 'true');
        else d.removeAttribute('aria-current');
      });
    }

    function start() {
      if (!still && !timer) timer = window.setInterval(function () { show(current + 1); }, DELAY);
    }
    function stop() { window.clearInterval(timer); timer = null; }

    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        show(parseInt(d.dataset.slide, 10));
        stop(); start();          // после ручного выбора отсчёт начинается заново
      });
    });

    // Во вкладке в фоне крутить нечего — не тратим ресурсы.
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stop(); else start();
    });

    if (document.readyState === 'complete') preload();
    else window.addEventListener('load', preload);
    start();
  }

  /* ---------- форма подбора ----------
     Уходит на сервер тем же адресом, что и все остальные формы. Раньше
     она только показывала «заявка отправлена» и ставила цель в Метрике,
     никуда ничего не отправляя, — обращение при этом пропадало. */
  function initQuick() {
    var form = document.getElementById('quick');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var trap = form.elements['ref_code'];
    if (trap && trap.value !== '') { return; }

      var bad = null;
      [['name', function (v) { return v.trim().length >= 2; }],
       ['phone', function (v) { return v.replace(/\D/g, '').length >= 10; }]].forEach(function (rule) {
        var el = form[rule[0]], ok = rule[1](el.value);
        el.setAttribute('aria-invalid', ok ? 'false' : 'true');
        if (!ok && !bad) bad = el;
      });
      form.consent.setAttribute('aria-invalid', form.consent.checked ? 'false' : 'true');
      if (!form.consent.checked && !bad) bad = form.consent;
      if (bad) { bad.focus(); return; }

      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = 'Отправляем…';

      fetch('/zayavka/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: 'request',
          fields: {
            name: form.name.value.trim(),
            phone: form.phone.value.trim(),
            comment: form.comment.value.trim()
          },
          consent: { given: true, at: new Date().toISOString() },
          page: location.href
        })
      })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (res) {
          if (!res.ok) throw new Error(res.error || 'Не удалось отправить заявку');
          if (window.Cart) window.Cart.goal('lead_quick', { source: 'home' });

          form.innerHTML = '<div class="form__body"><p style="margin:0"><b>Заявка ' +
            res.number + ' принята.</b> Перезвоним в рабочее время в течение двух часов.</p></div>';
        })
        .catch(function (err) {
          // Заявку терять нельзя: показываем причину и оставляем
          // заполненную форму, чтобы человек мог повторить отправку.
          btn.disabled = false;
          btn.textContent = 'Отправить заявку';
          var warn = form.querySelector('.form__error');
          if (!warn) {
            warn = document.createElement('p');
            warn.className = 'form__error';
            btn.parentNode.insertBefore(warn, btn);
          }
          warn.textContent = err.message + '. Позвоните нам — так быстрее.';
        });
    });
  }

  function init() { initHero(); initQuick(); }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})(window, document);
