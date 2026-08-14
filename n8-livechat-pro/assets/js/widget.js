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
    typingTimer: null,
    busy: false,
    status: 'open',
    agentTyping: null,
    availability: cfg.availability || 'online',
    csatRating: null
  };

  try {
    state.session = JSON.parse(localStorage.getItem(storageKey) || 'null');
  } catch (e) {
    state.session = null;
  }

  root.style.setProperty('--n8lc-accent', cfg.accentColor || '#111827');
  root.classList.add(cfg.position === 'left' ? 'n8lc-left' : 'n8lc-right');

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function api(path, options) {
    options = options || {};
    options.headers = options.headers || {};
    if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    } else if (typeof options.body === 'string') {
      options.headers['Content-Type'] = 'application/json';
    }
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
    root.innerHTML = '<button class="n8lc-launcher" type="button" aria-expanded="' + (state.open ? 'true' : 'false') + '" aria-controls="n8lc-panel"><span class="n8lc-launcher-icon">' + (state.open ? '&#10005;' : '&#128172;') + '</span></button>' +
      '<section id="n8lc-panel" class="n8lc-panel ' + (state.open ? 'is-open' : '') + '" aria-hidden="' + (state.open ? 'false' : 'true') + '">' +
        '<header class="n8lc-header"><div><strong>' + esc(cfg.title) + '</strong><span class="n8lc-presence n8lc-presence-' + esc(state.availability) + '">● ' + esc(state.availability === 'online' ? cfg.i18n.online : cfg.i18n.away) + '</span></div><div class="n8lc-header-actions">' + (state.session && state.status !== 'closed' ? '<button type="button" class="n8lc-end" title="' + esc(cfg.i18n.end) + '">End</button>' : '') + '<button type="button" class="n8lc-close" aria-label="' + esc(cfg.i18n.close) + '">&#10005;</button></div></header>' +
        '<div class="n8lc-content"></div>' +
      '</section>';

    root.querySelector('.n8lc-launcher').addEventListener('click', toggle);
    root.querySelector('.n8lc-close').addEventListener('click', toggle);
    var end = root.querySelector('.n8lc-end');
    if (end) end.addEventListener('click', endChat);
    renderContent();
  }

  function toggle() {
    state.open = !state.open;
    shell();
    if (state.open && state.session) {
      loadMessages();
      loadState();
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
      content.querySelector('.n8lc-start-form').addEventListener('submit', startSession);
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
    var away = state.availability === 'away' ? '<div class="n8lc-away-box">' + esc(cfg.offlineMessage || 'We are currently away. Leave a message and we will get back to you.') + '</div>' : '';
    return '<div class="n8lc-start"><p>Start a conversation with our team.</p>' + away + '<form class="n8lc-start-form">' +
      '<label>' + esc(cfg.i18n.name) + '<input name="name" type="text" autocomplete="name" maxlength="190"></label>' +
      '<label>' + esc(cfg.i18n.email) + (cfg.requireEmail ? ' *' : '') + '<input name="email" type="email" autocomplete="email" maxlength="190" ' + (cfg.requireEmail ? 'required' : '') + '></label>' +
      '<label>' + esc(cfg.i18n.phone) + '<input name="phone" type="text" autocomplete="tel" maxlength="80"></label>' + department +
      '<div class="n8lc-form-error" role="alert"></div><button type="submit" class="n8lc-primary">' + esc(cfg.i18n.start) + '</button></form></div>';
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
      body: {
        name: data.get('name') || '',
        email: data.get('email') || '',
        phone: data.get('phone') || '',
        department_id: data.get('department_id') || 0,
        url: window.location.href,
        referrer: document.referrer,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        language: navigator.language || '',
        screen: window.screen ? window.screen.width + 'x' + window.screen.height : ''
      }
    }).then(function (session) {
      state.session = session;
      state.availability = session.availability || state.availability;
      localStorage.setItem(storageKey, JSON.stringify(session));
      state.messages = [];
      state.lastId = 0;
      state.status = 'open';
      shell();
      return Promise.all([loadMessages(), loadState()]);
    }).then(startPolling).catch(function (err) {
      error.textContent = err.message || cfg.i18n.error;
    }).finally(function () {
      state.busy = false;
      if (button) button.disabled = false;
    });
  }

  function messageHtml(msg) {
    var who = msg.sender_type === 'visitor' ? 'visitor' : (msg.sender_type === 'system' ? 'system' : 'agent');
    var body = esc(msg.body || '');
    if (msg.attachment_url) {
      var url = esc(msg.attachment_url);
      var name = esc(msg.attachment_name || msg.body || 'Attachment');
      if (msg.message_type === 'image') {
        body = '<a href="' + url + '" target="_blank" rel="noopener"><img class="n8lc-chat-image" src="' + url + '" alt="' + name + '"></a><small class="n8lc-chat-file-name">' + name + '</small>';
      } else {
        body = '<a class="n8lc-chat-file" href="' + url + '" target="_blank" rel="noopener">📎 ' + name + '</a>';
      }
    }
    return '<div class="n8lc-msg n8lc-msg-' + who + '"><div class="n8lc-bubble">' + body + '</div><time>' + esc(timeLabel(msg.created_at)) + '</time></div>';
  }

  function ratingHtml() {
    if (!cfg.csatEnabled || state.status !== 'closed' || state.csatRating) return '';
    return '<div class="n8lc-rating"><strong>' + esc(cfg.i18n.rateUs) + '</strong><div class="n8lc-stars">' + [1,2,3,4,5].map(function (n) { return '<button type="button" data-rating="' + n + '" aria-label="' + n + ' stars">★</button>'; }).join('') + '</div><textarea id="n8lc-rating-comment" rows="2" maxlength="1000" placeholder="Optional comment"></textarea><div id="n8lc-rating-status"></div></div>';
  }

  function chatView() {
    var messages = state.messages.map(messageHtml).join('');
    var typing = state.agentTyping ? '<div class="n8lc-agent-typing">' + esc((state.agentTyping.name ? state.agentTyping.name + ' ' : '') + cfg.i18n.typing) + '</div>' : '<div class="n8lc-agent-typing"></div>';
    var attach = cfg.uploadsEnabled ? '<input type="file" id="n8lc-visitor-file" class="n8lc-hidden-file"><button type="button" class="n8lc-attach" title="' + esc(cfg.i18n.attach) + '">📎</button>' : '';
    var closed = state.status === 'closed' ? '<div class="n8lc-closed-note">This conversation is closed. Sending a new message will reopen it.</div>' : '';
    return '<div class="n8lc-chat"><div class="n8lc-messages" role="log">' + (messages || '<div class="n8lc-chat-loading">Connecting…</div>') + '</div>' + typing + ratingHtml() + closed + '<form class="n8lc-compose">' + attach + '<textarea name="message" rows="2" maxlength="5000" placeholder="' + esc(cfg.i18n.placeholder) + '" required></textarea><button type="submit" class="n8lc-send" aria-label="' + esc(cfg.i18n.send) + '">&#10148;</button></form><div id="n8lc-upload-status" class="n8lc-upload-status"></div></div>';
  }

  function bindChat() {
    var form = root.querySelector('.n8lc-compose');
    if (form) {
      form.addEventListener('submit', sendMessage);
      var textarea = form.querySelector('textarea');
      textarea.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && !ev.shiftKey) {
          ev.preventDefault();
          form.requestSubmit();
        }
      });
      textarea.addEventListener('input', visitorTyping);
    }
    var attach = root.querySelector('.n8lc-attach');
    var file = root.querySelector('#n8lc-visitor-file');
    if (attach && file) {
      attach.addEventListener('click', function () { file.click(); });
      file.addEventListener('change', uploadFile);
    }
    Array.prototype.forEach.call(root.querySelectorAll('.n8lc-stars button'), function (btn) {
      btn.addEventListener('click', submitRating);
    });
  }

  function visitorTyping() {
    if (!state.session) return;
    api('typing', { method: 'POST', body: { conversation_id: state.session.conversation_id, typing: true } }).catch(function () {});
    clearTimeout(state.typingTimer);
    state.typingTimer = setTimeout(function () {
      api('typing', { method: 'POST', body: { conversation_id: state.session.conversation_id, typing: false } }).catch(function () {});
    }, 2200);
  }

  function sendMessage(ev) {
    ev.preventDefault();
    if (state.busy || !state.session) return;
    var textarea = ev.currentTarget.querySelector('textarea');
    var text = textarea.value.trim();
    if (!text) return;
    state.busy = true;
    textarea.disabled = true;
    api('messages', { method: 'POST', body: { conversation_id: state.session.conversation_id, message: text, url: window.location.href } }).then(function () {
      textarea.value = '';
      state.status = state.availability === 'away' ? 'pending' : 'open';
      return api('typing', { method: 'POST', body: { conversation_id: state.session.conversation_id, typing: false } });
    }).then(function () {
      return Promise.all([loadMessages(), loadState()]);
    }).catch(function (err) {
      window.alert(err.message || cfg.i18n.error);
    }).finally(function () {
      state.busy = false;
      textarea.disabled = false;
      textarea.focus();
    });
  }

  function uploadFile(e) {
    var file = e.target.files && e.target.files[0];
    if (!file || !state.session || state.busy) return;
    if (Number(cfg.maxUploadMb || 5) * 1048576 < file.size) {
      window.alert('File is larger than the ' + Number(cfg.maxUploadMb || 5) + ' MB limit.');
      e.target.value = '';
      return;
    }
    state.busy = true;
    var status = root.querySelector('#n8lc-upload-status');
    if (status) status.textContent = 'Uploading ' + file.name + '…';
    var form = new FormData();
    form.append('file', file);
    form.append('conversation_id', state.session.conversation_id);
    api('upload', { method: 'POST', body: form }).then(function () {
      if (status) status.textContent = 'File sent';
      e.target.value = '';
      return loadMessages();
    }).catch(function (err) {
      if (status) status.textContent = err.message || cfg.i18n.error;
    }).finally(function () { state.busy = false; });
  }

  function submitRating(e) {
    if (!state.session) return;
    var rating = Number(e.currentTarget.dataset.rating || 0);
    var comment = root.querySelector('#n8lc-rating-comment');
    var status = root.querySelector('#n8lc-rating-status');
    api('rating', { method: 'POST', body: { conversation_id: state.session.conversation_id, rating: rating, comment: comment ? comment.value : '' } }).then(function () {
      state.csatRating = rating;
      renderContent();
    }).catch(function (err) { if (status) status.textContent = err.message; });
  }

  function endChat() {
    if (!state.session || state.status === 'closed') return;
    if (!window.confirm('End this chat?')) return;
    api('close', { method: 'POST', body: { conversation_id: state.session.conversation_id } }).then(function () {
      state.status = 'closed';
      shell();
    }).catch(function (err) { window.alert(err.message || cfg.i18n.error); });
  }

  function loadMessages() {
    if (!state.session) return Promise.resolve();
    return api('messages?conversation_id=' + encodeURIComponent(state.session.conversation_id) + '&after_id=' + encodeURIComponent(state.lastId), { method: 'GET' }).then(function (payload) {
      if (payload.messages && payload.messages.length) {
        payload.messages.forEach(function (msg) {
          state.messages.push(msg);
          state.lastId = Math.max(state.lastId, Number(msg.id) || 0);
        });
        if (state.open) renderContent();
      }
    }).catch(function (err) {
      if (/Invalid chat session/i.test(err.message || '')) resetSession();
    });
  }

  function loadState() {
    if (!state.session) return Promise.resolve();
    return api('state?conversation_id=' + encodeURIComponent(state.session.conversation_id), { method: 'GET' }).then(function (payload) {
      var oldStatus = state.status;
      var oldRating = state.csatRating;
      state.status = payload.status || state.status;
      state.agentTyping = payload.agent_typing || null;
      state.availability = payload.availability || state.availability;
      state.csatRating = payload.csat_rating == null ? state.csatRating : payload.csat_rating;
      var typingEl = root.querySelector('.n8lc-agent-typing');
      if (typingEl) typingEl.textContent = state.agentTyping ? ((state.agentTyping.name ? state.agentTyping.name + ' ' : '') + cfg.i18n.typing) : '';
      var presence = root.querySelector('.n8lc-presence');
      if (presence) {
        presence.textContent = '● ' + (state.availability === 'online' ? cfg.i18n.online : cfg.i18n.away);
        presence.className = 'n8lc-presence n8lc-presence-' + state.availability;
      }
      if (state.open && oldStatus !== state.status) shell();
      else if (state.open && oldRating !== state.csatRating) renderContent();
    }).catch(function () {});
  }

  function heartbeat() {
    if (!state.session) return;
    api('heartbeat', { method: 'POST', body: { conversation_id: state.session.conversation_id, url: window.location.href } }).catch(function () {});
  }

  function startPolling() {
    stopPolling();
    var interval = Math.max(1500, Number(cfg.pollInterval || (state.session && state.session.poll_interval) || 3000));
    state.polling = window.setInterval(function () { loadMessages(); loadState(); }, interval);
    state.heartbeat = window.setInterval(heartbeat, 30000);
  }

  function stopPolling() {
    if (state.polling) window.clearInterval(state.polling);
    if (state.heartbeat) window.clearInterval(state.heartbeat);
    state.polling = null;
    state.heartbeat = null;
  }

  function resetSession() {
    localStorage.removeItem(storageKey);
    state.session = null;
    state.messages = [];
    state.lastId = 0;
    state.status = 'open';
    state.agentTyping = null;
    stopPolling();
    shell();
  }

  function scrollBottom() {
    var box = root.querySelector('.n8lc-messages');
    if (box) box.scrollTop = box.scrollHeight;
  }

  function timeLabel(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T'));
    if (isNaN(parsed.getTime())) return value;
    return parsed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  shell();
  if (state.session) {
    loadState();
    loadMessages();
  }
}());
