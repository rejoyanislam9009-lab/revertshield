(function () {
  'use strict';

  if (!window.N8LCWidget) return;

  var cfg = window.N8LCWidget;
  var root = document.getElementById('n8lc-widget-root');
  if (!root) return;

  var storageKey = 'n8lc_session_v1';
  var state = {
    open: false,
    session: null,
    messages: [],
    lastId: 0,
    polling: null,
    heartbeat: null,
    busy: false
  };

  try {
    state.session = JSON.parse(localStorage.getItem(storageKey) || 'null');
  } catch (e) {
    state.session = null;
  }

  root.style.setProperty('--n8lc-accent', cfg.accentColor || '#111827');
  root.classList.add(cfg.position === 'left' ? 'n8lc-left' : 'n8lc-right');

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function api(path, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['Content-Type'] = 'application/json';
    if (state.session && state.session.token) {
      options.headers['X-N8LC-Token'] = state.session.token;
    }
    return fetch(cfg.restRoot + path, options).then(function (res) {
      return res.json().then(function (body) {
        if (!res.ok) throw new Error(body && body.message ? body.message : cfg.i18n.error);
        return body;
      });
    });
  }

  function shell() {
    root.innerHTML = '' +
      '<button class="n8lc-launcher" type="button" aria-expanded="' + (state.open ? 'true' : 'false') + '" aria-controls="n8lc-panel">' +
        '<span class="n8lc-launcher-icon">' + (state.open ? '&#10005;' : '&#128172;') + '</span>' +
      '</button>' +
      '<section id="n8lc-panel" class="n8lc-panel ' + (state.open ? 'is-open' : '') + '" aria-hidden="' + (state.open ? 'false' : 'true') + '">' +
        '<header class="n8lc-header"><div><strong>' + esc(cfg.title) + '</strong><span>Support</span></div><button type="button" class="n8lc-close" aria-label="' + esc(cfg.i18n.close) + '">&#10005;</button></header>' +
        '<div class="n8lc-content"></div>' +
      '</section>';

    root.querySelector('.n8lc-launcher').addEventListener('click', toggle);
    root.querySelector('.n8lc-close').addEventListener('click', toggle);
    renderContent();
  }

  function toggle() {
    state.open = !state.open;
    shell();
    if (state.open && state.session) {
      loadMessages();
      startPolling();
    } else if (!state.open) {
      stopPolling();
    }
  }

  function renderContent() {
    var content = root.querySelector('.n8lc-content');
    if (!content) return;
    if (!state.session) {
      content.innerHTML = startForm();
      var form = content.querySelector('.n8lc-start-form');
      form.addEventListener('submit', startSession);
      return;
    }
    content.innerHTML = chatView();
    bindChat();
    scrollBottom();
  }

  function startForm() {
    var department = '';
    if (cfg.departments && cfg.departments.length > 1) {
      department = '<label>' + esc(cfg.i18n.department) + '<select name="department_id">' + cfg.departments.map(function (d) {
        return '<option value="' + Number(d.id) + '">' + esc(d.name) + '</option>';
      }).join('') + '</select></label>';
    }
    return '<div class="n8lc-start">' +
      '<p>Start a conversation with our team.</p>' +
      '<form class="n8lc-start-form">' +
        '<label>' + esc(cfg.i18n.name) + '<input name="name" type="text" autocomplete="name" maxlength="190"></label>' +
        '<label>' + esc(cfg.i18n.email) + (cfg.requireEmail ? ' *' : '') + '<input name="email" type="email" autocomplete="email" maxlength="190" ' + (cfg.requireEmail ? 'required' : '') + '></label>' +
        '<label>' + esc(cfg.i18n.phone) + '<input name="phone" type="text" autocomplete="tel" maxlength="80"></label>' +
        department +
        '<div class="n8lc-form-error" role="alert"></div>' +
        '<button type="submit" class="n8lc-primary">' + esc(cfg.i18n.start) + '</button>' +
      '</form>' +
    '</div>';
  }

  function startSession(ev) {
    ev.preventDefault();
    if (state.busy) return;
    state.busy = true;
    var form = ev.currentTarget;
    var data = new FormData(form);
    var button = form.querySelector('button[type="submit"]');
    var error = form.querySelector('.n8lc-form-error');
    button.disabled = true;
    error.textContent = '';

    api('session', {
      method: 'POST',
      body: JSON.stringify({
        name: data.get('name') || '',
        email: data.get('email') || '',
        phone: data.get('phone') || '',
        department_id: data.get('department_id') || 0,
        url: window.location.href,
        referrer: document.referrer,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        language: navigator.language || '',
        screen: window.screen ? window.screen.width + 'x' + window.screen.height : ''
      })
    }).then(function (session) {
      state.session = session;
      localStorage.setItem(storageKey, JSON.stringify(session));
      state.messages = [];
      state.lastId = 0;
      renderContent();
      loadMessages();
      startPolling();
    }).catch(function (err) {
      error.textContent = err.message || cfg.i18n.error;
    }).finally(function () {
      state.busy = false;
      button.disabled = false;
    });
  }

  function chatView() {
    var messages = state.messages.map(function (msg) {
      var who = msg.sender_type === 'visitor' ? 'visitor' : (msg.sender_type === 'system' ? 'system' : 'agent');
      return '<div class="n8lc-msg n8lc-msg-' + who + '"><div class="n8lc-bubble">' + esc(msg.body) + '</div><time>' + esc(timeLabel(msg.created_at)) + '</time></div>';
    }).join('');

    return '<div class="n8lc-chat">' +
      '<div class="n8lc-messages" role="log">' + (messages || '<div class="n8lc-chat-loading">Connecting…</div>') + '</div>' +
      '<form class="n8lc-compose">' +
        '<textarea name="message" rows="2" maxlength="5000" placeholder="' + esc(cfg.i18n.placeholder) + '" required></textarea>' +
        '<button type="submit" aria-label="' + esc(cfg.i18n.send) + '">&#10148;</button>' +
      '</form>' +
    '</div>';
  }

  function bindChat() {
    var form = root.querySelector('.n8lc-compose');
    if (!form) return;
    form.addEventListener('submit', sendMessage);
    var textarea = form.querySelector('textarea');
    textarea.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) {
        ev.preventDefault();
        form.requestSubmit();
      }
    });
  }

  function sendMessage(ev) {
    ev.preventDefault();
    if (state.busy || !state.session) return;
    var textarea = ev.currentTarget.querySelector('textarea');
    var text = textarea.value.trim();
    if (!text) return;
    state.busy = true;
    textarea.disabled = true;

    api('messages', {
      method: 'POST',
      body: JSON.stringify({
        conversation_id: state.session.conversation_id,
        message: text,
        url: window.location.href
      })
    }).then(function () {
      textarea.value = '';
      return loadMessages();
    }).catch(function (err) {
      window.alert(err.message || cfg.i18n.error);
    }).finally(function () {
      state.busy = false;
      textarea.disabled = false;
      textarea.focus();
    });
  }

  function loadMessages() {
    if (!state.session) return Promise.resolve();
    return api('messages?conversation_id=' + encodeURIComponent(state.session.conversation_id) + '&after_id=' + encodeURIComponent(state.lastId), {
      method: 'GET'
    }).then(function (payload) {
      if (payload.messages && payload.messages.length) {
        payload.messages.forEach(function (msg) {
          state.messages.push(msg);
          state.lastId = Math.max(state.lastId, Number(msg.id) || 0);
        });
        if (state.open) renderContent();
      }
    }).catch(function (err) {
      if (/Invalid chat session/i.test(err.message || '')) {
        localStorage.removeItem(storageKey);
        state.session = null;
        state.messages = [];
        state.lastId = 0;
        renderContent();
      }
    });
  }

  function heartbeat() {
    if (!state.session) return;
    api('heartbeat', {
      method: 'POST',
      body: JSON.stringify({ conversation_id: state.session.conversation_id, url: window.location.href })
    }).catch(function () {});
  }

  function startPolling() {
    stopPolling();
    var interval = Math.max(1500, Number(cfg.pollInterval || (state.session && state.session.poll_interval) || 3000));
    state.polling = window.setInterval(loadMessages, interval);
    state.heartbeat = window.setInterval(heartbeat, 30000);
  }

  function stopPolling() {
    if (state.polling) window.clearInterval(state.polling);
    if (state.heartbeat) window.clearInterval(state.heartbeat);
    state.polling = null;
    state.heartbeat = null;
  }

  function timeLabel(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T'));
    if (isNaN(parsed.getTime())) return value;
    return parsed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  shell();
  if (state.session && state.open) startPolling();
}());
