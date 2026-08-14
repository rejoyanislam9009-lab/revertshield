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

## Phase 5A - WooCommerce health adapter - complete in 0.7.0

- Optional ecosystem health-probe adapter contract with no hard dependency on WooCommerce.
- Runtime detection of active WooCommerce without loading or activating it from RevertShield.
- Read-only public WooCommerce Store API product-collection probe with a bounded `per_page=1` request.
- Automatic inclusion in manual, post-guarded-update, and post-recovery health decisions through the shared health suite.
- Non-WooCommerce sites retain the existing required homepage and WordPress REST probes.
- No authenticated WooCommerce REST API credentials, order/customer/payment access, cart mutation, checkout mutation, or telemetry.
- Deterministic regression coverage for baseline, WooCommerce-active, and WooCommerce-failure behavior.
- Real current WooCommerce install/activation and Store API integration coverage on the latest supported WordPress/PHP runtime boundary.

## Phase 5B - Multisite safety boundaries - complete in 0.8.0

- Explicit current site/network context for snapshot ownership decisions.
- Site-scoped snapshot manifest metadata binding origin blog and network identifiers.
- Explicit Multisite site namespace inside snapshot storage paths in addition to WordPress uploads isolation.
- Independent verification fails closed when a snapshot belongs to another site/network context.
- Legacy single-site snapshots remain usable on single-site WordPress; snapshots without Multisite ownership metadata require fresh capture after Multisite enablement.
- Existing network-activated sites self-heal RevertShield schema/defaults on first load.
- Newly initialized Multisite sites receive RevertShield schema/defaults and cleanup schedules through `wp_initialize_site`.
- Guarded plugin updates and plugin-file recovery remain disabled on Multisite until RevertShield can validate post-change health across the affected network boundary; snapshots and local observation remain available.
- Persistent admin messaging explains the Multisite safety mode instead of implying unsupported shared-file recovery.
- Real WordPress Multisite runtime regression coverage for provisioning, storage isolation, snapshot ownership, cross-site rejection, and mutation fail-closed behavior.
- No network-wide automatic rollback or generic database rollback.

## Phase 6 - Operations and observability - in progress for 0.9.0

- Operations admin surface layered on the existing 0.8.0 safety baseline.
- Administrator-controlled snapshot pin/protect state without rewriting verified manifests or content-addressed objects.
- Original snapshot expiration preserved in a bounded site-scoped registry and restored on explicit unpin.
- Optional scheduled local health checks through WordPress Cron at bounded 1, 6, 12, or 24 hour cadences, disabled by default.
- Scheduled health execution reuses the existing multi-probe local health suite and persistence path.
- Read-only WP-CLI commands for operational status, persisted health state, recent snapshot metadata, snapshot inspection, and optional integrity verification.
- No WP-CLI guarded-update, recovery, pin/unpin, or rollback mutation in 0.9.0.
- Minimum/latest real WordPress operations regression coverage.
- Stable 0.8.0 to 0.9.0 in-place upgrade coverage with state-preservation assertions.

## Phase 7 - Remaining ecosystem/control adapters

- Network-scoped guarded plugin update/recovery only after a bounded network-wide health contract is defined and verified.
- Read-only REST endpoints with explicit permission callbacks before any mutation surface is considered.
- Optional external notification adapters only if they preserve RevertShield's local-first privacy model and explicit administrator control.
- Additional ecosystem health adapters through the bounded read-only probe contract.
