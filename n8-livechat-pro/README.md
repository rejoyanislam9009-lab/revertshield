# N8 LiveChat Pro 0.3.0

N8 LiveChat Pro is a standalone WordPress live chat and support-inbox plugin. Version 0.3.0 keeps the v0.1 visitor chat, agent inbox, departments, canned replies, visitors and analytics, then adds higher-functionality support operations.

## Included in v0.3.0

### Visitor chat
- Responsive floating chat widget.
- Lead capture for name, email and phone.
- Department selection.
- Persistent browser session and message history.
- Online/away state based on configurable business hours.
- Visitor and agent typing indicators.
- File and image attachments with size and MIME restrictions.
- End-chat flow and 1-5 star CSAT rating with optional comment.
- Polling transport with heartbeat and session recovery.

### Agent inbox
- Live conversation list with search, status and priority filters.
- Agent and department assignment.
- Automatic assignment using lowest active load or round robin.
- Low/normal/high/urgent priorities.
- Public replies and internal/private notes.
- File/image attachments from agents.
- Canned replies.
- Conversation tags.
- Custom key/value conversation fields.
- Visitor typing status.
- Email transcript action.
- SLA breach indicators.

### Dashboard and operations
- Open, pending, unread, online visitor, daily activity and SLA counters.
- Desktop browser notifications while the WordPress admin page is open.
- Separate Tags, Automation, Analytics, Audit & Export and Settings screens.
- CSV conversation export.
- Audit event viewer.
- 7/30/90-day analytics.
- Average first-response time, CSAT and SLA-breach metrics.

### Automation
- Configurable business hours.
- Automatic assignment to WordPress users with the LiveChat reply capability.
- Dedicated `N8 LiveChat Agent` WordPress role.
- First-response and resolution SLA targets.
- Five-minute SLA monitoring via WP-Cron.
- Automatic urgent priority on SLA breach.
- Optional admin escalation email.
- Optional new-message email notifications.

### n8n / CRM / integrations
- Signed outbound webhooks.
- HMAC-SHA256 signature in `X-N8LC-Signature`.
- Events for conversation creation, messages, conversation updates and CSAT.
- Existing WordPress actions remain available for custom integrations.

### Security and privacy
- Visitor tokens are stored as SHA-256 hashes.
- IP addresses are stored only as salted HMAC hashes.
- Public session/message/upload rate limits.
- WordPress REST nonce and capability checks for protected routes.
- Upload type and size allowlist.
- Upload validation extension hook: `n8lc_validate_upload`.
- Webhook delivery uses WordPress safe HTTP requests.
- Private notes never appear in visitor message APIs.
- Conservative uninstall: data is preserved unless the administrator explicitly enables deletion.

## Visual customizer (v0.3)
- 8 selectable inline-SVG launcher icons: message, chat, headset, support, sparkle, bot, phone and mail.
- Circle, rounded-square and pill launchers with optional text label.
- Indigo, ocean, emerald, violet, rose, sunset, midnight and custom color themes.
- Greeting teaser bubble with configurable delay and auto-hide.
- Support avatar/name, response-time subtitle and online/away presence.
- Configurable launcher size, panel width/height and corner radius.
- Pulse, float, glow or no animation.
- Unread badge, optional Web Audio reply chime and improved mobile full-screen layout.
- Dedicated WordPress visual customizer with a live website preview.

## Installation
1. Download `n8-livechat-pro-0.3.0.zip`.
2. In WordPress go to **Plugins -> Add New Plugin -> Upload Plugin**.
3. Upload the ZIP and activate **N8 LiveChat Pro**.
4. Open **N8 LiveChat -> Settings** and configure the widget.
5. Open **N8 LiveChat -> Automation** for business hours, assignment, SLA and webhooks.
6. Create or edit WordPress users and assign the **N8 LiveChat Agent** role as needed.
7. Use **N8 LiveChat -> Inbox** to answer visitors.

## Requirements
- WordPress 6.5+
- PHP 7.4+

## Main hooks
- `n8lc_conversation_created`
- `n8lc_message_created`
- `n8lc_conversation_updated`
- `n8lc_csat_submitted`
- `n8lc_after_daily_cleanup`
- `n8lc_validate_upload`
- `n8lc_allowed_upload_mimes`

## Notes
- Real WebSockets require a persistent socket service or compatible hosting layer. This build intentionally retains reliable REST polling so it works on ordinary WordPress hosting.
- File uploads are restricted but this plugin does not bundle a malware scanning engine. Sites that require scanning should connect a scanner through `n8lc_validate_upload`.
- WordPress email delivery depends on the site's mail configuration.

## License
GPL-2.0-or-later.
