<?php
/**
 * Database migrations.
 *
 * @package RevertShield
 */

namespace RevertShield\Database;

/**
 * Creates and upgrades RevertShield database tables.
 */
final class Migrator {
	const SCHEMA_VERSION = '2';

	/**
	 * Run migration when schema changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::SCHEMA_VERSION !== (string) get_option( 'revertshield_schema_version', '' ) ) {
			self::migrate();
		}
	}

	/**
	 * Create or update plugin tables.
	 *
	 * @return void
	 */
	public static function migrate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$changes         = Tables::changes();
		$health_runs     = Tables::health_runs();
		$snapshots       = Tables::snapshots();

		$sql_changes = "CREATE TABLE {$changes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(80) NOT NULL,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_name varchar(191) NOT NULL DEFAULT '',
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(40) NOT NULL DEFAULT 'core',
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY object_type (object_type),
			KEY actor_id (actor_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_health = "CREATE TABLE {$health_runs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			check_type varchar(80) NOT NULL,
			target varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL,
			http_code smallint(5) unsigned NOT NULL DEFAULT 0,
			duration_ms int(10) unsigned NOT NULL DEFAULT 0,
			message text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY check_type (check_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_snapshots = "CREATE TABLE {$snapshots} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snapshot_uuid char(36) NOT NULL,
			component_type varchar(40) NOT NULL,
			component_name varchar(191) NOT NULL,
			state varchar(30) NOT NULL DEFAULT 'preparing',
			storage_relpath varchar(255) NOT NULL DEFAULT '',
			manifest longtext NULL,
			size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			expires_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY snapshot_uuid (snapshot_uuid),
			KEY component (component_type, component_name),
			KEY state (state),
			KEY created_at (created_at),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		dbDelta( $sql_changes );
		dbDelta( $sql_health );
		dbDelta( $sql_snapshots );

		update_option( 'revertshield_schema_version', self::SCHEMA_VERSION, false );
	}
}
