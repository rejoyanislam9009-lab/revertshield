# RevertShield

RevertShield is a local-first WordPress change-safety plugin. It records high-value maintenance events, runs multi-probe health checks, creates integrity-verified plugin snapshots, executes guarded plugin updates through WordPress core APIs, and provides explicit scoped manual plugin recovery.

> Development status: pre-1.0. The 0.9.0 development line adds operations and observability controls on top of the validated 0.8.0 safety baseline. Do not submit unfinished development snapshots to WordPress.org. Release ZIPs are produced only from release-ready code that passes the project quality gates.

## Why RevertShield exists

A WordPress update can fail for many reasons, but the first operational question is usually the same: **what changed immediately before the site became unhealthy?** RevertShield builds a trustworthy local ledger and verified snapshot boundary around that question, then uses that boundary to gate updates and supported recovery operations.

## Current capabilities

- Dedicated, indexed change ledger, health history, and snapshot metadata tables.
- Plugin activation and deactivation tracking.
- Plugin, theme, and core upgrader event tracking.
- Theme switch tracking.
- Privacy-conscious tracking of selected critical option names without storing their values.
- On-demand local site-health suite covering the public homepage and WordPress REST API index.
- Optional ecosystem health-probe adapters without hard dependencies on the integrated plugin.
- Automatic read-only WooCommerce Store API product-collection health coverage when WooCommerce is active, bounded to one product and requiring no API credentials.
- Optional scheduled local health checks through WordPress Cron at bounded 1, 6, 12, or 24 hour cadences, disabled by default.
- Authorized, nonce-protected verified snapshots of installed plugin files.
- Canonical plugin source resolution with path traversal and symlink protections.
- SHA-256 file inventories and extensionless content-addressed snapshot storage.
- Independent snapshot verification before a snapshot is treated as ready.
- Snapshot history and bounded 1-90 day retention cleanup.
- Administrator-controlled snapshot pin/protect state that suspends a selected snapshot's original expiration until explicitly unpinned without rewriting its manifest or objects.
- Operations & Observability admin surface for protected snapshots and scheduled local health controls.
- Read-only WP-CLI commands for operational status, persisted health state, recent snapshots, snapshot inspection, and optional integrity verification.
- Explicit site/network ownership metadata for newly created Multisite snapshots.
- Explicit per-site Multisite snapshot storage namespaces and cross-site/network verification rejection.
- Per-site schema, defaults, and cleanup-schedule provisioning for network-activated and newly initialized Multisite sites.
- Administrator-controlled guarded plugin updates that require a matching, unexpired, independently verified ready snapshot on supported single-site installations.
- Update-offer revalidation immediately before execution and delegation to WordPress core `Plugin_Upgrader` APIs.
- Optional maintenance-window policy for guarded updates, disabled by default and enforced at execution time.
- Pause-on-failure guarded update batches that run sequentially and stop on the first safe failure or unhealthy health result.
- Post-update multi-probe health validation with local healthy/unhealthy ledger events.
- Explicit recovery recommendations after unhealthy guarded updates when the exact pre-update snapshot still passes independent verification.
- Shared WordPress-native Dashboard, Snapshots, Updates, Recovery, and Operations admin navigation.
- RevertShield-scoped admin notice management that auto-clears transient success/info notices while preserving warning/error visibility.
- Explicit administrator-controlled manual plugin recovery from an eligible verified snapshot on supported single-site installations.
- Protected recovery staging and per-file SHA-256/size verification before replacement.
- Transactional preservation of the current plugin files until exact post-restore inventory verification succeeds.
- Post-recovery multi-probe health validation and local recovery ledger events.
- Short-lived serialization lock to prevent concurrent manual recoveries.
- Multisite safety mode that keeps observation and snapshots site-scoped while guarded plugin updates and plugin-file recovery fail closed until bounded network-wide health validation exists.
- Safe Multisite uninstall behavior that retains site data until an explicit bounded network-wide deletion policy exists.
- Real WordPress runtime regression coverage for activation, snapshot integrity, guarded-update failure-closed behavior, policy enforcement, guarded batches, scoped recovery, health persistence, tamper detection, notification management, WooCommerce health integration, Multisite safety boundaries, operations controls, WP-CLI inspection, lifecycle behavior, and stable-version upgrades.
- Real current WooCommerce install/activation and Store API integration coverage on the latest supported WordPress/PHP runtime boundary.
- Capability checks, nonces, input sanitization, output escaping, and redaction of obvious secret-like context keys.

RevertShield 0.9.0 does **not** implement generic database rollback, automatic rollback after an unhealthy health result, self-recovery of RevertShield, or WP-CLI mutation of guarded updates/recovery. Supported recovery remains explicit, administrator-controlled, component-scoped, and verification-gated. On WordPress Multisite, installed plugin files are shared network-wide, so guarded plugin updates and plugin-file recovery intentionally fail closed until RevertShield has bounded network-wide post-change health validation.

## Read-only WP-CLI

The 0.9.0 development line adds a deliberately non-destructive WP-CLI surface:

```text
wp revertshield status [--format=table|json|csv|yaml]
wp revertshield health [--format=table|json|csv|yaml]
wp revertshield snapshots [--limit=<1-100>] [--format=table|json|csv|yaml]
wp revertshield snapshot <snapshot_uuid> [--verify] [--format=table|json|csv|yaml]
```

These commands inspect local RevertShield state. They do not run guarded plugin updates, recovery, snapshot pin/unpin mutations, or generic rollback operations.

## Architecture

```text
revertshield.php
src/
  Admin/       Admin UI, scoped notices, settings, guarded updates, recovery, and operations controls
  Cli/         Read-only WP-CLI operational inspection
  Core/        Lifecycle and service coordination
  Database/    Schema and table names
  Health/      Core/optional probes, aggregate results, and scheduled local health execution
  Ledger/      Change and recovery event persistence
  Policy/      Maintenance-window policy
  Recovery/    Recovery eligibility and scoped plugin-file restore execution
  Snapshot/    Snapshot inventory, storage, lifecycle, verification, and pin/protect registry
  Support/     Site/network context, Multisite provisioning, retention, and housekeeping
  Update/      Guarded update eligibility, execution, and pause-on-failure batches
assets/        Shipped admin assets
  js/          RevertShield-scoped notice manager
docs/          Architecture, roadmap, release checklist
tests/         Real WordPress runtime, operations, Multisite, and ecosystem integration assertions
.github/       CI, runtime matrix, upgrade, operations, and WordPress Plugin Check workflows
```

Recovery is intentionally component-aware. RevertShield does not implement generic SQL reversal. A supported recovery operation must know which component changed, what was snapshotted, whether the snapshot is intact, whether it belongs to the target component and current site context, and whether the restored files exactly match the verified manifest.

Snapshot pinning is deliberately metadata-only. Pinning suspends the selected snapshot's expiration while preserving its original expiration in a site-scoped non-autoloaded registry. The verified snapshot manifest and content-addressed objects are not rewritten; unpinning restores the original expiration timestamp and normal retention eligibility.

Scheduled health checks reuse the same local health suite used by manual, post-update, and post-recovery decisions. Scheduling is opt-in, uses WordPress Cron, and does not introduce telemetry or a remote account requirement.

The WooCommerce health adapter is intentionally read-only. It uses the customer-facing Store API product collection only when WooCommerce is already active and does not access orders, customers, payments, carts, checkout mutation endpoints, API credentials, or external services.

## Development requirements

- WordPress 6.5+
- PHP 7.4+
- Composer for development tooling only

## Quality gates

Pull requests and release candidates are expected to pass:

- PHP syntax checks across supported PHP versions.
- WordPress Coding Standards via PHPCS.
- Real WordPress runtime smoke and regression tests on the minimum supported boundary and the latest supported boundary.
- Operations & observability regression tests on the minimum and latest supported boundaries, including pin/unpin retention behavior and read-only WP-CLI smoke assertions.
- Real WordPress Multisite safety regression tests on the minimum supported boundary and the latest supported boundary.
- Stable-version in-place upgrade coverage from the previous release while preserving existing site state.
- Real current WooCommerce activation and Store API integration coverage on the latest supported boundary for releases that change the WooCommerce adapter.
- Official WordPress Plugin Check against the built distribution package.
- Release allowlist packaging so development-only files are never shipped accidentally.

The runtime matrix installs WordPress, activates the built RevertShield package, creates and verifies a fixture snapshot, proves guarded-update and recovery gates fail closed, validates maintenance-window policy and batch pause behavior, performs a real scoped fixture recovery, checks multi-probe health and ledger persistence, verifies tamper rejection, and smoke-renders the administrator screens. The operations matrix verifies protected snapshot retention behavior, scheduled-health reconciliation, and the read-only WP-CLI surface. The upgrade matrix installs the previous stable release and then performs an in-place update to the current development release while checking that existing state remains intact. The Multisite matrix verifies per-site provisioning, storage isolation, snapshot ownership, cross-site rejection, and shared-plugin-file mutation fail-closed behavior. The latest runtime boundary also installs and activates the current WooCommerce release and validates the real public Store API route used by the optional health adapter.

## WordPress.org policy

GitHub is the development repository. WordPress.org SVN will be treated as a release repository only. Directory releases will use a versioned `Stable tag`, human-readable source, GPL-compatible assets, and no hidden telemetry or remotely delivered executable code.

## License

GPL-2.0-or-later. See `LICENSE`.
