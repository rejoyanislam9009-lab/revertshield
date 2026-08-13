=== RevertShield ===
Tags: maintenance, updates, debugging, health check, activity log
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.8.0
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
* Runs an on-demand local site-health suite covering the public homepage and WordPress REST API index.
* When WooCommerce is active, adds a bounded read-only WooCommerce Store API product-collection probe to the same health decision without requiring API credentials.
* Stores change, health, and snapshot history in dedicated, indexed database tables.
* Lets authorized administrators create integrity-verified snapshots of installed plugin files.
* Stores snapshot contents as extensionless, SHA-256-addressed objects under the WordPress uploads directory.
* Re-verifies stored snapshot manifests and object hashes before treating a snapshot as ready.
* Provides snapshot history and configurable 1-90 day snapshot retention with bounded cleanup.
* On Multisite, binds new snapshot manifests to the current site and network and stores snapshots in explicit per-site namespaces.
* On Multisite, rejects snapshot verification when site or network ownership does not match the current context.
* Provides per-site schema, settings, and cleanup-schedule provisioning for network-activated and newly initialized Multisite sites.
* Provides an administrator-controlled guarded plugin update screen for updates currently reported by WordPress on supported single-site installations.
* Requires a matching, unexpired, independently verified ready snapshot before a guarded plugin update can run.
* Revalidates the WordPress update offer immediately before update execution.
* Executes guarded plugin updates through WordPress core Plugin_Upgrader APIs rather than custom package-download logic.
* Runs the multi-probe site-health suite after successful guarded updates and records healthy or unhealthy outcomes locally.
* Supports an optional administrator-configured maintenance window for guarded update execution. The policy is disabled by default.
* Supports bounded pause-on-failure guarded update batches. Updates run sequentially and the batch stops on the first safe failure or unhealthy health result.
* Can recommend the exact independently reverified pre-update snapshot for manual review after an unhealthy guarded update, without starting recovery automatically.
* Provides WordPress-native Dashboard, Snapshots, Updates, and Recovery navigation across RevertShield admin screens.
* Provides a RevertShield-scoped notification center on RevertShield admin screens so transient success and informational notices do not remain piled around the page header.
* Auto-clears transient success and informational notices after a short delay while keeping warning and error notices visible until dismissed.
* Provides explicit administrator-controlled manual plugin recovery from a matching, unexpired, independently verified ready snapshot on supported single-site installations.
* Stages and verifies every recovery file before replacing the installed plugin files.
* Preserves the pre-recovery plugin files during the transaction until the restored plugin passes exact inventory, SHA-256, and size verification.
* Runs the multi-probe site-health suite after successful manual recovery and records healthy or unhealthy outcomes locally.
* Prevents concurrent manual recovery operations with a short-lived recovery lock.
* On Multisite, intentionally disables guarded plugin updates and plugin-file recovery because installed plugin files are shared network-wide while current health validation is site-scoped.
* Includes automated real-WordPress runtime regression coverage for bootstrap, snapshot integrity, guarded-update safety gates, policy enforcement, scoped recovery, health persistence, tamper detection, WooCommerce health integration, Multisite safety boundaries, and admin rendering.
* Sends no telemetry and requires no external account.

RevertShield 0.8.0 keeps supported recovery explicit, administrator-controlled, component-scoped, and verification-gated. On Multisite, snapshots and observation remain site-scoped while guarded plugin updates and plugin-file recovery intentionally fail closed until bounded network-wide health validation is available. It does not perform generic database rollback, does not restore RevertShield itself, and does not automatically roll back or restore after an unhealthy health result.

== Installation ==

1. Upload the `revertshield` folder to `/wp-content/plugins/`, or install the release ZIP from Plugins > Add Plugin > Upload Plugin.
2. Activate RevertShield.
3. Open Tools > RevertShield for health checks, maintenance-window settings, and the activity ledger.
4. Open Tools > RevertShield Snapshots to create and review verified plugin snapshots.
5. Open Tools > RevertShield Updates to run an eligible guarded plugin update or pause-on-failure guarded batch when WordPress reports available updates.
6. Open Tools > RevertShield Recovery to manually restore an eligible plugin snapshot after reviewing the confirmation warning.

== Frequently Asked Questions ==

= Does RevertShield send site data to an external service? =

No. The current release stores its ledger, health-check results, snapshot metadata, policy settings, and recovery records on the site's own WordPress installation and includes no telemetry.

= Does RevertShield store WordPress option values? =

The built-in critical-option observer stores option names only. It does not store the previous or new option values.

= Where are plugin snapshots stored? =

Snapshot objects are stored below the site's WordPress uploads directory in a RevertShield-managed path. Original executable plugin filenames are not copied into uploads; file bytes are stored as extensionless SHA-256-addressed objects with integrity manifests and verification checks. On Multisite, RevertShield adds an explicit current-site namespace and stores site/network ownership in new snapshot manifests.

= What does the site-health suite check? =

RevertShield always checks the site's public homepage and WordPress REST API index through the WordPress HTTP API. When WooCommerce is active, it also performs a bounded read-only request against the public WooCommerce Store API product collection. All applicable probes must pass for the aggregate site-health result to pass. RevertShield does not use WooCommerce API credentials and does not read orders, customers, payments, carts, or checkout data for this probe.

= How does a guarded plugin update work? =

On supported single-site installations, RevertShield only shows plugin updates currently reported by WordPress. Before an update can run, an authorized administrator must select a matching ready snapshot. RevertShield independently verifies the snapshot again, checks the optional maintenance-window policy, confirms that the update offer still exists, and then delegates the update to WordPress core's plugin upgrader. Afterward, RevertShield runs the site-health suite, including applicable ecosystem probes, and records the result locally. On Multisite in version 0.8.0, guarded plugin updates intentionally fail closed because installed plugin files are network-shared while current post-change health validation is site-scoped.

= How do guarded update batches work? =

On supported single-site installations, an authorized administrator can select up to 20 eligible guarded updates. RevertShield runs them one at a time. If an update fails safely or its post-update health result is unhealthy, the batch pauses immediately and later selected updates are not started.

= Does a recovery recommendation restore anything automatically? =

No. After an unhealthy guarded update, RevertShield may recommend the exact verified pre-update snapshot for review. The Recovery screen still requires an explicit administrator action and confirmation before any files are restored.

= Why do notices disappear on RevertShield screens? =

Transient success and informational notices on RevertShield screens are collected into a scoped notice area and automatically cleared after a short delay. Warning and error notices remain visible until they are explicitly dismissed. RevertShield does not change notice behavior on unrelated WordPress admin screens.

= How does manual plugin recovery work? =

On supported single-site installations, an authorized administrator explicitly selects an eligible ready snapshot and confirms the recovery action. RevertShield verifies the snapshot again, stages and verifies its files, preserves the current plugin files during the transaction, replaces only that plugin's files, verifies the restored inventory exactly, and then runs the multi-probe site-health suite. RevertShield itself is excluded from manual self-recovery in this release. On Multisite in version 0.8.0, plugin-file recovery intentionally fails closed because plugin files are network-shared while current health validation is site-scoped.

= Does RevertShield automatically roll back an unhealthy update or recovery? =

No. Version 0.8.0 provides explicit manual plugin recovery on single-site WordPress, not automatic rollback. On Multisite, plugin-file recovery is intentionally deferred because plugin files are shared network-wide while current health validation is site-scoped. If a post-update or post-recovery health result is unhealthy, RevertShield records the result and stops or pauses the current batch. It does not perform a generic database rollback or automatically start another restore.

= Does uninstall remove RevertShield data? =

Not by default. On single-site WordPress, enable the delete-on-uninstall setting before uninstalling if you want RevertShield's local tables, settings, and managed snapshot storage removed. On Multisite, uninstall retains RevertShield data until a bounded network-wide deletion policy exists.

== Changelog ==

= 0.8.0 =
* Added explicit current site and network context for Multisite snapshot ownership decisions.
* Bound newly created snapshot manifests to their origin blog/site and network identifiers.
* Added explicit per-site Multisite snapshot storage namespaces below the WordPress uploads directory.
* Added independent verification that rejects snapshots from a different Multisite site or network context.
* Kept legacy single-site snapshots valid on single-site WordPress while requiring fresh scoped snapshots after Multisite enablement.
* Added per-site schema, defaults, and cleanup-schedule provisioning for network-activated and newly initialized Multisite sites.
* Added safe creation and revalidation of WordPress-resolved uploads directories for newly initialized Multisite sites.
* Deferred guarded plugin updates and plugin-file recovery on Multisite because installed plugin files are network-shared while current health validation is site-scoped.
* Added a persistent RevertShield admin warning explaining the Multisite safety boundary.
* Retained RevertShield data on Multisite uninstall until an explicit bounded network-wide deletion policy exists.
* Added real WordPress Multisite regression coverage on the minimum and latest supported runtime boundaries.
* Kept network-wide automatic rollback, generic database rollback, cross-site snapshot authorization, RevertShield self-recovery, remote executable code, telemetry, REST mutation, and WP-CLI mutation disabled.

= 0.7.0 =
* Added an optional ecosystem health-probe adapter contract for local integration-specific health checks.
* Added automatic WooCommerce runtime detection without making WooCommerce a hard dependency or loading it from RevertShield.
* Added a bounded read-only WooCommerce Store API product-collection probe when WooCommerce is active.
* Added WooCommerce health to manual site checks, post-guarded-update validation, and post-recovery validation through the shared health suite.
* Kept non-WooCommerce sites on the existing required homepage and WordPress REST API probes.
* Added failure-closed aggregate handling when the WooCommerce Store API probe is unhealthy.
* Added deterministic WooCommerce adapter regression coverage on supported runtime boundaries.
* Added a real current WooCommerce install, activation, and Store API integration assertion on the latest supported WordPress/PHP runtime boundary.
* Kept WooCommerce API credentials, order/customer/payment access, cart or checkout mutation, telemetry, generic database rollback, and automatic rollback disabled.

= 0.6.0 =
* Added a RevertShield-scoped admin notification center on RevertShield screens.
* Added automatic clearing and current-session repeat suppression for transient success and informational notices while preserving warning and error visibility.
* Added automatic cleanup of RevertShield one-time action status query arguments after rendering.
* Expanded local health validation into a multi-probe site-health suite covering the public homepage and WordPress REST API index.
* Added an optional administrator-configured guarded-update maintenance window, disabled by default and enforced at the execution-service boundary.
* Added bounded pause-on-failure guarded update batches with sequential execution and immediate stop on the first safe failure or unhealthy result.
* Added explicit recovery recommendations after unhealthy guarded updates when the exact pre-update snapshot still passes independent verification.
* Added Recovery-screen highlighting for recommended snapshots without bypassing the existing manual confirmation requirement.
* Expanded real WordPress runtime regression coverage for notification management, maintenance-window enforcement, guarded batches, multi-probe health, and recovery recommendations.
* Kept generic database rollback, automatic rollback, RevertShield self-recovery, arbitrary package URL execution, and telemetry disabled.

= 0.5.0 =
* Added real WordPress install-and-activate runtime smoke tests to the release quality gates.
* Added boundary runtime coverage on WordPress 6.5 with PHP 7.4 and the latest supported WordPress with PHP 8.4.
* Added automated assertions for plugin bootstrap, schema migration, custom tables, safe defaults, cleanup schedules, and registered protected admin actions.
* Added real fixture-plugin snapshot creation and independent integrity verification tests.
* Added guarded-update failure-closed tests for target mismatch, preparing or expired snapshots, unavailable updates, and blocked RevertShield self-update.
* Added recovery failure-closed tests for target mismatch, preparing or expired snapshots, blocked self-recovery, and concurrent recovery locking.
* Added an automated real scoped restore that verifies restored plugin files, version, post-recovery health persistence, and recovery ledger events.
* Added stored snapshot-object tamper detection tests proving corrupted objects cannot pass verification, guarded-update eligibility, or recovery eligibility.
* Added Dashboard, Snapshots, Updates, Recovery, and WordPress-native navigation render smoke coverage.
* Kept generic database rollback, automatic rollback, RevertShield self-recovery, arbitrary package URL execution, and telemetry disabled.

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
