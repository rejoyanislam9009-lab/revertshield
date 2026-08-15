# N8 LiveChat Pro 0.5.0

N8 LiveChat Pro is a standalone WordPress live-chat and support-operations plugin. Version 0.5.0 preserves the validated v0.3 chat core and adds a professional platform layer for team management, routing, customer data, knowledge, privacy, integrations, diagnostics and deeper widget behavior.

## 0.4.3 frontend visibility hotfix

- Fixes the floating chat launcher not appearing on the public site even when the widget is enabled and mobile/desktop hiding is off.
- The widget mount is now rendered on `wp_body_open` when the theme supports it, with an early `wp_footer` fallback before footer JavaScript executes.
- Duplicate-render protection ensures themes firing both hooks still output exactly one `#n8lc-widget-root` mount.
- This specifically fixes the ordering bug where `widget-v03.js` executed first, found no widget root, and exited permanently.

## 0.4.1 recovery hotfix

- Introduces a recovery-safe bootstrap so a runtime failure in a LiveChat module does not take down the WordPress dashboard.
- Activation/database migration errors are caught, recorded and the plugin is automatically deactivated instead of leaving WordPress in a fatal-error loop.
- Professional schema installation is now recovery-guarded; after a platform failure, public requests skip repeated retries while wp-admin can retry safely.
- Administrators get a sanitized recovery notice with module/file/line context, while detailed errors are also sent to the server PHP log.
- PHP 7.4+ and WordPress 6.5+ requirements are checked before the runtime modules boot.
- Existing chat data and v0.4 platform data are preserved during recovery/deactivation.

## What is included

### Visitor live chat
- Persistent composer draft during polling and incoming agent messages, with hardened mobile text visibility.
- Real agent-presence status: Online, Away, or Offline based on business hours plus active LiveChat agent heartbeat.
- Live session elapsed timer and configurable abandoned-session auto-close after visitor heartbeat inactivity.
- Five-face happiness/CSAT review with optional written feedback and a thank-you state.
- Responsive floating widget with persistent browser sessions and message history.
- Name, email, phone and department capture.
- Admin-defined pre-chat custom fields: text, email, phone, number, textarea, select and checkbox.
- Optional or required consent checkbox with configurable text.
- Online/away state from business hours.
- Visitor and agent typing indicators.
- Image/file attachments with MIME and size controls.
- End-chat flow and 1-5 star CSAT with optional comment.
- Greeting teaser, unread badge, notification sound and mobile full-screen mode.
- Optional knowledge-base suggestions before a visitor starts a chat.
- Auto-open, desktop/mobile visibility, page exclusions, offsets, z-index, font scaling, reduced-motion and forced-RTL controls.
- Shortcodes for a chat button, support status and knowledge cards.

### Team inbox and support operations
- Searchable conversation inbox with status and priority filters.
- Public replies and internal/private notes.
- Agent/department assignment and automatic assignment.
- Tags, canned replies and structured conversation data.
- Transcript email, attachments, SLA indicators and visitor typing state.
- Bulk status, priority, agent and department actions for selected conversations.
- Saved inbox views for reusable filters.
- Customer segments for reusable grouping/reporting definitions.
- Visitor blocking with optional expiry; a block can persist through hashed email/network identifiers without storing raw IP addresses.

### Team and access management
- Dedicated **N8 LiveChat Agent** and **N8 LiveChat Manager** WordPress roles.
- Administrators can onboard existing WordPress users as Agent, Manager or No LiveChat Access.
- Per-agent title, avatar, availability, workload limit, languages, skills and notification preferences.
- Capability-based REST permissions for chat, settings, team, routing, custom fields, integrations, privacy, knowledge and diagnostics.

### Routing and automation
- Existing business hours, round-robin/load assignment and SLA monitoring remain intact.
- Conditional routing rules can match source, department, email domain, URL, referrer, visitor name and business-hours state.
- Routing actions can set priority, status, agent, department and tag; rules can stop or continue processing.
- Five-minute automation loop and daily maintenance remain WP-Cron compatible.

### Knowledge base
- Native WordPress Knowledge Article content type with revisions, author and topics.
- Public read-only knowledge REST endpoint for published articles.
- Optional pre-chat knowledge suggestions in the widget.
- `[n8_livechat_kb]` shortcode for on-page knowledge cards.

### Integrations
- Existing signed webhook configuration remains supported.
- Multiple admin-authorized integration endpoints can be configured for webhook, n8n, CRM or custom HTTPS receivers.
- Supported outbound events: conversation creation, message creation, conversation update and CSAT submission.
- Per-integration HMAC-SHA256 signing secret.
- Integration secrets are masked in REST responses and are never sent to visitors.
- No external integration request is made until an administrator explicitly configures and enables an endpoint.

### Privacy, security and abuse controls
- Visitor session tokens are stored as SHA-256 hashes.
- IP addresses are represented by salted HMAC hashes rather than raw IP storage.
- Public endpoint rate limits and server-side visitor-session verification.
- Admin endpoints use WordPress capabilities and REST nonces.
- Private notes are excluded from visitor APIs.
- WordPress personal-data exporter and eraser callbacks for visitor profiles and chat data.
- Suggested privacy-policy text through WordPress privacy tools.
- Optional, disabled-by-default automatic anonymization of old closed visitors.
- Optional, disabled-by-default deletion of old messages from closed conversations.
- Integration endpoints require HTTPS and use WordPress safe HTTP requests.
- File uploads retain the existing MIME/size allowlist and validation hook.

### Dashboard and diagnostics
- Existing dashboard, analytics, audit/export and visual customizer remain available.
- New **N8 LiveChat -> Platform** workspace for Team, Routing, Saved Views, Segments, Custom Fields, Integrations, Blocks, Widget Experience and Diagnostics.
- Site Health tests for professional schema and scheduled jobs.
- Environment diagnostics expose operational state without exposing filesystem upload paths to regular support users.

### Visual customization retained from v0.3
- 8 inline-SVG launcher icons.
- Circle, rounded-square and pill launcher styles with optional label.
- Theme presets plus custom accent color.
- Greeting delay/auto-hide, support avatar/name/subtitle, launcher size, panel dimensions and corner radius.
- Pulse, float, glow or disabled animation.
- Live visual preview in WordPress admin.

## Upgrade safety

v0.4 is additive by design:

- The existing v0.3 core tables, REST routes and established chat behavior remain compatibility boundaries.
- Professional features use separate tables/options and hooks where practical.
- Automatic privacy deletion/anonymization is **off by default**.
- Existing plugin data is preserved on uninstall unless **Delete data on uninstall** is explicitly enabled.
- RevertShield's own plugin code remains outside the `n8-livechat-pro/` folder and is not part of this LiveChat release.

## Installation / upgrade
1. Back up the WordPress database and site files before a production upgrade.
2. Upload `n8-livechat-pro-0.4.3.zip` from **Plugins -> Add New Plugin -> Upload Plugin**.
3. When replacing an earlier N8 LiveChat Pro version, use WordPress's replace-current-plugin flow.
4. Open **N8 LiveChat -> Settings** for existing chat/visual settings.
5. Open **N8 LiveChat -> Platform** for team, routing, fields, integrations, privacy behavior and diagnostics.
6. Open **N8 LiveChat -> Automation** for business hours, assignment, SLA and legacy signed webhook settings.

## Requirements
- WordPress 6.5+
- PHP 7.4+
- JavaScript enabled for the visitor widget and admin applications

## Main extension hooks
- `n8lc_conversation_created`
- `n8lc_message_created`
- `n8lc_conversation_updated`
- `n8lc_csat_submitted`
- `n8lc_after_daily_cleanup`
- `n8lc_validate_upload`
- `n8lc_allowed_upload_mimes`

## Operational notes
- The default transport remains REST polling so the plugin works on conventional WordPress/PHP hosting. A true WebSocket/SSE service requires a persistent server process or external realtime layer.
- The plugin restricts uploads but does not bundle a malware-scanning engine. High-risk environments should connect a scanner through `n8lc_validate_upload`.
- WordPress email delivery depends on the site's mail configuration.
- CRM/n8n/custom endpoints are generic signed HTTPS integrations; vendor-specific OAuth flows are not bundled in this release.
- Production rollouts should be tested on a staging site with the site's actual theme, caching/CDN, security plugins and mail/upload configuration.

## License
GPL-2.0-or-later.


### v0.5.3 delivery, inbox and close-flow reliability
- Adds cache-busted/no-store visitor and admin sync requests so public REST polling is not served stale by browser/cache layers.
- Reduces active visitor sync latency to roughly 0.8–1.2 seconds while avoiding overlapping poll loops.
- Adds a sequential visitor outbox so rapid messages can be typed/sent without locking the composer; optimistic bubbles reconcile with server message IDs.
- Keeps each admin conversation draft/private-note state isolated by conversation ID so switching between many customers cannot leak a draft into the wrong chat.
- Adds immediate admin reply echo, faster selected-thread reconciliation, live-customer badges and last-message previews.
- Makes visitor End deterministic: eligible CSAT stays visible until submitted or skipped, then the local closed session is cleared and the panel closes.
- Keeps idle-close resumable while agent-closed chats offer a clean new-chat action.
- Clarifies launcher positioning controls: side-edge and bottom-edge distances can be tuned in Platform → Widget Experience.
- Keeps v0.5.1 theme isolation/responsive fixes and all prior chat history/schema compatibility.
