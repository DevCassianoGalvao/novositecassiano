/* =========================================================
   Cassiano Galvão — interações e animações
   GSAP + ScrollTrigger + Lenis (carregados via CDN no index.html).
   Se alguma biblioteca não carregar, o conteúdo continua visível.
   ========================================================= */
(function () {
  'use strict';

  var DATA = window.SITE_DATA || {};
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var hasGSAP = typeof window.gsap !== 'undefined';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- starbursts decorativos ---------- */
  $$('[data-burst]').forEach(function (svg) {
    var ns = 'http://www.w3.org/2000/svg';
    var n = 14, seed = 7;
    function rnd() { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; }
    for (var i = 0; i < n; i++) {
      var a = (i / n) * Math.PI * 2 + rnd() * 0.25;
      var r1 = 22 + rnd() * 10, r2 = 62 + rnd() * 34;
      var l = document.createElementNS(ns, 'line');
      l.setAttribute('x1', 100 + Math.cos(a) * r1); l.setAttribute('y1', 100 + Math.sin(a) * r1);
      l.setAttribute('x2', 100 + Math.cos(a) * r2); l.setAttribute('y2', 100 + Math.sin(a) * r2);
      l.setAttribute('stroke-width', (9 + rnd() * 6).toFixed(1));
      svg.appendChild(l);
    }
  });

  /* ---------- portfólio ---------- */
  var ARROW = '<svg viewBox="0 0 24 24"><path d="M7 17L17 7M9 7h8v8"/></svg>';
  var PLAY = '<svg viewBox="0 0 24 24"><path d="M6 4l14 8-14 8z"/></svg>';
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  function workCard(p) {
    var isMotion = p.cat === 'motion';
    var shot;
    if (isMotion && p.video) {
      shot = '<video src="' + esc(p.video) + '"' + (p.poster ? ' poster="' + esc(p.poster) + '"' : '') + ' muted loop playsinline preload="metadata"></video>';
    } else if (p.img) {
      shot = '<img src="' + esc(p.img) + '" alt="Print do projeto ' + esc(p.nome) + '" loading="lazy">';
    } else {
      shot = '<div class="ph"><span>' + (isMotion ? '[VÍDEO 9:16]' : '[PRINT DO SITE]') + '</span></div>';
    }
    var go = p.url
      ? '<a class="work__go" href="' + esc(p.url) + '" target="_blank" rel="noopener" aria-label="Visitar ' + esc(p.nome) + '">' + ARROW + '</a>'
      : (isMotion ? '' : '<span class="work__go" aria-disabled="true">' + ARROW + '</span>');
    return '<article class="work glass spot' + (isMotion ? ' work--v' : '') + '">' +
      '<div class="work__shot">' + shot + (isMotion ? '<span class="work__play" aria-hidden="true">' + PLAY + '</span>' : '') + '</div>' +
      '<div class="work__meta"><div><span class="work__kind">' + esc(p.tipo) + '</span>' +
      '<h3 class="work__name">' + esc(p.nome) + '</h3><p class="work__desc">' + esc(p.desc) + '</p></div>' + go + '</div></article>';
  }

  var works = $('#works');
  function renderWorks(cat, animate) {
    if (!works) return;
    var list = (DATA.projetos || []).filter(function (p) { return p.cat === cat; });
    works.className = 'works' + (cat === 'motion' ? ' works--motion' : '');
    works.setAttribute('aria-labelledby', 'tab-' + cat);
    works.innerHTML = list.map(workCard).join('');
    bindSpots(works);
    $$('video', works).forEach(function (v) {
      var card = v.closest('.work');
      card.addEventListener('mouseenter', function () { v.play().catch(function () {}); });
      card.addEventListener('mouseleave', function () { v.pause(); });
      card.addEventListener('click', function () { v.paused ? v.play().catch(function () {}) : v.pause(); });
    });
    if (animate && hasGSAP && !reduce) {
      gsap.from($$('.work', works), { y: 40, opacity: 0, duration: .7, stagger: .08, ease: 'power3.out', clearProps: 'all' });
    }
    if (window.ScrollTrigger) ScrollTrigger.refresh();
  }

  var tabs = $$('.tab');
  var ink = $('.tab__ink');
  function moveInk(btn) {
    if (!ink || !btn) return;
    ink.style.left = btn.offsetLeft + 'px';
    ink.style.width = btn.offsetWidth + 'px';
  }
  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabs.forEach(function (b) { b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
      btn.classList.add('is-active'); btn.setAttribute('aria-selected', 'true');
      moveInk(btn);
      renderWorks(btn.dataset.filter, true);
    });
  });
  renderWorks('sites', false);
  moveInk($('.tab.is-active'));
  window.addEventListener('resize', function () { moveInk($('.tab.is-active')); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { moveInk($('.tab.is-active')); });

  /* ---------- depoimentos ---------- */
  function ytId(v) {
    if (!v) return '';
    var m = String(v).match(/(?:youtu\.be\/|v=|shorts\/|embed\/)([\w-]{11})/);
    return m ? m[1] : (/^[\w-]{11}$/.test(v) ? v : '');
  }
  var testis = $('#testis');
  if (testis) {
    testis.innerHTML = (DATA.depoimentos || []).map(function (d) {
      var id = ytId(d.youtube);
      var initials = (d.nome || '?').replace(/[\[\]]/g, '').split(' ').map(function (w) { return w[0] || ''; }).join('').slice(0, 2).toUpperCase();
      var media = id
        ? '<div class="testi__video" data-yt="' + id + '"><img src="https://i.ytimg.com/vi/' + id + '/hqdefault.jpg" alt="" loading="lazy">' +
          '<button class="testi__btn" type="button" aria-label="Assistir depoimento de ' + esc(d.nome) + '">' + PLAY + '</button></div>'
        : '<div class="testi__video"><div class="ph"><span>[VÍDEO DO YOUTUBE]</span></div></div>';
      return '<article class="testi glass spot">' + media +
        '<p class="testi__quote">“' + esc(d.frase) + '”</p>' +
        '<div class="testi__who"><span class="testi__av" aria-hidden="true">' + esc(initials) + '</span><div><b>' + esc(d.nome) + '</b><span>' + esc(d.empresa) + '</span></div></div></article>';
    }).join('');
    $$('[data-yt]', testis).forEach(function (box) {
      box.addEventListener('click', function () {
        if (box.querySelector('iframe')) return;
        var f = document.createElement('iframe');
        f.src = 'https://www.youtube-nocookie.com/embed/' + box.dataset.yt + '?autoplay=1&rel=0';
        f.title = 'Depoimento em vídeo';
        f.allow = 'autoplay; encrypted-media; picture-in-picture';
        f.allowFullscreen = true;
        box.appendChild(f);
      });
    });
  }

  /* ---------- luz que segue o cursor ---------- */
  function bindSpots(root) {
    if (!finePointer) return;
    $$('.spot', root).forEach(function (el) {
      if (el._spot) return; el._spot = true;
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect();
        el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        el.style.setProperty('--my', (e.clientY - r.top) + 'px');
      });
    });
  }
  bindSpots(document);

  /* ---------- antes x depois ---------- */
  var ba = $('#ba');
  var auto = null;
  if (ba) {
    var view = $('.ba__view', ba);
    var range = $('#ba-range');
    var setPos = function (p) {
      p = Math.max(0, Math.min(100, p));
      ba.style.setProperty('--pos', p + '%');
      if (range) range.value = Math.round(p);
    };
    var stopAuto = function () { if (auto) { auto.kill(); auto = null; } };
    var fromEvent = function (e) {
      var r = view.getBoundingClientRect();
      setPos(((e.clientX - r.left) / r.width) * 100);
    };
    var dragging = false;
    view.addEventListener('pointerdown', function (e) { dragging = true; stopAuto(); fromEvent(e); view.setPointerCapture && view.setPointerCapture(e.pointerId); });
    view.addEventListener('pointermove', function (e) { if (dragging || e.pointerType === 'mouse') { stopAuto(); fromEvent(e); } });
    window.addEventListener('pointerup', function () { dragging = false; });
    if (range) range.addEventListener('input', function () { stopAuto(); setPos(+range.value); });
    if (hasGSAP && !reduce) {
      var o = { p: 50 };
      auto = gsap.timeline({ repeat: -1, delay: 2.2, defaults: { ease: 'sine.inOut', onUpdate: function () { setPos(o.p); } } })
        .to(o, { p: 22, duration: 1.6 })
        .to(o, { p: 80, duration: 2.6 })
        .to(o, { p: 50, duration: 1.6 });
    }
  }

  /* ---------- formulário ---------- */
  var form = $('#leadForm');
  if (form) {
    var fNome = $('#f-nome'), fWhats = $('#f-whats'), fInsta = $('#f-insta'), fServ = $('#f-servico'), fHp = $('#f-site');
    var submitBtn = $('#f-submit'), done = $('#formDone'), waLink = $('#waLink');

    fWhats.addEventListener('input', function () {
      var d = fWhats.value.replace(/\D/g, '');
      if (d.length > 11 && d.indexOf('55') === 0) d = d.slice(2);
      d = d.slice(0, 11);
      var out = d;
      if (d.length > 2) out = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length > 7) out = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length - 4) + '-' + d.slice(-4);
      fWhats.value = out;
    });
    fInsta.addEventListener('blur', function () { fInsta.value = normInsta(fInsta.value); });

    function normInsta(v) {
      v = String(v || '').trim();
      var m = v.match(/instagram\.com\/([A-Za-z0-9._]+)/i);
      if (m) v = m[1];
      v = v.replace(/^@+/, '').replace(/\s+/g, '').replace(/[^A-Za-z0-9._]/g, '');
      return v ? '@' + v : '';
    }
    function setErr(input, bad) { input.closest('.field').classList.toggle('is-invalid', bad); return bad; }
    function validate() {
      var bad = false, first = null;
      var checks = [
        [fNome, fNome.value.trim().length < 2],
        [fWhats, !/^\d{10,11}$/.test(fWhats.value.replace(/\D/g, ''))],
        [fInsta, normInsta(fInsta.value).length < 3],
        [fServ, !fServ.value]
      ];
      checks.forEach(function (c) { if (setErr(c[0], c[1])) { bad = true; first = first || c[0]; } });
      if (first) first.focus();
      return !bad;
    }
    [fNome, fWhats, fInsta, fServ].forEach(function (el) {
      el.addEventListener('input', function () { el.closest('.field').classList.remove('is-invalid'); });
      el.addEventListener('change', function () { el.closest('.field').classList.remove('is-invalid'); });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      fInsta.value = normInsta(fInsta.value);
      if (!validate()) return;

      var servicoTxt = fServ.options[fServ.selectedIndex].text;
      var payload = {
        nome: fNome.value.trim(),
        whatsapp: fWhats.value.replace(/\D/g, ''),
        instagram: fInsta.value,
        servico: fServ.value,
        servico_label: servicoTxt,
        website: fHp.value,
        pagina: location.href
      };
      var msg = 'Oi, Cassiano! Sou ' + payload.nome + ', da ' + payload.instagram + '. Vim pelo site e ' +
        servicoTxt.charAt(0).toLowerCase() + servicoTxt.slice(1) + '.';
      var wa = 'https://wa.me/' + (DATA.whatsapp || '5522997560717') + '?text=' + encodeURIComponent(msg);
      waLink.href = wa;

      submitBtn.classList.add('is-loading');
      submitBtn.querySelector('.btn__label').textContent = 'Enviando...';

      var finish = function () {
        done.hidden = false;
        if (hasGSAP && !reduce) gsap.from(done, { opacity: 0, scale: .96, duration: .5, ease: 'power3.out' });
        try { window.dataLayer && window.dataLayer.push({ event: 'lead_form', servico: payload.servico }); } catch (err) {}
        setTimeout(function () { window.location.href = wa; }, 1200);
      };

      if (payload.website) { finish(); return; } // bot no honeypot: não envia

      var ctrl = 'AbortController' in window ? new AbortController() : null;
      var t = setTimeout(function () { ctrl && ctrl.abort(); }, 8000);
      fetch(DATA.endpoint || 'api/lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
        signal: ctrl ? ctrl.signal : undefined
      }).catch(function () { /* mesmo se o e-mail falhar, o lead segue pro WhatsApp */ })
        .then(function () { clearTimeout(t); finish(); });
    });
  }

  /* ---------- navegação, progresso, botão flutuante ---------- */
  var nav = $('#nav'), fab = $('.fab'), bar = $('.progress span'), cta = $('#contato');
  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    nav.classList.toggle('is-scrolled', y > 30);
    if (bar) bar.style.transform = 'scaleX(' + (max > 0 ? y / max : 0) + ')';
    if (fab && cta) {
      var ctaTop = cta.getBoundingClientRect().top;
      fab.classList.toggle('is-on', y > window.innerHeight * 0.9 && ctaTop > window.innerHeight * 0.6);
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- daqui pra baixo: só com GSAP e sem reduced motion ---------- */
  if (!hasGSAP || reduce) {
    bindAnchors(null);
    return;
  }
  gsap.registerPlugin(ScrollTrigger);

  /* rolagem suave */
  var lenis = null;
  if (window.Lenis) {
    lenis = new Lenis({ duration: 1.15, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
    gsap.ticker.lagSmoothing(0);
  }
  bindAnchors(lenis);

  /* intro do hero */
  var intro = gsap.timeline({ defaults: { ease: 'power4.out' } });
  intro
    .from('.nav', { y: -30, opacity: 0, duration: .8 })
    .from('[data-hero="eyebrow"]', { y: 16, opacity: 0, duration: .6 }, '-=.5')
    .from('.hero__line .w', { yPercent: 115, duration: 1, stagger: .055 }, '-=.35')
    .from('.hero__pill', { scale: .4, rotate: -14, opacity: 0, duration: .9, ease: 'back.out(2.2)' }, '-=.55')
    .from('[data-hero="sub"]', { y: 20, opacity: 0, duration: .7 }, '-=.6')
    .from('[data-hero="ctas"] > *', { y: 20, opacity: 0, duration: .6, stagger: .08 }, '-=.5')
    .from('[data-hero="stage"]', { y: 90, opacity: 0, duration: 1.3, ease: 'expo.out' }, '-=.6')
    .from('.hero__star', { scale: 0, rotate: -90, duration: 1, ease: 'back.out(2)' }, '-=1.1')
    .from('.hero__burst line', { scale: 0, transformOrigin: '100px 100px', duration: .8, stagger: .03, ease: 'back.out(2)' }, '-=1');

  /* mockup deita para frente conforme rola */
  gsap.fromTo('.ba', { rotateX: 16, scale: .94 }, {
    rotateX: 0, scale: 1, ease: 'none',
    scrollTrigger: { trigger: '.hero__stage', start: 'top 95%', end: 'top 25%', scrub: true }
  });

  /* parallax de luzes e formas */
  gsap.to('.hero__glow--a', { yPercent: 40, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.to('.hero__star', { rotate: 180, y: 120, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.to('.hero__burst', { rotate: 90, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.to('.section__glow', { yPercent: 50, ease: 'none', scrollTrigger: { trigger: '#portfolio', start: 'top bottom', end: 'bottom top', scrub: true } });
  gsap.to('.about__star', { rotate: 270, ease: 'none', scrollTrigger: { trigger: '#sobre', start: 'top bottom', end: 'bottom top', scrub: true } });
  gsap.to('.cta__burst', { rotate: 120, ease: 'none', scrollTrigger: { trigger: '#contato', start: 'top bottom', end: 'bottom top', scrub: true } });

  /* títulos h2 palavra por palavra */
  $$('.h2').forEach(function (h) {
    splitWords(h);
    gsap.from($$('.wi', h), {
      yPercent: 110, duration: .9, stagger: .045, ease: 'power4.out',
      scrollTrigger: { trigger: h, start: 'top 88%', once: true }
    });
  });

  /* revelações */
  $$('[data-reveal]').forEach(function (el) {
    gsap.from(el, {
      y: 50, opacity: 0, duration: 1, ease: 'power3.out',
      scrollTrigger: { trigger: el, start: 'top 90%', once: true }
    });
  });
  $$('[data-stagger]').forEach(function (group) {
    gsap.from(group.children, {
      y: 40, opacity: 0, duration: .8, stagger: .1, ease: 'power3.out',
      scrollTrigger: { trigger: group, start: 'top 88%', once: true }
    });
  });

  /* contadores */
  $$('[data-count]').forEach(function (el) {
    var end = +el.dataset.count, o = { v: 0 };
    gsap.to(o, {
      v: end, duration: 2, ease: 'power2.out',
      scrollTrigger: { trigger: el, start: 'top 95%', once: true },
      onStart: function () { el.textContent = '0'; },
      onUpdate: function () { el.textContent = Math.round(o.v); }
    });
  });

  /* visuais dos serviços com parallax leve */
  gsap.matchMedia().add('(min-width: 1001px)', function () {
    $$('.svc__visual').forEach(function (v) {
      gsap.fromTo(v, { y: 30 }, { y: -30, ease: 'none', scrollTrigger: { trigger: v, start: 'top bottom', end: 'bottom top', scrub: true } });
    });
  });
  gsap.from('.vw__grid i, .vw__h, .vw__btn', {
    scaleX: 0, transformOrigin: '0 50%', duration: .8, stagger: .08, ease: 'power3.out',
    scrollTrigger: { trigger: '.vis-web', start: 'top 80%', once: true }
  });
  gsap.from('.node', {
    scale: .5, opacity: 0, duration: .6, stagger: .12, ease: 'back.out(2)',
    scrollTrigger: { trigger: '.vis-ai', start: 'top 80%', once: true }
  });

  /* foto do sobre */
  gsap.fromTo('.frame', { clipPath: 'inset(12% 12% 12% 12% round 36px)' }, {
    clipPath: 'inset(0% 0% 0% 0% round 36px)', ease: 'none',
    scrollTrigger: { trigger: '.about__photo', start: 'top 90%', end: 'top 35%', scrub: true }
  });
  gsap.from('.float-pill', { y: 30, opacity: 0, duration: .8, stagger: .15, ease: 'back.out(2)', scrollTrigger: { trigger: '.about__photo', start: 'top 70%', once: true } });

  /* painel do CTA cresce ao entrar */
  gsap.fromTo('#ctaPanel', { scale: .9, borderRadius: 80 }, {
    scale: 1, borderRadius: 40, ease: 'none',
    scrollTrigger: { trigger: '#contato', start: 'top bottom', end: 'top 35%', scrub: true }
  });

  /* botões magnéticos */
  if (finePointer) {
    $$('.magnetic').forEach(function (el) {
      var xTo = gsap.quickTo(el, 'x', { duration: .5, ease: 'power3' });
      var yTo = gsap.quickTo(el, 'y', { duration: .5, ease: 'power3' });
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect();
        xTo((e.clientX - (r.left + r.width / 2)) * .22);
        yTo((e.clientY - (r.top + r.height / 2)) * .32);
      });
      el.addEventListener('pointerleave', function () { xTo(0); yTo(0); });
    });
  }

  window.addEventListener('load', function () { ScrollTrigger.refresh(); });

  /* ---------- utilitários ---------- */
  function splitWords(el) {
    var walk = function (node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (n) {
        if (n.nodeType === 3) {
          var frag = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part) return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(' ')); return; }
            var o = document.createElement('span'); o.className = 'wo';
            var i = document.createElement('span'); i.className = 'wi'; i.textContent = part;
            o.appendChild(i); frag.appendChild(o);
          });
          node.replaceChild(frag, n);
        } else if (n.nodeType === 1) { walk(n); }
      });
    };
    el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
    walk(el);
  }

  function bindAnchors(l) {
    $$('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var id = a.getAttribute('href');
        if (id.length < 2) return;
        var target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        if (l) l.scrollTo(target, { offset: -90, duration: 1.4 });
        else window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 90, behavior: reduce ? 'auto' : 'smooth' });
        if (id === '#contato') setTimeout(function () { var f = document.getElementById('f-nome'); f && f.focus({ preventScroll: true }); }, l ? 1400 : 600);
      });
    });
  }
})();
