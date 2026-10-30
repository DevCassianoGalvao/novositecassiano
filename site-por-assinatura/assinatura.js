/* =========================================================
   Site por assinatura — interações (JS puro, sem bibliotecas)
   Entradas animadas, ilustrações, portfólio (só sites),
   depoimentos, FAQ, pop-up de planos e eventos do Meta Pixel.
   ========================================================= */
(function () {
  'use strict';

  var DATA = window.SITE_DATA || {};
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  // os caminhos do data.js são relativos à raiz do site
  var asset = function (p) { return p && /^assets\//.test(p) ? '/' + p : p; };
  // Eventos: vão para o Pixel (assim que ele carregar) e para a dataLayer (Google Tag Manager).
  // As tags são cadastradas no painel (/admin > Rastreamento) e carregadas por /api/tags.php.
  var pendingEv = [], flushTimer = null;
  var sendPixel = function (ev, params) {
    if (window.fbq) { try { window.fbq('track', ev, params); } catch (e) {} return; }
    pendingEv.push([ev, params]);
    if (flushTimer) return;
    var tries = 0;
    flushTimer = setInterval(function () {
      tries++;
      if (window.fbq || tries > 40) {
        clearInterval(flushTimer); flushTimer = null;
        var q = pendingEv.splice(0);
        if (window.fbq) q.forEach(function (a) { try { window.fbq('track', a[0], a[1]); } catch (e) {} });
      }
    }, 250);
  };
  var track = function (ev, params) {
    params = params || {};
    try {
      window.dataLayer = window.dataLayer || [];
      var dl = { event: ev };
      Object.keys(params).forEach(function (k) { dl[k] = params[k]; });
      window.dataLayer.push(dl);
    } catch (e) {}
    sendPixel(ev, params);
  };
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href*="wa.me"]');
    if (a) track('Contact', { content_name: 'WhatsApp · site por assinatura' });
  });

  var ano = $('#ano');
  if (ano) ano.textContent = new Date().getFullYear();

  /* ---------- UTMs: guarda ao carregar e repassa no envio ---------- */
  var UTM = {};
  (function () {
    var KEY = 'sa_utm', saved = {};
    try { saved = JSON.parse(sessionStorage.getItem(KEY) || '{}') || {}; } catch (e) {}
    var q = new URLSearchParams(location.search), found = false;
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid'].forEach(function (k) {
      if (q.get(k)) { UTM[k] = q.get(k).slice(0, 200); found = true; }
    });
    if (!found) UTM = saved;
    try { sessionStorage.setItem(KEY, JSON.stringify(UTM)); } catch (e) {}
  })();

  /* ---------- luz que segue o cursor nos cards ---------- */
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

  /* ---------- header e barra de progresso ---------- */
  var nav = $('#nav'), bar = $('.progress span');
  var onScroll = function () {
    var y = window.scrollY, h = document.documentElement.scrollHeight - window.innerHeight;
    if (nav) nav.classList.toggle('is-scrolled', y > 20);
    if (bar) bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, y / h) : 0) + ')';
  };
  window.addEventListener('scroll', function () { window.requestAnimationFrame(onScroll); }, { passive: true });
  onScroll();

  /* ---------- entradas: fade + leve deslocamento, stagger em listas ---------- */
  var show = function (el) { el.classList.add('is-in'); };

  var revealEls = $$('[data-reveal], [data-step]');
  $$('[data-stagger]').forEach(function (g) {
    $$(':scope > *', g).forEach(function (c, i) { c.style.setProperty('--dl', Math.min(i * 0.08, 0.6) + 's'); revealEls.push(c); });
  });
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { show(e.target); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(show);
  }

  /* ---------- contadores do hero ---------- */
  $$('[data-count]').forEach(function (el) {
    if (reduce) return;
    var end = +el.dataset.count, t0 = null, dur = 1800, box = el.parentNode;
    box.style.setProperty('--w', box.getBoundingClientRect().width + 'px'); // reserva a largura: o número subindo não empurra nada
    el.textContent = '0';
    var step = function (t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * e);
      if (p < 1) window.requestAnimationFrame(step);
    };
    setTimeout(function () { window.requestAnimationFrame(step); }, 700);
  });

  /* ---------- como funciona: linha da timeline acompanha o scroll ---------- */
  var stepsEl = $('.sa-steps');
  if (stepsEl) {
    var updSteps = function () {
      var r = stepsEl.getBoundingClientRect(), vh = window.innerHeight;
      var p = (vh * 0.7 - r.top) / r.height;
      stepsEl.style.setProperty('--prog', Math.max(0, Math.min(1, p)).toFixed(3));
    };
    window.addEventListener('scroll', function () { window.requestAnimationFrame(updSteps); }, { passive: true });
    updSteps();
  }

  /* ---------- relógio de 48h + checklist (loop quando visível) ---------- */
  var clock = $('.sa-clock__card');
  if (clock) {
    var ring = $('.sa-ring__fg', clock), clockTxt = $('#saClock'), checks = $$('.sa-checks li', clock);
    var running = false, raf = null, start = 0, CYCLE = 7000;
    var paint = function (p) {
      var hrs = 48 * (1 - p), hh = Math.floor(hrs), mm = Math.floor((hrs - hh) * 60);
      clockTxt.textContent = (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
      ring.style.strokeDashoffset = (1 - p).toFixed(4);
      checks.forEach(function (li, i) { li.classList.toggle('on', p >= (i + 0.5) / checks.length); });
    };
    var loop = function (t) {
      if (!start) start = t;
      var e = (t - start) % (CYCLE + 1600);
      paint(Math.min(1, e / CYCLE));
      raf = window.requestAnimationFrame(loop);
    };
    if (reduce) paint(1);
    else if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        var vis = en[0].isIntersecting;
        if (vis && !running) { running = true; start = 0; raf = window.requestAnimationFrame(loop); }
        if (!vis && running) { running = false; window.cancelAnimationFrame(raf); }
      }).observe(clock);
    }
  }

  /* ---------- seções fora da tela: pausa as animações CSS ---------- */
  if ('IntersectionObserver' in window) {
    var pauseIO = new IntersectionObserver(function (es) {
      es.forEach(function (e) { e.target.classList.toggle('is-off', !e.isIntersecting); });
    }, { rootMargin: '200px 0px' });
    $$('main > section, main > div').forEach(function (el) { pauseIO.observe(el); });
  }

  /* =========================================================
     PORTFÓLIO — só a categoria Sites
     ========================================================= */
  var ARROW = '<svg viewBox="0 0 24 24"><path d="M7 17L17 7M9 7h8v8"/></svg>';
  var works = $('#works');
  if (works) {
    var sites = (DATA.projetos || []).filter(function (p) { return p.cat === 'sites' && p.img; });
    var card = function (p, clone) {
      var tag = p.url ? 'a' : 'div';
      var attrs = p.url ? ' href="' + esc(p.url) + '" target="_blank" rel="noopener"' + (clone ? ' tabindex="-1" aria-hidden="true"' : ' aria-label="Visitar o site ' + esc(p.nome) + '"') : '';
      return '<' + tag + ' class="work glass spot"' + attrs + '><div class="work__shot"><img src="' + esc(asset(p.img)) + '" alt="' + (clone ? '' : 'Tela inicial do site ' + esc(p.nome)) + '" width="1200" height="750" loading="lazy" decoding="async"></div>' +
        '<div class="work__meta"><div><span class="work__kind">' + esc(p.tipo) + '</span><h3 class="work__name">' + esc(p.nome) + '</h3>' +
        (p.desc ? '<p class="work__desc">' + esc(p.desc) + '</p>' : '') + '</div>' + (p.url ? '<span class="work__go" aria-hidden="true">' + ARROW + '</span>' : '') + '</div></' + tag + '>';
    };
    // três cópias da lista: o scroll fica sempre na do meio e "pula" sem emenda quando chega numa ponta
    works.innerHTML = '<div class="sa-port__track">' + sites.map(function (p) { return card(p, true); }).join('') +
      sites.map(function (p) { return card(p, false); }).join('') + sites.map(function (p) { return card(p, true); }).join('') + '</div>';
    works.insertAdjacentHTML('afterend', '<div class="sa-port__nav"><button class="slider__btn" type="button" data-dir="-1" aria-label="Site anterior"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>' +
      '<button class="slider__btn" type="button" data-dir="1" aria-label="Próximo site"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button></div>');
    var cardsP = $$('.work', works), n = sites.length;
    var setW = function () { return cardsP[n].offsetLeft - cardsP[0].offsetLeft; };
    var stepP = function () { return cardsP[1].offsetLeft - cardsP[0].offsetLeft; };
    var wrap = function () {
      var w = setW();
      if (works.scrollLeft < w * 0.5) works.scrollLeft += w;
      else if (works.scrollLeft > w * 1.5) works.scrollLeft -= w;
    };
    var resumeAt = 0, pos = 0;
    var hold = function (ms) { resumeAt = Date.now() + (ms || 2500); };
    var startPort = function () { works.scrollLeft = setW(); pos = works.scrollLeft; };
    if (document.readyState === 'complete') startPort(); else window.addEventListener('load', startPort);
    startPort();
    works.addEventListener('scroll', function () { wrap(); if (Date.now() < resumeAt) pos = works.scrollLeft; }, { passive: true });

    // rolagem automática contínua (pausa enquanto a pessoa mexe e volta sozinha)
    var portVisible = true, last = 0;
    if ('IntersectionObserver' in window) new IntersectionObserver(function (es) { portVisible = es[0].isIntersecting; }).observe(works);
    var tick = function (t) {
      var dt = last ? Math.min(64, t - last) : 16; last = t;
      if (!reduce && portVisible && Date.now() >= resumeAt && !dragging) {
        pos += dt * 0.045; // ~45px por segundo
        works.scrollLeft = pos;
        if (Math.abs(works.scrollLeft - pos) > 2) pos = works.scrollLeft; // o wrap mudou a posição
      }
      window.requestAnimationFrame(tick);
    };
    window.requestAnimationFrame(tick);

    // arrastar com o mouse (no celular o dedo já rola nativo)
    var dragging = false, sx = 0, sl = 0, moved = false;
    works.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse') return;
      dragging = true; moved = false; sx = e.clientX; sl = works.scrollLeft; hold();
    });
    window.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      var dx = e.clientX - sx;
      if (Math.abs(dx) > 5) { moved = true; works.classList.add('is-drag'); }
      works.scrollLeft = sl - dx; pos = works.scrollLeft; hold();
    });
    window.addEventListener('pointerup', function () { if (!dragging) return; dragging = false; works.classList.remove('is-drag'); pos = works.scrollLeft; hold(); });
    works.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
    ['touchstart', 'touchmove', 'wheel'].forEach(function (ev) { works.addEventListener(ev, function () { hold(); }, { passive: true }); });

    $$('.sa-port__nav .slider__btn').forEach(function (b) {
      b.addEventListener('click', function () {
        hold(3000);
        works.scrollBy({ left: stepP() * +b.dataset.dir, behavior: 'smooth' });
        setTimeout(function () { pos = works.scrollLeft; }, 600);
      });
    });
  }

  /* =========================================================
     DEPOIMENTOS — mesmo slider do site principal
     ========================================================= */
  var PLAY = '<svg viewBox="0 0 24 24"><path d="M6 4l14 8-14 8z"/></svg>';
  var ytId = function (v) {
    if (!v) return '';
    var m = String(v).match(/(?:youtu\.be\/|v=|shorts\/|embed\/)([\w-]{11})/);
    return m ? m[1] : (/^[\w-]{11}$/.test(v) ? v : '');
  };
  var tTrack = $('#testis');
  if (tTrack) {
    tTrack.innerHTML = (DATA.depoimentos || []).map(function (d) {
      var id = ytId(d.youtube);
      var btn = '<button class="testi__btn" type="button" aria-label="Assistir depoimento de ' + esc(d.nome) + '">' + PLAY + '</button>';
      var media;
      if (d.video) media = '<div class="testi__video" data-video="' + esc(asset(d.video)) + '">' + (d.poster ? '<img src="' + esc(asset(d.poster)) + '" alt="" loading="lazy">' : '') + btn + '</div>';
      else if (id) media = '<div class="testi__video is-short" data-yt="' + id + '"><img src="https://i.ytimg.com/vi/' + id + '/hqdefault.jpg" alt="" loading="lazy">' + btn + '</div>';
      else return '';
      return '<article class="testi glass spot">' + media + (d.frase ? '<p class="testi__quote">“' + esc(d.frase) + '”</p>' : '') + '<p class="testi__who testi__name">' + esc(d.nome) + '</p></article>';
    }).join('');

    var cards = $$('.testi', tTrack), dotsEl = $('#tDots'), prev = $('#tPrev'), next = $('#tNext');
    var stepW = function () { return cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : tTrack.clientWidth; };
    var sync = function () {
      var i = Math.round(tTrack.scrollLeft / stepW());
      $$('i', dotsEl).forEach(function (d, k) { d.classList.toggle('on', k === i); });
      if (prev) prev.disabled = tTrack.scrollLeft <= 4;
      if (next) next.disabled = tTrack.scrollLeft >= tTrack.scrollWidth - tTrack.clientWidth - 4;
    };
    var buildDots = function () {
      var n = Math.max(1, Math.round((tTrack.scrollWidth - tTrack.clientWidth) / stepW()) + 1);
      if (dotsEl) dotsEl.innerHTML = new Array(n + 1).join('<i></i>');
      sync();
    };
    if (prev) prev.addEventListener('click', function () { tTrack.scrollBy({ left: -stepW(), behavior: 'smooth' }); });
    if (next) next.addEventListener('click', function () { tTrack.scrollBy({ left: stepW(), behavior: 'smooth' }); });
    tTrack.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
    tTrack.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); tTrack.scrollBy({ left: stepW(), behavior: 'smooth' }); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); tTrack.scrollBy({ left: -stepW(), behavior: 'smooth' }); }
    });
    window.addEventListener('resize', buildDots);
    buildDots();

    $$('[data-video]', tTrack).forEach(function (box) {
      box.addEventListener('click', function () {
        if (box.querySelector('video')) return;
        $$('video', tTrack).forEach(function (v) { v.pause(); });
        var v = document.createElement('video');
        v.src = box.dataset.video; v.controls = true; v.autoplay = true; v.playsInline = true;
        box.appendChild(v); v.play().catch(function () {});
      });
    });
    $$('[data-yt]', tTrack).forEach(function (box) {
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

  /* =========================================================
     FAQ — accordion, uma aberta por vez
     ========================================================= */
  var faqItems = $$('.sa-acc__it');
  faqItems.forEach(function (it, k) {
    var btn = $('button', it), panel = $('.sa-acc__p', it), pid = 'faq-p' + k, bid = 'faq-b' + k;
    btn.id = bid; panel.id = pid; panel.setAttribute('role', 'region'); panel.setAttribute('aria-labelledby', bid);
    btn.setAttribute('aria-controls', pid);
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      faqItems.forEach(function (o) {
        $('button', o).setAttribute('aria-expanded', 'false');
        $('.sa-acc__p', o).hidden = true;
        o.classList.remove('is-open');
      });
      if (!open) {
        btn.setAttribute('aria-expanded', 'true');
        panel.hidden = false; it.classList.add('is-open');
        panel.classList.remove('is-anim'); void panel.offsetWidth; panel.classList.add('is-anim');
      }
    });
  });

  /* =========================================================
     PIXEL: ViewContent quando os planos entram na tela
     ========================================================= */
  var plansSec = $('#planos');
  if (plansSec && 'IntersectionObserver' in window) {
    var vcIO = new IntersectionObserver(function (es) {
      if (es[0].isIntersecting) { track('ViewContent', { content_name: 'Planos' }); vcIO.disconnect(); }
    }, { threshold: 0.3 });
    vcIO.observe(plansSec);
  }

  /* =========================================================
     POP-UP DE PLANOS (2 passos)
     ========================================================= */
  var PLANS = {
    essencial: { nome: 'Essencial', preco: 67 },
    completo: { nome: 'Completo', preco: 97 }
  };
  var modal = $('#planModal'), box = $('.sa-modal__box', modal);
  var step1 = $('[data-mstep="1"]', modal), step2 = $('[data-mstep="2"]', modal), stepOk = $('[data-mstep="ok"]', modal);
  var opts = $$('.sa-opt', modal), pickErr = $('#mPickErr'), planName = $('#mPlanName');
  var dots = $$('.sa-modal__steps i', modal), form = $('#mForm'), submitBtn = $('#mSubmit');
  var chosen = '', lastFocus = null;

  /* ---------- período: mensal, trimestral (-10%), semestral (-15%), anual (-20%) ---------- */
  var DISC = { 1: 0, 3: 10, 6: 15, 12: 20 }, CYC = { 1: 'Mensal', 3: 'Trimestral', 6: 'Semestral', 12: 'Anual' };
  var PER = { 1: 'por mês', 3: 'a cada 3 meses', 6: 'a cada 6 meses', 12: 'por ano' };
  var cycle = 1, mTotal = $('#mTotal');
  var brl = function (n) { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var total = function (m, c) { return Math.round(m * 100 * c * (100 - DISC[c]) / 100) / 100; };
  var perMonth = function (m, c) { return Math.round(total(m, c) / c * 100) / 100; };
  var short = function (v) { return v % 1 === 0 ? String(v) : brl(v); };
  var updTotal = function () {
    if (!mTotal) return;
    if (!chosen || cycle === 1) { mTotal.hidden = true; return; }
    var m = PLANS[chosen].preco;
    mTotal.innerHTML = 'Você paga <b>R$ ' + brl(total(m, cycle)) + '</b> à vista ' + PER[cycle] + ' e economiza <b>R$ ' + brl(m * cycle - total(m, cycle)) + '</b>.';
    mTotal.hidden = false;
  };
  var setCycle = function (c) {
    cycle = DISC[c] !== undefined ? c : 1;
    $$('.sa-cycle button').forEach(function (b) { var on = +b.dataset.cycle === cycle; b.setAttribute('aria-checked', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1; });
    $$('.sa-plan__price[data-monthly]').forEach(function (p) { $('b', p).textContent = short(perMonth(+p.dataset.monthly, cycle)); });
    $$('[data-bill]').forEach(function (el) {
      var m = +el.dataset.bill;
      el.innerHTML = cycle === 1 ? 'Cobrado todo mês' : 'R$ ' + brl(total(m, cycle)) + ' à vista ' + PER[cycle] + ' · <b>economia de R$ ' + brl(m * cycle - total(m, cycle)) + '</b>';
    });
    $$('.sa-opt__price[data-monthly]').forEach(function (p) { p.innerHTML = 'R$ ' + short(perMonth(+p.dataset.monthly, cycle)) + '<small>/mês</small>'; });
    updTotal();
  };
  $$('.sa-cycle').forEach(function (g) {
    g.addEventListener('click', function (e) { var b = e.target.closest('button[data-cycle]'); if (b) setCycle(+b.dataset.cycle); });
    g.addEventListener('keydown', function (e) {
      if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(e.key) < 0) return;
      e.preventDefault();
      var bs = $$('button', g), i = bs.findIndex(function (b) { return +b.dataset.cycle === cycle; });
      i = (i + (e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? -1 : 1) + bs.length) % bs.length;
      setCycle(+bs[i].dataset.cycle); bs[i].focus();
    });
  });
  setCycle(1);

  var focusables = function () {
    return $$('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])', box).filter(function (el) {
      return !el.disabled && !el.closest('[hidden]') && el.offsetParent !== null;
    });
  };
  var pick = function (key) {
    chosen = PLANS[key] ? key : '';
    opts.forEach(function (o) { o.setAttribute('aria-checked', o.dataset.pick === chosen ? 'true' : 'false'); o.tabIndex = (o.dataset.pick === chosen || (!chosen && o === opts[0])) ? 0 : -1; });
    if (chosen) pickErr.hidden = true;
    updTotal();
  };
  var goStep = function (n) {
    step1.hidden = n !== 1; step2.hidden = n !== 2; stepOk.hidden = n !== 'ok';
    $('.sa-modal__steps', modal).style.visibility = n === 'ok' ? 'hidden' : '';
    dots.forEach(function (d, i) { d.classList.toggle('on', n === 'ok' || i < n); });
    box.setAttribute('aria-labelledby', n === 1 ? 'mTitle1' : (n === 2 ? 'mTitle2' : 'mOkTitle'));
    box.scrollTop = 0;
    var target = n === 1 ? (opts.filter(function (o) { return o.tabIndex === 0; })[0]) : (n === 2 ? $('#m-nome') : $('#mOkTitle'));
    if (target) setTimeout(function () { target.focus(); }, 60);
  };
  var openModal = function (plan) {
    lastFocus = document.activeElement;
    pick(plan || chosen);
    modal.hidden = false;
    document.body.classList.add('sa-lock');
    window.requestAnimationFrame(function () { modal.classList.add('is-open'); });
    // se veio de um botão de plano, o plano já vem escolhido; mesmo assim mostra o passo 1 pra confirmar
    goStep(1);
  };
  var closeModal = function () {
    modal.classList.remove('is-open');
    document.body.classList.remove('sa-lock');
    setTimeout(function () {
      modal.hidden = true;
      if (!stepOk.hidden) { form.reset(); goStep(1); }
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }, reduce ? 0 : 320);
  };

  $$('[data-plan]').forEach(function (b) {
    b.addEventListener('click', function () { openModal(b.dataset.plan); });
  });
  $$('[data-close]', modal).forEach(function (b) { b.addEventListener('click', closeModal); });
  opts.forEach(function (o) {
    o.addEventListener('click', function () { pick(o.dataset.pick); });
  });
  $('.sa-opts', modal).addEventListener('keydown', function (e) {
    if (['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft'].indexOf(e.key) < 0) return;
    e.preventDefault();
    var i = Math.max(0, opts.indexOf(document.activeElement));
    var n = opts[(i + (e.key === 'ArrowDown' || e.key === 'ArrowRight' ? 1 : opts.length - 1)) % opts.length];
    pick(n.dataset.pick); n.focus();
  });
  modal.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
    if (e.key !== 'Tab') return;
    var f = focusables(); if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  $('#mNext').addEventListener('click', function () {
    if (!chosen) { pickErr.hidden = false; return; }
    var pm = PLANS[chosen].preco;
    planName.textContent = PLANS[chosen].nome + ' · ' + CYC[cycle] + ' · ' + (cycle === 1 ? 'R$ ' + pm + '/mês' : 'R$ ' + brl(total(pm, cycle)) + ' à vista (R$ ' + short(perMonth(pm, cycle)) + '/mês)');
    goStep(2);
  });
  $('#mBack').addEventListener('click', function () { goStep(1); });

  /* ---------- passo 2: validação e máscara ---------- */
  var fNome = $('#m-nome'), fWhats = $('#m-whats'), fEmail = $('#m-email'), fHp = $('#m-site');
  var mask = function (v) {
    var d = v.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d.length ? '(' + d : '';
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  };
  fWhats.addEventListener('input', function () { fWhats.value = mask(fWhats.value); });
  var setErr = function (input, bad) { input.closest('.field').classList.toggle('is-invalid', bad); input.setAttribute('aria-invalid', bad ? 'true' : 'false'); return bad; };
  [fNome, fWhats, fEmail].forEach(function (el) {
    el.addEventListener('input', function () { setErr(el, false); });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var bad = [
      setErr(fNome, fNome.value.trim().length < 2),
      setErr(fWhats, fWhats.value.replace(/\D/g, '').length < 10),
      setErr(fEmail, !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(fEmail.value.trim()))
    ];
    if (bad.some(Boolean)) { ($('.field.is-invalid input', form) || fNome).focus(); return; }

    var payload = {
      nome: fNome.value.trim(),
      whatsapp: fWhats.value.replace(/\D/g, ''),
      email: fEmail.value.trim(),
      plano: chosen,
      ciclo: cycle,
      website: fHp.value,
      pagina: location.href
    };
    Object.keys(UTM).forEach(function (k) { payload[k] = UTM[k]; });

    track('Lead', { content_name: PLANS[chosen].nome });

    // O lead é gravado no banco (painel /admin) e avisado por e-mail pelo api/assinatura.php.
    submitBtn.classList.add('is-loading');
    $('.btn__label', submitBtn).textContent = 'Enviando...';
    var finish = function () {
      submitBtn.classList.remove('is-loading');
      $('.btn__label', submitBtn).textContent = 'Ir para o pagamento';
      goStep('ok');
    };
    var ctrl = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, 8000);
    fetch('/api/assinatura.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: ctrl ? ctrl.signal : undefined
    }).then(function (r) { return r.json(); }).catch(function () { return null; }).then(function (d) {
      clearTimeout(timer);
      // lead salvo: segue para a página de pagamento (Pix ou cartão)
      if (d && d.ok && d.pay_url && /^https?:\/\//.test(d.pay_url)) {
        track('InitiateCheckout', { value: total(PLANS[chosen].preco, cycle), currency: 'BRL' });
        $('.btn__label', submitBtn).textContent = 'Abrindo o pagamento...';
        setTimeout(function () { location.href = d.pay_url; }, 250);
        return;
      }
      finish();
    });
  });

  bindSpots(document);
})();
