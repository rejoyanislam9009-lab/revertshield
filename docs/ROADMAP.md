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

## Phase 4 - Core policy engine - complete in 0.6.0

- Multi-probe local site-health suite covering the public homepage and WordPress REST API index.
- Optional administrator-configured maintenance windows for guarded update execution, disabled by default.
- Bounded pause-on-failure guarded update batches with sequential execution.
- RevertShield-scoped local admin notice management with transient success/info auto-clear and persistent warning/error visibility.
- Explicit policy allowing an unhealthy guarded update to recommend the exact independently reverified pre-update snapshot without silently triggering recovery.
- Runtime regression coverage for maintenance-window enforcement, batch pause behavior, multi-probe health persistence, scoped notice management, and recovery recommendations.
- External notification delivery adapters remain deferred; 0.6.0 adds no telemetry, remote account requirement, or external notification service.

## Phase 5A - WooCommerce health adapter - in progress for 0.7.0

- Optional ecosystem health-probe adapter contract with no hard dependency on WooCommerce.
- Runtime detection of active WooCommerce without loading or activating it from RevertShield.
- Read-only public WooCommerce Store API product-collection probe with a bounded `per_page=1` request.
- Automatic inclusion in manual, post-guarded-update, and post-recovery health decisions through the shared health suite.
- No authenticated WooCommerce REST API credentials, order/customer/payment access, cart mutation, checkout mutation, or telemetry.
- Deterministic runtime regression coverage for baseline, WooCommerce-active, and WooCommerce-failure behavior.

## Phase 5B - Remaining ecosystem adapters

- Multisite-aware storage and operations.
- WP-CLI commands.
- REST endpoints with explicit permission callbacks.
- Optional external notification adapters only if they can preserve RevertShield's local-first privacy model and explicit administrator control.
