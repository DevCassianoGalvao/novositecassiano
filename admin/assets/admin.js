(function () {
  'use strict';

  // menu no celular
  var mb = document.querySelector('[data-menu]');
  if (mb) mb.addEventListener('click', function () { document.body.classList.toggle('menu-open'); });

  // copiar campo
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var el = document.getElementById(b.getAttribute('data-copy'));
    if (!el) return;
    var txt = el.value !== undefined ? el.value : el.textContent;
    var done = function () { var o = b.textContent; b.textContent = 'Copiado!'; setTimeout(function () { b.textContent = o; }, 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(txt).then(done, function () { el.select && el.select(); });
    else { el.select && el.select(); try { document.execCommand('copy'); done(); } catch (x) {} }
  });

  // confirmação em ações delicadas
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var msg = f.getAttribute && f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // máscara de WhatsApp
  document.querySelectorAll('input[data-phone]').forEach(function (i) {
    i.addEventListener('input', function () {
      var d = i.value.replace(/\D/g, '').slice(0, 11), o = d;
      if (d.length > 6) o = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length > 10 ? 7 : 6) + '-' + d.slice(d.length > 10 ? 7 : 6);
      else if (d.length > 2) o = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      i.value = o;
    });
  });
})();
