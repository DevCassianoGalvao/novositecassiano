(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* menu no celular */
  var mb = document.querySelector('[data-menu]');
  function setMenu(open) {
    document.body.classList.toggle('menu-open', open);
    if (mb) mb.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (mb) mb.addEventListener('click', function () { setMenu(!document.body.classList.contains('menu-open')); });
  document.addEventListener('click', function (e) { if (e.target.closest('[data-menu-close]')) setMenu(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

  /* copiar campo */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var el = document.getElementById(b.getAttribute('data-copy'));
    if (!el) return;
    var txt = el.value !== undefined ? el.value : el.textContent;
    var label = b.querySelector('span') || b;
    var done = function () {
      var o = label.textContent; label.textContent = 'Copiado!'; b.classList.add('is-done');
      setTimeout(function () { label.textContent = o; b.classList.remove('is-done'); }, 1600);
    };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(done, fallback); else fallback();
    function fallback() { var t = document.createElement('textarea'); t.value = txt; t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (x) {} document.body.removeChild(t); }
  });

  /* confirmação em ações delicadas */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var msg = f.getAttribute && f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  /* máscara de WhatsApp */
  document.querySelectorAll('input[data-phone]').forEach(function (i) {
    i.addEventListener('input', function () {
      var d = i.value.replace(/\D/g, '').slice(0, 11), o = d;
      if (d.length > 6) o = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length > 10 ? 7 : 6) + '-' + d.slice(d.length > 10 ? 7 : 6);
      else if (d.length > 2) o = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      i.value = o;
    });
  });

  /* números que contam (uma vez, quando aparecem) */
  function fmt(n, money) {
    if (money) return 'R$ ' + (n / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return Math.round(n).toLocaleString('pt-BR');
  }
  function countUp(el) {
    var to = parseFloat(el.getAttribute('data-count')) || 0, money = el.hasAttribute('data-money');
    if (reduce || to === 0) { el.textContent = fmt(to, money); return; }
    var t0 = null, dur = 1100;
    (function step(t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(to * e, money);
      if (p < 1) requestAnimationFrame(step);
    })(performance.now());
  }

  /* linha do gráfico: comprimento para o desenho */
  document.querySelectorAll('.chart .line').forEach(function (p) {
    try { p.style.setProperty('--len', Math.ceil(p.getTotalLength()) + 'px'); } catch (x) {}
  });

  /* entrada orquestrada: os blocos sobem em sequência, uma vez */
  var items = [].slice.call(document.querySelectorAll('.rise'));
  items.forEach(function (el, i) { el.style.setProperty('--dl', Math.min(i * 0.06, 0.6) + 's'); });
  function show(el) {
    el.classList.add('is-in');
    el.querySelectorAll('[data-count]').forEach(countUp);
    el.querySelectorAll('.chart').forEach(function (c) { c.classList.add('is-in'); });
  }
  if (!('IntersectionObserver' in window) || reduce) { items.forEach(show); }
  else {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { show(e.target); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -40px 0px' });
    items.forEach(function (el) { io.observe(el); });
  }
})();
