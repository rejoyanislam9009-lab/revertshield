=== N8 LiveChat Pro ===
Contributors: n8
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.2
License: GPLv2 or later

Professional WordPress live chat and support operations with team roles, routing, attachments, typing, custom fields, knowledge, SLA, CSAT, privacy tools, analytics, audit/export and signed integrations.

== Description ==
N8 LiveChat Pro provides a visitor chat widget, multi-agent support inbox, departments, assignment, typing indicators, attachments, tags, canned replies, business hours, SLA automation, CSAT, analytics, audit/export and signed webhooks.

Version 0.4 adds a separate professional platform layer with team onboarding, Manager/Agent access, agent profiles, conditional routing rules, saved views, customer segments, pre-chat custom fields, multiple signed HTTPS integrations, visitor blocks, Knowledge Base, WordPress privacy exporter/eraser callbacks, opt-in retention automation, Site Health tests, deeper widget behavior and accessibility controls.

External integrations are disabled until an administrator explicitly configures and enables an HTTPS endpoint.

== Installation ==
1. Upload the `n8-livechat-pro` folder or release ZIP through WordPress Plugins.
2. Activate N8 LiveChat Pro.
3. Configure **N8 LiveChat -> Settings** and **Automation**.
4. Configure professional operations under **N8 LiveChat -> Platform**.
5. Use **N8 LiveChat -> Inbox** to answer visitors.

== Frequently Asked Questions ==

= Does v0.4 delete my existing chats? =
No. The v0.4 professional layer is additive. Automatic privacy deletion/anonymization is disabled by default. Uninstall also preserves data unless the administrator explicitly enables data deletion.

= Does it require WebSockets? =
No. The default transport is REST polling for compatibility with conventional WordPress hosting. Persistent WebSocket/SSE realtime requires a compatible server service and is not bundled here.

= Does it send data to third parties automatically? =
No. Generic CRM/n8n/custom integration delivery only occurs after an administrator creates and enables an HTTPS endpoint. The site administrator is responsible for the privacy policy and authorization for services they enable.

= Does it scan uploads for malware? =
The plugin enforces its upload allowlist and size controls but does not bundle a malware engine. Security products can integrate through the `n8lc_validate_upload` hook.

== Changelog ==

= 0.5.2 =
* Added no-store/cache-busted chat sync and faster 0.8–1.2s active visitor polling.
* Added a sequential optimistic visitor outbox so rapid messages do not lock the composer.
* Isolated admin reply drafts per conversation to prevent cross-customer draft leakage when switching chats.
* Added last-message previews, live visitor badges, faster selected-thread reconciliation and immediate admin reply echo.
* Made visitor End/CSAT persistent until feedback or skip, then cleanly closes and clears the local ended session.
* Clarified side/bottom launcher offset controls and expanded their adjustment range.

= 0.5.1 =
* Loaded the experience stylesheet on the public widget and isolated chat controls from theme-global styles.
* Added responsive Team Inbox behavior, message deduplication, focus preservation and CSAT lifecycle protections.

= 0.5.0 =
* Fixed visitor composer drafts being lost during background message refresh and hardened mobile text/caret visibility.
* Added real agent presence heartbeat so visitors see Online only while a reply-capable LiveChat user is active; otherwise Away/Offline is shown.
* Added animated visitor/agent typing presence without replacing the active draft.
* Added configurable abandoned-chat auto-close based on visitor heartbeat inactivity, plus an optional live session timer.
* Replaced basic star-only CSAT with five expressive happiness faces, optional written feedback and a thank-you state.
* Added CSAT face badges in the admin conversation list and stronger mobile composer/feedback styling.

= 0.4.3 =
* Fixed the frontend launcher not appearing because the widget mount was rendered after footer JavaScript executed.
* The widget mount now renders on wp_body_open when available with an early wp_footer fallback.
* Added duplicate-render protection so themes firing both hooks still output exactly one widget root.

= 0.4.2 =
* Fixed a WordPress admin fatal caused by passing partial stdClass user-query rows into user_can().
* LiveChat dashboard, inbox, settings, team and platform pages now request full WP_User objects before capability checks.
* Added an admin-runtime regression gate to prevent this capability-check crash from returning.

= 0.4.1 =
* Recovery-safe bootstrap: runtime module failures no longer take down wp-admin.
* Activation failures automatically deactivate the plugin while preserving data.
* Platform schema/runtime failures are recovery-guarded, and repeated public retries are suppressed after a failure.
* Added PHP/WordPress requirement guard and persistent admin recovery diagnostics.

= 0.4.0 =
* Added N8 LiveChat Manager access and administrator-controlled Agent/Manager onboarding for existing WordPress users.
* Added agent profiles with workload, availability, languages, skills, avatar and notification preferences.
* Added conditional routing rules, saved inbox views, customer segments and bulk conversation actions.
* Added structured pre-chat custom fields with server-side required-field validation and optional/required consent.
* Added visitor blocking backed by visitor ID plus hashed email/network identifiers.
* Added multiple administrator-authorized signed HTTPS integrations for webhook, n8n, CRM and custom receivers.
* Added Knowledge Base content, public published-article search and optional widget suggestions.
* Added WordPress personal-data exporter/eraser integration and suggested privacy-policy content.
* Added optional disabled-by-default automatic anonymization and closed-chat message retention cleanup.
* Added Site Health tests, safer diagnostics and deeper widget experience controls including page exclusions, offsets, font scaling, RTL and reduced motion.
* Added chat/status/knowledge shortcodes without inline JavaScript handlers.
* Preserved the validated v0.3 chat, inbox, attachments, typing, tags, SLA, CSAT, analytics, audit/export, automation, webhooks and visual customizer as compatibility boundaries.

= 0.3.0 =
* Added the premium visual customization module, launcher icon/style controls, greeting teaser, agent identity, live preview and responsive widget polish.

= 0.2.0 =
* Added typing indicators, attachments, tags, custom fields, auto assignment, business hours, SLA monitoring, CSAT, transcript email, audit log, CSV export, desktop notifications and signed webhooks.
* Added the N8 LiveChat Agent role.
