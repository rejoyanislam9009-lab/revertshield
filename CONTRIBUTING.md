# Contributing

RevertShield is developed with WordPress.org distribution requirements in mind.

## Rules

- Keep code human-readable.
- Use WordPress APIs and bundled libraries where practical.
- Sanitize input early, validate expected values, and escape output late.
- Require capabilities and nonces for state-changing admin actions.
- Never add telemetry without explicit user consent and clear documentation.
- Never add remote executable code, obfuscated code, or hidden local premium functionality to the WordPress.org build.
- Add or update tests for behavior changes when the relevant test harness exists.
- Keep WordPress.org SVN for releases rather than development commits.
