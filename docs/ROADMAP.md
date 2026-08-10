# Roadmap

## Phase 1 - Foundation

- Plugin bootstrap and lifecycle.
- Dedicated indexed tables.
- Change ledger.
- Plugin/theme/core maintenance observers.
- Local homepage health check.
- Retention and uninstall controls.
- GitHub CI and WordPress Plugin Check.

## Phase 2 - Snapshot contracts

- Snapshot interfaces and immutable manifest format.
- Plugin metadata capture before updates.
- Filesystem storage boundary and disk-space preflight.
- Snapshot retention policies.
- Checksums and integrity verification.

## Phase 3 - Safe plugin update flow

- Opt-in update guard for selected plugins.
- Pre-update snapshot.
- Post-update homepage and REST health policies.
- Failed-health incident record.
- Manual scoped restore for supported plugin updates.

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
