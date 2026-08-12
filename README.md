# RevertShield

RevertShield is a local-first WordPress change-safety plugin. It records high-value maintenance events, runs health checks, creates integrity-verified plugin snapshots, executes guarded plugin updates through WordPress core APIs, and provides explicit scoped manual plugin recovery.

> Development status: pre-1.0. Do not submit unfinished development snapshots to WordPress.org. Release ZIPs are produced only from release-ready code that passes the project quality gates.

## Why RevertShield exists

A WordPress update can fail for many reasons, but the first operational question is usually the same: **what changed immediately before the site became unhealthy?** RevertShield builds a trustworthy local ledger and verified snapshot boundary around that question, then uses that boundary to gate updates and supported recovery operations.

## Current capabilities

- Dedicated, indexed change ledger, health history, and snapshot metadata tables.
- Plugin activation and deactivation tracking.
- Plugin, theme, and core upgrader event tracking.
- Theme switch tracking.
- Privacy-conscious tracking of selected critical option names without storing their values.
- On-demand homepage HTTP health checks using the WordPress HTTP API.
- Authorized, nonce-protected verified snapshots of installed plugin files.
- Canonical plugin source resolution with path traversal and symlink protections.
- SHA-256 file inventories and extensionless content-addressed snapshot storage.
- Independent snapshot verification before a snapshot is treated as ready.
- Snapshot history and bounded 1-90 day retention cleanup.
- Administrator-controlled guarded plugin updates that require a matching, unexpired, independently verified ready snapshot.
- Update-offer revalidation immediately before execution and delegation to WordPress core `Plugin_Upgrader` APIs.
- Post-update homepage health validation with local healthy/unhealthy ledger events.
- Shared WordPress-native Dashboard, Snapshots, Updates, and Recovery admin navigation.
- Explicit administrator-controlled manual plugin recovery from an eligible verified snapshot.
- Protected recovery staging and per-file SHA-256/size verification before replacement.
- Transactional preservation of the current plugin files until exact post-restore inventory verification succeeds.
- Post-recovery homepage health validation and local recovery ledger events.
- Short-lived serialization lock to prevent concurrent manual recoveries.
- Capability checks, nonces, input sanitization, output escaping, and redaction of obvious secret-like context keys.

RevertShield 0.4.0 does **not** implement generic database rollback, automatic rollback after an unhealthy health result, or self-recovery of RevertShield. Supported recovery remains explicit, administrator-controlled, component-scoped, and verification-gated.

## Architecture

```text
revertshield.php
src/
  Admin/       Admin UI, navigation, settings, guarded updates, and recovery actions
  Core/        Lifecycle and service coordination
  Database/    Schema and table names
  Health/      Health probes and results
  Ledger/      Change and recovery event persistence
  Recovery/    Recovery eligibility and scoped plugin-file restore execution
  Snapshot/    Snapshot inventory, storage, lifecycle, and verification
  Support/     Retention and housekeeping
  Update/      Guarded update eligibility and execution
assets/        Shipped admin assets
docs/          Architecture, roadmap, release checklist
.github/       CI and WordPress Plugin Check workflow
```

Recovery is intentionally component-aware. RevertShield does not implement generic SQL reversal. A supported recovery operation must know which component changed, what was snapshotted, whether the snapshot is intact, whether it belongs to the target component, and whether the restored files exactly match the verified manifest.

## Development requirements

- WordPress 6.5+
- PHP 7.4+
- Composer for development tooling only

## Quality gates

Pull requests and release candidates are expected to pass:

- PHP syntax checks across supported PHP versions.
- WordPress Coding Standards via PHPCS.
- Official WordPress Plugin Check against the built distribution directory.
- Release allowlist packaging so development-only files are never shipped accidentally.

## WordPress.org policy

GitHub is the development repository. WordPress.org SVN will be treated as a release repository only. Directory releases will use a versioned `Stable tag`, human-readable source, GPL-compatible assets, and no hidden telemetry or remotely delivered executable code.

## License

GPL-2.0-or-later. See `LICENSE`.
