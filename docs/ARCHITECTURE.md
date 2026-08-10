# Architecture

## Goal

RevertShield is a transactional change-safety layer for WordPress. It should identify important maintenance changes, verify site health, and eventually perform only scoped, component-aware recovery operations.

## Current modules

- `Core`: lifecycle and service coordination.
- `Database`: schema and table naming.
- `Ledger`: normalized change events with redacted context.
- `Health`: local health probes and persisted results.
- `Snapshot`: component inventory, integrity manifests, storage boundaries, and snapshot lifecycle metadata.
- `Admin`: settings and operational UI.
- `Support`: bounded retention cleanup.

## Data design

High-volume operational history is stored in dedicated indexed tables rather than a large serialized option.

### `wp_revertshield_changes`

Append-oriented event ledger. Core observers intentionally avoid storing option values or secrets.

### `wp_revertshield_health_runs`

Health probe results with status, HTTP response code, duration, and a short sanitized error message.

### `wp_revertshield_snapshots`

Snapshot lifecycle metadata. It stores a UUID, scoped component identifier, lifecycle state, uploads-relative storage path, integrity manifest, represented byte count, and timestamps. Absolute server paths are not persisted.

## Snapshot trust boundary

Snapshot preparation is deliberately fail-closed.

1. A component adapter resolves an installed WordPress component from WordPress-owned metadata rather than accepting an arbitrary filesystem path.
2. Canonical paths must remain inside the expected component root.
3. Symlinks are rejected until their restore semantics can be defined safely.
4. Every regular file in a prepared plugin inventory receives a SHA-256 digest and byte count.
5. Storage locations are derived from a generated UUID and remain under `wp-content/uploads/revertshield/snapshots/`.
6. Preflight checks verify the uploads boundary is writable and, when detectable, has adequate free disk space with a safety margin.
7. A snapshot cannot become `ready` unless its manifest can be encoded and its metadata transition is persisted successfully.

The current Phase 2 slice prepares inventory and metadata contracts. It does not yet expose restore behavior or claim that a file inventory alone is a recoverable snapshot.

## Recovery design rule

RevertShield will not implement generic SQL reversal. Recovery must be performed through component adapters that know what changed and what can be restored safely.

The architecture separates:

1. pre-flight collection,
2. snapshot creation,
3. change execution observation,
4. post-change health policy,
5. recovery eligibility,
6. explicit recovery execution,
7. recovery verification.

## Privacy

The WordPress.org build is local-first and has no telemetry by default. Any future third-party service must be optional, documented, consent-based, and materially provide the service functionality.
