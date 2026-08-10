=== RevertShield ===
Tags: maintenance, updates, debugging, health check, activity log
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track important WordPress maintenance changes and run local health checks without sending telemetry.

== Description ==

RevertShield helps administrators answer a practical troubleshooting question: what changed immediately before a site became unhealthy?

The current release provides a local-first operational foundation:

* Records plugin activation and deactivation events.
* Records plugin, theme, and core upgrader events.
* Records theme switches.
* Can record the names of selected critical WordPress options without storing their old or new values.
* Runs an on-demand homepage HTTP health check using the WordPress HTTP API.
* Stores change and health history in dedicated, indexed database tables.
* Uses configurable retention and bounded cleanup jobs.
* Sends no telemetry and requires no external account.

RevertShield does not currently perform automatic rollback. Recovery features will only be introduced with component-specific snapshots, integrity checks, explicit eligibility rules, and post-recovery verification.

== Installation ==

1. Upload the `revertshield` folder to `/wp-content/plugins/`, or install the release ZIP from Plugins > Add Plugin > Upload Plugin.
2. Activate RevertShield.
3. Open Tools > RevertShield.
4. Run a homepage health check and review the activity ledger.

== Frequently Asked Questions ==

= Does RevertShield send site data to an external service? =

No. The current release stores its ledger and health-check results in the site's own WordPress database and includes no telemetry.

= Does RevertShield store WordPress option values? =

The built-in critical-option observer stores option names only. It does not store the previous or new option values.

= Does uninstall remove RevertShield data? =

Not by default. Enable the delete-on-uninstall setting before uninstalling if you want RevertShield's local tables and settings removed.

= Does RevertShield automatically roll back failed updates? =

No. Automatic rollback is intentionally not included in this release. RevertShield first establishes a reliable change ledger, health checks, and storage boundaries before recovery functionality is added.

== Changelog ==

= 0.1.0 =
* Added the local change ledger and health-history tables.
* Added plugin, theme, core update, activation, deactivation, and theme-switch observers.
* Added privacy-conscious tracking of selected option names.
* Added on-demand homepage health checks.
* Added retention cleanup and uninstall controls.
* Added WordPress.org-focused packaging and automated quality checks.
