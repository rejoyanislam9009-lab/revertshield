(function () {
  'use strict';
  var cfg = window.N8LCPlatform || {};
  var root = document.getElementById('n8lc-platform-app');
  if (!root) return;

  var state = { tab: 'overview', summary: null, settings: null, cache: {}, busy: false };
  var tabs = [
    ['overview', 'Overview'], ['team', 'Team'], ['customers', 'Customers'], ['routing-rules', 'Routing'], ['saved-views', 'Saved Views'],
    ['segments', 'Segments'], ['custom-fields', 'Custom Fields'], ['integrations', 'Integrations'],
    ['blocks', 'Blocked Visitors'], ['bulk', 'Bulk Operations'], ['experience', 'Widget Experience'], ['diagnostics', 'Diagnostics']
  ];

  function esc(v) { var d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; }
  function api(path, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['X-WP-Nonce'] = cfg.nonce || '';
    if (options.body && typeof options.body !== 'string') {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    return fetch(cfg.restRoot + path, options).then(function (r) {
      return r.json().then(function (b) { if (!r.ok) throw new Error(b && b.message ? b.message : 'Request failed'); return b; });
    });
  }
  function field(label, control, help) { return '<label class="n8lc-p-field"><span>' + esc(label) + '</span>' + control + (help ? '<small>' + esc(help) + '</small>' : '') + '</label>'; }
  function input(name, value, type, attrs) { return '<input name="' + esc(name) + '" type="' + esc(type || 'text') + '" value="' + esc(value == null ? '' : value) + '" ' + (attrs || '') + '>'; }
  function checkbox(name, checked, label) { return '<label class="n8lc-p-check"><input type="checkbox" name="' + esc(name) + '" ' + (checked ? 'checked' : '') + '><span>' + esc(label) + '</span></label>'; }
  function select(name, value, options) {
    return '<select name="' + esc(name) + '">' + options.map(function (o) { return '<option value="' + esc(o[0]) + '" ' + (String(o[0]) === String(value) ? 'selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select>';
  }
  function textarea(name, value, attrs) { return '<textarea name="' + esc(name) + '" ' + (attrs || '') + '>' + esc(value || '') + '</textarea>'; }
  function toast(msg, bad) {
    var el = document.createElement('div'); el.className = 'n8lc-p-toast ' + (bad ? 'is-bad' : ''); el.textContent = msg;
    document.body.appendChild(el); setTimeout(function () { el.remove(); }, 2600);
  }
  function nav() {
    return '<div class="n8lc-p-tabs" role="tablist">' + tabs.map(function (t) {
      return '<button type="button" data-tab="' + t[0] + '" class="' + (state.tab === t[0] ? 'is-active' : '') + '">' + esc(t[1]) + '</button>';
    }).join('') + '</div>';
  }
  function shell(body) { root.innerHTML = nav() + '<div class="n8lc-p-body">' + body + '</div>'; bindNav(); }
  function bindNav() {
    Array.prototype.forEach.call(root.querySelectorAll('[data-tab]'), function (b) {
      b.addEventListener('click', function () { state.tab = b.dataset.tab; render(); });
    });
  }

  function loadSummary() {
    return Promise.all([api('summary'), api('settings')]).then(function (r) { state.summary = r[0]; state.settings = r[1]; });
  }
  function metric(label, value, hint) { return '<div class="n8lc-p-metric"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong><small>' + esc(hint || '') + '</small></div>'; }
  function overview() {
    var s = state.summary || { counts: {} };
    var c = s.counts || {};
    return '<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>Workspace overview</h2><p>Operational controls added on top of the stable live-chat core.</p></div><span class="n8lc-p-badge">WordPress ' + esc(s.wordpress || '') + '</span></div>' +
      '<div class="n8lc-p-metrics">' + metric('Agents', s.agents || 0, 'reply-capable users') + metric('Routing rules', c.routing_rules || 0, 'conditional assignment') + metric('Custom fields', c.custom_fields || 0, 'structured customer data') + metric('Integrations', c.integrations || 0, 'admin-authorized endpoints') + metric('Segments', c.segments || 0, 'customer grouping') + metric('KB articles', s.knowledge_articles || 0, 'self-service content') + '</div>' +
      '<div class="n8lc-p-grid two"><div class="n8lc-p-card"><h3>Professional controls</h3><p>Team profiles, routing, saved views, segments, custom fields, bulk actions, blocks, integration endpoints, privacy exporters/erasers, Site Health checks and knowledge content.</p></div>' +
      '<div class="n8lc-p-card"><h3>Compatibility boundary</h3><p>Existing v0.3 conversations, messages, attachments, tags, automation, SLA, CSAT and signed webhook behavior remain in place. v0.4 extends them through separate tables and hooks.</p></div></div></section>';
  }

  function loadResource(name) {
    if (state.cache[name]) return Promise.resolve(state.cache[name]);
    return api(name).then(function (r) { state.cache[name] = r.items || []; return state.cache[name]; });
  }
  function removeResource(name, id) { return api(name + '/' + id, { method: 'DELETE' }).then(function () { delete state.cache[name]; toast('Deleted'); render(); }); }
  function saveResource(name, body, id) { return api(name + (id ? '/' + id : ''), { method: id ? 'POST' : 'POST', body: body }).then(function () { delete state.cache[name]; toast('Saved'); render(); }); }
  function cardList(items, renderItem) { return '<div class="n8lc-p-list">' + (items.length ? items.map(renderItem).join('') : '<div class="n8lc-p-empty">Nothing configured yet.</div>') + '</div>'; }

  function team() {
    shell('<div class="n8lc-p-loading">Loading team…</div>');
    api('agents').then(function (r) {
      var items = r.items || [];
      shell('<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>Team & agent profiles</h2><p>Control workload, availability, skills, languages and notification preferences.</p></div></div>' +
        cardList(items, function (a) {
          var p = a.profile || {};
          return '<form class="n8lc-p-card n8lc-agent-form" data-user="' + Number(a.user_id) + '"><div class="n8lc-p-card-head"><div><h3>' + esc(a.display_name) + '</h3><p>' + esc(a.email) + '</p></div><span class="n8lc-p-badge">' + esc(p.availability || 'auto') + '</span></div><div class="n8lc-p-grid three">' +
            field('Title', input('title', p.title)) + field('Availability', select('availability', p.availability, [['auto','Auto'],['online','Online'],['away','Away'],['offline','Offline']])) + field('Max active chats', input('max_active_chats', p.max_active_chats || 8, 'number', 'min="1" max="100"')) +
            (a.can_onboard && a.access_level !== 'administrator' ? field('Workspace access', select('access_level', a.access_level || 'none', [['none','No LiveChat access'],['agent','Agent'],['manager','Manager']])) : field('Workspace access','<input value="'+esc(a.access_level || 'agent')+'" disabled>')) +
            field('Languages', input('languages', (p.languages || []).join(', '))) + field('Skills', input('skills', (p.skills || []).join(', '))) + field('Avatar URL', input('avatar_url', p.avatar_url, 'url')) + '</div><div class="n8lc-p-checks">' + checkbox('email_notifications', p.email_notifications, 'Email') + checkbox('browser_notifications', p.browser_notifications, 'Browser') + checkbox('sound_notifications', p.sound_notifications, 'Sound') + '</div><button class="button button-primary">Save agent</button></form>';
        }) + '</section>');
      Array.prototype.forEach.call(root.querySelectorAll('.n8lc-agent-form'), function (f) {
        f.addEventListener('submit', function (e) { e.preventDefault(); var d = new FormData(f); api('agents', { method:'POST', body:{ user_id:Number(f.dataset.user), access_level:d.get('access_level') || undefined, title:d.get('title'), availability:d.get('availability'), max_active_chats:Number(d.get('max_active_chats')), languages:String(d.get('languages')||'').split(','), skills:String(d.get('skills')||'').split(','), avatar_url:d.get('avatar_url'), email_notifications:d.has('email_notifications'), browser_notifications:d.has('browser_notifications'), sound_notifications:d.has('sound_notifications') } }).then(function(){ toast('Agent updated'); }); });
      });
    }).catch(function (e) { shell('<div class="n8lc-p-error">' + esc(e.message) + '</div>'); });
  }

  function customers(search) {
    shell('<div class="n8lc-p-loading">Loading customers…</div>');
    api('customers' + (search ? '?search=' + encodeURIComponent(search) : '')).then(function (r) {
      var items = r.items || [];
      shell('<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>Customer 360</h2><p>Contact profile, support history, consent and structured pre-chat data.</p></div><form id="n8lc-customer-search" class="n8lc-p-inline-search"><input name="search" value="'+esc(search||'')+'" placeholder="Search name, email or phone"><button class="button">Search</button></form></div>' + cardList(items, function(c){
        var p=c.profile||{}, fields=c.custom_fields||{};
        return '<form class="n8lc-p-card n8lc-customer-form" data-id="'+Number(c.id)+'"><div class="n8lc-p-card-head"><div><h3>'+esc(c.name||'Anonymous visitor')+'</h3><p>'+esc(c.email||'No email')+' · '+esc(c.phone||'No phone')+'</p></div><span class="n8lc-p-badge">'+Number(c.conversation_count||0)+' chats</span></div><div class="n8lc-p-grid three">'+field('Name',input('name',c.name))+field('Email',input('email',c.email,'email'))+field('Phone',input('phone',c.phone))+field('Company',input('company',p.company||''))+field('Customer status',select('status',p.status||'customer',[['lead','Lead'],['customer','Customer'],['vip','VIP'],['at_risk','At risk']]))+field('Last seen','<input value="'+esc(c.last_seen||'')+'" disabled>')+'</div>'+field('Internal profile notes',textarea('notes',p.notes||'','rows="3"'))+'<div class="n8lc-p-meta"><strong>Pre-chat fields</strong><pre>'+esc(JSON.stringify(fields,null,2))+'</pre><span>Consent: '+(c.consent?'Yes':'No')+'</span></div><button class="button button-primary">Save customer</button></form>';
      })+'</section>');
      document.getElementById('n8lc-customer-search').addEventListener('submit',function(e){e.preventDefault();customers(new FormData(e.currentTarget).get('search')||'');});
      Array.prototype.forEach.call(root.querySelectorAll('.n8lc-customer-form'),function(f){f.addEventListener('submit',function(e){e.preventDefault();var d=new FormData(f);api('customers/'+f.dataset.id,{method:'POST',body:{name:d.get('name'),email:d.get('email'),phone:d.get('phone'),company:d.get('company'),status:d.get('status'),notes:d.get('notes')}}).then(function(){toast('Customer updated');});});});
    }).catch(function(e){shell('<div class="n8lc-p-error">'+esc(e.message)+'</div>');});
  }

  function bulkOperations() {
    shell('<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>Bulk conversation operations</h2><p>Apply a controlled action to up to 100 conversation IDs without rewriting the stable Inbox application.</p></div></div><form id="n8lc-bulk-form" class="n8lc-p-card"><div class="n8lc-p-grid three">'+field('Conversation IDs',textarea('ids','','rows="3" placeholder="101, 102, 103"'))+field('Action',select('action','status',[['status','Status'],['priority','Priority'],['agent','Agent assignment'],['department','Department assignment']]))+field('Value',input('value','open'))+'</div><p class="description">Status: open/pending/closed · Priority: low/normal/high/urgent · Agent/Department: numeric ID, or 0 to unassign.</p><button class="button button-primary">Apply bulk action</button></form><div id="n8lc-bulk-result"></div></section>');
    document.getElementById('n8lc-bulk-form').addEventListener('submit',function(e){e.preventDefault();var d=new FormData(e.currentTarget);var ids=String(d.get('ids')||'').split(',').map(function(v){return Number(v.trim())}).filter(Boolean).slice(0,100);api('bulk',{method:'POST',body:{ids:ids,action:d.get('action'),value:d.get('value')}}).then(function(r){document.getElementById('n8lc-bulk-result').innerHTML='<div class="notice notice-success inline"><p>Updated '+Number(r.changed||0)+' of '+Number(r.selected||0)+' selected conversations.</p></div>';});});
  }

  function routing() {
    shell('<div class="n8lc-p-loading">Loading routing…</div>');
    loadResource('routing-rules').then(function (items) {
      shell('<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>Routing rules</h2><p>Match source, email domain, URL, referrer, department or business hours, then set agent, department, priority, status or tag.</p></div></div>' +
        '<form id="n8lc-route-new" class="n8lc-p-card"><h3>New routing rule</h3><div class="n8lc-p-grid three">' + field('Name', input('name','')) + field('Priority', input('priority',100,'number')) + field('Email domain', input('email_domain','')) + field('URL contains', input('url_contains','')) + field('Department ID', input('department_id','0','number')) + field('Set priority', select('set_priority','normal',[['low','Low'],['normal','Normal'],['high','High'],['urgent','Urgent']])) + field('Agent ID', input('agent_id','0','number')) + field('Set department ID', input('set_department_id','0','number')) + '</div>' + checkbox('is_active', true, 'Active') + '<p><button class="button button-primary">Create rule</button></p></form>' +
        cardList(items, function (x) { return '<div class="n8lc-p-card"><div class="n8lc-p-card-head"><div><h3>' + esc(x.name) + '</h3><p>Priority ' + Number(x.priority) + '</p></div><span class="n8lc-p-badge">' + (x.is_active ? 'Active' : 'Disabled') + '</span></div><pre>' + esc(JSON.stringify({match:x.match,action:x.action},null,2)) + '</pre><button class="button-link-delete" data-del-resource="routing-rules" data-id="' + Number(x.id) + '">Delete</button></div>'; }) + '</section>');
      document.getElementById('n8lc-route-new').addEventListener('submit', function (e) { e.preventDefault(); var d=new FormData(e.currentTarget); saveResource('routing-rules',{name:d.get('name'),priority:Number(d.get('priority')),is_active:d.has('is_active'),match:{email_domain:d.get('email_domain'),url_contains:d.get('url_contains'),department_id:Number(d.get('department_id'))||0},action:{priority:d.get('set_priority'),agent_id:Number(d.get('agent_id'))||0,department_id:Number(d.get('set_department_id'))||0}}); }); bindDeletes();
    });
  }

  function simpleResource(name, title, desc, formHtml, bodyFn, itemFn) {
    shell('<div class="n8lc-p-loading">Loading…</div>');
    loadResource(name).then(function (items) {
      shell('<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>' + esc(title) + '</h2><p>' + esc(desc) + '</p></div></div><form id="n8lc-resource-new" class="n8lc-p-card">' + formHtml + '<p><button class="button button-primary">Add</button></p></form>' + cardList(items, itemFn) + '</section>');
      document.getElementById('n8lc-resource-new').addEventListener('submit', function (e) { e.preventDefault(); saveResource(name, bodyFn(new FormData(e.currentTarget))); }); bindDeletes();
    });
  }
  function bindDeletes() { Array.prototype.forEach.call(root.querySelectorAll('[data-del-resource]'), function (b) { b.addEventListener('click', function(){ if(confirm('Delete this item?')) removeResource(b.dataset.delResource, Number(b.dataset.id)); }); }); }

  function savedViews() { simpleResource('saved-views','Saved inbox views','Save reusable inbox filters and optionally share them with the team','<h3>New saved view</h3>'+field('Name',input('name',''))+field('Status',select('status','open',[['all','All'],['open','Open'],['pending','Pending'],['closed','Closed']]))+checkbox('is_shared',false,'Share with team'),function(d){return{name:d.get('name'),filters:{status:d.get('status')},is_shared:d.has('is_shared')}} ,function(x){return '<div class="n8lc-p-card"><div class="n8lc-p-card-head"><h3>'+esc(x.name)+'</h3><span class="n8lc-p-badge">'+(x.is_shared?'Shared':'Private')+'</span></div><pre>'+esc(JSON.stringify(x.filters||{},null,2))+'</pre><button class="button-link-delete" data-del-resource="saved-views" data-id="'+Number(x.id)+'">Delete</button></div>';}); }
  function segments() { simpleResource('segments','Customer segments','Define reusable customer groups for reporting and future automations','<h3>New segment</h3><div class="n8lc-p-grid two">'+field('Name',input('name',''))+field('Color',input('color','#64748b','color'))+'</div>'+field('Description',textarea('description','')),function(d){return{name:d.get('name'),description:d.get('description'),color:d.get('color'),rules:{}}},function(x){return '<div class="n8lc-p-card"><div class="n8lc-p-card-head"><h3><i class="n8lc-dot" style="background:'+esc(x.color)+'"></i>'+esc(x.name)+'</h3><span class="n8lc-p-badge">'+(x.is_active?'Active':'Disabled')+'</span></div><p>'+esc(x.description||'')+'</p><button class="button-link-delete" data-del-resource="segments" data-id="'+Number(x.id)+'">Delete</button></div>';}); }
  function customFields() { simpleResource('custom-fields','Custom fields','Build structured pre-chat, visitor and conversation fields without changing the core schema','<h3>New field</h3><div class="n8lc-p-grid three">'+field('Key',input('field_key',''))+field('Label',input('label',''))+field('Scope',select('scope','prechat',[['prechat','Pre-chat'],['visitor','Visitor'],['conversation','Conversation']]))+field('Type',select('field_type','text',[['text','Text'],['email','Email'],['tel','Phone'],['number','Number'],['textarea','Textarea'],['select','Select'],['checkbox','Checkbox']]))+field('Options (comma separated)',input('options',''))+field('Sort order',input('sort_order',100,'number'))+'</div>'+checkbox('is_required',false,'Required')+checkbox('is_active',true,'Active'),function(d){return{field_key:d.get('field_key'),label:d.get('label'),scope:d.get('scope'),field_type:d.get('field_type'),options:String(d.get('options')||'').split(','),sort_order:Number(d.get('sort_order')),is_required:d.has('is_required'),is_active:d.has('is_active')}},function(x){return '<div class="n8lc-p-card"><div class="n8lc-p-card-head"><div><h3>'+esc(x.label)+'</h3><p><code>'+esc(x.field_key)+'</code> · '+esc(x.scope)+' · '+esc(x.field_type)+'</p></div><span class="n8lc-p-badge">'+(x.is_required?'Required':'Optional')+'</span></div><button class="button-link-delete" data-del-resource="custom-fields" data-id="'+Number(x.id)+'">Delete</button></div>';}); }
  function integrations() { simpleResource('integrations','Integrations','Admin-authorized HTTPS endpoints receive signed support events. No external request is made unless an endpoint is configured and enabled.','<h3>New integration</h3><div class="n8lc-p-grid two">'+field('Name',input('name',''))+field('Type',select('integration_type','webhook',[['webhook','Webhook'],['n8n','n8n'],['crm','CRM'],['custom','Custom']]))+field('HTTPS endpoint',input('endpoint_url','https://','url'))+field('Events',input('events','conversation.created, message.created'))+'</div>'+checkbox('is_active',false,'Enable immediately'),function(d){return{name:d.get('name'),integration_type:d.get('integration_type'),endpoint_url:d.get('endpoint_url'),events:String(d.get('events')||'').split(',').map(function(v){return v.trim()}),is_active:d.has('is_active')}},function(x){return '<div class="n8lc-p-card"><div class="n8lc-p-card-head"><div><h3>'+esc(x.name)+'</h3><p>'+esc(x.integration_type)+' · '+esc(x.endpoint_url)+'</p></div><span class="n8lc-p-badge">'+(x.is_active?'Enabled':'Disabled')+'</span></div><p>'+esc((x.events||[]).join(', '))+'</p><button class="button-link-delete" data-del-resource="integrations" data-id="'+Number(x.id)+'">Delete</button></div>';}); }
  function blocks() { simpleResource('blocks','Blocked visitors','Block abusive or unwanted visitor records by visitor ID, with optional expiry','<h3>Block visitor</h3><div class="n8lc-p-grid two">'+field('Visitor ID',input('visitor_id','0','number'))+field('Reason',input('reason',''))+'</div>',function(d){return{visitor_id:Number(d.get('visitor_id')),reason:d.get('reason')}},function(x){return '<div class="n8lc-p-card"><div class="n8lc-p-card-head"><h3>Visitor #'+Number(x.visitor_id)+'</h3><span class="n8lc-p-badge">Blocked</span></div><p>'+esc(x.reason||'No reason supplied')+'</p><button class="button-link-delete" data-del-resource="blocks" data-id="'+Number(x.id)+'">Unblock</button></div>';}); }

  function experience() {
    var s = state.settings || {};
    var flags = ['enable_customer_profiles','enable_saved_views','enable_segments','enable_custom_fields','enable_routing_rules','enable_knowledge_base','enable_integrations','enable_privacy_tools','enable_health_checks'];
    shell('<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>Workspace & widget experience</h2><p>Advanced behavior layered on top of the v0.3 visual customizer.</p></div></div><form id="n8lc-settings-form" class="n8lc-p-card"><div class="n8lc-p-grid three">'+field('Workspace name',input('workspace_name',s.workspace_name))+field('Inbox density',select('inbox_density',s.inbox_density,[['compact','Compact'],['comfortable','Comfortable'],['spacious','Spacious']]))+field('Default sort',select('default_sort',s.default_sort,[['recent','Recent'],['oldest','Oldest'],['priority','Priority'],['sla','SLA']]))+field('Auto-open delay (seconds)',input('widget_auto_open_delay',s.widget_auto_open_delay,'number'))+field('Horizontal offset',input('widget_offset_x',s.widget_offset_x,'number'))+field('Vertical offset',input('widget_offset_y',s.widget_offset_y,'number'))+field('Z-index',input('widget_z_index',s.widget_z_index,'number'))+field('Font scale %',input('widget_font_scale',s.widget_font_scale,'number'))+'</div><h3>Widget behavior</h3><div class="n8lc-p-checks">'+checkbox('widget_auto_open',s.widget_auto_open,'Auto-open')+checkbox('widget_hide_mobile',s.widget_hide_mobile,'Hide on mobile')+checkbox('widget_hide_desktop',s.widget_hide_desktop,'Hide on desktop')+checkbox('widget_reduce_motion',s.widget_reduce_motion,'Reduce motion')+checkbox('widget_rtl',s.widget_rtl,'Force RTL')+checkbox('widget_show_knowledge',s.widget_show_knowledge,'Show knowledge suggestions')+'</div>'+field('Hide on URL patterns (one per line)',textarea('widget_page_exclusions',s.widget_page_exclusions,'rows="4"'))+'<h3>Privacy consent</h3><div class="n8lc-p-checks">'+checkbox('prechat_consent_enabled',s.prechat_consent_enabled,'Show consent checkbox')+checkbox('prechat_consent_required',s.prechat_consent_required,'Require consent')+'</div>'+field('Consent text',textarea('prechat_consent_text',s.prechat_consent_text,'rows="2"'))+'<h3>Retention & privacy automation</h3><div class="n8lc-p-grid two">'+field('Message retention days',input('privacy_retention_messages',s.privacy_retention_messages,'number','min="7" max="3650"'))+field('Anonymize after days',input('privacy_anonymize_after',s.privacy_anonymize_after,'number','min="30" max="3650"'))+'</div><div class="n8lc-p-checks">'+checkbox('privacy_auto_anonymize',s.privacy_auto_anonymize,'Auto-anonymize old closed visitors')+checkbox('privacy_auto_delete_messages',s.privacy_auto_delete_messages,'Delete old messages from closed chats')+'</div><p class="description">Automatic privacy cleanup is off by default and only runs when explicitly enabled.</p><h3>Feature flags</h3><div class="n8lc-p-checks">'+flags.map(function(k){return checkbox(k,s[k],k.replace(/^enable_/,'').replace(/_/g,' '));}).join('')+'</div><p><button class="button button-primary">Save workspace settings</button></p></form></section>');
    document.getElementById('n8lc-settings-form').addEventListener('submit', function(e){e.preventDefault();var d=new FormData(e.currentTarget),body={};Array.prototype.forEach.call(e.currentTarget.elements,function(el){if(!el.name)return;body[el.name]=el.type==='checkbox'?el.checked:el.value;});['widget_auto_open_delay','widget_offset_x','widget_offset_y','widget_z_index','widget_font_scale','privacy_retention_messages','privacy_anonymize_after'].forEach(function(k){body[k]=Number(body[k])});api('settings',{method:'POST',body:body}).then(function(r){state.settings=r;toast('Settings saved');});});
  }

  function diagnostics() {
    shell('<div class="n8lc-p-loading">Running diagnostics…</div>');
    api('diagnostics').then(function(d){shell('<section class="n8lc-p-section"><div class="n8lc-p-section-head"><div><h2>Diagnostics</h2><p>Environment and operational checks for support troubleshooting.</p></div><span class="n8lc-p-badge">Schema '+esc(d.schema_version)+'</span></div><div class="n8lc-p-card"><pre>'+esc(JSON.stringify(d,null,2))+'</pre></div></section>');});
  }

  function render() {
    if (!state.summary || !state.settings) { shell('<div class="n8lc-p-loading">Loading professional workspace…</div>'); loadSummary().then(render).catch(function(e){shell('<div class="n8lc-p-error">'+esc(e.message)+'</div>')}); return; }
    if (state.tab === 'overview') shell(overview());
    else if (state.tab === 'team') team();
    else if (state.tab === 'customers') customers('');
    else if (state.tab === 'routing-rules') routing();
    else if (state.tab === 'saved-views') savedViews();
    else if (state.tab === 'segments') segments();
    else if (state.tab === 'custom-fields') customFields();
    else if (state.tab === 'integrations') integrations();
    else if (state.tab === 'blocks') blocks();
    else if (state.tab === 'bulk') bulkOperations();
    else if (state.tab === 'experience') experience();
    else if (state.tab === 'diagnostics') diagnostics();
  }

  render();
}());
