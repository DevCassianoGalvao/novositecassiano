<?php
/**
 * Injeta na página /site-por-assinatura as tags cadastradas no painel
 * (Pixel da Meta, Google Tag Manager, GA4, qualquer script).
 * A página carrega este arquivo com <script defer src="/api/tags.php">.
 */

declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=120');
header('X-Content-Type-Options: nosniff');

$cfg = null;
try {
    require dirname(__DIR__) . '/admin/inc/bootstrap.php';
    require dirname(__DIR__) . '/admin/inc/tags.php';
    $cfg = tags_config();
} catch (Throwable $e) {
    // sem banco ainda (antes da instalação): mantém o Pixel que já estava no site
    try {
        require_once dirname(__DIR__) . '/admin/inc/tags.php';
        $cfg = ['head' => default_tags_head(), 'body' => '', 'enabled' => true];
    } catch (Throwable $e2) {
        $cfg = ['head' => '', 'body' => '', 'enabled' => false];
    }
}

$head = $cfg['enabled'] ? $cfg['head'] : '';
$body = $cfg['enabled'] ? $cfg['body'] : '';
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
?>
(function () {
  var HEAD = <?= json_encode($head, $flags) ?>;
  var BODY = <?= json_encode($body, $flags) ?>;
  function inject(html, target) {
    if (!html || !target) return;
    var tpl = document.createElement('template');
    tpl.innerHTML = html;
    Array.prototype.forEach.call(tpl.content.childNodes, function (n) {
      try {
        if (n.nodeName === 'SCRIPT') {
          var s = document.createElement('script');
          Array.prototype.forEach.call(n.attributes, function (a) { s.setAttribute(a.name, a.value); });
          if (!n.src) s.text = n.textContent;
          target.appendChild(s);
        } else if (n.nodeType === 1 && n.nodeName !== 'NOSCRIPT') {
          target.appendChild(document.importNode(n, true));
        }
      } catch (e) {}
    });
  }
  inject(HEAD, document.head);
  if (document.body) inject(BODY, document.body);
  else document.addEventListener('DOMContentLoaded', function () { inject(BODY, document.body); });
  window.__saTags = true;
  try { document.dispatchEvent(new Event('sa:tags')); } catch (e) {}
})();
