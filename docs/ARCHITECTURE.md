# Architecture

## Goal

RevertShield is a transactional change-safety layer for WordPress. It should identify important maintenance changes, verify site health, and eventually perform only scoped, component-aware recovery operations.

## Current modules

- `Core`: lifecycle and service coordination.
- `Database`: schema and table naming.
- `Ledger`: normalized change events with redacted context.
- `Health`: local health probes and persisted results.
- `Snapshot`: component resolution, inventory, integrity manifests, storage boundaries, content-addressed objects, and snapshot lifecycle metadata.
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

Snapshot creation is deliberately fail-closed.

1. A component locator resolves an installed WordPress plugin from `get_plugins()` rather than accepting an arbitrary filesystem path.
2. Canonical source paths must remain inside the expected plugin component root.
3. Symlinks are rejected until their recovery semantics can be defined safely.
4. Every regular source file receives a SHA-256 digest and byte count before materialization.
5. Preflight verifies that uploads storage is writable and, when detectable, has enough free disk space with a safety margin.
6. Storage locations are derived from a generated UUID and remain below `wp-content/uploads/revertshield/snapshots/`.
7. Original plugin filenames are not copied into uploads. File bytes are stored as extensionless content-addressed objects named `objects/<sha256>`.
8. Each source file is re-hashed immediately before copy. If it changed after inventory, snapshot creation fails.
9. Every copied object is re-hashed after copy. A mismatch fails the snapshot and removes the partial snapshot directory.
10. The manifest is written through a same-directory temporary file and committed by move, then its stored contents are re-read and verified.
11. Apache/IIS deny rules and an index guard are written at the RevertShield uploads root as defense in depth; extensionless object naming is the primary execution-safety boundary.
12. Snapshot metadata can transition from `preparing` to `ready` only after storage materialization succeeds. Failed materialization moves the metadata to `failed` and removes partial storage.

Phase 2 intentionally does not expose restore behavior. A verified snapshot is a prerequisite for recovery, not proof that restoration is safe for every component or runtime state.

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
