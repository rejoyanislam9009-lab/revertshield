# Architecture

## Goal

RevertShield is a transactional change-safety layer for WordPress. It should identify important maintenance changes, verify site health, and eventually perform only scoped, component-aware recovery operations.

## Current modules

- `Core`: lifecycle and service coordination.
- `Database`: schema and table naming.
- `Ledger`: normalized change events with redacted context.
- `Health`: local health probes and persisted results.
- `Admin`: settings and operational UI.
- `Support`: bounded retention cleanup.

## Data design

High-volume operational history is stored in dedicated indexed tables rather than a large serialized option.

### `wp_revertshield_changes`

Append-oriented event ledger. Core observers intentionally avoid storing option values or secrets.

### `wp_revertshield_health_runs`

Health probe results with status, HTTP response code, duration, and a short sanitized error message.

## Recovery design rule

RevertShield will not implement generic SQL reversal. Recovery must be performed through component adapters that know what was changed and what can be restored safely.

Future contracts will separate:

1. pre-flight collection,
2. snapshot creation,
3. change execution observation,
4. post-change health policy,
5. recovery eligibility,
6. explicit recovery execution,
7. recovery verification.

## Privacy

The WordPress.org build is local-first and has no telemetry by default. Any future third-party service must be optional, documented, consent-based, and materially provide the service functionality.
