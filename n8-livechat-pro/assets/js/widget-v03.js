(function () {
  'use strict';
  if (!window.N8LCWidget) return;

  var cfg = window.N8LCWidget;
  var root = document.getElementById('n8lc-widget-root');
  if (!root) return;

  var storageKey = 'n8lc_session_v1';
  var themes = {
    indigo:   { a: '#4f46e5', b: '#7c3aed', soft: '#eef2ff' },
    ocean:    { a: '#0284c7', b: '#06b6d4', soft: '#ecfeff' },
    emerald:  { a: '#059669', b: '#10b981', soft: '#ecfdf5' },
    violet:   { a: '#7c3aed', b: '#a855f7', soft: '#faf5ff' },
    rose:     { a: '#e11d48', b: '#f43f5e', soft: '#fff1f2' },
    sunset:   { a: '#ea580c', b: '#f59e0b', soft: '#fff7ed' },
    midnight: { a: '#0f172a', b: '#334155', soft: '#f8fafc' },
    custom:   { a: cfg.accentColor || '#111827', b: cfg.accentColor || '#111827', soft: '#f8fafc' }
  };
  var theme = themes[cfg.themePreset] || themes.indigo;

  var state = {
    open: false,
    session: null,
    messages: [],
    lastId: 0,
    polling: null,
    heartbeat: null,
    typingTimer: null,
    greetingTimer: null,
    greetingHideTimer: null,
    busy: false,
    status: 'open',
    agentTyping: null,
    availability: cfg.availability || 'online',
    csatRating: null,
    greetingVisible: false,
    unread: 0,
    audioReady: false,
    draft: '',
    sessionStartedAt: null,
    sessionExpiresAt: null,
    idleTimeoutMinutes: 15,
    showSessionTimer: true,
    sessionClock: null,
    csatSelection: 0,
    csatComment: '',
    csatThanks: false
  };

  try {
    state.session = JSON.parse(localStorage.getItem(storageKey) || 'null');
  } catch (e) {
    state.session = null;
  }

  root.style.setProperty('--n8lc-accent', theme.a);
  root.style.setProperty('--n8lc-accent-2', theme.b);
  root.style.setProperty('--n8lc-soft', theme.soft);
  root.style.setProperty('--n8lc-launcher-size', Math.max(48, Math.min(84, Number(cfg.launcherSize || 64))) + 'px');
  root.style.setProperty('--n8lc-panel-width', Math.max(320, Math.min(520, Number(cfg.panelWidth || 400))) + 'px');
  root.style.setProperty('--n8lc-panel-height', Math.max(460, Math.min(820, Number(cfg.panelHeight || 660))) + 'px');
  root.style.setProperty('--n8lc-panel-radius', Math.max(12, Math.min(36, Number(cfg.panelRadius || 24))) + 'px');
  root.classList.add(cfg.position === 'left' ? 'n8lc-left' : 'n8lc-right');
  root.classList.add('n8lc-shape-' + safeToken(cfg.launcherShape, ['circle', 'rounded', 'pill'], 'circle'));
  root.classList.add('n8lc-anim-' + safeToken(cfg.launcherAnimation, ['none', 'pulse', 'float', 'glow'], 'pulse'));

  function safeToken(value, allowed, fallback) {
    value = String(value || '').toLowerCase();
    return allowed.indexOf(value) !== -1 ? value : fallback;
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function icon(name) {
    var icons = {
      message: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 4.7-7.6A8.38 8.38 0 0 1 12.5 3h.5a8.48 8.48 0 0 1 8 8z"/></svg>',
      chat: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 10h8M8 14h5"/><path d="M21 12a8 8 0 0 1-8 8H7l-4 2 1.3-4.1A8 8 0 1 1 21 12z"/></svg>',
      headset: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-1a8 8 0 0 1 16 0v1"/><path d="M4 13h3v6H5a1 1 0 0 1-1-1v-5zm16 0h-3v6h2a1 1 0 0 0 1-1v-5zM17 19c0 1.1-.9 2-2 2h-3"/></svg>',
      support: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6m5.6 5.6 3.6 3.6m0-12.8-3.6 3.6m-5.6 5.6-3.6 3.6"/></svg>',
      sparkle: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.3 4.1L17 9l-3.7 1.9L12 15l-1.3-4.1L7 9l3.7-1.9L12 3zM19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14zM5 13l.7 1.8L7.5 15l-1.8.7L5 17.5l-.7-1.8L2.5 15l1.8-.2L5 13z"/></svg>',
      bot: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="7" width="16" height="12" rx="4"/><path d="M12 3v4M9 12h.01M15 12h.01M8.5 16h7M2 12h2m16 0h2"/></svg>',
      phone: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.3 19.3 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7A2 2 0 0 1 22 16.9z"/></svg>',
      mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>',
      close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
      minus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12"/></svg>',
      send: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7zM11 13l11-11"/></svg>',
      attach: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21.4 11.6-8.5 8.5a6 6 0 0 1-8.5-8.5l9-9a4 4 0 0 1 5.7 5.7l-9 9a2 2 0 1 1-2.8-2.8l8.4-8.4"/></svg>'
    };
    return icons[name] || icons.message;
  }

  function initials(name) {
    var parts = String(name || 'Support').trim().split(/\s+/).slice(0, 2);
    return parts.map(function (p) { return p.charAt(0).toUpperCase(); }).join('') || 'S';
  }

  function avatarHtml() {
    if (cfg.agentAvatarUrl) {
      return '<span class="n8lc-avatar"><img src="' + esc(cfg.agentAvatarUrl) + '" alt="' + esc(cfg.agentName || 'Support') + '"></span>';
    }
    return '<span class="n8lc-avatar n8lc-avatar-fallback">' + esc(initials(cfg.agentName)) + '</span>';
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
    if (state.session && state.session.token) options.headers['X-N8LC-Token'] = state.session.token;
    return fetch(cfg.restRoot + path, options).then(function (res) {
      return res.json().then(function (body) {
        if (!res.ok) throw new Error(body && body.message ? body.message : cfg.i18n.error);
        return body;
      });
    });
  }

  function launcherHtml() {
    var currentIcon = state.open ? icon('close') : icon(safeToken(cfg.launcherIcon, ['message','chat','headset','support','sparkle','bot','phone','mail'], 'message'));
    var label = !state.open && cfg.launcherLabel ? '<span class="n8lc-launcher-label">' + esc(cfg.launcherLabel) + '</span>' : '';
    var badge = !state.open && state.unread > 0 ? '<span class="n8lc-unread-badge">' + (state.unread > 99 ? '99+' : state.unread) + '</span>' : '';
    return '<button class="n8lc-launcher" type="button" aria-expanded="' + (state.open ? 'true' : 'false') + '" aria-controls="n8lc-panel"><span class="n8lc-launcher-icon">' + currentIcon + '</span>' + label + badge + '</button>';
  }

  function greetingHtml() {
    if (!state.greetingVisible || state.open || !cfg.showGreeting || !cfg.greetingText) return '';
    return '<button type="button" class="n8lc-greeting" aria-label="Open live chat"><span class="n8lc-greeting-avatar">' + avatarHtml() + '</span><span><strong>' + esc(cfg.agentName || 'Support Team') + '</strong><span>' + esc(cfg.greetingText) + '</span></span><span class="n8lc-greeting-close" aria-hidden="true">×</span></button>';
  }

  function shell() {
    var headerSubtitle = state.availability === 'online' ? (cfg.headerSubtitle || cfg.i18n.online) : (state.availability === 'offline' ? cfg.i18n.offline : cfg.i18n.away);
    root.innerHTML = greetingHtml() + launcherHtml() +
      '<section id="n8lc-panel" class="n8lc-panel ' + (state.open ? 'is-open' : '') + '" aria-hidden="' + (state.open ? 'false' : 'true') + '">' +
        '<header class="n8lc-header"><div class="n8lc-header-main">' + avatarHtml() + '<div class="n8lc-header-copy"><strong>' + esc(cfg.title) + '</strong><span class="n8lc-presence n8lc-presence-' + esc(state.availability) + '"><i></i>' + esc(headerSubtitle) + '</span></div></div>' +
        '<div class="n8lc-header-actions">' + (state.session && state.status !== 'closed' ? '<button type="button" class="n8lc-end" title="' + esc(cfg.i18n.end) + '">End</button>' : '') + '<button type="button" class="n8lc-minimize" aria-label="' + esc(cfg.i18n.close) + '">' + icon('minus') + '</button></div></header>' +
        '<div class="n8lc-content"></div>' +
        (cfg.showBranding ? '<footer class="n8lc-branding">Powered by <strong>N8 LiveChat</strong></footer>' : '') +
      '</section>';

    root.querySelector('.n8lc-launcher').addEventListener('click', toggle);
    var minimize = root.querySelector('.n8lc-minimize');
    if (minimize) minimize.addEventListener('click', toggle);
    var end = root.querySelector('.n8lc-end');
    if (end) end.addEventListener('click', endChat);
    var greeting = root.querySelector('.n8lc-greeting');
    if (greeting) {
      greeting.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('n8lc-greeting-close')) {
          hideGreeting();
          e.stopPropagation();
          return;
        }
        state.open = true;
        state.greetingVisible = false;
        state.unread = 0;
        prepareAudio();
        shell();
        if (state.session) { loadMessages(); loadState(); startPolling(); }
      });
    }
    renderContent();
  }

  function toggle() {
    state.open = !state.open;
    state.greetingVisible = false;
    if (state.open) {
      state.unread = 0;
      prepareAudio();
    }
    shell();
    if (state.session) {
      loadMessages();
      loadState();
      startPolling();
    }
  }

  function scheduleGreeting() {
    if (!cfg.showGreeting || !cfg.greetingText || state.open) return;
    clearTimeout(state.greetingTimer);
    clearTimeout(state.greetingHideTimer);
    state.greetingTimer = setTimeout(function () {
      if (state.open) return;
      state.greetingVisible = true;
      shell();
      state.greetingHideTimer = setTimeout(hideGreeting, Math.max(3, Number(cfg.greetingAutoHide || 12)) * 1000);
    }, Math.max(0, Number(cfg.greetingDelay || 1800)));
  }

  function hideGreeting() {
    clearTimeout(state.greetingTimer);
    clearTimeout(state.greetingHideTimer);
    if (!state.greetingVisible) return;
    state.greetingVisible = false;
    shell();
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

  function customFieldsHtml() {
    var fields = Array.isArray(cfg.customFields) ? cfg.customFields : [];
    return fields.map(function (f) {
      var key = String(f.key || '').replace(/[^a-z0-9_\-]/gi, '');
      if (!key) return '';
      var label = esc(f.label || key) + (f.required ? ' *' : '');
      var req = f.required ? ' required' : '';
      var ph = f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : '';
      if (f.type === 'textarea') return '<label>' + label + '<textarea name="n8lc_custom_' + esc(key) + '" maxlength="1000"' + req + ph + '></textarea></label>';
      if (f.type === 'select') {
        return '<label>' + label + '<select name="n8lc_custom_' + esc(key) + '"' + req + '><option value="">Select…</option>' + (f.options || []).map(function (o) { return '<option value="' + esc(o) + '">' + esc(o) + '</option>'; }).join('') + '</select></label>';
      }
      if (f.type === 'checkbox') return '<label class="n8lc-dynamic-field-checkbox"><input name="n8lc_custom_' + esc(key) + '" type="checkbox" value="1"' + req + '><span>' + label + '</span></label>';
      var type = ['email','tel','number'].indexOf(f.type) !== -1 ? f.type : 'text';
      return '<label>' + label + '<input name="n8lc_custom_' + esc(key) + '" type="' + type + '" maxlength="190"' + req + ph + '></label>';
    }).join('');
  }

  function consentHtml() {
    if (!cfg.consentEnabled) return '';
    return '<label class="n8lc-consent"><input name="n8lc_consent" type="checkbox" value="1" ' + (cfg.consentRequired ? 'required' : '') + '><span>' + esc(cfg.consentText || 'I agree to the use of my details for this support request.') + '</span></label>';
  }

  function startForm() {
    var department = '';
    if (cfg.departments && cfg.departments.length > 1) {
      department = '<label>' + esc(cfg.i18n.department) + '<select name="department_id">' + cfg.departments.map(function (d) {
        return '<option value="' + Number(d.id) + '">' + esc(d.name) + '</option>';
      }).join('') + '</select></label>';
    }
    var away = state.availability !== 'online' ? '<div class="n8lc-away-box"><span>' + (state.availability === 'offline' ? '○' : '☾') + '</span><div><strong>' + (state.availability === 'offline' ? 'Support is offline right now' : 'We are away right now') + '</strong><p>' + esc(cfg.offlineMessage || 'Leave a message and we will get back to you.') + '</p></div></div>' : '';
    return '<div class="n8lc-start"><div class="n8lc-start-hero"><div class="n8lc-start-avatars">' + avatarHtml() + '<span class="n8lc-hero-status"></span></div><h3>How can we help?</h3><p>' + esc(cfg.welcomeText || 'Start a conversation with our team. We are here to help.') + '</p></div>' + away + '<form class="n8lc-start-form">' +
      '<div class="n8lc-fields"><label>' + esc(cfg.i18n.name) + '<input name="name" type="text" autocomplete="name" maxlength="190" placeholder="Jane Doe"></label>' +
      '<label>' + esc(cfg.i18n.email) + (cfg.requireEmail ? ' *' : '') + '<input name="email" type="email" autocomplete="email" maxlength="190" placeholder="you@example.com" ' + (cfg.requireEmail ? 'required' : '') + '></label>' +
      '<label>' + esc(cfg.i18n.phone) + '<input name="phone" type="text" autocomplete="tel" maxlength="80" placeholder="Optional"></label>' + department + customFieldsHtml() + '</div>' + consentHtml() +
      '<div class="n8lc-form-error" role="alert"></div><button type="submit" class="n8lc-primary"><span>' + esc(cfg.i18n.start) + '</span>' + icon('send') + '</button></form></div>';
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

    var customData = {};
    (Array.isArray(cfg.customFields) ? cfg.customFields : []).forEach(function (f) {
      var key = String(f.key || '').replace(/[^a-z0-9_\-]/gi, '');
      if (!key) return;
      var formKey = 'n8lc_custom_' + key;
      customData[key] = f.type === 'checkbox' ? (data.get(formKey) ? '1' : '0') : (data.get(formKey) || '');
    });

    api('session', { method: 'POST', body: {
      name: data.get('name') || '', email: data.get('email') || '', phone: data.get('phone') || '',
      department_id: data.get('department_id') || 0, url: window.location.href, referrer: document.referrer,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '', language: navigator.language || '',
      screen: window.screen ? window.screen.width + 'x' + window.screen.height : '',
      custom_data: customData, consent: data.get('n8lc_consent') ? true : false
    }}).then(function (session) {
      state.session = session;
      state.availability = session.availability || state.availability;
      localStorage.setItem(storageKey, JSON.stringify(session));
      state.messages = [];
      state.lastId = 0;
      state.status = state.availability === 'online' ? 'open' : 'pending';
      state.sessionStartedAt = session.created_at || null;
      state.idleTimeoutMinutes = Number(session.idle_timeout_minutes || 15);
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
        body = '<a class="n8lc-chat-file" href="' + url + '" target="_blank" rel="noopener">' + icon('attach') + '<span>' + name + '</span></a>';
      }
    }
    var avatar = who === 'agent' ? '<span class="n8lc-message-avatar">' + avatarHtml() + '</span>' : '';
    return '<div class="n8lc-msg n8lc-msg-' + who + '">' + avatar + '<div class="n8lc-msg-stack"><div class="n8lc-bubble">' + body + '</div><time>' + esc(timeLabel(msg.created_at)) + '</time></div></div>';
  }

  function ratingHtml() {
    if (!cfg.csatEnabled || state.status !== 'closed') return '';
    if (state.csatRating || state.csatThanks) return '<div class="n8lc-csat-thanks"><span>💚</span><strong>Thank you for your feedback!</strong><small>Your review helps us improve support.</small></div>';
    var faces = [{n:1,e:'😞',l:'Poor'},{n:2,e:'🙁',l:'Not good'},{n:3,e:'😐',l:'Okay'},{n:4,e:'🙂',l:'Good'},{n:5,e:'😍',l:'Great'}];
    return '<div class="n8lc-rating"><strong>' + esc(cfg.i18n.rateUs) + '</strong><small>How did this conversation feel?</small><div class="n8lc-csat-faces">' + faces.map(function (f) { return '<button type="button" class="' + (state.csatSelection===f.n?'is-selected':'') + '" data-rating="' + f.n + '" aria-label="' + esc(f.l) + '"><span>' + f.e + '</span><em>' + esc(f.l) + '</em></button>'; }).join('') + '</div><textarea id="n8lc-rating-comment" rows="2" maxlength="1000" placeholder="Tell us what went well or what we can improve (optional)">' + esc(state.csatComment || '') + '</textarea><button type="button" id="n8lc-submit-rating" class="n8lc-rating-submit" ' + (state.csatSelection?'':'disabled') + '>Send feedback</button><div id="n8lc-rating-status"></div></div>';
  }

  function sessionMetaHtml() {
    if (!state.showSessionTimer || !state.session) return '';
    return '<div class="n8lc-session-meta"><span class="n8lc-session-live"></span><span id="n8lc-session-clock">Session 00:00</span><span>· auto-closes after ' + Number(state.idleTimeoutMinutes || 15) + 'm disconnected</span></div>';
  }

  function typingHtml() {
    if (!state.agentTyping) return '<div class="n8lc-agent-typing"></div>';
    return '<div class="n8lc-agent-typing"><span class="n8lc-typing-dots"><i></i><i></i><i></i></span><span>' + esc((state.agentTyping.name ? state.agentTyping.name + ' ' : '') + cfg.i18n.typing) + '</span></div>';
  }

  function chatView() {
    var messages = state.messages.map(messageHtml).join('');
    var attach = cfg.uploadsEnabled ? '<input type="file" id="n8lc-visitor-file" class="n8lc-hidden-file"><button type="button" class="n8lc-attach" title="' + esc(cfg.i18n.attach) + '">' + icon('attach') + '</button>' : '';
    var closed = state.status === 'closed' ? '<div class="n8lc-closed-note">This conversation is closed. Sending a new message will reopen it.</div>' : '';
    return '<div class="n8lc-chat"><div class="n8lc-messages" role="log">' + (messages || '<div class="n8lc-empty-chat"><span>' + icon('sparkle') + '</span><strong>Your conversation starts here</strong><p>Send a message and our team will jump in.</p></div>') + '</div>' + typingHtml() + ratingHtml() + closed +
      sessionMetaHtml() + '<form class="n8lc-compose">' + attach + '<textarea name="message" rows="1" maxlength="5000" placeholder="' + esc(cfg.i18n.placeholder) + '" required>' + esc(state.draft || '') + '</textarea><button type="submit" class="n8lc-send" aria-label="' + esc(cfg.i18n.send) + '">' + icon('send') + '</button></form><div id="n8lc-upload-status" class="n8lc-upload-status"></div></div>';
  }

  function bindChat() {
    var form = root.querySelector('.n8lc-compose');
    if (form) {
      form.addEventListener('submit', sendMessage);
      var textarea = form.querySelector('textarea');
      textarea.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); form.requestSubmit(); }
      });
      textarea.addEventListener('input', function () { state.draft = textarea.value; autoGrow(textarea); visitorTyping(); });
      if (state.draft) autoGrow(textarea);
    }
    var attach = root.querySelector('.n8lc-attach');
    var file = root.querySelector('#n8lc-visitor-file');
    if (attach && file) {
      attach.addEventListener('click', function () { file.click(); });
      file.addEventListener('change', uploadFile);
    }
    Array.prototype.forEach.call(root.querySelectorAll('.n8lc-csat-faces button'), function (btn) { btn.addEventListener('click', selectRating); });
    var ratingComment = root.querySelector('#n8lc-rating-comment');
    if (ratingComment) ratingComment.addEventListener('input', function () { state.csatComment = ratingComment.value; });
    var ratingSubmit = root.querySelector('#n8lc-submit-rating');
    if (ratingSubmit) ratingSubmit.addEventListener('click', submitRating);
  }

  function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(110, el.scrollHeight) + 'px';
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
      state.draft = '';
      textarea.style.height = 'auto';
      state.status = state.availability === 'online' ? 'open' : 'pending';
      return api('typing', { method: 'POST', body: { conversation_id: state.session.conversation_id, typing: false } });
    }).then(function () { return Promise.all([loadMessages(), loadState()]); }).catch(function (err) {
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

  function selectRating(e) {
    state.csatSelection = Number(e.currentTarget.dataset.rating || 0);
    renderContent();
    var comment = root.querySelector('#n8lc-rating-comment');
    if (comment) comment.focus();
  }

  function submitRating() {
    if (!state.session || !state.csatSelection) return;
    var rating = Number(state.csatSelection || 0);
    var comment = root.querySelector('#n8lc-rating-comment');
    var status = root.querySelector('#n8lc-rating-status');
    var submit = root.querySelector('#n8lc-submit-rating');
    if (submit) submit.disabled = true;
    api('rating', { method: 'POST', body: { conversation_id: state.session.conversation_id, rating: rating, comment: comment ? comment.value : state.csatComment } }).then(function () {
      state.csatRating = rating;
      state.csatComment = comment ? comment.value : state.csatComment;
      state.csatThanks = true;
      renderContent();
    }).catch(function (err) {
      if (status) status.textContent = err.message;
      if (submit) submit.disabled = false;
    });
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
        var newAgent = 0;
        payload.messages.forEach(function (msg) {
          state.messages.push(msg);
          state.lastId = Math.max(state.lastId, Number(msg.id) || 0);
          if (msg.sender_type === 'agent') newAgent += 1;
        });
        if (!state.open && newAgent) {
          state.unread += newAgent;
          chime();
          shell();
        } else if (state.open) {
          renderContent();
        }
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
      state.sessionStartedAt = payload.session_started_at || state.sessionStartedAt;
      state.sessionExpiresAt = payload.session_expires_at || state.sessionExpiresAt;
      state.idleTimeoutMinutes = Number(payload.idle_timeout_minutes || state.idleTimeoutMinutes || 15);
      state.showSessionTimer = payload.show_session_timer !== false;
      var typingEl = root.querySelector('.n8lc-agent-typing');
      if (typingEl && state.open) typingEl.outerHTML = typingHtml();
      var presence = root.querySelector('.n8lc-presence');
      if (presence) {
        presence.className = 'n8lc-presence n8lc-presence-' + state.availability;
        var dot = '<i></i>';
        var label = state.availability === 'online' ? cfg.i18n.online : (state.availability === 'offline' ? cfg.i18n.offline : cfg.i18n.away);
        presence.innerHTML = dot + esc(label);
      }
      updateSessionClock();
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
    var base = Math.max(1500, Number(cfg.pollInterval || (state.session && state.session.poll_interval) || 3000));
    var interval = state.open ? base : Math.max(6000, base * 2);
    state.polling = window.setInterval(function () { loadMessages(); loadState(); }, interval);
    state.heartbeat = window.setInterval(heartbeat, 30000);
    state.sessionClock = window.setInterval(updateSessionClock, 1000);
    updateSessionClock();
  }

  function stopPolling() {
    if (state.polling) window.clearInterval(state.polling);
    if (state.heartbeat) window.clearInterval(state.heartbeat);
    if (state.sessionClock) window.clearInterval(state.sessionClock);
    state.polling = null;
    state.heartbeat = null;
    state.sessionClock = null;
  }

  function resetSession() {
    localStorage.removeItem(storageKey);
    state.session = null;
    state.messages = [];
    state.lastId = 0;
    state.status = 'open';
    state.agentTyping = null;
    state.unread = 0;
    state.draft = '';
    state.sessionStartedAt = null;
    state.sessionExpiresAt = null;
    state.csatSelection = 0;
    state.csatComment = '';
    state.csatThanks = false;
    stopPolling();
    shell();
  }

  function updateSessionClock() {
    var el = root.querySelector('#n8lc-session-clock');
    if (!el || !state.sessionStartedAt) return;
    var started = new Date(String(state.sessionStartedAt).replace(' ', 'T')).getTime();
    if (!started || isNaN(started)) return;
    var seconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var sec = seconds % 60;
    var label = h > 0 ? (String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0')) : (String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0'));
    el.textContent = 'Session ' + label;
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

  function prepareAudio() {
    state.audioReady = true;
  }

  function chime() {
    if (!cfg.soundEnabled || !state.audioReady || !window.AudioContext) return;
    try {
      var ctx = new AudioContext();
      var gain = ctx.createGain();
      gain.gain.setValueAtTime(0.0001, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.09, ctx.currentTime + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.34);
      gain.connect(ctx.destination);
      [620, 830].forEach(function (freq, i) {
        var osc = ctx.createOscillator();
        osc.type = 'sine';
        osc.frequency.value = freq;
        osc.connect(gain);
        osc.start(ctx.currentTime + i * 0.08);
        osc.stop(ctx.currentTime + 0.22 + i * 0.08);
      });
      setTimeout(function () { ctx.close(); }, 500);
    } catch (e) {}
  }

  shell();
  scheduleGreeting();
  if (state.session) {
    loadState();
    loadMessages();
    startPolling();
  }
}());
