# RevertShield

RevertShield is a local-first WordPress change-safety plugin. It records high-value maintenance events, runs multi-probe health checks, creates integrity-verified plugin snapshots, executes guarded plugin updates through WordPress core APIs, and provides explicit scoped manual plugin recovery.

> Development status: pre-1.0. Do not submit unfinished development snapshots to WordPress.org. Release ZIPs are produced only from release-ready code that passes the project quality gates.

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
- Authorized, nonce-protected verified snapshots of installed plugin files.
- Canonical plugin source resolution with path traversal and symlink protections.
- SHA-256 file inventories and extensionless content-addressed snapshot storage.
- Independent snapshot verification before a snapshot is treated as ready.
- Snapshot history and bounded 1-90 day retention cleanup.
- Administrator-controlled guarded plugin updates that require a matching, unexpired, independently verified ready snapshot.
- Update-offer revalidation immediately before execution and delegation to WordPress core `Plugin_Upgrader` APIs.
- Optional maintenance-window policy for guarded updates, disabled by default and enforced at execution time.
- Pause-on-failure guarded update batches that run sequentially and stop on the first safe failure or unhealthy health result.
- Post-update multi-probe health validation with local healthy/unhealthy ledger events.
- Explicit recovery recommendations after unhealthy guarded updates when the exact pre-update snapshot still passes independent verification.
- Shared WordPress-native Dashboard, Snapshots, Updates, and Recovery admin navigation.
- RevertShield-scoped admin notice management that auto-clears transient success/info notices while preserving warning/error visibility.
- Explicit administrator-controlled manual plugin recovery from an eligible verified snapshot.
- Protected recovery staging and per-file SHA-256/size verification before replacement.
- Transactional preservation of the current plugin files until exact post-restore inventory verification succeeds.
- Post-recovery multi-probe health validation and local recovery ledger events.
- Short-lived serialization lock to prevent concurrent manual recoveries.
- Real WordPress runtime regression coverage for activation, snapshot integrity, guarded-update failure-closed behavior, policy enforcement, guarded batches, scoped recovery, health persistence, tamper detection, notification management, WooCommerce health integration, and admin rendering.
- Real current WooCommerce install/activation and Store API integration coverage on the latest supported WordPress/PHP runtime boundary.
- Capability checks, nonces, input sanitization, output escaping, and redaction of obvious secret-like context keys.

RevertShield 0.7.0 does **not** implement generic database rollback, automatic rollback after an unhealthy health result, or self-recovery of RevertShield. Supported recovery remains explicit, administrator-controlled, component-scoped, and verification-gated.

## Architecture

```text
revertshield.php
src/
  Admin/       Admin UI, scoped notice management, settings, guarded updates, and recovery actions
  Core/        Lifecycle and service coordination
  Database/    Schema and table names
  Health/      Core and optional ecosystem health probes plus aggregate results
  Ledger/      Change and recovery event persistence
  Policy/      Maintenance-window policy
  Recovery/    Recovery eligibility and scoped plugin-file restore execution
  Snapshot/    Snapshot inventory, storage, lifecycle, and verification
  Support/     Retention and housekeeping
  Update/      Guarded update eligibility, execution, and pause-on-failure batches
assets/        Shipped admin assets
  js/          RevertShield-scoped notice manager
docs/          Architecture, roadmap, release checklist
tests/         Real WordPress runtime smoke, regression, and ecosystem integration assertions
.github/       CI, runtime matrix, and WordPress Plugin Check workflow
```

Recovery is intentionally component-aware. RevertShield does not implement generic SQL reversal. A supported recovery operation must know which component changed, what was snapshotted, whether the snapshot is intact, whether it belongs to the target component, and whether the restored files exactly match the verified manifest.

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
- Real current WooCommerce activation and Store API integration coverage on the latest supported boundary for releases that change the WooCommerce adapter.
- Official WordPress Plugin Check against the built distribution directory.
- Release allowlist packaging so development-only files are never shipped accidentally.

The runtime matrix installs WordPress, activates the built RevertShield package, creates and verifies a fixture snapshot, proves guarded-update and recovery gates fail closed, validates maintenance-window policy and batch pause behavior, performs a real scoped fixture recovery, checks multi-probe health and ledger persistence, verifies tamper rejection, and smoke-renders the administrator screens. The latest runtime boundary also installs and activates the current WooCommerce release and validates the real public Store API route used by the optional health adapter.

## WordPress.org policy

GitHub is the development repository. WordPress.org SVN will be treated as a release repository only. Directory releases will use a versioned `Stable tag`, human-readable source, GPL-compatible assets, and no hidden telemetry or remotely delivered executable code.

## License

GPL-2.0-or-later. See `LICENSE`.
