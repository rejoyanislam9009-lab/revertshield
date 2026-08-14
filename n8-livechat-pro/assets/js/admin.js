(function () {
  'use strict';
  if (!window.N8LCAdmin) return;
  var cfg = window.N8LCAdmin;
  var app = document.getElementById('n8lc-admin-app');
  if (!app) return;

  function esc(v) {
    return String(v == null ? '' : v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  function api(path, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['X-WP-Nonce'] = cfg.nonce;
    if (options.body && typeof options.body !== 'string') {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    return fetch(cfg.restRoot + path, options).then(function (r) {
      return r.json().then(function (b) {
        if (!r.ok) throw new Error(b && b.message ? b.message : cfg.i18n.error);
        return b;
      });
    });
  }

  function moneylessCard(label, value, hint) {
    return '<div class="n8lc-card n8lc-stat"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong><small>' + esc(hint || '') + '</small></div>';
  }

  function formatDate(v) {
    if (!v) return '—';
    var d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d.getTime()) ? v : d.toLocaleString();
  }

  function errorView(err) {
    app.innerHTML = '<div class="notice notice-error inline"><p>' + esc(err.message || cfg.i18n.error) + '</p></div>';
  }

  function statusBadge(status) {
    return '<span class="n8lc-badge n8lc-' + esc(status) + '">' + esc(status) + '</span>';
  }

  function renderDashboard() {
    app.innerHTML = '<div class="n8lc-loading">' + esc(cfg.i18n.loading) + '</div>';
    Promise.all([api('admin/stats'), api('admin/conversations?per_page=10')]).then(function (r) {
      var s = r[0], list = r[1].conversations || [];
      app.innerHTML = '<div class="n8lc-stats-grid">' +
        moneylessCard('Open', s.open, 'Active conversations') +
        moneylessCard('Pending', s.pending, 'Waiting for follow-up') +
        moneylessCard('Unread', s.unread, 'Messages needing attention') +
        moneylessCard('Visitors online', s.online_visitors, 'Seen in the last 5 minutes') +
        moneylessCard('Chats today', s.conversations_today, 'New conversations') +
        moneylessCard('Messages today', s.messages_today, 'Visitor + team messages') +
      '</div>' +
      '<div class="n8lc-card"><div class="n8lc-card-head"><h2>Recent conversations</h2><a class="button button-primary" href="admin.php?page=n8-livechat-inbox">Open inbox</a></div>' + conversationTable(list) + '</div>';
    }).catch(errorView);
  }

  function conversationTable(list) {
    if (!list.length) return '<div class="n8lc-empty">' + esc(cfg.i18n.empty) + '</div>';
    return '<div class="n8lc-table-wrap"><table class="widefat striped"><thead><tr><th>Visitor</th><th>Status</th><th>Priority</th><th>Department</th><th>Agent</th><th>Unread</th><th>Updated</th></tr></thead><tbody>' + list.map(function (c) {
      return '<tr><td><strong>' + esc(c.visitor_name || 'Anonymous') + '</strong><br><small>' + esc(c.visitor_email || '') + '</small></td><td>' + statusBadge(c.status) + '</td><td>' + esc(c.priority) + '</td><td>' + esc(c.department_name || '—') + '</td><td>' + esc(c.agent_name || 'Unassigned') + '</td><td>' + esc(c.unread_agent) + '</td><td>' + esc(formatDate(c.last_message_at || c.updated_at)) + '</td></tr>';
    }).join('') + '</tbody></table></div>';
  }

  var inbox = { conversations: [], selected: null, messages: [], filter: '', search: '', canned: [], departments: [] };

  function renderInbox() {
    app.innerHTML = '<div class="n8lc-inbox">' +
      '<aside class="n8lc-inbox-list"><div class="n8lc-inbox-tools"><input id="n8lc-search" type="search" placeholder="Search visitor or subject"><select id="n8lc-status"><option value="">All</option><option value="open">Open</option><option value="pending">Pending</option><option value="closed">Closed</option></select></div><div id="n8lc-conversation-list" class="n8lc-conversation-list"><div class="n8lc-loading">Loading…</div></div></aside>' +
      '<main id="n8lc-thread" class="n8lc-thread"><div class="n8lc-thread-empty"><span class="dashicons dashicons-format-chat"></span><h2>Select a conversation</h2><p>Read messages, assign an agent, change priority, add notes, and reply.</p></div></main>' +
    '</div>';
    document.getElementById('n8lc-status').addEventListener('change', function (e) { inbox.filter = e.target.value; loadConversations(); });
    var timer;
    document.getElementById('n8lc-search').addEventListener('input', function (e) {
      inbox.search = e.target.value; clearTimeout(timer); timer = setTimeout(loadConversations, 250);
    });
    Promise.all([api('admin/canned-replies'), api('admin/departments')]).then(function (r) {
      inbox.canned = r[0].canned_replies || [];
      inbox.departments = r[1].departments || [];
    }).catch(function () {});
    loadConversations();
    window.setInterval(function () { if (cfg.page === 'n8-livechat-inbox') loadConversations(true); }, Number(cfg.pollInterval || 4000));
  }

  function loadConversations(silent) {
    var params = new URLSearchParams();
    if (inbox.filter) params.set('status', inbox.filter);
    if (inbox.search) params.set('search', inbox.search);
    params.set('per_page', '50');
    if (!silent) document.getElementById('n8lc-conversation-list').innerHTML = '<div class="n8lc-loading">Loading…</div>';
    api('admin/conversations?' + params.toString()).then(function (data) {
      inbox.conversations = data.conversations || [];
      drawConversationList();
      if (inbox.selected) loadThread(inbox.selected, true);
    }).catch(function (e) {
      document.getElementById('n8lc-conversation-list').innerHTML = '<div class="n8lc-inline-error">' + esc(e.message) + '</div>';
    });
  }

  function drawConversationList() {
    var el = document.getElementById('n8lc-conversation-list');
    if (!inbox.conversations.length) { el.innerHTML = '<div class="n8lc-empty">No conversations.</div>'; return; }
    el.innerHTML = inbox.conversations.map(function (c) {
      var active = Number(inbox.selected) === Number(c.id) ? ' is-active' : '';
      return '<button class="n8lc-conversation' + active + '" data-id="' + Number(c.id) + '"><div><strong>' + esc(c.visitor_name || 'Anonymous') + '</strong>' + (Number(c.unread_agent) ? '<span class="n8lc-unread">' + Number(c.unread_agent) + '</span>' : '') + '</div><p>' + esc(c.subject || c.visitor_email || 'New conversation') + '</p><small>' + statusBadge(c.status) + ' · ' + esc(formatDate(c.last_message_at || c.created_at)) + '</small></button>';
    }).join('');
    Array.prototype.forEach.call(el.querySelectorAll('.n8lc-conversation'), function (btn) {
      btn.addEventListener('click', function () { inbox.selected = Number(btn.dataset.id); drawConversationList(); loadThread(inbox.selected); });
    });
  }

  function getSelectedConversation() {
    return inbox.conversations.find(function (c) { return Number(c.id) === Number(inbox.selected); }) || null;
  }

  function loadThread(id, silent) {
    if (!silent) document.getElementById('n8lc-thread').innerHTML = '<div class="n8lc-loading">Loading conversation…</div>';
    api('admin/conversations/' + id + '/messages').then(function (data) {
      inbox.messages = data.messages || [];
      drawThread();
    }).catch(function (e) {
      document.getElementById('n8lc-thread').innerHTML = '<div class="n8lc-inline-error">' + esc(e.message) + '</div>';
    });
  }

  function drawThread() {
    var c = getSelectedConversation();
    if (!c) return;
    var agentOptions = '<option value="0">Unassigned</option>' + (cfg.agents || []).map(function (a) { return '<option value="' + Number(a.id) + '" ' + (Number(c.agent_id) === Number(a.id) ? 'selected' : '') + '>' + esc(a.name) + '</option>'; }).join('');
    var deptOptions = '<option value="0">No department</option>' + inbox.departments.map(function (d) { return '<option value="' + Number(d.id) + '" ' + (Number(c.department_id) === Number(d.id) ? 'selected' : '') + '>' + esc(d.name) + '</option>'; }).join('');
    var cannedOptions = '<option value="">Insert canned reply…</option>' + inbox.canned.filter(function (x) { return Number(x.is_active) === 1; }).map(function (x) { return '<option value="' + Number(x.id) + '">' + esc((x.shortcut ? '/' + x.shortcut + ' · ' : '') + x.title) + '</option>'; }).join('');
    var messages = inbox.messages.map(function (m) {
      var type = m.is_private == 1 ? 'note' : m.sender_type;
      return '<div class="n8lc-admin-msg n8lc-admin-msg-' + esc(type) + '"><div><span class="n8lc-sender">' + esc(type === 'visitor' ? (c.visitor_name || 'Visitor') : (type === 'note' ? 'Private note' : (type === 'system' ? 'System' : 'Agent'))) + '</span><div class="n8lc-admin-bubble">' + esc(m.body) + '</div><time>' + esc(formatDate(m.created_at)) + '</time></div></div>';
    }).join('');

    var el = document.getElementById('n8lc-thread');
    el.innerHTML = '<div class="n8lc-thread-head"><div><h2>' + esc(c.visitor_name || 'Anonymous') + '</h2><p>' + esc(c.visitor_email || '') + (c.visitor_last_seen ? ' · last seen ' + esc(formatDate(c.visitor_last_seen)) : '') + '</p></div><div class="n8lc-thread-actions"><select id="n8lc-status-edit"><option value="open">Open</option><option value="pending">Pending</option><option value="closed">Closed</option></select><select id="n8lc-priority-edit"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select><select id="n8lc-agent-edit">' + agentOptions + '</select><select id="n8lc-dept-edit">' + deptOptions + '</select></div></div>' +
      '<div class="n8lc-thread-body" id="n8lc-thread-body">' + messages + '</div>' +
      '<div class="n8lc-thread-compose"><div class="n8lc-compose-top"><select id="n8lc-canned-select">' + cannedOptions + '</select><label><input type="checkbox" id="n8lc-private-note"> Private note</label></div><textarea id="n8lc-reply-text" rows="3" maxlength="5000" placeholder="Write a reply…"></textarea><div class="n8lc-compose-bottom"><span id="n8lc-reply-status"></span><button class="button button-primary" id="n8lc-send-reply">Send reply</button></div></div>';
    document.getElementById('n8lc-status-edit').value = c.status;
    document.getElementById('n8lc-priority-edit').value = c.priority;
    ['status','priority','agent','dept'].forEach(function (key) {
      var map = {status:'n8lc-status-edit',priority:'n8lc-priority-edit',agent:'n8lc-agent-edit',dept:'n8lc-dept-edit'};
      document.getElementById(map[key]).addEventListener('change', updateConversation);
    });
    document.getElementById('n8lc-canned-select').addEventListener('change', function (e) {
      var found = inbox.canned.find(function (x) { return Number(x.id) === Number(e.target.value); });
      if (found) document.getElementById('n8lc-reply-text').value = found.body;
    });
    document.getElementById('n8lc-send-reply').addEventListener('click', sendReply);
    var body = document.getElementById('n8lc-thread-body'); body.scrollTop = body.scrollHeight;
  }

  function updateConversation() {
    var id = inbox.selected;
    api('admin/conversations/' + id, { method: 'PATCH', body: {
      status: document.getElementById('n8lc-status-edit').value,
      priority: document.getElementById('n8lc-priority-edit').value,
      agent_id: Number(document.getElementById('n8lc-agent-edit').value || 0),
      department_id: Number(document.getElementById('n8lc-dept-edit').value || 0)
    }}).then(function () { loadConversations(true); }).catch(function (e) { window.alert(e.message); });
  }

  function sendReply() {
    var text = document.getElementById('n8lc-reply-text');
    var status = document.getElementById('n8lc-reply-status');
    var button = document.getElementById('n8lc-send-reply');
    var body = text.value.trim(); if (!body) return;
    button.disabled = true; status.textContent = 'Sending…';
    api('admin/conversations/' + inbox.selected + '/reply', { method: 'POST', body: { message: body, is_private: document.getElementById('n8lc-private-note').checked }}).then(function () {
      text.value = ''; status.textContent = 'Sent'; loadThread(inbox.selected, true); loadConversations(true);
    }).catch(function (e) { status.textContent = e.message; }).finally(function () { button.disabled = false; });
  }

  function renderVisitors() {
    app.innerHTML = '<div class="n8lc-card"><div class="n8lc-card-head"><h2>Visitor directory</h2><input id="n8lc-visitor-search" type="search" placeholder="Search name, email, phone"></div><div id="n8lc-visitors-table" class="n8lc-loading">Loading…</div></div>';
    var timer;
    document.getElementById('n8lc-visitor-search').addEventListener('input', function (e) { clearTimeout(timer); timer = setTimeout(function () { loadVisitors(e.target.value); }, 250); });
    loadVisitors('');
  }

  function loadVisitors(search) {
    api('admin/visitors?search=' + encodeURIComponent(search || '')).then(function (d) {
      var rows = d.visitors || [];
      document.getElementById('n8lc-visitors-table').innerHTML = rows.length ? '<div class="n8lc-table-wrap"><table class="widefat striped"><thead><tr><th>Visitor</th><th>Phone</th><th>Chats</th><th>First seen</th><th>Last seen</th><th>Last page</th></tr></thead><tbody>' + rows.map(function (v) {
        return '<tr><td><strong>' + esc(v.name || 'Anonymous') + '</strong><br><small>' + esc(v.email || '') + '</small></td><td>' + esc(v.phone || '—') + '</td><td>' + esc(v.conversations) + '</td><td>' + esc(formatDate(v.first_seen)) + '</td><td>' + esc(formatDate(v.last_seen)) + '</td><td class="n8lc-url-cell">' + esc(v.last_url || '—') + '</td></tr>';
      }).join('') + '</tbody></table></div>' : '<div class="n8lc-empty">No visitors found.</div>';
    }).catch(errorView);
  }

  function renderCanned() {
    app.innerHTML = '<div class="n8lc-two-col"><div class="n8lc-card"><h2>Add canned reply</h2><form id="n8lc-canned-form" class="n8lc-form"><label>Title<input name="title" required></label><label>Shortcut<input name="shortcut" placeholder="refund"></label><label>Message<textarea name="body" rows="7" required></textarea></label><button class="button button-primary" type="submit">Save reply</button><span class="n8lc-form-status"></span></form></div><div class="n8lc-card"><div class="n8lc-card-head"><h2>Saved replies</h2></div><div id="n8lc-canned-list" class="n8lc-loading">Loading…</div></div></div>';
    document.getElementById('n8lc-canned-form').addEventListener('submit', function (e) {
      e.preventDefault(); var f = e.currentTarget, d = new FormData(f), s = f.querySelector('.n8lc-form-status'); s.textContent = 'Saving…';
      api('admin/canned-replies', { method:'POST', body:{ title:d.get('title'), shortcut:d.get('shortcut'), body:d.get('body') }}).then(function(){ f.reset(); s.textContent='Saved'; loadCanned(); }).catch(function(err){ s.textContent=err.message; });
    });
    loadCanned();
  }

  function loadCanned() {
    api('admin/canned-replies').then(function (d) {
      var rows = d.canned_replies || [], el = document.getElementById('n8lc-canned-list');
      el.innerHTML = rows.length ? rows.map(function (x) { return '<div class="n8lc-list-item"><div><strong>' + esc(x.title) + '</strong><small>' + esc(x.shortcut ? '/' + x.shortcut : 'No shortcut') + '</small><p>' + esc(x.body) + '</p></div><button class="button-link-delete n8lc-delete-canned" data-id="' + Number(x.id) + '">Delete</button></div>'; }).join('') : '<div class="n8lc-empty">No canned replies yet.</div>';
      Array.prototype.forEach.call(el.querySelectorAll('.n8lc-delete-canned'), function (b) { b.addEventListener('click', function(){ if (!window.confirm('Delete this canned reply?')) return; api('admin/canned-replies/' + b.dataset.id,{method:'DELETE'}).then(loadCanned); }); });
    }).catch(errorView);
  }

  function renderDepartments() {
    app.innerHTML = '<div class="n8lc-two-col"><div class="n8lc-card"><h2>Add department</h2><form id="n8lc-dept-form" class="n8lc-form"><label>Name<input name="name" required></label><label>Slug<input name="slug" placeholder="sales"></label><label>Description<textarea name="description" rows="5"></textarea></label><button class="button button-primary" type="submit">Add department</button><span class="n8lc-form-status"></span></form></div><div class="n8lc-card"><h2>Departments</h2><div id="n8lc-dept-list" class="n8lc-loading">Loading…</div></div></div>';
    document.getElementById('n8lc-dept-form').addEventListener('submit', function(e){ e.preventDefault(); var f=e.currentTarget,d=new FormData(f),s=f.querySelector('.n8lc-form-status'); s.textContent='Saving…'; api('admin/departments',{method:'POST',body:{name:d.get('name'),slug:d.get('slug'),description:d.get('description')}}).then(function(){f.reset();s.textContent='Saved';loadDepartments();}).catch(function(err){s.textContent=err.message;}); });
    loadDepartments();
  }

  function loadDepartments() {
    api('admin/departments').then(function(d){ var rows=d.departments||[],el=document.getElementById('n8lc-dept-list'); el.innerHTML=rows.length?rows.map(function(x){return '<div class="n8lc-list-item"><div><strong>'+esc(x.name)+'</strong><small>/'+esc(x.slug)+' · '+(Number(x.is_active)?'Active':'Inactive')+'</small><p>'+esc(x.description||'')+'</p></div></div>';}).join(''):'<div class="n8lc-empty">No departments.</div>'; }).catch(errorView);
  }

  function renderAnalytics() {
    app.innerHTML = '<div class="n8lc-card"><div class="n8lc-card-head"><h2>30-day activity</h2><select id="n8lc-analytics-days"><option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option></select></div><div id="n8lc-analytics" class="n8lc-loading">Loading…</div></div>';
    document.getElementById('n8lc-analytics-days').addEventListener('change', function(e){ loadAnalytics(e.target.value); }); loadAnalytics(30);
  }

  function bars(rows, label) {
    if (!rows.length) return '<div class="n8lc-empty">No data.</div>';
    var max = Math.max.apply(null, rows.map(function(x){return Number(x.total)||0;})) || 1;
    return '<div class="n8lc-bars"><h3>'+esc(label)+'</h3>' + rows.map(function(x){ var w=Math.max(2,Math.round((Number(x.total)||0)/max*100)); return '<div class="n8lc-bar-row"><span>'+esc(x.day||x.status||x.department)+'</span><div><i style="width:'+w+'%"></i></div><strong>'+esc(x.total)+'</strong></div>'; }).join('') + '</div>';
  }

  function loadAnalytics(days) {
    api('admin/analytics?days=' + encodeURIComponent(days)).then(function(d){ document.getElementById('n8lc-analytics').innerHTML='<div class="n8lc-analytics-grid">'+bars(d.daily_conversations||[],'Conversations by day')+bars(d.daily_messages||[],'Messages by day')+bars(d.by_status||[],'Conversation status')+bars(d.by_department||[],'Department volume')+'</div>'; }).catch(errorView);
  }

  function renderSettings() {
    app.innerHTML = '<div class="n8lc-card n8lc-settings-card"><div class="n8lc-loading">Loading settings…</div></div>';
    api('admin/settings').then(function(s){ var el=app.querySelector('.n8lc-settings-card'); el.innerHTML='<h2>Widget & behavior</h2><form id="n8lc-settings-form" class="n8lc-form n8lc-settings-form">'+
      '<label class="n8lc-check"><input type="checkbox" name="enabled" '+(Number(s.enabled)?'checked':'')+'> Enable live chat widget</label>'+
      '<label>Widget title<input name="widget_title" value="'+esc(s.widget_title||'')+'"></label>'+
      '<label>Welcome message<textarea name="welcome_message" rows="3">'+esc(s.welcome_message||'')+'</textarea></label>'+
      '<label>Offline message<textarea name="offline_message" rows="3">'+esc(s.offline_message||'')+'</textarea></label>'+
      '<label>Widget position<select name="position"><option value="right" '+(s.position==='right'?'selected':'')+'>Right</option><option value="left" '+(s.position==='left'?'selected':'')+'>Left</option></select></label>'+
      '<label>Accent color<input name="accent_color" type="color" value="'+esc(s.accent_color||'#111827')+'"></label>'+
      '<label class="n8lc-check"><input type="checkbox" name="require_email" '+(Number(s.require_email)?'checked':'')+'> Require visitor email</label>'+
      '<label class="n8lc-check"><input type="checkbox" name="privacy_mode" '+(Number(s.privacy_mode)?'checked':'')+'> Privacy mode</label>'+
      '<label>Polling interval (ms)<input name="poll_interval" type="number" min="1500" max="15000" value="'+esc(s.poll_interval||3000)+'"></label>'+
      '<label>Event retention (days)<input name="retention_days" type="number" min="7" max="3650" value="'+esc(s.retention_days||365)+'"></label>'+
      '<div><button class="button button-primary" type="submit">Save settings</button> <span class="n8lc-form-status"></span></div></form>';
      document.getElementById('n8lc-settings-form').addEventListener('submit', saveSettings);
    }).catch(errorView);
  }

  function saveSettings(e) {
    e.preventDefault(); var f=e.currentTarget,d=new FormData(f),status=f.querySelector('.n8lc-form-status'); status.textContent='Saving…';
    api('admin/settings',{method:'PATCH',body:{enabled:d.get('enabled')==='on',widget_title:d.get('widget_title'),welcome_message:d.get('welcome_message'),offline_message:d.get('offline_message'),position:d.get('position'),accent_color:d.get('accent_color'),require_email:d.get('require_email')==='on',privacy_mode:d.get('privacy_mode')==='on',poll_interval:Number(d.get('poll_interval')),retention_days:Number(d.get('retention_days'))}}).then(function(){status.textContent='Saved';}).catch(function(err){status.textContent=err.message;});
  }

  var pages = {
    'n8-livechat': renderDashboard,
    'n8-livechat-inbox': renderInbox,
    'n8-livechat-visitors': renderVisitors,
    'n8-livechat-canned': renderCanned,
    'n8-livechat-departments': renderDepartments,
    'n8-livechat-analytics': renderAnalytics,
    'n8-livechat-settings': renderSettings
  };
  (pages[cfg.page] || renderDashboard)();
}());
