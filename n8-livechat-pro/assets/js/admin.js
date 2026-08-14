(function () {
  'use strict';
  if (!window.N8LCAdmin) return;

  var cfg = window.N8LCAdmin;
  var app = document.getElementById('n8lc-admin-app');
  if (!app) return;

  var lastUnread = null;

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
    options.headers['X-WP-Nonce'] = cfg.nonce;
    if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    return fetch(cfg.restRoot + path, options).then(function (res) {
      return res.json().then(function (body) {
        if (!res.ok) throw new Error(body && body.message ? body.message : cfg.i18n.error);
        return body;
      });
    });
  }

  function fmtDate(v) {
    if (!v) return '—';
    var d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d.getTime()) ? v : d.toLocaleString();
  }

  function humanSize(bytes) {
    bytes = Number(bytes || 0);
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  function errorView(err) {
    app.innerHTML = '<div class="notice notice-error inline"><p>' + esc(err.message || cfg.i18n.error) + '</p></div>';
  }

  function card(label, value, hint, extraClass) {
    return '<div class="n8lc-card n8lc-stat ' + esc(extraClass || '') + '"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong><small>' + esc(hint || '') + '</small></div>';
  }

  function badge(status) {
    return '<span class="n8lc-badge n8lc-' + esc(status) + '">' + esc(status) + '</span>';
  }

  function tagPills(tags) {
    tags = tags || [];
    if (!tags.length) return '';
    return '<span class="n8lc-tag-list">' + tags.map(function (tag) {
      return '<span class="n8lc-tag" style="--tag:' + esc(tag.color || '#64748b') + '">' + esc(tag.name) + '</span>';
    }).join('') + '</span>';
  }

  function attachmentHtml(message) {
    if (!message.attachment_url) return esc(message.body || '');
    var url = esc(message.attachment_url);
    var name = esc(message.attachment_name || message.body || 'Attachment');
    if (message.message_type === 'image') {
      return '<a href="' + url + '" target="_blank" rel="noopener"><img class="n8lc-message-image" src="' + url + '" alt="' + name + '"></a><small class="n8lc-file-meta">' + name + ' · ' + esc(humanSize(message.attachment_size)) + '</small>';
    }
    return '<a class="n8lc-file-link" href="' + url + '" target="_blank" rel="noopener">📎 ' + name + '</a><small class="n8lc-file-meta">' + esc(humanSize(message.attachment_size)) + '</small>';
  }

  function maybeNotify(stats) {
    var unread = Number(stats && stats.unread || 0);
    if (lastUnread !== null && unread > lastUnread && window.Notification && Notification.permission === 'granted') {
      try {
        new Notification('N8 LiveChat', { body: 'You have a new live chat message.' });
      } catch (e) {}
    }
    lastUnread = unread;
  }

  function notificationButton() {
    if (!window.Notification) return '';
    var label = Notification.permission === 'granted' ? 'Desktop notifications enabled' : 'Enable desktop notifications';
    return '<button type="button" class="button" id="n8lc-enable-notifications">' + esc(label) + '</button>';
  }

  function bindNotificationButton() {
    var btn = document.getElementById('n8lc-enable-notifications');
    if (!btn || !window.Notification) return;
    btn.addEventListener('click', function () {
      Notification.requestPermission().then(function (result) {
        btn.textContent = result === 'granted' ? 'Desktop notifications enabled' : 'Notifications not enabled';
      });
    });
  }

  function conversationTable(list) {
    if (!list.length) return '<div class="n8lc-empty">' + esc(cfg.i18n.empty) + '</div>';
    return '<div class="n8lc-table-wrap"><table class="widefat striped"><thead><tr><th>Visitor</th><th>Status</th><th>Priority</th><th>Tags</th><th>Department</th><th>Agent</th><th>SLA</th><th>Unread</th><th>Updated</th></tr></thead><tbody>' + list.map(function (c) {
      return '<tr><td><strong>' + esc(c.visitor_name || 'Anonymous') + '</strong><br><small>' + esc(c.visitor_email || '') + '</small></td><td>' + badge(c.status) + '</td><td>' + esc(c.priority) + '</td><td>' + tagPills(c.tags) + '</td><td>' + esc(c.department_name || '—') + '</td><td>' + esc(c.agent_name || 'Unassigned') + '</td><td>' + (Number(c.sla_breached) ? '<span class="n8lc-sla-breach">BREACHED</span>' : esc(c.first_response_due_at || '—')) + '</td><td>' + esc(c.unread_agent) + '</td><td>' + esc(fmtDate(c.last_message_at || c.updated_at)) + '</td></tr>';
    }).join('') + '</tbody></table></div>';
  }

  function renderDashboard() {
    app.innerHTML = '<div class="n8lc-loading">' + esc(cfg.i18n.loading) + '</div>';
    Promise.all([api('admin/stats'), api('admin/conversations?per_page=10')]).then(function (r) {
      var s = r[0];
      var list = r[1].conversations || [];
      maybeNotify(s);
      app.innerHTML = '<div class="n8lc-toolbar"><span class="n8lc-live-state n8lc-live-' + esc(s.availability) + '">● ' + esc(s.availability) + '</span>' + notificationButton() + '<a class="button button-primary" href="admin.php?page=n8-livechat-inbox">Open inbox</a></div>' +
        '<div class="n8lc-stats-grid n8lc-stats-grid-7">' +
          card('Open', s.open, 'Active conversations') +
          card('Pending', s.pending, 'Waiting for follow-up') +
          card('Unread', s.unread, 'Messages needing attention') +
          card('SLA breached', s.sla_breached, 'Urgent conversations', Number(s.sla_breached) ? 'n8lc-stat-danger' : '') +
          card('Visitors online', s.online_visitors, 'Seen in last 5 minutes') +
          card('Chats today', s.conversations_today, 'New conversations') +
          card('Messages today', s.messages_today, 'Visitor + team messages') +
        '</div>' +
        '<div class="n8lc-card"><div class="n8lc-card-head"><h2>Recent conversations</h2><a href="admin.php?page=n8-livechat-analytics">View analytics</a></div>' + conversationTable(list) + '</div>';
      bindNotificationButton();
    }).catch(errorView);
  }

  var inbox = {
    conversations: [], selected: null, messages: [], filter: '', search: '', canned: [], departments: [], tags: [], typingTimer: null, refreshTimer: null, stateTimer: null, threadRequestSeq: 0, lastThreadMessageId: 0
  };

  function renderInbox() {
    app.innerHTML = '<div class="n8lc-inbox">' +
      '<aside class="n8lc-inbox-list"><div class="n8lc-inbox-tools"><input id="n8lc-search" type="search" placeholder="Search visitor or subject"><select id="n8lc-status"><option value="">All</option><option value="open">Open</option><option value="pending">Pending</option><option value="closed">Closed</option></select><select id="n8lc-priority"><option value="">Any priority</option><option value="urgent">Urgent</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option></select></div><div id="n8lc-conversation-list" class="n8lc-conversation-list"><div class="n8lc-loading">Loading…</div></div></aside>' +
      '<main id="n8lc-thread" class="n8lc-thread"><div class="n8lc-thread-empty"><span class="dashicons dashicons-format-chat"></span><h2>Select a conversation</h2><p>Read messages, send files, assign agents, use tags, track SLA, add notes and reply.</p></div></main>' +
    '</div>';

    document.getElementById('n8lc-status').addEventListener('change', function (e) { inbox.filter = e.target.value; loadConversations(); });
    document.getElementById('n8lc-priority').addEventListener('change', loadConversations);
    var searchTimer;
    document.getElementById('n8lc-search').addEventListener('input', function (e) {
      inbox.search = e.target.value;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(loadConversations, 250);
    });

    Promise.all([api('admin/canned-replies'), api('admin/departments'), api('admin/tags')]).then(function (r) {
      inbox.canned = r[0].canned_replies || [];
      inbox.departments = r[1].departments || [];
      inbox.tags = r[2].tags || [];
    }).catch(function () {});

    loadConversations();
    inbox.refreshTimer = window.setInterval(function () { loadConversations(true); }, Number(cfg.pollInterval || 4000));
    inbox.stateTimer = window.setInterval(loadThreadState, 2000);
  }

  function loadConversations(silent) {
    var list = document.getElementById('n8lc-conversation-list');
    if (!list) return;
    var params = new URLSearchParams();
    if (inbox.filter) params.set('status', inbox.filter);
    if (inbox.search) params.set('search', inbox.search);
    var p = document.getElementById('n8lc-priority');
    if (p && p.value) params.set('priority', p.value);
    params.set('per_page', '60');
    if (!silent) list.innerHTML = '<div class="n8lc-loading">Loading…</div>';
    api('admin/conversations?' + params.toString()).then(function (data) {
      inbox.conversations = data.conversations || [];
      drawConversationList();
      if (inbox.selected && silent) {
        var selected = selectedConversation();
        if (selected && Number(selected.unread_agent || 0) > 0) loadThread(inbox.selected, true);
      }
      api('admin/stats').then(maybeNotify).catch(function () {});
    }).catch(function (e) {
      list.innerHTML = '<div class="n8lc-inline-error">' + esc(e.message) + '</div>';
    });
  }

  function drawConversationList() {
    var el = document.getElementById('n8lc-conversation-list');
    if (!el) return;
    if (!inbox.conversations.length) {
      el.innerHTML = '<div class="n8lc-empty">No conversations.</div>';
      return;
    }
    el.innerHTML = inbox.conversations.map(function (c) {
      var active = Number(inbox.selected) === Number(c.id) ? ' is-active' : '';
      var warn = Number(c.sla_breached) ? '<span class="n8lc-mini-alert">SLA</span>' : '';
      var csat = Number(c.csat_rating || 0) ? '<span class="n8lc-mini-csat">' + ['','😞','🙁','😐','🙂','😍'][Number(c.csat_rating)] + ' ' + Number(c.csat_rating) + '/5</span>' : '';
      return '<button class="n8lc-conversation' + active + '" data-id="' + Number(c.id) + '"><div><strong>' + esc(c.visitor_name || 'Anonymous') + '</strong><span>' + warn + csat + (Number(c.unread_agent) ? '<span class="n8lc-unread">' + Number(c.unread_agent) + '</span>' : '') + '</span></div><p>' + esc(c.subject || c.visitor_email || 'New conversation') + '</p>' + tagPills(c.tags) + '<small>' + badge(c.status) + ' · ' + esc(c.priority) + ' · ' + esc(fmtDate(c.last_message_at || c.created_at)) + '</small></button>';
    }).join('');
    Array.prototype.forEach.call(el.querySelectorAll('.n8lc-conversation'), function (btn) {
      btn.addEventListener('click', function () {
        inbox.selected = Number(btn.dataset.id);
        var inboxEl = document.querySelector('.n8lc-inbox');
        if (inboxEl) inboxEl.classList.add('is-thread-open');
        drawConversationList();
        loadThread(inbox.selected);
      });
    });
  }

  function selectedConversation() {
    return inbox.conversations.find(function (c) { return Number(c.id) === Number(inbox.selected); }) || null;
  }

  function loadThread(id, silent) {
    var draftEl = document.getElementById('n8lc-reply-text');
    var draft = draftEl ? draftEl.value : '';
    var hadFocus = !!draftEl && document.activeElement === draftEl;
    var selectionStart = hadFocus ? draftEl.selectionStart : null;
    var selectionEnd = hadFocus ? draftEl.selectionEnd : null;
    var privateEl = document.getElementById('n8lc-private-note');
    var wasPrivate = privateEl ? privateEl.checked : false;
    var thread = document.getElementById('n8lc-thread');
    if (!thread) return;
    var requestSeq = ++inbox.threadRequestSeq;
    if (!silent) thread.innerHTML = '<div class="n8lc-loading">Loading conversation…</div>';
    Promise.all([api('admin/conversations/' + id + '/messages'), api('admin/conversations/' + id + '/state')]).then(function (r) {
      if (requestSeq !== inbox.threadRequestSeq || Number(id) !== Number(inbox.selected)) return;
      inbox.messages = r[0].messages || [];
      inbox.lastThreadMessageId = inbox.messages.reduce(function (max, m) { return Math.max(max, Number(m.id || 0)); }, 0);
      drawThread(r[1]);
      var restored = document.getElementById('n8lc-reply-text');
      if (restored) {
        restored.value = draft;
        if (hadFocus) {
          restored.focus({ preventScroll: true });
          if (selectionStart !== null && restored.setSelectionRange) {
            var max = restored.value.length;
            restored.setSelectionRange(Math.min(selectionStart, max), Math.min(selectionEnd, max));
          }
        }
      }
      var restoredPrivate = document.getElementById('n8lc-private-note');
      if (restoredPrivate) restoredPrivate.checked = wasPrivate;
    }).catch(function (e) {
      if (requestSeq === inbox.threadRequestSeq) thread.innerHTML = '<div class="n8lc-inline-error">' + esc(e.message) + '</div>';
    });
  }

  function visitorTypingHtml(active) {
    return active ? '<span class="n8lc-admin-typing-dots"><i></i><i></i><i></i></span><strong>Visitor is typing…</strong>' : '';
  }

  function loadThreadState() {
    if (!inbox.selected || cfg.page !== 'n8-livechat-inbox') return;
    api('admin/conversations/' + inbox.selected + '/state').then(function (s) {
      var el = document.getElementById('n8lc-visitor-typing');
      if (el) el.innerHTML = visitorTypingHtml(!!s.visitor_typing);
      if (Number(s.latest_message_id || 0) > Number(inbox.lastThreadMessageId || 0)) loadThread(inbox.selected, true);
    }).catch(function () {});
  }

  function customDataLines(data) {
    data = data && typeof data === 'object' ? data : {};
    return Object.keys(data).map(function (key) { return key + '=' + data[key]; }).join('\n');
  }

  function parseCustomData(text) {
    var out = {};
    String(text || '').split(/\r?\n/).forEach(function (line) {
      var at = line.indexOf('=');
      if (at < 1) return;
      var key = line.slice(0, at).trim().toLowerCase().replace(/[^a-z0-9_-]/g, '_');
      if (!key) return;
      out[key] = line.slice(at + 1).trim().slice(0, 300);
    });
    return out;
  }

  function adminMessageHtml(m, c) {
    c = c || selectedConversation() || {};
    var type = Number(m.is_private) === 1 ? 'note' : m.sender_type;
    var sender = type === 'visitor' ? (c.visitor_name || 'Visitor') : (type === 'note' ? 'Private note' : (type === 'system' ? 'System' : 'Agent'));
    return '<div class="n8lc-admin-msg n8lc-admin-msg-' + esc(type) + '" data-message-id="' + Number(m.id || 0) + '"><div><span class="n8lc-sender">' + esc(sender) + '</span><div class="n8lc-admin-bubble">' + attachmentHtml(m) + '</div><time>' + esc(fmtDate(m.created_at)) + '</time></div></div>';
  }

  function appendAdminMessage(m) {
    if (!m) return;
    var id = Number(m.id || 0);
    if (id && inbox.messages.some(function (x) { return Number(x.id) === id; })) return;
    inbox.messages.push(m);
    if (id) inbox.lastThreadMessageId = Math.max(inbox.lastThreadMessageId, id);
    var body = document.getElementById('n8lc-thread-body');
    if (body) {
      body.insertAdjacentHTML('beforeend', adminMessageHtml(m));
      body.scrollTop = body.scrollHeight;
    }
  }

  function drawThread(stateData) {
    var c = selectedConversation();
    if (!c) return;
    var agentOptions = '<option value="0">Unassigned</option>' + (cfg.agents || []).map(function (a) {
      return '<option value="' + Number(a.id) + '" ' + (Number(c.agent_id) === Number(a.id) ? 'selected' : '') + '>' + esc(a.name) + '</option>';
    }).join('');
    var deptOptions = '<option value="0">No department</option>' + inbox.departments.map(function (d) {
      return '<option value="' + Number(d.id) + '" ' + (Number(c.department_id) === Number(d.id) ? 'selected' : '') + '>' + esc(d.name) + '</option>';
    }).join('');
    var cannedOptions = '<option value="">Insert canned reply…</option>' + inbox.canned.filter(function (x) { return Number(x.is_active) === 1; }).map(function (x) {
      return '<option value="' + Number(x.id) + '">' + esc((x.shortcut ? '/' + x.shortcut + ' · ' : '') + x.title) + '</option>';
    }).join('');
    var currentTagIds = (c.tags || []).map(function (t) { return Number(t.id); });
    var tagChecks = inbox.tags.length ? inbox.tags.map(function (t) {
      var checked = currentTagIds.indexOf(Number(t.id)) >= 0 ? ' checked' : '';
      return '<label class="n8lc-tag-check"><input type="checkbox" value="' + Number(t.id) + '"' + checked + '><span style="--tag:' + esc(t.color || '#64748b') + '">' + esc(t.name) + '</span></label>';
    }).join('') : '<small>No tags created yet.</small>';

    var messages = inbox.messages.map(function (m) { return adminMessageHtml(m, c); }).join('');

    var sla = Number(c.sla_breached) ? '<span class="n8lc-sla-breach">SLA BREACHED</span>' : '<span class="n8lc-sla-ok">First response due: ' + esc(fmtDate(c.first_response_due_at)) + '</span>';
    var el = document.getElementById('n8lc-thread');
    el.innerHTML = '<div class="n8lc-thread-head"><button type="button" class="button n8lc-mobile-back" aria-label="Back to conversations">← Conversations</button><div><h2>' + esc(c.visitor_name || 'Anonymous') + '</h2><p>' + esc(c.visitor_email || '') + (c.visitor_phone ? ' · ' + esc(c.visitor_phone) : '') + '</p><div class="n8lc-thread-meta">' + sla + ' ' + tagPills(c.tags) + '</div></div><div class="n8lc-thread-actions"><select id="n8lc-status-edit"><option value="open">Open</option><option value="pending">Pending</option><option value="closed">Closed</option></select><select id="n8lc-priority-edit"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select><select id="n8lc-agent-edit">' + agentOptions + '</select><select id="n8lc-dept-edit">' + deptOptions + '</select></div></div>' +
      '<div class="n8lc-thread-body" id="n8lc-thread-body">' + messages + '</div>' +
      '<div id="n8lc-visitor-typing" class="n8lc-typing-line">' + visitorTypingHtml(!!(stateData && stateData.visitor_typing)) + '</div>' +
      '<details class="n8lc-context"><summary>Conversation context, tags & custom fields</summary><div class="n8lc-context-grid"><div><strong>Tags</strong><div id="n8lc-thread-tags" class="n8lc-tag-picker">' + tagChecks + '</div><button type="button" class="button" id="n8lc-save-tags">Save tags</button></div><div><strong>Custom fields</strong><textarea id="n8lc-custom-data" rows="5" placeholder="order_id=12345\nplan=pro">' + esc(customDataLines(c.custom_data)) + '</textarea><button type="button" class="button" id="n8lc-save-custom">Save fields</button></div></div></details>' +
      '<div class="n8lc-thread-compose"><div class="n8lc-compose-top"><select id="n8lc-canned-select">' + cannedOptions + '</select><label><input type="checkbox" id="n8lc-private-note"> Private note</label><button type="button" class="button" id="n8lc-send-transcript">Email transcript</button></div><textarea id="n8lc-reply-text" rows="3" maxlength="5000" placeholder="Write a reply…"></textarea><div class="n8lc-compose-bottom"><div><input type="file" id="n8lc-agent-file" class="n8lc-hidden-file"><button type="button" class="button" id="n8lc-attach-file">📎 Attach</button><span id="n8lc-reply-status"></span></div><button class="button button-primary" id="n8lc-send-reply">Send reply</button></div></div>';

    var mobileBack = document.querySelector('.n8lc-mobile-back');
    if (mobileBack) mobileBack.addEventListener('click', function () { var inboxEl = document.querySelector('.n8lc-inbox'); if (inboxEl) inboxEl.classList.remove('is-thread-open'); });
    document.getElementById('n8lc-status-edit').value = c.status;
    document.getElementById('n8lc-priority-edit').value = c.priority;
    ['n8lc-status-edit', 'n8lc-priority-edit', 'n8lc-agent-edit', 'n8lc-dept-edit'].forEach(function (id) {
      document.getElementById(id).addEventListener('change', updateConversation);
    });
    document.getElementById('n8lc-canned-select').addEventListener('change', function (e) {
      var found = inbox.canned.find(function (x) { return Number(x.id) === Number(e.target.value); });
      if (found) document.getElementById('n8lc-reply-text').value = found.body;
    });
    document.getElementById('n8lc-send-reply').addEventListener('click', sendReply);
    document.getElementById('n8lc-reply-text').addEventListener('input', agentTyping);
    document.getElementById('n8lc-attach-file').addEventListener('click', function () { document.getElementById('n8lc-agent-file').click(); });
    document.getElementById('n8lc-agent-file').addEventListener('change', uploadAgentFile);
    document.getElementById('n8lc-save-tags').addEventListener('click', saveThreadTags);
    document.getElementById('n8lc-save-custom').addEventListener('click', saveCustomData);
    document.getElementById('n8lc-send-transcript').addEventListener('click', sendTranscript);
    var body = document.getElementById('n8lc-thread-body');
    body.scrollTop = body.scrollHeight;
  }

  function updateConversation() {
    api('admin/conversations/' + inbox.selected, {
      method: 'PATCH',
      body: {
        status: document.getElementById('n8lc-status-edit').value,
        priority: document.getElementById('n8lc-priority-edit').value,
        agent_id: Number(document.getElementById('n8lc-agent-edit').value || 0),
        department_id: Number(document.getElementById('n8lc-dept-edit').value || 0)
      }
    }).then(function () { loadConversations(true); }).catch(function (e) { window.alert(e.message); });
  }

  function agentTyping() {
    if (!inbox.selected) return;
    api('admin/conversations/' + inbox.selected + '/typing', { method: 'POST', body: { typing: true } }).catch(function () {});
    clearTimeout(inbox.typingTimer);
    inbox.typingTimer = setTimeout(function () {
      api('admin/conversations/' + inbox.selected + '/typing', { method: 'POST', body: { typing: false } }).catch(function () {});
    }, 2500);
  }

  function sendReply() {
    var text = document.getElementById('n8lc-reply-text');
    var status = document.getElementById('n8lc-reply-status');
    var button = document.getElementById('n8lc-send-reply');
    var isPrivate = document.getElementById('n8lc-private-note').checked;
    var body = text.value.trim();
    if (!body) return;
    button.disabled = true;
    status.textContent = 'Sending…';
    api('admin/conversations/' + inbox.selected + '/reply', {
      method: 'POST', body: { message: body, is_private: isPrivate }
    }).then(function (payload) {
      text.value = '';
      status.textContent = 'Sent ✓';
      if (payload && payload.message) appendAdminMessage(payload.message);
      if (!isPrivate) {
        var c = selectedConversation();
        if (c) c.status = 'open';
        var statusEdit = document.getElementById('n8lc-status-edit');
        if (statusEdit) statusEdit.value = 'open';
      }
      api('admin/conversations/' + inbox.selected + '/typing', { method: 'POST', body: { typing: false } }).catch(function () {});
      loadConversations(true);
      if (!payload || !payload.message) loadThread(inbox.selected, true);
    }).catch(function (e) {
      status.textContent = e.message;
    }).finally(function () {
      button.disabled = false;
      var liveText = document.getElementById('n8lc-reply-text');
      if (liveText) liveText.focus({ preventScroll: true });
    });
  }

  function uploadAgentFile(e) {
    var file = e.target.files && e.target.files[0];
    if (!file || !inbox.selected) return;
    var status = document.getElementById('n8lc-reply-status');
    var form = new FormData();
    form.append('file', file);
    status.textContent = 'Uploading ' + file.name + '…';
    api('admin/conversations/' + inbox.selected + '/upload', { method: 'POST', body: form }).then(function () {
      status.textContent = 'File sent';
      e.target.value = '';
      loadThread(inbox.selected, true);
      loadConversations(true);
    }).catch(function (err) { status.textContent = err.message; });
  }

  function saveThreadTags() {
    var ids = Array.prototype.map.call(document.querySelectorAll('#n8lc-thread-tags input:checked'), function (el) { return Number(el.value); });
    api('admin/conversations/' + inbox.selected + '/tags', { method: 'PATCH', body: { tag_ids: ids } }).then(function () {
      loadConversations(true);
    }).catch(function (e) { window.alert(e.message); });
  }

  function saveCustomData() {
    var data = parseCustomData(document.getElementById('n8lc-custom-data').value);
    api('admin/conversations/' + inbox.selected, { method: 'PATCH', body: { custom_data: data } }).then(function () {
      loadConversations(true);
    }).catch(function (e) { window.alert(e.message); });
  }

  function sendTranscript() {
    var btn = document.getElementById('n8lc-send-transcript');
    btn.disabled = true;
    btn.textContent = 'Sending…';
    api('admin/conversations/' + inbox.selected + '/transcript', { method: 'POST', body: {} }).then(function () {
      btn.textContent = 'Transcript sent';
    }).catch(function (e) {
      btn.textContent = 'Email transcript';
      window.alert(e.message);
    }).finally(function () { btn.disabled = false; });
  }

  function renderVisitors() {
    app.innerHTML = '<div class="n8lc-card"><div class="n8lc-card-head"><h2>Visitor directory</h2><input id="n8lc-visitor-search" type="search" placeholder="Search name, email, phone"></div><div id="n8lc-visitors-table" class="n8lc-loading">Loading…</div></div>';
    var timer;
    document.getElementById('n8lc-visitor-search').addEventListener('input', function (e) {
      clearTimeout(timer);
      timer = setTimeout(function () { loadVisitors(e.target.value); }, 250);
    });
    loadVisitors('');
  }

  function loadVisitors(search) {
    api('admin/visitors?search=' + encodeURIComponent(search || '')).then(function (d) {
      var rows = d.visitors || [];
      document.getElementById('n8lc-visitors-table').innerHTML = rows.length ? '<div class="n8lc-table-wrap"><table class="widefat striped"><thead><tr><th>Visitor</th><th>Phone</th><th>Chats</th><th>First seen</th><th>Last seen</th><th>Last page</th></tr></thead><tbody>' + rows.map(function (v) {
        return '<tr><td><strong>' + esc(v.name || 'Anonymous') + '</strong><br><small>' + esc(v.email || '') + '</small></td><td>' + esc(v.phone || '—') + '</td><td>' + esc(v.conversations) + '</td><td>' + esc(fmtDate(v.first_seen)) + '</td><td>' + esc(fmtDate(v.last_seen)) + '</td><td class="n8lc-url-cell">' + esc(v.last_url || '—') + '</td></tr>';
      }).join('') + '</tbody></table></div>' : '<div class="n8lc-empty">No visitors.</div>';
    }).catch(errorView);
  }

  function renderCanned() {
    app.innerHTML = '<div class="n8lc-two-col"><div class="n8lc-card"><h2>Add canned reply</h2><form id="n8lc-canned-form" class="n8lc-form"><label>Title<input name="title" required></label><label>Shortcut<input name="shortcut" placeholder="refund"></label><label>Message<textarea name="body" rows="7" required></textarea></label><button class="button button-primary">Save reply</button><span class="n8lc-form-status"></span></form></div><div class="n8lc-card"><h2>Saved replies</h2><div id="n8lc-canned-list" class="n8lc-loading">Loading…</div></div></div>';
    document.getElementById('n8lc-canned-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var data = new FormData(e.currentTarget);
      api('admin/canned-replies', { method: 'POST', body: { title: data.get('title'), shortcut: data.get('shortcut'), body: data.get('body') } }).then(function () {
        e.currentTarget.reset(); loadCanned();
      }).catch(function (err) { e.currentTarget.querySelector('.n8lc-form-status').textContent = err.message; });
    });
    loadCanned();
  }

  function loadCanned() {
    api('admin/canned-replies').then(function (d) {
      var rows = d.canned_replies || [];
      var el = document.getElementById('n8lc-canned-list');
      el.innerHTML = rows.length ? rows.map(function (r) {
        return '<div class="n8lc-list-item"><div><strong>' + esc(r.title) + '</strong><small>' + esc(r.shortcut ? '/' + r.shortcut : '') + '</small><p>' + esc(r.body) + '</p></div><button class="button-link-delete n8lc-delete-canned" data-id="' + Number(r.id) + '">Delete</button></div>';
      }).join('') : '<div class="n8lc-empty">No canned replies.</div>';
      Array.prototype.forEach.call(el.querySelectorAll('.n8lc-delete-canned'), function (b) {
        b.addEventListener('click', function () { api('admin/canned-replies/' + b.dataset.id, { method: 'DELETE' }).then(loadCanned); });
      });
    }).catch(errorView);
  }

  function renderDepartments() {
    app.innerHTML = '<div class="n8lc-two-col"><div class="n8lc-card"><h2>Add department</h2><form id="n8lc-dept-form" class="n8lc-form"><label>Name<input name="name" required></label><label>Slug<input name="slug"></label><label>Description<textarea name="description" rows="5"></textarea></label><button class="button button-primary">Add department</button></form></div><div class="n8lc-card"><h2>Departments</h2><div id="n8lc-dept-list" class="n8lc-loading">Loading…</div></div></div>';
    document.getElementById('n8lc-dept-form').addEventListener('submit', function (e) {
      e.preventDefault(); var fd = new FormData(e.currentTarget);
      api('admin/departments', { method: 'POST', body: { name: fd.get('name'), slug: fd.get('slug'), description: fd.get('description') } }).then(function () { e.currentTarget.reset(); loadDepartments(); }).catch(function (err) { window.alert(err.message); });
    });
    loadDepartments();
  }

  function loadDepartments() {
    api('admin/departments').then(function (d) {
      var el = document.getElementById('n8lc-dept-list');
      var rows = d.departments || [];
      el.innerHTML = rows.length ? rows.map(function (r) {
        return '<div class="n8lc-list-item"><div><strong>' + esc(r.name) + '</strong><small>' + esc(r.slug) + '</small><p>' + esc(r.description || '') + '</p></div><button class="button-link-delete n8lc-delete-dept" data-id="' + Number(r.id) + '">Delete</button></div>';
      }).join('') : '<div class="n8lc-empty">No departments.</div>';
      Array.prototype.forEach.call(el.querySelectorAll('.n8lc-delete-dept'), function (b) {
        b.addEventListener('click', function () { if (window.confirm('Delete this department?')) api('admin/departments/' + b.dataset.id, { method: 'DELETE' }).then(loadDepartments); });
      });
    }).catch(errorView);
  }

  function renderTags() {
    app.innerHTML = '<div class="n8lc-two-col"><div class="n8lc-card"><h2>Create tag</h2><form id="n8lc-tag-form" class="n8lc-form"><label>Name<input name="name" required></label><label>Slug<input name="slug"></label><label>Color<input name="color" type="color" value="#64748b"></label><button class="button button-primary">Create tag</button></form></div><div class="n8lc-card"><h2>Tags</h2><div id="n8lc-tag-list" class="n8lc-loading">Loading…</div></div></div>';
    document.getElementById('n8lc-tag-form').addEventListener('submit', function (e) {
      e.preventDefault(); var fd = new FormData(e.currentTarget);
      api('admin/tags', { method: 'POST', body: { name: fd.get('name'), slug: fd.get('slug'), color: fd.get('color') } }).then(function () { e.currentTarget.reset(); loadTags(); }).catch(function (err) { window.alert(err.message); });
    });
    loadTags();
  }

  function loadTags() {
    api('admin/tags').then(function (d) {
      var rows = d.tags || [];
      var el = document.getElementById('n8lc-tag-list');
      el.innerHTML = rows.length ? rows.map(function (r) {
        return '<div class="n8lc-list-item"><div>' + tagPills([r]) + '<small>' + esc(r.slug) + '</small></div><button class="button-link-delete n8lc-delete-tag" data-id="' + Number(r.id) + '">Delete</button></div>';
      }).join('') : '<div class="n8lc-empty">No tags.</div>';
      Array.prototype.forEach.call(el.querySelectorAll('.n8lc-delete-tag'), function (b) {
        b.addEventListener('click', function () { api('admin/tags/' + b.dataset.id, { method: 'DELETE' }).then(loadTags); });
      });
    }).catch(errorView);
  }

  function dayRow(day, label, value) {
    value = value || { enabled: 0, start: '09:00', end: '17:00' };
    return '<div class="n8lc-hours-row" data-day="' + day + '"><label><input type="checkbox" class="n8lc-hours-enabled" ' + (Number(value.enabled) ? 'checked' : '') + '> ' + label + '</label><input type="time" class="n8lc-hours-start" value="' + esc(value.start || '09:00') + '"><span>to</span><input type="time" class="n8lc-hours-end" value="' + esc(value.end || '17:00') + '"></div>';
  }

  function renderAutomation() {
    app.innerHTML = '<div class="n8lc-loading">Loading automation settings…</div>';
    api('admin/settings').then(function (s) {
      var h = s.business_hours || {};
      app.innerHTML = '<form id="n8lc-automation-form"><div class="n8lc-grid-2">' +
        '<div class="n8lc-card"><h2>Auto assignment</h2><label class="n8lc-check"><input type="checkbox" id="n8lc-auto-enabled" ' + (Number(s.auto_assign_enabled) ? 'checked' : '') + '> Enable automatic agent assignment</label><label>Mode<select id="n8lc-auto-mode"><option value="load">Lowest active load</option><option value="round_robin">Round robin</option></select></label><p class="description">New online conversations are assigned to WordPress users with the LiveChat reply capability.</p></div>' +
        '<div class="n8lc-card"><h2>SLA & escalation</h2><label class="n8lc-check"><input type="checkbox" id="n8lc-sla-enabled" ' + (Number(s.sla_enabled) ? 'checked' : '') + '> Enable SLA timers</label><label>First response target (minutes)<input type="number" id="n8lc-first-response" min="1" value="' + esc(s.first_response_minutes || 15) + '"></label><label>Resolution target (minutes)<input type="number" id="n8lc-resolution" min="1" value="' + esc(s.resolution_minutes || 480) + '"></label><label class="n8lc-check"><input type="checkbox" id="n8lc-escalation-email" ' + (Number(s.escalation_email) ? 'checked' : '') + '> Email admin on SLA breach</label></div>' +
        '<div class="n8lc-card"><h2>Business hours</h2><label class="n8lc-check"><input type="checkbox" id="n8lc-hours-enabled" ' + (Number(s.business_hours_enabled) ? 'checked' : '') + '> Use business hours</label><div class="n8lc-hours">' + dayRow('mon','Monday',h.mon) + dayRow('tue','Tuesday',h.tue) + dayRow('wed','Wednesday',h.wed) + dayRow('thu','Thursday',h.thu) + dayRow('fri','Friday',h.fri) + dayRow('sat','Saturday',h.sat) + dayRow('sun','Sunday',h.sun) + '</div></div>' +
        '<div class="n8lc-card"><h2>Notifications & webhook</h2><label class="n8lc-check"><input type="checkbox" id="n8lc-email-notifications" ' + (Number(s.email_notifications) ? 'checked' : '') + '> Email agent/admin on visitor messages</label><label class="n8lc-check"><input type="checkbox" id="n8lc-webhook-enabled" ' + (Number(s.webhook_enabled) ? 'checked' : '') + '> Enable signed outbound webhooks</label><label>Webhook URL<input type="url" id="n8lc-webhook-url" value="' + esc(s.webhook_url || '') + '" placeholder="https://your-n8n.example/webhook/livechat"></label><label>Webhook secret<input type="text" id="n8lc-webhook-secret" value="' + esc(s.webhook_secret || '') + '"></label><p class="description">Payloads include X-N8LC-Signature: sha256=&lt;HMAC&gt;.</p></div>' +
      '</div><p><button class="button button-primary" type="submit">Save automation settings</button> <span id="n8lc-auto-status"></span></p></form>';
      document.getElementById('n8lc-auto-mode').value = s.auto_assign_mode || 'load';
      document.getElementById('n8lc-automation-form').addEventListener('submit', saveAutomation);
    }).catch(errorView);
  }

  function collectHours() {
    var hours = {};
    Array.prototype.forEach.call(document.querySelectorAll('.n8lc-hours-row'), function (row) {
      hours[row.dataset.day] = {
        enabled: row.querySelector('.n8lc-hours-enabled').checked ? 1 : 0,
        start: row.querySelector('.n8lc-hours-start').value,
        end: row.querySelector('.n8lc-hours-end').value
      };
    });
    return hours;
  }

  function saveAutomation(e) {
    e.preventDefault();
    var status = document.getElementById('n8lc-auto-status');
    status.textContent = 'Saving…';
    api('admin/settings', { method: 'PATCH', body: {
      auto_assign_enabled: document.getElementById('n8lc-auto-enabled').checked,
      auto_assign_mode: document.getElementById('n8lc-auto-mode').value,
      sla_enabled: document.getElementById('n8lc-sla-enabled').checked,
      first_response_minutes: Number(document.getElementById('n8lc-first-response').value || 15),
      resolution_minutes: Number(document.getElementById('n8lc-resolution').value || 480),
      escalation_email: document.getElementById('n8lc-escalation-email').checked,
      business_hours_enabled: document.getElementById('n8lc-hours-enabled').checked,
      business_hours: collectHours(),
      email_notifications: document.getElementById('n8lc-email-notifications').checked,
      webhook_enabled: document.getElementById('n8lc-webhook-enabled').checked,
      webhook_url: document.getElementById('n8lc-webhook-url').value,
      webhook_secret: document.getElementById('n8lc-webhook-secret').value
    }}).then(function () { status.textContent = 'Saved'; }).catch(function (err) { status.textContent = err.message; });
  }

  function bars(title, rows, labelKey) {
    rows = rows || [];
    var max = rows.reduce(function (m, r) { return Math.max(m, Number(r.total || 0)); }, 1);
    return '<div class="n8lc-bars"><h3>' + esc(title) + '</h3>' + (rows.length ? rows.map(function (r) {
      var label = r[labelKey] || 'Unknown';
      var width = Math.max(2, (Number(r.total || 0) / max) * 100);
      return '<div class="n8lc-bar-row"><span>' + esc(label) + '</span><div><i style="width:' + width + '%"></i></div><strong>' + esc(r.total) + '</strong></div>';
    }).join('') : '<div class="n8lc-empty">No data.</div>') + '</div>';
  }

  function renderAnalytics() {
    app.innerHTML = '<div class="n8lc-toolbar"><select id="n8lc-days"><option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option></select></div><div id="n8lc-analytics" class="n8lc-loading">Loading analytics…</div>';
    document.getElementById('n8lc-days').addEventListener('change', loadAnalytics);
    loadAnalytics();
  }

  function loadAnalytics() {
    var days = Number(document.getElementById('n8lc-days').value || 30);
    api('admin/analytics?days=' + days).then(function (d) {
      document.getElementById('n8lc-analytics').innerHTML = '<div class="n8lc-stats-grid"><div class="n8lc-card n8lc-stat"><span>Avg first response</span><strong>' + esc(d.avg_first_response == null ? '—' : d.avg_first_response + 'm') + '</strong><small>Across replied chats</small></div><div class="n8lc-card n8lc-stat"><span>Average CSAT</span><strong>' + esc(d.avg_csat == null ? '—' : d.avg_csat + '/5') + '</strong><small>Visitor ratings</small></div><div class="n8lc-card n8lc-stat"><span>SLA breaches</span><strong>' + esc(d.sla_breaches) + '</strong><small>In selected period</small></div></div><div class="n8lc-card"><div class="n8lc-analytics-grid">' + bars('Conversation status', d.by_status, 'status') + bars('Department volume', d.by_department, 'department') + bars('Daily conversations', d.daily_conversations, 'day') + bars('Daily messages', d.daily_messages, 'day') + '</div></div>';
    }).catch(errorView);
  }

  function renderAudit() {
    app.innerHTML = '<div class="n8lc-toolbar"><button class="button button-primary" id="n8lc-export">Download conversations CSV</button><select id="n8lc-audit-type"><option value="">All events</option><option value="conversation_created">Conversation created</option><option value="visitor_message">Visitor messages</option><option value="agent_message">Agent replies</option><option value="sla_breached">SLA breaches</option><option value="attachment_uploaded">Uploads</option><option value="settings_updated">Settings changes</option></select></div><div class="n8lc-card"><div id="n8lc-audit-list" class="n8lc-loading">Loading audit log…</div></div>';
    document.getElementById('n8lc-export').addEventListener('click', exportCsv);
    document.getElementById('n8lc-audit-type').addEventListener('change', loadAudit);
    loadAudit();
  }

  function loadAudit() {
    var type = document.getElementById('n8lc-audit-type').value;
    api('admin/audit?limit=200' + (type ? '&event_type=' + encodeURIComponent(type) : '')).then(function (d) {
      var rows = d.events || [];
      document.getElementById('n8lc-audit-list').innerHTML = rows.length ? '<div class="n8lc-table-wrap"><table class="widefat striped"><thead><tr><th>Time</th><th>Event</th><th>Conversation</th><th>Agent</th><th>Payload</th></tr></thead><tbody>' + rows.map(function (r) {
        return '<tr><td>' + esc(fmtDate(r.created_at)) + '</td><td><code>' + esc(r.event_type) + '</code></td><td>' + esc(r.conversation_id || '—') + '</td><td>' + esc(r.agent_name || r.agent_id || '—') + '</td><td class="n8lc-payload-cell"><code>' + esc(r.payload || '') + '</code></td></tr>';
      }).join('') + '</tbody></table></div>' : '<div class="n8lc-empty">No audit events.</div>';
    }).catch(errorView);
  }

  function exportCsv() {
    var btn = document.getElementById('n8lc-export');
    btn.disabled = true; btn.textContent = 'Preparing CSV…';
    api('admin/export?limit=5000').then(function (d) {
      var blob = new Blob([d.csv || ''], { type: 'text/csv;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url; a.download = d.filename || 'n8-livechat-export.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }).catch(function (e) { window.alert(e.message); }).finally(function () { btn.disabled = false; btn.textContent = 'Download conversations CSV'; });
  }

  function renderSettings() {
    app.innerHTML = '<div class="n8lc-loading">Loading settings…</div>';
    api('admin/settings').then(function (s) {
      app.innerHTML = '<div class="n8lc-card n8lc-settings-card"><h2>Widget & storage settings</h2><form id="n8lc-settings-form" class="n8lc-settings-form"><label>Widget title<input id="n8lc-title" value="' + esc(s.widget_title || '') + '"></label><label>Position<select id="n8lc-position"><option value="right">Right</option><option value="left">Left</option></select></label><label>Accent color<input id="n8lc-color" type="color" value="' + esc(s.accent_color || '#111827') + '"></label><label>Welcome message<textarea id="n8lc-welcome" rows="3">' + esc(s.welcome_message || '') + '</textarea></label><label>Offline message<textarea id="n8lc-offline" rows="3">' + esc(s.offline_message || '') + '</textarea></label><label>Poll interval (ms)<input id="n8lc-poll" type="number" min="1500" max="30000" value="' + esc(s.poll_interval || 3000) + '"></label><label>Event retention (days)<input id="n8lc-retention" type="number" min="7" max="3650" value="' + esc(s.retention_days || 365) + '"></label><label>Max upload size (MB)<input id="n8lc-upload-mb" type="number" min="1" max="25" value="' + esc(s.max_upload_mb || 5) + '"></label><div class="n8lc-check-grid"><label class="n8lc-check"><input id="n8lc-enabled" type="checkbox" ' + (Number(s.enabled) ? 'checked' : '') + '> Enable widget</label><label class="n8lc-check"><input id="n8lc-require-email" type="checkbox" ' + (Number(s.require_email) ? 'checked' : '') + '> Require visitor email</label><label class="n8lc-check"><input id="n8lc-uploads" type="checkbox" ' + (Number(s.uploads_enabled) ? 'checked' : '') + '> Allow visitor uploads</label><label class="n8lc-check"><input id="n8lc-csat" type="checkbox" ' + (Number(s.csat_enabled) ? 'checked' : '') + '> Enable CSAT rating</label><label class="n8lc-check"><input id="n8lc-delete-data" type="checkbox" ' + (Number(s.delete_data_on_uninstall) ? 'checked' : '') + '> Delete all chat data on uninstall</label></div><p><button class="button button-primary">Save settings</button> <span id="n8lc-settings-status"></span></p></form></div>';
      document.getElementById('n8lc-position').value = s.position || 'right';
      document.getElementById('n8lc-settings-form').addEventListener('submit', function (e) {
        e.preventDefault(); var status = document.getElementById('n8lc-settings-status'); status.textContent = 'Saving…';
        api('admin/settings', { method: 'PATCH', body: {
          enabled: document.getElementById('n8lc-enabled').checked,
          widget_title: document.getElementById('n8lc-title').value,
          position: document.getElementById('n8lc-position').value,
          accent_color: document.getElementById('n8lc-color').value,
          welcome_message: document.getElementById('n8lc-welcome').value,
          offline_message: document.getElementById('n8lc-offline').value,
          poll_interval: Number(document.getElementById('n8lc-poll').value || 3000),
          retention_days: Number(document.getElementById('n8lc-retention').value || 365),
          max_upload_mb: Number(document.getElementById('n8lc-upload-mb').value || 5),
          require_email: document.getElementById('n8lc-require-email').checked,
          uploads_enabled: document.getElementById('n8lc-uploads').checked,
          csat_enabled: document.getElementById('n8lc-csat').checked,
          delete_data_on_uninstall: document.getElementById('n8lc-delete-data').checked
        }}).then(function () { status.textContent = 'Saved'; }).catch(function (err) { status.textContent = err.message; });
      });
    }).catch(errorView);
  }

  var pages = {
    'n8-livechat': renderDashboard,
    'n8-livechat-inbox': renderInbox,
    'n8-livechat-visitors': renderVisitors,
    'n8-livechat-canned': renderCanned,
    'n8-livechat-departments': renderDepartments,
    'n8-livechat-tags': renderTags,
    'n8-livechat-automation': renderAutomation,
    'n8-livechat-analytics': renderAnalytics,
    'n8-livechat-audit': renderAudit,
    'n8-livechat-settings': renderSettings
  };

  (pages[cfg.page] || renderDashboard)();
}());
