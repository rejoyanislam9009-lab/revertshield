# WordPress.org Release Checklist

Do not submit a development milestone merely to reserve a slug. Submit only when the plugin is complete enough for its stated directory purpose.

Before every directory release:

1. Confirm the display name and slug do not create trademark or project-name confusion.
2. Confirm every bundled file and asset is GPL-compatible.
3. Build the release ZIP from the distribution allowlist.
4. Run PHP syntax checks on all shipped PHP files.
5. Run the official WordPress Plugin Check action against the built directory.
6. Run WordPress Coding Standards checks.
7. Test activation, deactivation, upgrade, uninstall, and supported multisite behavior.
8. Test the declared minimum PHP and WordPress versions.
9. Verify all state-changing actions have capability and nonce checks.
10. Verify user-controlled input is sanitized or validated and dynamic output is escaped.
11. Verify external services, if any, are optional or necessary and documented in `readme.txt`.
12. Verify `readme.txt` is accurate, concise, and does not keyword-stuff or claim unimplemented features.
13. Verify `Tested up to` reflects an actually tested stable WordPress release.
14. Verify the main plugin version and `Stable tag` match.
15. Push only release-ready changes to WordPress.org SVN.
