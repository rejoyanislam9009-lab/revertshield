(function () {
  'use strict';
  var cfg = window.N8LCPro || {};
  var root = document.getElementById('n8lc-widget-root');
  if (!root) return;

  function excluded() {
    var path = window.location.href;
    return (cfg.pageExclusions || []).some(function (p) { return p && path.indexOf(p) !== -1; });
  }
  if (excluded()) { root.style.display = 'none'; return; }

  var mobile = window.matchMedia && window.matchMedia('(max-width: 782px)').matches;
  if ((mobile && cfg.hideMobile) || (!mobile && cfg.hideDesktop)) { root.style.display = 'none'; return; }

  root.style.setProperty('--n8lc-offset-x', Math.max(0, Number(cfg.offsetX || 20)) + 'px');
  root.style.setProperty('--n8lc-offset-y', Math.max(0, Number(cfg.offsetY || 20)) + 'px');
  root.style.setProperty('--n8lc-z-index', Math.max(1000, Number(cfg.zIndex || 999999)));
  root.style.setProperty('--n8lc-font-scale', Math.max(80, Math.min(140, Number(cfg.fontScale || 100))) / 100);
  if (cfg.reduceMotion) root.classList.add('n8lc-reduce-motion');
  if (cfg.rtl) root.classList.add('n8lc-force-rtl');

  if (window.N8LCWidget) {
    window.N8LCWidget.customFields = cfg.customFields || [];
    window.N8LCWidget.consentEnabled = !!cfg.consentEnabled;
    window.N8LCWidget.consentRequired = !!cfg.consentRequired;
    window.N8LCWidget.consentText = cfg.consentText || '';
  }


  var knowledgeItems = null;
  function renderKnowledge() {
    if (!cfg.showKnowledge || !Array.isArray(knowledgeItems) || !knowledgeItems.length) return;
    var start = root.querySelector('.n8lc-start');
    if (!start || start.querySelector('.n8lc-kb-suggestions')) return;
    var section = document.createElement('section');
    section.className = 'n8lc-kb-suggestions';
    var heading = document.createElement('strong');
    heading.textContent = 'Popular help';
    section.appendChild(heading);
    knowledgeItems.forEach(function (item) {
      var details = document.createElement('details');
      var summary = document.createElement('summary');
      var excerpt = document.createElement('p');
      summary.textContent = item.title || 'Help article';
      excerpt.textContent = item.excerpt || '';
      details.appendChild(summary);
      details.appendChild(excerpt);
      section.appendChild(details);
    });
    var form = start.querySelector('.n8lc-start-form');
    start.insertBefore(section, form || null);
  }
  function loadKnowledge() {
    if (!cfg.showKnowledge || !cfg.knowledgeUrl || knowledgeItems !== null) return;
    knowledgeItems = [];
    fetch(cfg.knowledgeUrl, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('Knowledge request failed');
      return r.json();
    }).then(function (body) {
      knowledgeItems = Array.isArray(body.items) ? body.items : [];
      renderKnowledge();
    }).catch(function () { knowledgeItems = []; });
  }
  var observer = new MutationObserver(function () { renderKnowledge(); });
  observer.observe(root, { childList: true, subtree: true });
  loadKnowledge();

  Array.prototype.forEach.call(document.querySelectorAll('[data-n8lc-open-chat]'), function (button) {
    button.addEventListener('click', function () {
      var launcher = root.querySelector('.n8lc-launcher');
      if (launcher) { launcher.click(); launcher.focus(); }
    });
  });

  if (cfg.autoOpen) {
    window.setTimeout(function () {
      var launcher = root.querySelector('.n8lc-launcher');
      var panel = root.querySelector('.n8lc-panel');
      if (launcher && panel && !panel.classList.contains('is-open')) launcher.click();
    }, Math.max(0, Number(cfg.autoOpenDelay || 8)) * 1000);
  }
}());
