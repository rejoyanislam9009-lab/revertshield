=== RevertShield ===
Tags: maintenance, updates, debugging, health check, activity log
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track WordPress changes, create verified snapshots, run guarded updates, and manually recover plugin files with integrity checks.

== Description ==

RevertShield helps administrators answer a practical troubleshooting question: what changed immediately before a site became unhealthy?

The current release provides a local-first operational safety layer:

* Records plugin activation and deactivation events.
* Records plugin, theme, and core upgrader events.
* Records theme switches.
* Can record the names of selected critical WordPress options without storing their old or new values.
* Runs on-demand homepage HTTP health checks using the WordPress HTTP API.
* Stores change, health, and snapshot history in dedicated, indexed database tables.
* Lets authorized administrators create integrity-verified snapshots of installed plugin files.
* Stores snapshot contents as extensionless, SHA-256-addressed objects under the WordPress uploads directory.
* Re-verifies stored snapshot manifests and object hashes before treating a snapshot as ready.
* Provides snapshot history and configurable 1-90 day snapshot retention with bounded cleanup.
* Provides an administrator-controlled guarded plugin update screen for updates currently reported by WordPress.
* Requires a matching, unexpired, independently verified ready snapshot before a guarded plugin update can run.
* Revalidates the WordPress update offer immediately before update execution.
* Executes guarded plugin updates through WordPress core Plugin_Upgrader APIs rather than custom package-download logic.
* Runs a homepage health check after a successful guarded update and records healthy or unhealthy outcomes in the local ledger.
* Provides WordPress-native Dashboard, Snapshots, Updates, and Recovery navigation across RevertShield admin screens.
* Provides explicit administrator-controlled manual plugin recovery from a matching, unexpired, independently verified ready snapshot.
* Stages and verifies every recovery file before replacing the installed plugin files.
* Preserves the pre-recovery plugin files during the transaction until the restored plugin passes exact inventory, SHA-256, and size verification.
* Runs a homepage health check after a successful manual recovery and records healthy or unhealthy outcomes locally.
* Prevents concurrent manual recovery operations with a short-lived recovery lock.
* Sends no telemetry and requires no external account.

RevertShield 0.4.0 supports explicit manual recovery for eligible plugin-file snapshots. It does not perform generic database rollback, does not restore RevertShield itself, and does not automatically roll back a completed update or recovery when a later homepage health check is unhealthy.

== Installation ==

1. Upload the `revertshield` folder to `/wp-content/plugins/`, or install the release ZIP from Plugins > Add Plugin > Upload Plugin.
2. Activate RevertShield.
3. Open Tools > RevertShield for health checks and the activity ledger.
4. Open Tools > RevertShield Snapshots to create and review verified plugin snapshots.
5. Open Tools > RevertShield Updates to run an eligible guarded plugin update when WordPress reports an available update.
6. Open Tools > RevertShield Recovery to manually restore an eligible plugin snapshot after reviewing the confirmation warning.

== Frequently Asked Questions ==

= Does RevertShield send site data to an external service? =

No. The current release stores its ledger, health-check results, snapshot metadata, and recovery records on the site's own WordPress installation and includes no telemetry.

= Does RevertShield store WordPress option values? =

The built-in critical-option observer stores option names only. It does not store the previous or new option values.

= Where are plugin snapshots stored? =

Snapshot objects are stored below the site's WordPress uploads directory in a RevertShield-managed path. Original executable plugin filenames are not copied into uploads; file bytes are stored as extensionless SHA-256-addressed objects with integrity manifests and verification checks.

= How does a guarded plugin update work? =

RevertShield only shows plugin updates currently reported by WordPress. Before an update can run, an authorized administrator must select a matching ready snapshot. RevertShield independently verifies the snapshot again, confirms that the update offer still exists, and then delegates the update to WordPress core's plugin upgrader. Afterward, RevertShield runs a homepage health check and records the result locally.

= How does manual plugin recovery work? =

An authorized administrator explicitly selects an eligible ready snapshot and confirms the recovery action. RevertShield verifies the snapshot again, stages and verifies its files, preserves the current plugin files during the transaction, replaces only that plugin's files, verifies the restored inventory exactly, and then runs a homepage health check. RevertShield itself is excluded from manual self-recovery in this release.

= Does RevertShield automatically roll back an unhealthy update or recovery? =

No. Version 0.4.0 provides explicit manual plugin recovery, not automatic rollback. If the post-update or post-recovery homepage health check is unhealthy, RevertShield records the result and stops. It does not perform a generic database rollback or automatically start another restore.

= Does uninstall remove RevertShield data? =

Not by default. Enable the delete-on-uninstall setting before uninstalling if you want RevertShield's local tables, settings, and managed snapshot storage removed.

== Changelog ==

= 0.4.0 =
* Added shared WordPress-native Dashboard, Snapshots, Updates, and Recovery navigation.
* Added scoped recovery eligibility requiring a ready, matching, unexpired, independently verified plugin snapshot.
* Added an administrator-only manual plugin recovery screen with explicit confirmation.
* Added protected recovery staging with per-file SHA-256 and size verification before replacement.
* Added transactional preservation of the existing plugin files until exact post-restore inventory verification succeeds.
* Added post-recovery homepage health validation and local recovery ledger events.
* Added a short-lived lock to prevent concurrent manual recovery operations.
* Kept RevertShield self-recovery, generic database rollback, and automatic rollback disabled.

= 0.3.0 =
* Added an admin-only guarded plugin update screen for currently available WordPress plugin updates.
* Added enforced matching of guarded updates to unexpired, independently verified ready snapshots.
* Added update-offer revalidation before execution.
* Added guarded update execution through WordPress core Plugin_Upgrader APIs.
* Added post-update homepage health validation.
* Added local ledger events for guarded update start, safe failure, healthy completion, and unhealthy completion.
* Kept automatic rollback disabled while recovery eligibility and post-restore verification remained under development.

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
