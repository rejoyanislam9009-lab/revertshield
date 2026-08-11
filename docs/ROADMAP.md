# Roadmap

## Phase 1 - Foundation - complete

- Plugin bootstrap and lifecycle.
- Dedicated indexed tables.
- Change ledger.
- Plugin/theme/core maintenance observers.
- Local homepage health check.
- Retention and uninstall controls.
- GitHub CI and WordPress Plugin Check.

## Phase 2 - Snapshot integrity foundation - complete on `develop`

- Immutable manifest format and explicit lifecycle states.
- Canonical installed-plugin source resolution.
- Filesystem storage boundary and disk-space preflight.
- Extensionless content-addressed snapshot objects.
- SHA-256 verification before and after copy.
- Independent post-finalization integrity verification.
- Bounded expiration metadata and safe uninstall cleanup.

## Phase 3A - Admin snapshot operations - active

- Admin-only, nonce-protected verified plugin snapshots.
- Snapshot history and lifecycle visibility.
- Configurable snapshot retention.
- Bounded daily expiration cleanup.
- Safe-update precondition requiring a matching, unexpired, independently verified ready snapshot.

## Phase 3B - Safe plugin update execution

- Explicit opt-in safe update action for selected plugins.
- Verified pre-update snapshot required by the safe-update gate.
- Update execution through WordPress-owned upgrader APIs.
- Post-update homepage and REST health policies.
- Failed-health incident record and pause behavior.

## Phase 3C - Scoped recovery

- Recovery eligibility contract.
- Manual restore for supported plugin-file snapshots.
- Restore staging and atomic replacement strategy.
- Post-restore integrity and health verification.
- No generic SQL rollback.

## Phase 4 - Policy engine

- Multiple health probes.
- Maintenance windows.
- Pause-on-failure update batches.
- Notification adapters.

## Phase 5 - Ecosystem adapters

- WooCommerce health probes.
- Multisite-aware storage and operations.
- WP-CLI commands.
- REST endpoints with explicit permission callbacks.
