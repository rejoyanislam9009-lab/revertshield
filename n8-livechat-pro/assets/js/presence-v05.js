(function () {
  'use strict';
  if (!window.N8LCPresence) return;
  var cfg = window.N8LCPresence;
  var timer = null;

  function ping(online, keepalive) {
    var headers = { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' };
    return fetch(cfg.restRoot + 'admin/presence', {
      method: 'POST', headers: headers, credentials: 'same-origin',
      keepalive: !!keepalive, body: JSON.stringify({ online: online !== false })
    }).catch(function () {});
  }

  function start() {
    ping(true);
    if (timer) window.clearInterval(timer);
    timer = window.setInterval(function () {
      if (!document.hidden) ping(true);
    }, 25000);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) ping(true);
  });
  window.addEventListener('pagehide', function () { ping(false, true); });
  start();
}());
