(function () {
  'use strict';
  if (!window.N8LCAdmin || window.N8LCAdmin.page !== 'n8-livechat-settings') return;

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
      return r.json().then(function (body) {
        if (!r.ok) throw new Error(body && body.message ? body.message : cfg.i18n.error);
        return body;
      });
    });
  }

  function icon(name) {
    var icons = {
      message: '<svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 4.7-7.6A8.38 8.38 0 0 1 12.5 3h.5a8.48 8.48 0 0 1 8 8z"/></svg>',
      chat: '<svg viewBox="0 0 24 24"><path d="M8 10h8M8 14h5"/><path d="M21 12a8 8 0 0 1-8 8H7l-4 2 1.3-4.1A8 8 0 1 1 21 12z"/></svg>',
      headset: '<svg viewBox="0 0 24 24"><path d="M4 13v-1a8 8 0 0 1 16 0v1"/><path d="M4 13h3v6H5a1 1 0 0 1-1-1v-5zm16 0h-3v6h2a1 1 0 0 0 1-1v-5zM17 19c0 1.1-.9 2-2 2h-3"/></svg>',
      support: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6m5.6 5.6 3.6 3.6m0-12.8-3.6 3.6m-5.6 5.6-3.6 3.6"/></svg>',
      sparkle: '<svg viewBox="0 0 24 24"><path d="m12 3 1.3 4.1L17 9l-3.7 1.9L12 15l-1.3-4.1L7 9l3.7-1.9L12 3zM19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14z"/></svg>',
      bot: '<svg viewBox="0 0 24 24"><rect x="4" y="7" width="16" height="12" rx="4"/><path d="M12 3v4M9 12h.01M15 12h.01M8.5 16h7M2 12h2m16 0h2"/></svg>',
      phone: '<svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.3 19.3 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7A2 2 0 0 1 22 16.9z"/></svg>',
      mail: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>'
    };
    return icons[name] || icons.message;
  }

  function theme(name, accent) {
    var map = {
      indigo:['#4f46e5','#7c3aed'], ocean:['#0284c7','#06b6d4'], emerald:['#059669','#10b981'],
      violet:['#7c3aed','#a855f7'], rose:['#e11d48','#f43f5e'], sunset:['#ea580c','#f59e0b'],
      midnight:['#0f172a','#334155'], custom:[accent || '#111827', accent || '#111827']
    };
    return map[name] || map.indigo;
  }

  function initials(name) {
    return String(name || 'Support Team').trim().split(/\s+/).slice(0,2).map(function (x) { return x.charAt(0).toUpperCase(); }).join('') || 'S';
  }

  function avatarMarkup(agent, url, cls) {
    cls = cls || 'n8lc-v3-avatar';
    return url ? '<span class="'+cls+'"><img src="'+esc(url)+'" alt=""></span>' : '<span class="'+cls+'">'+esc(initials(agent))+'</span>';
  }

  function renderPreview() {
    var p = document.getElementById('n8lc-v3-preview');
    if (!p) return;
    var themeName = document.getElementById('n8lc-v3-theme').value || 'indigo';
    var colors = theme(themeName, document.getElementById('n8lc-v3-color').value);
    var iconInput = document.querySelector('input[name="n8lc-v3-icon"]:checked');
    var iconName = iconInput ? iconInput.value : 'message';
    var shape = document.getElementById('n8lc-v3-shape').value || 'circle';
    var title = document.getElementById('n8lc-v3-title').value || 'Chat with us';
    var agent = document.getElementById('n8lc-v3-agent').value || 'Support Team';
    var subtitle = document.getElementById('n8lc-v3-subtitle').value || 'Typically replies in a few minutes';
    var avatarUrl = document.getElementById('n8lc-v3-avatar-url').value || '';
    var greeting = document.getElementById('n8lc-v3-greeting-text').value || 'Hi there! Need a hand?';
    var label = document.getElementById('n8lc-v3-label').value || '';
    var radius = Number(document.getElementById('n8lc-v3-radius').value || 24);
    var size = Number(document.getElementById('n8lc-v3-size').value || 64);
    var avatar = avatarMarkup(agent, avatarUrl, 'n8lc-v3-preview-avatar');

    p.style.setProperty('--v3a', colors[0]);
    p.style.setProperty('--v3b', colors[1]);
    p.innerHTML = '<div class="n8lc-v3-browser"><div class="n8lc-v3-fake-nav"><i></i><i></i><i></i></div><div class="n8lc-v3-fake-site"><b></b><span></span><span></span><div><em></em><em></em></div></div>'+
      '<div class="n8lc-v3-preview-chat" style="border-radius:'+radius+'px"><div class="n8lc-v3-preview-head">'+avatar+'<div><strong>'+esc(title)+'</strong><small><i></i>'+esc(subtitle)+'</small></div><b>−</b></div><div class="n8lc-v3-preview-body"><div class="n8lc-v3-msg agent">Hi! How can we help today?</div><div class="n8lc-v3-msg visitor">I have a question about my order.</div><div class="n8lc-v3-preview-typing"><em></em><em></em><em></em> '+esc(agent)+' is typing...</div></div><div class="n8lc-v3-preview-compose"><span>Type a message...</span><b>➤</b></div></div>'+
      (document.getElementById('n8lc-v3-show-greeting').checked ? '<div class="n8lc-v3-preview-greeting">'+avatarMarkup(agent, avatarUrl, 'n8lc-v3-preview-avatar')+'<span><strong>'+esc(agent)+'</strong><small>'+esc(greeting)+'</small></span></div>' : '')+
      '<div class="n8lc-v3-preview-launcher '+shape+'" style="height:'+size+'px;'+(shape==='pill'?'':'width:'+size+'px')+'">'+icon(iconName)+(shape==='pill'&&label?'<strong>'+esc(label)+'</strong>':'')+'</div></div>';
  }

  function render(core, visual) {
    var icons = [['message','Message'],['chat','Chat'],['headset','Headset'],['support','Support'],['sparkle','Sparkle'],['bot','Bot'],['phone','Phone'],['mail','Mail']];
    var iconChoices = icons.map(function (x) {
      return '<label class="n8lc-v3-icon"><input type="radio" name="n8lc-v3-icon" value="'+x[0]+'" '+((visual.launcher_icon||'message')===x[0]?'checked':'')+'><span>'+icon(x[0])+'</span><small>'+x[1]+'</small></label>';
    }).join('');

    app.innerHTML = '<div class="n8lc-v3-layout"><main><form id="n8lc-v3-form">'+
      '<section class="n8lc-card n8lc-v3-section"><div class="n8lc-v3-section-head"><div><h2>Widget identity</h2><p>Customize the support identity visitors see in the popup.</p></div><span>v'+esc(cfg.version)+'</span></div><div class="n8lc-v3-grid"><label>Widget title<input id="n8lc-v3-title" value="'+esc(core.widget_title||'Chat with us')+'"></label><label>Support name<input id="n8lc-v3-agent" value="'+esc(visual.agent_name||'Support Team')+'"></label><label class="wide">Header subtitle<input id="n8lc-v3-subtitle" value="'+esc(visual.header_subtitle||'Typically replies in a few minutes')+'"></label><label class="wide">Agent avatar URL<input id="n8lc-v3-avatar-url" type="url" placeholder="https://example.com/avatar.jpg" value="'+esc(visual.agent_avatar_url||'')+'"><small>Optional. Leave blank to show initials.</small></label></div></section>'+
      '<section class="n8lc-card n8lc-v3-section"><div class="n8lc-v3-section-head"><div><h2>Launcher icon & style</h2><p>Choose the floating button visitors click to open chat.</p></div></div><div class="n8lc-v3-icons">'+iconChoices+'</div><div class="n8lc-v3-grid"><label>Shape<select id="n8lc-v3-shape"><option value="circle">Circle</option><option value="rounded">Rounded square</option><option value="pill">Pill</option></select></label><label>Animation<select id="n8lc-v3-animation"><option value="pulse">Pulse</option><option value="float">Float</option><option value="glow">Glow</option><option value="none">None</option></select></label><label>Launcher size <output id="n8lc-v3-size-out">'+esc(visual.launcher_size||64)+'</output><input id="n8lc-v3-size" type="range" min="48" max="84" value="'+esc(visual.launcher_size||64)+'"></label><label>Text (pill launcher)<input id="n8lc-v3-label" value="'+esc(visual.launcher_label||'')+'" placeholder="Chat with us"></label></div></section>'+
      '<section class="n8lc-card n8lc-v3-section"><div class="n8lc-v3-section-head"><div><h2>Theme & popup</h2><p>Use a preset or custom accent, then tune the chat window.</p></div></div><div class="n8lc-v3-grid"><label>Theme<select id="n8lc-v3-theme"><option value="indigo">Indigo</option><option value="ocean">Ocean</option><option value="emerald">Emerald</option><option value="violet">Violet</option><option value="rose">Rose</option><option value="sunset">Sunset</option><option value="midnight">Midnight</option><option value="custom">Custom</option></select></label><label>Custom accent<input id="n8lc-v3-color" type="color" value="'+esc(core.accent_color||'#111827')+'"></label><label>Panel width (px)<input id="n8lc-v3-width" type="number" min="320" max="520" value="'+esc(visual.panel_width||400)+'"></label><label>Panel height (px)<input id="n8lc-v3-height" type="number" min="460" max="820" value="'+esc(visual.panel_height||660)+'"></label><label>Corner radius <output id="n8lc-v3-radius-out">'+esc(visual.panel_radius||24)+'</output><input id="n8lc-v3-radius" type="range" min="12" max="36" value="'+esc(visual.panel_radius||24)+'"></label><label>Position<select id="n8lc-v3-position"><option value="right">Bottom right</option><option value="left">Bottom left</option></select></label></div></section>'+
      '<section class="n8lc-card n8lc-v3-section"><div class="n8lc-v3-section-head"><div><h2>Greeting bubble</h2><p>Invite visitors to chat before they open the full window.</p></div></div><div class="n8lc-v3-grid"><label class="wide">Greeting text<input id="n8lc-v3-greeting-text" value="'+esc(visual.greeting_text||'Hi there! Need a hand?')+'"></label><label>Show after (ms)<input id="n8lc-v3-delay" type="number" min="0" max="20000" value="'+esc(visual.greeting_delay||1800)+'"></label><label>Auto-hide (seconds)<input id="n8lc-v3-hide" type="number" min="3" max="60" value="'+esc(visual.greeting_auto_hide||12)+'"></label><div class="n8lc-v3-checks wide"><label><input id="n8lc-v3-show-greeting" type="checkbox" '+(Number(visual.show_greeting)?'checked':'')+'> Show greeting bubble</label><label><input id="n8lc-v3-sound" type="checkbox" '+(Number(visual.sound_enabled)?'checked':'')+'> Reply notification sound</label><label><input id="n8lc-v3-branding" type="checkbox" '+(Number(visual.show_branding)?'checked':'')+'> Show N8 branding</label></div></div></section>'+
      '<section class="n8lc-card n8lc-v3-section"><div class="n8lc-v3-section-head"><div><h2>Chat & storage</h2><p>Core v0.2 controls remain available and are saved through the existing settings API.</p></div></div><div class="n8lc-v3-grid"><label class="wide">Welcome message<textarea id="n8lc-v3-welcome" rows="3">'+esc(core.welcome_message||'')+'</textarea></label><label class="wide">Offline message<textarea id="n8lc-v3-offline" rows="3">'+esc(core.offline_message||'')+'</textarea></label><label>Poll interval (ms)<input id="n8lc-v3-poll" type="number" min="1500" max="30000" value="'+esc(core.poll_interval||3000)+'"></label><label>Retention (days)<input id="n8lc-v3-retention" type="number" min="7" max="3650" value="'+esc(core.retention_days||365)+'"></label><label>Max upload (MB)<input id="n8lc-v3-upload-mb" type="number" min="1" max="25" value="'+esc(core.max_upload_mb||5)+'"></label><div></div><div class="n8lc-v3-checks wide"><label><input id="n8lc-v3-enabled" type="checkbox" '+(Number(core.enabled)?'checked':'')+'> Enable widget</label><label><input id="n8lc-v3-require-email" type="checkbox" '+(Number(core.require_email)?'checked':'')+'> Require visitor email</label><label><input id="n8lc-v3-uploads" type="checkbox" '+(Number(core.uploads_enabled)?'checked':'')+'> Allow visitor uploads</label><label><input id="n8lc-v3-csat" type="checkbox" '+(Number(core.csat_enabled)?'checked':'')+'> Enable CSAT</label><label><input id="n8lc-v3-delete" type="checkbox" '+(Number(core.delete_data_on_uninstall)?'checked':'')+'> Delete data on uninstall</label></div></div></section>'+
      '<div class="n8lc-v3-save"><button class="button button-primary button-hero">Save widget settings</button><span id="n8lc-v3-status"></span></div></form></main>'+
      '<aside><div class="n8lc-v3-sticky"><div class="n8lc-v3-preview-title"><strong>Live website preview</strong><span>Desktop</span></div><div id="n8lc-v3-preview"></div><p>Visual preview only. Save to publish these settings on the site.</p></div></aside></div>';

    document.getElementById('n8lc-v3-theme').value = visual.theme_preset || 'indigo';
    document.getElementById('n8lc-v3-shape').value = visual.launcher_shape || 'circle';
    document.getElementById('n8lc-v3-animation').value = visual.launcher_animation || 'pulse';
    document.getElementById('n8lc-v3-position').value = core.position || 'right';
    renderPreview();

    Array.prototype.forEach.call(document.querySelectorAll('#n8lc-v3-form input,#n8lc-v3-form select,#n8lc-v3-form textarea'), function (el) {
      var eventName = el.type === 'range' || el.type === 'color' || el.type === 'text' || el.type === 'url' ? 'input' : 'change';
      el.addEventListener(eventName, function () {
        document.getElementById('n8lc-v3-size-out').textContent = document.getElementById('n8lc-v3-size').value;
        document.getElementById('n8lc-v3-radius-out').textContent = document.getElementById('n8lc-v3-radius').value;
        renderPreview();
      });
    });

    document.getElementById('n8lc-v3-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var status = document.getElementById('n8lc-v3-status');
      var iconInput = document.querySelector('input[name="n8lc-v3-icon"]:checked');
      status.textContent = 'Saving...';

      var corePayload = {
        enabled: document.getElementById('n8lc-v3-enabled').checked,
        widget_title: document.getElementById('n8lc-v3-title').value,
        position: document.getElementById('n8lc-v3-position').value,
        accent_color: document.getElementById('n8lc-v3-color').value,
        welcome_message: document.getElementById('n8lc-v3-welcome').value,
        offline_message: document.getElementById('n8lc-v3-offline').value,
        poll_interval: Number(document.getElementById('n8lc-v3-poll').value || 3000),
        retention_days: Number(document.getElementById('n8lc-v3-retention').value || 365),
        max_upload_mb: Number(document.getElementById('n8lc-v3-upload-mb').value || 5),
        require_email: document.getElementById('n8lc-v3-require-email').checked,
        uploads_enabled: document.getElementById('n8lc-v3-uploads').checked,
        csat_enabled: document.getElementById('n8lc-v3-csat').checked,
        delete_data_on_uninstall: document.getElementById('n8lc-v3-delete').checked
      };

      var visualPayload = {
        theme_preset: document.getElementById('n8lc-v3-theme').value,
        launcher_icon: iconInput ? iconInput.value : 'message',
        launcher_shape: document.getElementById('n8lc-v3-shape').value,
        launcher_size: Number(document.getElementById('n8lc-v3-size').value || 64),
        launcher_label: document.getElementById('n8lc-v3-label').value,
        launcher_animation: document.getElementById('n8lc-v3-animation').value,
        show_greeting: document.getElementById('n8lc-v3-show-greeting').checked,
        greeting_text: document.getElementById('n8lc-v3-greeting-text').value,
        greeting_delay: Number(document.getElementById('n8lc-v3-delay').value || 1800),
        greeting_auto_hide: Number(document.getElementById('n8lc-v3-hide').value || 12),
        agent_name: document.getElementById('n8lc-v3-agent').value,
        agent_avatar_url: document.getElementById('n8lc-v3-avatar-url').value,
        header_subtitle: document.getElementById('n8lc-v3-subtitle').value,
        panel_width: Number(document.getElementById('n8lc-v3-width').value || 400),
        panel_height: Number(document.getElementById('n8lc-v3-height').value || 660),
        panel_radius: Number(document.getElementById('n8lc-v3-radius').value || 24),
        sound_enabled: document.getElementById('n8lc-v3-sound').checked,
        show_branding: document.getElementById('n8lc-v3-branding').checked
      };

      Promise.all([
        api('admin/settings', { method: 'PATCH', body: corePayload }),
        api('admin/visual-settings', { method: 'PATCH', body: visualPayload })
      ]).then(function () {
        status.textContent = 'Saved ✓';
      }).catch(function (err) {
        status.textContent = err.message || cfg.i18n.error;
      });
    });
  }

  app.innerHTML = '<div class="n8lc-loading">Loading visual customizer...</div>';
  Promise.all([api('admin/settings'), api('admin/visual-settings')]).then(function (r) {
    render(r[0] || {}, r[1] || {});
  }).catch(function (err) {
    app.innerHTML = '<div class="notice notice-error inline"><p>'+esc(err.message || cfg.i18n.error)+'</p></div>';
  });
}());
