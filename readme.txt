=== RevertShield ===
Tags: maintenance, updates, debugging, health check, activity log
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track important WordPress maintenance changes, run local health checks, and create integrity-verified plugin snapshots without sending telemetry.

== Description ==

RevertShield helps administrators answer a practical troubleshooting question: what changed immediately before a site became unhealthy?

The current release provides a local-first operational foundation:

* Records plugin activation and deactivation events.
* Records plugin, theme, and core upgrader events.
* Records theme switches.
* Can record the names of selected critical WordPress options without storing their old or new values.
* Runs an on-demand homepage HTTP health check using the WordPress HTTP API.
* Stores change, health, and snapshot history in dedicated, indexed database tables.
* Lets authorized administrators create integrity-verified snapshots of installed plugin files.
* Stores snapshot contents as extensionless, SHA-256-addressed objects under the WordPress uploads directory.
* Re-verifies stored snapshot manifests and object hashes before treating a snapshot as ready.
* Provides snapshot history and configurable 1-90 day snapshot retention with bounded cleanup.
* Includes a safe-update eligibility gate that requires a matching, unexpired, verified ready snapshot before future guarded update execution can proceed.
* Sends no telemetry and requires no external account.

RevertShield does not currently execute guarded plugin updates or automatic rollback. Recovery features will only be introduced with component-specific eligibility rules, post-change health validation, scoped restore behavior, and post-recovery verification.

== Installation ==

1. Upload the `revertshield` folder to `/wp-content/plugins/`, or install the release ZIP from Plugins > Add Plugin > Upload Plugin.
2. Activate RevertShield.
3. Open Tools > RevertShield for health checks and the activity ledger.
4. Open Tools > RevertShield Snapshots to create and review verified plugin snapshots.

== Frequently Asked Questions ==

= Does RevertShield send site data to an external service? =

No. The current release stores its ledger, health-check results, and snapshot metadata on the site's own WordPress installation and includes no telemetry.

= Does RevertShield store WordPress option values? =

The built-in critical-option observer stores option names only. It does not store the previous or new option values.

= Where are plugin snapshots stored? =

Snapshot objects are stored below the site's WordPress uploads directory in a RevertShield-managed path. Original executable plugin filenames are not copied into uploads; file bytes are stored as extensionless SHA-256-addressed objects with integrity manifests and verification checks.

= Does uninstall remove RevertShield data? =

Not by default. Enable the delete-on-uninstall setting before uninstalling if you want RevertShield's local tables, settings, and managed snapshot storage removed.

= Does RevertShield automatically update or roll back plugins? =

No. Version 0.2.0 can create and verify plugin snapshots and validate snapshot eligibility for a future guarded update, but it does not yet execute the update or restore automatically.

== Changelog ==

= 0.2.0 =
* Added dedicated snapshot metadata storage and lifecycle states.
* Added canonical installed-plugin source resolution with traversal and symlink protections.
* Added bounded file inventory with SHA-256 hashes and represented byte counts.
* Added writable/disk-space preflight checks before snapshot capture.
* Added extensionless content-addressed snapshot object storage with source and destination verification.
* Added independent post-finalization snapshot verification and corruption protection.
* Added an admin-only, nonce-protected Verified Plugin Snapshots screen.
* Added snapshot history and configurable snapshot retention with bounded cleanup.
* Added local ledger events for snapshot success and safe failure.
* Added a safe-update eligibility gate without enabling update execution or rollback.

= 0.1.0 =
* Added the local change ledger and health-history tables.
* Added plugin, theme, core update, activation, deactivation, and theme-switch observers.
* Added privacy-conscious tracking of selected option names.
* Added on-demand homepage health checks.
* Added retention cleanup and uninstall controls.
* Added WordPress.org-focused packaging and automated quality checks.
