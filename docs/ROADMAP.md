# Roadmap

## Phase 1 - Foundation - complete

- Plugin bootstrap and lifecycle.
- Dedicated indexed tables.
- Change ledger.
- Plugin/theme/core maintenance observers.
- Local homepage health check.
- Retention and uninstall controls.
- GitHub CI and WordPress Plugin Check.

## Phase 2 - Snapshot integrity foundation - complete

- Immutable manifest format and explicit lifecycle states.
- Canonical installed-plugin source resolution.
- Filesystem storage boundary and disk-space preflight.
- Extensionless content-addressed snapshot objects.
- SHA-256 verification before and after copy.
- Independent post-finalization integrity verification.
- Bounded expiration metadata and safe uninstall cleanup.

## Phase 3A - Admin snapshot operations - complete

- Admin-only, nonce-protected verified plugin snapshots.
- Snapshot history and lifecycle visibility.
- Configurable snapshot retention.
- Bounded daily expiration cleanup.
- Safe-update precondition requiring a matching, unexpired, independently verified ready snapshot.

## Phase 3B - Guarded plugin update execution - complete

- Explicit opt-in guarded update action for selected plugins.
- Verified pre-update snapshot required by the safe-update gate.
- Update-offer revalidation immediately before execution.
- Update execution through WordPress-owned `Plugin_Upgrader` APIs.
- Post-update homepage health validation.
- Local started, failed, healthy, and unhealthy update events.
- No automatic rollback.

## Phase 3C - Scoped plugin recovery - complete in 0.4.0

- Recovery eligibility contract for ready, matching, unexpired plugin snapshots.
- Independent snapshot verification immediately before recovery.
- Explicit administrator-controlled manual restore for supported plugin-file snapshots.
- Protected staging and per-file SHA-256/size verification before replacement.
- Transactional preservation of pre-recovery plugin files until exact post-restore verification succeeds.
- Exact restored inventory, hash, and size verification.
- Post-recovery homepage health validation.
- Short-lived lock preventing concurrent manual recoveries.
- No RevertShield self-recovery, generic SQL rollback, or automatic rollback.

## Phase 3D - Runtime regression coverage - complete in 0.5.0

- Real WordPress install-and-activate smoke coverage.
- Minimum supported boundary coverage on WordPress 6.5 and PHP 7.4.
- Latest supported boundary coverage on the current WordPress release and PHP 8.4.
- Bootstrap, migration, custom-table, default-setting, cleanup-schedule, and protected-action assertions.
- Real fixture-plugin snapshot creation and independent verification.
- Guarded-update failure-closed coverage for mismatched, not-ready, expired, unavailable-update, and self-update cases.
- Recovery failure-closed coverage for mismatched, not-ready, expired, self-recovery, and concurrent-recovery cases.
- Real scoped fixture recovery with exact restored-file and version verification.
- Post-recovery health persistence and recovery ledger verification.
- Snapshot-object tamper detection proving verification, update eligibility, and recovery eligibility fail closed.
- Dashboard, Snapshots, Updates, Recovery, and native navigation render smoke coverage.

## Phase 4 - Policy engine

- Multiple health probes.
- Maintenance windows.
- Pause-on-failure update batches.
- Notification adapters.
- Explicit policy for when a failed health result may recommend, but not silently trigger, a recovery action.

## Phase 5 - Ecosystem adapters

- WooCommerce health probes.
- Multisite-aware storage and operations.
- WP-CLI commands.
- REST endpoints with explicit permission callbacks.
