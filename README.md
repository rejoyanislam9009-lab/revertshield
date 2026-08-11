# RevertShield

RevertShield is a local-first WordPress change-safety plugin. It records high-value maintenance events, runs health checks, and creates integrity-verified plugin snapshots so administrators can understand and contain risky maintenance changes before recovery features are enabled.

> Development status: pre-1.0. Do not submit this repository snapshot to WordPress.org yet. The directory ZIP will be produced only from release-ready code.

## Why RevertShield exists

A WordPress update can fail for many reasons, but the first operational question is usually the same: **what changed immediately before the site became unhealthy?** RevertShield builds a trustworthy local ledger and verified snapshot boundary around that question.

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
- A safe-update eligibility gate that requires a matching, unexpired, verified ready snapshot before future guarded update execution.
- Capability checks, nonces, input sanitization, output escaping, and redaction of obvious secret-like context keys.

RevertShield 0.2.0 does **not** execute guarded plugin updates or automatic rollback. Those operations remain blocked until post-change health policies and scoped recovery verification are implemented.

## Architecture

```text
revertshield.php
src/
  Admin/       Admin UI and settings
  Core/        Lifecycle and service coordination
  Database/    Schema and table names
  Health/      Health probes and results
  Ledger/      Change observers and persistence
  Snapshot/    Snapshot inventory, storage, lifecycle, and verification
  Support/     Retention and housekeeping
  Update/      Safe-update precondition contracts
assets/        Shipped admin assets
docs/          Architecture, roadmap, release checklist
.github/       CI and WordPress Plugin Check workflow
```

Recovery is intentionally adapter-based. RevertShield will not implement generic SQL reversal. A future recovery operation must know which component changed, what was snapshotted, whether the snapshot is intact, whether the snapshot belongs to the target component, and whether restoration is safe.

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
