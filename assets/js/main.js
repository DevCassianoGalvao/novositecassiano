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
  var hasGSAP = typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ARROW = '<svg viewBox="0 0 24 24"><path d="M7 17L17 7M9 7h8v8"/></svg>';
  var PLAY = '<svg viewBox="0 0 24 24"><path d="M6 4l14 8-14 8z"/></svg>';

  /* ---------- ano automático no rodapé ---------- */
  var ano = $('#ano');
  if (ano) ano.textContent = new Date().getFullYear();

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

  /* =========================================================
     WEB & DESIGN — tela que troca sozinha a cada 5s
     ========================================================= */
  var CODE = [
    [['t', '<section'], ['a', ' class='], ['v', '"hero"'], ['t', '>']],
    [['x', '  '], ['t', '<h1>'], ['x', 'Sua empresa, do tamanho'], ['t', '</h1>']],
    [['x', '  '], ['t', '<p>'], ['x', 'Atendimento rápido e confiável.'], ['t', '</p>']],
    [['x', '  '], ['t', '<a'], ['a', ' href='], ['v', '"#contato"'], ['a', ' class='], ['v', '"btn"'], ['t', '>']],
    [['x', '    Pedir orçamento']],
    [['x', '  '], ['t', '</a>']],
    [['t', '</section>']],
    [],
    [['t', '<' + 'script>'], ['x', ' gsap.'], ['v', 'from'], ['x', '(".hero", { y: 40 })'], ['t', '<' + '/script>']]
  ];
  var codeEl = $('#codeLines'), codeTimer = null;
  function typeCode() {
    if (!codeEl) return;
    clearTimeout(codeTimer);
    codeEl.innerHTML = '';
    if (reduce) {
      codeEl.innerHTML = CODE.map(function (line) {
        return '<li>' + line.map(function (t) { return '<span class="' + t[0] + '">' + esc(t[1]) + '</span>'; }).join('') + '</li>';
      }).join('');
      return;
    }
    var li = 0, ti = 0, ci = 0, liEl = null, spanEl = null;
    var cur = document.createElement('span'); cur.className = 'cur';
    (function step() {
      if (li >= CODE.length) { return; }
      if (!liEl) { liEl = document.createElement('li'); codeEl.appendChild(liEl); }
      var line = CODE[li];
      if (ti >= line.length) { li++; ti = 0; ci = 0; liEl = null; codeTimer = setTimeout(step, 90); return; }
      var tok = line[ti];
      if (ci === 0) { spanEl = document.createElement('span'); spanEl.className = tok[0]; liEl.appendChild(spanEl); }
      spanEl.textContent += tok[1].charAt(ci);
      liEl.appendChild(cur);
      ci++;
      if (ci >= tok[1].length) { ti++; ci = 0; }
      codeTimer = setTimeout(step, 14 + Math.random() * 18);
    })();
  }

  var webShow = $('#webShow');
  if (webShow) {
    var scenes = $$('.ws__s', webShow), chips = $$('.chip', webShow), wsTitle = $('.ws__title', webShow);
    var current = 0, timer = null, DUR = 5000, inView = true;
    var show = function (i) {
      current = (i + scenes.length) % scenes.length;
      scenes.forEach(function (s, k) {
        s.classList.remove('is-on');
        if (k === current) { void s.offsetWidth; s.classList.add('is-on'); }
      });
      chips.forEach(function (c, k) {
        c.classList.remove('is-on');
        c.setAttribute('aria-selected', k === current ? 'true' : 'false');
        if (k === current) { void c.offsetWidth; c.classList.add('is-on'); }
      });
      if (wsTitle) wsTitle.textContent = scenes[current].dataset.title || '';
      if (current === 1) typeCode();
      clearTimeout(timer);
      if (!reduce) timer = setTimeout(function () { if (inView) show(current + 1); else timer = null; }, DUR);
    };
    chips.forEach(function (c, k) { c.style.setProperty('--dur', DUR + 'ms'); c.addEventListener('click', function () { show(k); }); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        inView = entries[0].isIntersecting;
        if (inView && !timer && !reduce) show(current + 1);
      }).observe(webShow);
    }
    show(0);
  }

  /* =========================================================
     PROCESSO — 4 etapas (scroll no desktop, automático no celular)
     ========================================================= */
  var process = $('#processo');
  var showStep = function () {};
  if (process) {
    var stepsEl = $$('.step', process), layers = $$('.pv__l', process), bar = $('#pvBar'), curStep = -1;
    showStep = function (i) {
      if (i === curStep) return;
      curStep = i;
      stepsEl.forEach(function (s, k) { s.classList.toggle('is-on', k === i); });
      layers.forEach(function (l, k) { l.classList.toggle('is-on', k === i || (i === 3 && k === 2)); });
      if (bar) bar.style.width = ((i + 1) / 4 * 100) + '%';
      process.classList.toggle('is-live', i === 3);
      process.setAttribute('data-step', i);
      $$('.pv-ic', process).forEach(function (ic, k) { ic.classList.toggle('is-on', k === i); });
    };
    showStep(0);
    stepsEl.forEach(function (s, k) { s.addEventListener('click', function () { showStep(k); }); });
  }
  var autoProcess = null;
  function startAutoProcess() {
    if (autoProcess || reduce || !process) return;
    var i = 0;
    autoProcess = setInterval(function () { i = (i + 1) % 4; showStep(i); }, 2800);
  }

  /* =========================================================
     PORTFÓLIO
     ========================================================= */
  function workCard(p) {
    var isMotion = p.cat === 'motion', shot;
    if (isMotion && p.video) {
      shot = '<video src="' + esc(p.video) + '"' + (p.poster ? ' poster="' + esc(p.poster) + '"' : '') + ' muted loop playsinline preload="metadata"></video>';
    } else if (p.imgs && p.imgs.length) {
      shot = '<div class="gal">' + p.imgs.map(function (src, k) {
        return '<img class="gal__img' + (k === 0 ? ' is-on' : '') + '" src="' + esc(src) + '" alt="' + esc(p.nome + ' · ' + ((p.legendas || [])[k] || 'tela ' + (k + 1))) + '" loading="lazy">';
      }).join('') + '<div class="gal__bar">' + p.imgs.map(function (src, k) {
        return '<button type="button" class="gal__dot' + (k === 0 ? ' is-on' : '') + '" data-k="' + k + '">' + esc((p.legendas || [])[k] || 'Tela ' + (k + 1)) + '</button>';
      }).join('') + '</div></div>';
    } else if (p.img) {
      shot = '<img src="' + esc(p.img) + '" alt="Print do projeto ' + esc(p.nome) + '" loading="lazy">';
    } else {
      shot = '<div class="ph"><span>' + (isMotion ? '[VÍDEO 9:16]' : '[PRINT DO SITE]') + '</span></div>';
    }
    var go = p.url
      ? '<a class="work__go" href="' + esc(p.url) + '" target="_blank" rel="noopener" aria-label="Visitar ' + esc(p.nome) + '">' + ARROW + '</a>'
      : ((isMotion || p.imgs) ? '' : '<span class="work__go" aria-disabled="true">' + ARROW + '</span>');
    return '<article class="work glass spot' + (isMotion ? ' work--v' : '') + (p.wide ? ' work--wide' : '') + '">' +
      '<div class="work__shot">' + shot + (isMotion ? '<span class="work__play" aria-hidden="true">' + PLAY + '</span>' : '') + '</div>' +
      '<div class="work__meta"><div><span class="work__kind">' + esc(p.tipo) + '</span>' +
      '<h3 class="work__name">' + esc(p.nome) + '</h3>' + (p.desc ? '<p class="work__desc">' + esc(p.desc) + '</p>' : '') + '</div>' + go + '</div></article>';
  }
  var works = $('#works');
  var LINK = '<svg viewBox="0 0 24 24"><path d="M7 17L17 7M9 7h8v8"/></svg>';
  function renderWorks(cat, animate) {
    if (!works) return;
    var all = (DATA.projetos || []).filter(function (p) { return p.cat === cat; });
    works.className = 'works works--' + cat;
    works.setAttribute('aria-labelledby', 'tab-' + cat);
    var html = '';
    if (cat === 'motion') {
      html = '<div class="mslider"><div class="mslider__track" tabindex="0" aria-label="Vídeos de motion">' + all.map(workCard).join('') + '</div>' +
        '<div class="mslider__nav"><button type="button" class="slider__btn" data-dir="-1" aria-label="Vídeo anterior"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>' +
        '<button type="button" class="slider__btn" data-dir="1" aria-label="Próximo vídeo"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button></div></div>';
    } else {
      var feat = all.filter(function (p) { return p.destaque; }), rest = all.filter(function (p) { return !p.destaque; });
      html = '<div class="works__grid">' + feat.map(workCard).join('') + '</div>';
      if (rest.length) {
        html += '<div class="more"><p class="more__title">Mais sites no ar</p><ul class="more__list">' + rest.map(function (p) {
          return '<li><a href="' + esc(p.url) + '" target="_blank" rel="noopener"><span><b>' + esc(p.nome) + '</b><small>' + esc(p.tipo) + '</small></span>' + LINK + '</a></li>';
        }).join('') + '</ul></div>';
      }
    }
    works.innerHTML = html;
    bindSpots(works);
    $$('video', works).forEach(function (v) {
      var card = v.closest('.work');
      card.addEventListener('mouseenter', function () { v.play().catch(function () {}); });
      card.addEventListener('mouseleave', function () { if (v.muted) v.pause(); });
      card.addEventListener('click', function () {
        if (v.muted || v.paused) {
          $$('video', works).forEach(function (o) { if (o !== v) { o.pause(); o.muted = true; o.closest('.work').classList.remove('is-sound'); } });
          v.muted = false; v.play().catch(function () {}); card.classList.add('is-sound');
        } else { v.pause(); card.classList.remove('is-sound'); }
      });
    });
    $$('.gal', works).forEach(function (g) {
      var imgs = $$('.gal__img', g), dots = $$('.gal__dot', g), cur = 0, t = null;
      var go = function (k) {
        cur = (k + imgs.length) % imgs.length;
        imgs.forEach(function (im, i) { im.classList.toggle('is-on', i === cur); });
        dots.forEach(function (d, i) { d.classList.toggle('is-on', i === cur); });
        clearTimeout(t); if (!reduce) t = setTimeout(function () { go(cur + 1); }, 4000);
      };
      dots.forEach(function (d) { d.addEventListener('click', function (e) { e.stopPropagation(); go(+d.dataset.k); }); });
      go(0);
    });
    var mt = $('.mslider__track', works);
    if (mt) {
      var stepM = function () { var c = $$('.work', mt); return c.length > 1 ? c[1].offsetLeft - c[0].offsetLeft : mt.clientWidth; };
      var syncM = function () {
        $$('.mslider__nav .slider__btn', works).forEach(function (b) {
          b.disabled = (+b.dataset.dir < 0) ? mt.scrollLeft <= 4 : mt.scrollLeft >= mt.scrollWidth - mt.clientWidth - 4;
        });
      };
      $$('.mslider__nav .slider__btn', works).forEach(function (b) {
        b.addEventListener('click', function () { mt.scrollBy({ left: stepM() * +b.dataset.dir, behavior: 'smooth' }); });
      });
      mt.addEventListener('scroll', function () { window.requestAnimationFrame(syncM); }, { passive: true });
      syncM();
    }
    if (animate && hasGSAP && !reduce) gsap.from($$('.work, .more', works), { y: 40, opacity: 0, duration: .7, stagger: .08, ease: 'power3.out', clearProps: 'all' });
    if (hasGSAP) ScrollTrigger.refresh();
  }
  var tabs = $$('.tab'), ink = $('.tab__ink');
  function moveInk(btn) { if (ink && btn) { ink.style.left = btn.offsetLeft + 'px'; ink.style.width = btn.offsetWidth + 'px'; } }
  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabs.forEach(function (b) { b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
      btn.classList.add('is-active'); btn.setAttribute('aria-selected', 'true');
      moveInk(btn); renderWorks(btn.dataset.filter, true);
    });
  });
  renderWorks('sites', false);
  moveInk($('.tab.is-active'));
  window.addEventListener('resize', function () { moveInk($('.tab.is-active')); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { moveInk($('.tab.is-active')); });

  /* =========================================================
     DEPOIMENTOS — slider
     ========================================================= */
  function ytId(v) {
    if (!v) return '';
    var m = String(v).match(/(?:youtu\.be\/|v=|shorts\/|embed\/)([\w-]{11})/);
    return m ? m[1] : (/^[\w-]{11}$/.test(v) ? v : '');
  }
  var track = $('#testis');
  if (track) {
    track.innerHTML = (DATA.depoimentos || []).map(function (d) {
      var id = ytId(d.youtube);
      var btn = '<button class="testi__btn" type="button" aria-label="Assistir depoimento de ' + esc(d.nome) + '">' + PLAY + '</button>';
      var media;
      if (d.video) {
        media = '<div class="testi__video" data-video="' + esc(d.video) + '">' + (d.poster ? '<img src="' + esc(d.poster) + '" alt="" loading="lazy">' : '') + btn + '</div>';
      } else if (id) {
        media = '<div class="testi__video ' + (d.formato === 'video' ? 'is-wide' : 'is-short') + '" data-yt="' + id + '"><img src="https://i.ytimg.com/vi/' + id + '/hqdefault.jpg" alt="" loading="lazy">' + btn + '</div>';
      } else {
        media = '<div class="testi__video"><div class="ph"><span>[VÍDEO DO YOUTUBE]</span></div></div>';
      }
      return '<article class="testi glass spot">' + media +
        (d.frase ? '<p class="testi__quote">“' + esc(d.frase) + '”</p>' : '') +
        '<p class="testi__who testi__name">' + esc(d.nome) + '</p></article>';
    }).join('');

    var cards = $$('.testi', track), dotsEl = $('#tDots'), prev = $('#tPrev'), next = $('#tNext');
    var stepW = function () { return cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : track.clientWidth; };
    var pages = function () { return Math.max(1, Math.round((track.scrollWidth - track.clientWidth) / stepW()) + 1); };
    var buildDots = function () {
      if (!dotsEl) return;
      var n = pages();
      dotsEl.innerHTML = new Array(n + 1).join('<i></i>');
      sync();
    };
    var sync = function () {
      var i = Math.round(track.scrollLeft / stepW());
      $$('i', dotsEl).forEach(function (d, k) { d.classList.toggle('on', k === i); });
      if (prev) prev.disabled = track.scrollLeft <= 4;
      if (next) next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;
    };
    if (prev) prev.addEventListener('click', function () { track.scrollBy({ left: -stepW(), behavior: 'smooth' }); });
    if (next) next.addEventListener('click', function () { track.scrollBy({ left: stepW(), behavior: 'smooth' }); });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); track.scrollBy({ left: stepW(), behavior: 'smooth' }); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); track.scrollBy({ left: -stepW(), behavior: 'smooth' }); }
    });
    window.addEventListener('resize', buildDots);
    buildDots();

    // arrastar com o mouse
    var down = false, startX = 0, startL = 0, moved = false;
    track.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse') return;
      down = true; moved = false; startX = e.clientX; startL = track.scrollLeft;
    });
    window.addEventListener('pointermove', function (e) {
      if (!down) return;
      var dx = e.clientX - startX;
      if (Math.abs(dx) > 4) { moved = true; track.classList.add('is-drag'); }
      track.scrollLeft = startL - dx;
    });
    window.addEventListener('pointerup', function () {
      if (!down) return; down = false;
      if (track.classList.contains('is-drag')) {
        track.classList.remove('is-drag');
        var i = Math.round(track.scrollLeft / stepW());
        track.scrollTo({ left: i * stepW(), behavior: 'smooth' });
      }
    });
    track.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);

    $$('[data-video]', track).forEach(function (box) {
      box.addEventListener('click', function () {
        if (box.querySelector('video')) return;
        $$('video', track).forEach(function (v) { v.pause(); });
        var v = document.createElement('video');
        v.src = box.dataset.video; v.controls = true; v.autoplay = true; v.playsInline = true;
        box.appendChild(v); v.play().catch(function () {});
      });
    });
    $$('[data-yt]', track).forEach(function (box) {
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

  bindSpots(document);

  /* =========================================================
     FORMULÁRIO
     ========================================================= */
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
    var normInsta = function (v) {
      v = String(v || '').trim();
      var m = v.match(/instagram\.com\/([A-Za-z0-9._]+)/i);
      if (m) v = m[1];
      v = v.replace(/^@+/, '').replace(/\s+/g, '').replace(/[^A-Za-z0-9._]/g, '');
      return v ? '@' + v : '';
    };
    fInsta.addEventListener('blur', function () { fInsta.value = normInsta(fInsta.value); });
    var setErr = function (input, bad) { input.closest('.field').classList.toggle('is-invalid', bad); return bad; };
    var validate = function () {
      var bad = false, first = null;
      [
        [fNome, fNome.value.trim().length < 2],
        [fWhats, !/^\d{10,11}$/.test(fWhats.value.replace(/\D/g, ''))],
        [fInsta, normInsta(fInsta.value).length < 3],
        [fServ, !fServ.value]
      ].forEach(function (c) { if (setErr(c[0], c[1])) { bad = true; first = first || c[0]; } });
      if (first) first.focus();
      return !bad;
    };
    [fNome, fWhats, fInsta, fServ].forEach(function (el) {
      ['input', 'change'].forEach(function (ev) { el.addEventListener(ev, function () { el.closest('.field').classList.remove('is-invalid'); }); });
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
        try { window.fbq && window.fbq('track', 'Lead', { content_name: servicoTxt }); } catch (err) {}
        setTimeout(function () { window.location.href = wa; }, 1200);
      };
      if (payload.website) { finish(); return; } // honeypot

      var ctrl = 'AbortController' in window ? new AbortController() : null;
      var t = setTimeout(function () { ctrl && ctrl.abort(); }, 8000);
      fetch(DATA.endpoint || 'api/lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
        signal: ctrl ? ctrl.signal : undefined
      }).catch(function () { /* se o e-mail falhar, o lead segue pro WhatsApp */ })
        .then(function () { clearTimeout(t); finish(); });
    });
  }

  /* ---------- nav, progresso, botão flutuante ---------- */
  var nav = $('#nav'), fab = $('.fab'), prog = $('.progress span'), cta = $('#contato');
  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    nav.classList.toggle('is-scrolled', y > 30);
    if (prog) prog.style.transform = 'scaleX(' + (max > 0 ? y / max : 0) + ')';
    if (fab && cta) fab.classList.toggle('is-on', y > window.innerHeight * 0.9 && cta.getBoundingClientRect().top > window.innerHeight * 0.6);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  /* menu: traço manuscrito + item ativo conforme a seção na tela */
  var navLinks = $$('.nav__links a');
  navLinks.forEach(function (l) {
    l.insertAdjacentHTML('beforeend', '<svg class="scrib" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true">' +
      '<path pathLength="1" d="M3 6.4C12 4.2 19 7.6 30 5.4S47 3.6 58 6.2 78 4.4 90 5.6c4 .4 6 .2 7-.4"/>' +
      '<path pathLength="1" d="M8 8.6C24 7.2 40 8.8 56 7.8S82 7.4 94 8.2"/></svg>');
  });
  var navSecs = navLinks.map(function (l) { return document.querySelector(l.getAttribute('href')); });
  var navLock = 0;
  var setActive = function (i) { navLinks.forEach(function (l, k) { l.classList.toggle('is-active', k === i); }); };
  var spyNav = function () {
    if (Date.now() < navLock) return;
    var line = window.innerHeight * 0.4, idx = -1;
    navSecs.forEach(function (sec, k) { if (sec && sec.getBoundingClientRect().top <= line) idx = k; });
    var cta = document.getElementById('contato');
    if (cta && cta.getBoundingClientRect().top <= line) idx = navSecs.length - 1; // no CTA mantém "Sobre"
    setActive(idx);
  };
  navLinks.forEach(function (l, k) { l.addEventListener('click', function () { setActive(k); navLock = Date.now() + 1700; setTimeout(spyNav, 1750); }); });
  window.addEventListener('scroll', function () { window.requestAnimationFrame(spyNav); }, { passive: true });
  spyNav();
  onScroll();

  /* =========================================================
     DAQUI PRA BAIXO: só com GSAP e sem reduced motion
     ========================================================= */
  if (!hasGSAP || reduce) {
    bindAnchors(null);
    if (!reduce) startAutoProcess();
    return;
  }
  gsap.registerPlugin(ScrollTrigger);

  var lenis = null;
  if (window.Lenis) {
    lenis = new Lenis({ duration: 1.15, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
    gsap.ticker.lagSmoothing(0);
  }
  bindAnchors(lenis);

  /* ---------- entrada do hero ---------- */
  gsap.timeline({ defaults: { ease: 'power4.out' } })
    .from('.nav', { y: -30, opacity: 0, duration: .8 })
    .from('.blink', { opacity: 0, scale: .4, duration: 1.6, stagger: .15, ease: 'power2.out' }, 0)
    .from('[data-hero="eyebrow"]', { y: 16, opacity: 0, duration: .6 }, .25)
    .from('.hero__line:not(.hero__line--last) .w', { yPercent: 115, duration: 1, stagger: .05 }, .35)
    .from('.hero__line--last .w', { y: 40, opacity: 0, duration: .9 }, .75)
    .from('.hero__pill', { scale: .4, rotate: -14, opacity: 0, duration: .9, ease: 'back.out(2.2)' }, .85)
    .from('[data-hero="sub"]', { y: 20, opacity: 0, duration: .7 }, .95)
    .from('[data-hero="ctas"] > *', { y: 20, opacity: 0, duration: .6, stagger: .08 }, 1.05)
    .from('.hero__stats li', { y: 20, opacity: 0, duration: .6, stagger: .08 }, 1.15)
    .fromTo('.portrait', { clipPath: 'inset(100% 0% 0% 0% round 34px)' }, { clipPath: 'inset(0% 0% 0% 0% round 34px)', duration: 1.4, ease: 'expo.inOut' }, .3)
    .from('.portrait img', { scale: 1.3, duration: 1.8, ease: 'expo.out' }, .6)
    .from('.hero__glowring', { opacity: 0, scale: .6, duration: 1.4, ease: 'power2.out' }, .5)
    .from('.chip-f, .pill-since', { scale: 0, opacity: 0, duration: .7, stagger: .12, ease: 'back.out(2.4)' }, 1.3)
    .from('.hero__spark-a, .hero__spark-b', { scale: 0, rotate: -180, duration: .9, stagger: .1, ease: 'back.out(2)' }, 1.4)
    .from('.hero__spark', { opacity: 0, scale: .5, duration: 2, ease: 'power2.out' }, 0);

  // contadores do hero
  $$('[data-count]').forEach(function (el) {
    var end = +el.dataset.count, o = { v: 0 };
    el.textContent = '0';
    gsap.to(o, { v: end, duration: 2.2, delay: 1.2, ease: 'power2.out', onUpdate: function () { el.textContent = Math.round(o.v); } });
  });

  // chips flutuando
  $$('.chip-f, .pill-since').forEach(function (el, i) {
    gsap.to(el, { y: i % 2 ? 10 : -10, duration: 2.4 + i * .4, ease: 'sine.inOut', repeat: -1, yoyo: true, delay: 2 });
  });

  // parallax com o mouse no hero
  if (finePointer) {
    var hero = $('.hero');
    var depthLayers = $$("[data-depth]").map(function (el) {
      return { d: +el.dataset.depth, x: gsap.quickTo(el, 'x', { duration: .9, ease: 'power3' }), r: gsap.quickTo(el, 'rotation', { duration: .9, ease: 'power3' }) };
    });
    var pX = gsap.quickTo('.portrait', 'x', { duration: 1.1, ease: 'power3' });
    var pY = gsap.quickTo('.portrait', 'y', { duration: 1.1, ease: 'power3' });
    hero.addEventListener('pointermove', function (e) {
      var nx = e.clientX / window.innerWidth - .5, ny = e.clientY / window.innerHeight - .5;
      depthLayers.forEach(function (l) { l.x(nx * 18 * l.d); });
      pX(nx * -10); pY(ny * -8);
    });
    hero.addEventListener('pointerleave', function () { depthLayers.forEach(function (l) { l.x(0); }); pX(0); pY(0); });
  }

  // saída suave do hero ao rolar
  gsap.to('.hero__photo', { yPercent: -8, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.to('.hero__spark', { yPercent: 40, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });

  /* ---------- títulos palavra por palavra ---------- */
  $$('.h2').forEach(function (h) {
    splitWords(h);
    gsap.from($$('.wi', h), { yPercent: 110, duration: .9, stagger: .045, ease: 'power4.out', scrollTrigger: { trigger: h, start: 'top 88%', once: true } });
  });

  /* ---------- revelações ---------- */
  $$('[data-reveal]').forEach(function (el) {
    gsap.from(el, { y: 50, opacity: 0, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: el, start: 'top 90%', once: true } });
  });
  $$('[data-stagger]').forEach(function (group) {
    gsap.from(group.children, { y: 40, opacity: 0, duration: .8, stagger: .1, ease: 'power3.out', scrollTrigger: { trigger: group, start: 'top 88%', once: true } });
  });
  gsap.from('.node', { scale: .5, opacity: 0, duration: .6, stagger: .12, ease: 'back.out(2)', scrollTrigger: { trigger: '.vis-ai', start: 'top 80%', once: true } });

  /* ---------- processo: pin + scrub no desktop ---------- */
  var mm = gsap.matchMedia();
  mm.add('(min-width: 1001px)', function () {
    $$('.svc__visual').forEach(function (v) {
      gsap.fromTo(v, { y: 30 }, { y: -30, ease: 'none', scrollTrigger: { trigger: v, start: 'top bottom', end: 'bottom top', scrub: true } });
    });
    ScrollTrigger.create({
      trigger: '#processPin', start: 'center center', end: '+=160%', pin: true, anticipatePin: 1,
      onUpdate: function (self) { showStep(Math.min(3, Math.floor(self.progress * 4))); }
    });
  });
  mm.add('(max-width: 1000px)', function () {
    ScrollTrigger.create({ trigger: '#processo', start: 'top 70%', once: true, onEnter: startAutoProcess });
  });

  /* ---------- ilustração do processo: inclinação e profundidade com o mouse ---------- */
  if (finePointer) {
    var pvWrap = $('.process__vis');
    if (pvWrap) {
      var rx = gsap.quickTo('.pv', 'rotationX', { duration: .8, ease: 'power3' });
      var ry = gsap.quickTo('.pv', 'rotationY', { duration: .8, ease: 'power3' });
      gsap.set('.pv', { transformPerspective: 1200 });
      var deep = $$('.pv-ic, .pv-cursor').map(function (el, k) {
        return { x: gsap.quickTo(el, 'x', { duration: 1, ease: 'power3' }), y: gsap.quickTo(el, 'y', { duration: 1, ease: 'power3' }), d: el.classList.contains('pv-cursor') ? 6 : 16 + k * 4 };
      });
      pvWrap.addEventListener('pointermove', function (e) {
        var r = pvWrap.getBoundingClientRect();
        var nx = (e.clientX - r.left) / r.width - .5, ny = (e.clientY - r.top) / r.height - .5;
        ry(nx * 10); rx(ny * -8);
        deep.forEach(function (o) { o.x(nx * o.d); o.y(ny * o.d); });
      });
      pvWrap.addEventListener('pointerleave', function () { rx(0); ry(0); deep.forEach(function (o) { o.x(0); o.y(0); }); });
    }
  }

  /* ---------- sobre e CTA ---------- */
  gsap.to('.about__spark', { rotate: 180, ease: 'none', scrollTrigger: { trigger: '#sobre', start: 'top bottom', end: 'bottom top', scrub: true } });
  gsap.from('.about__year', { scale: .6, opacity: 0, duration: 1, ease: 'back.out(2)', scrollTrigger: { trigger: '#sobre', start: 'top 70%', once: true } });
  gsap.fromTo('#ctaPanel', { scale: .92, borderRadius: 80 }, { scale: 1, borderRadius: 40, ease: 'none', scrollTrigger: { trigger: '#contato', start: 'top bottom', end: 'top 35%', scrub: true } });
  gsap.from('.cta__spark-bl', { scale: 0, rotate: -120, duration: 1.2, ease: 'back.out(2)', scrollTrigger: { trigger: '#contato', start: 'top 70%', once: true } });

  /* ---------- botões magnéticos ---------- */
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
    el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
    (function walk(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (n) {
        if (n.nodeType === 3) {
          var frag = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part) return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(' ')); return; }
            var o = document.createElement('span'); o.className = 'wo'; o.setAttribute('aria-hidden', 'true');
            var i = document.createElement('span'); i.className = 'wi'; i.textContent = part;
            o.appendChild(i); frag.appendChild(o);
          });
          node.replaceChild(frag, n);
        } else if (n.nodeType === 1) { walk(n); }
      });
    })(el);
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
        if (id === '#contato') setTimeout(function () { var f = document.getElementById('f-nome'); if (f) f.focus({ preventScroll: true }); }, l ? 1400 : 600);
      });
    });
  }
})();

/* Meta Pixel: clique direto no WhatsApp conta como Contato */
document.addEventListener('click', function (e) {
  var a = e.target.closest && e.target.closest('a[href*="wa.me"]');
  if (a && a.id !== 'waLink') { try { window.fbq && window.fbq('track', 'Contact'); } catch (err) {} }
});
