# RevertShield

RevertShield is a local-first WordPress change-safety plugin. It records high-value maintenance events and runs health checks so administrators can understand what changed before recovery features are introduced.

> Development status: pre-1.0. Do not submit this repository snapshot to WordPress.org yet. The directory ZIP will be produced only from release-ready code.

## Why RevertShield exists

A WordPress update can fail for many reasons, but the first operational question is usually the same: **what changed immediately before the site became unhealthy?** RevertShield builds a trustworthy local ledger around that question.

## Current capabilities

- Dedicated, indexed change ledger tables instead of unbounded serialized options.
- Plugin activation and deactivation tracking.
- Plugin, theme, and core upgrader event tracking.
- Theme switch tracking.
- Privacy-conscious tracking of selected critical option names without storing their values.
- On-demand homepage HTTP health checks using the WordPress HTTP API.
- Local health history, bounded retention, and uninstall controls.
- Capability checks, nonces, input sanitization, output escaping, and redaction of obvious secret-like context keys.

## Architecture

```text
revertshield.php
src/
  Admin/       Admin UI and settings
  Core/        Lifecycle and service coordination
  Database/    Schema and table names
  Health/      Health probes and results
  Ledger/      Change observers and persistence
  Support/     Retention and housekeeping
assets/        Shipped admin assets
docs/          Architecture, roadmap, release checklist
.github/       CI and WordPress Plugin Check workflow
```

Recovery is intentionally adapter-based. RevertShield will not implement generic SQL reversal. A future recovery operation must know which component changed, what was snapshotted, whether the snapshot is intact, and whether restoring it is safe.

## Development requirements

- WordPress 6.5+
- PHP 7.4+
- Composer for development tooling only

## Quality gates

Pull requests and main-branch changes are expected to pass:

- PHP syntax checks across supported PHP versions.
- WordPress Coding Standards via PHPCS.
- Official WordPress Plugin Check against the built distribution directory.
- Release allowlist packaging so development-only files are never shipped accidentally.

## WordPress.org policy

GitHub is the development repository. WordPress.org SVN will be treated as a release repository only. Directory releases will use a versioned `Stable tag`, human-readable source, GPL-compatible assets, and no hidden telemetry or remotely delivered executable code.

## License

GPL-2.0-or-later. See `LICENSE`.
