# N8 LiveChat Pro

N8 LiveChat Pro is a standalone WordPress live-chat and support-inbox plugin being developed in an isolated branch of this repository. It is intentionally separated under `n8-livechat-pro/` so the existing RevertShield plugin remains untouched unless this branch is deliberately merged.

## Current feature set

### Visitor experience

- Floating responsive chat launcher and panel.
- Lead capture: name, email, phone, and department.
- Optional required email.
- Persistent browser chat session using a high-entropy visitor token.
- Message history restore after page navigation/reload.
- Near-real-time polling with configurable interval.
- Visitor heartbeat and online-presence tracking.
- Welcome message and widget branding controls.
- Left/right widget placement and accent-color control.
- Responsive mobile layout.

### Agent dashboard

- WordPress admin dashboard with live operational counters.
- Team inbox with conversation list and unread badges.
- Search by visitor/subject.
- Filter by open, pending, or closed state.
- Conversation assignment to WordPress agents.
- Department assignment.
- Priority: low, normal, high, urgent.
- Public agent replies.
- Internal/private notes hidden from visitors.
- Canned replies with slash-style shortcuts.
- Visitor directory with last page, first seen, last seen, and chat count.
- Department management.
- 7/30/90-day activity analytics.
- Status and department volume reports.
- Admin-bar unread counter.
- Dedicated WordPress capabilities for chat, replies, settings, and analytics.

### Data and security

- Dedicated indexed tables for visitors, conversations, messages, departments, canned replies, and events.
- Visitor tokens are stored as SHA-256 hashes, not plaintext.
- IP addresses are stored only as salted HMAC hashes.
- Public message/session rate limiting.
- WordPress REST nonces for admin endpoints.
- Capability checks for all protected actions.
- Input sanitization and escaped UI output.
- Private notes are excluded from visitor message APIs.
- Conservative uninstall: chat history is preserved by default.
- Daily event-retention cleanup.

### Extensibility

The plugin emits WordPress actions so integrations can be added without rewriting chat core:

- `n8lc_conversation_created`
- `n8lc_message_created`
- `n8lc_after_daily_cleanup`

These hooks are intended for future n8n/webhook, CRM, Slack, email, AI-assistant, and notification adapters.

## Installation from this branch

1. Copy the `n8-livechat-pro` directory into `wp-content/plugins/`.
2. Activate **N8 LiveChat Pro** from WordPress Plugins.
3. Open **N8 LiveChat → Settings** and configure the widget.
4. Open **N8 LiveChat → Inbox** to answer visitors.

Requirements:

- WordPress 6.5+
- PHP 7.4+

## Architecture

```text
n8-livechat-pro/
├── n8-livechat-pro.php
├── uninstall.php
├── includes/
│   ├── class-n8lc-core.php
│   ├── class-n8lc-db.php
│   ├── class-n8lc-security.php
│   ├── class-n8lc-rest.php
│   ├── class-n8lc-admin.php
│   └── class-n8lc-widget.php
└── assets/
    ├── css/
    │   ├── admin.css
    │   └── widget.css
    └── js/
        ├── admin.js
        └── widget.js
```

## Data model

- `wp_n8lc_visitors` — visitor identity, privacy-conscious metadata, browser-session token hash, presence.
- `wp_n8lc_conversations` — assignment, department, status, priority, unread counters.
- `wp_n8lc_messages` — visitor messages, agent replies, private notes, system messages.
- `wp_n8lc_departments` — support queues/departments.
- `wp_n8lc_canned_replies` — reusable agent responses.
- `wp_n8lc_events` — operational event stream for analytics/integrations.

## Next high-functionality milestones

The current build is a strong database-backed polling version. The next production milestones should be added without removing existing behavior:

1. WebSocket/SSE transport adapter while retaining polling fallback.
2. Typing indicators and agent presence.
3. File/image attachments with MIME, size, malware, and capability controls.
4. Conversation tags, custom fields, and saved inbox views.
5. Round-robin/load-based auto-assignment.
6. Business hours, offline tickets, SLA timers, and escalation policies.
7. Email notifications and visitor transcript delivery.
8. Signed outbound webhooks and n8n/CRM integrations.
9. Browser/desktop push notifications.
10. CSAT/rating flow and richer response-time analytics.
11. AI suggested replies with administrator-controlled provider configuration.
12. Multi-site/network-aware controls.
13. Import/export and backup tools.
14. Audit log viewer and granular retention policies.
15. Automated WordPress runtime tests, PHPCS, Plugin Check, and release ZIP workflow.

## Development rule

Existing working functions should be treated as compatibility boundaries. New features should be layered behind stable services, database migrations, REST versioning, and feature flags rather than replacing known-good behavior.

## License

GPL-2.0-or-later.
