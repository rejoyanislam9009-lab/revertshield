<?php
/**
 * Main plugin coordinator.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Admin\AdminPage;
use RevertShield\Admin\Settings;
use RevertShield\Admin\SnapshotAdminPage;
use RevertShield\Database\Migrator;
use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeObserver;
use RevertShield\Ledger\ChangeRepository;
use RevertShield\Snapshot\PluginSnapshotService;
use RevertShield\Snapshot\SnapshotCleanup;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Support\Cleanup;

/**
 * Coordinates RevertShield runtime services.
 */
final class Plugin {
	/**
	 * Boot plugin services.
	 *
	 * @return void
	 */
	public function boot() {
		Migrator::maybe_upgrade();

		$repository          = new ChangeRepository();
		$health              = new HealthChecker();
		$snapshot_repository = new SnapshotRepository();

		( new ChangeObserver( $repository ) )->register();
		( new Settings() )->register();
		( new AdminPage( $repository, $health ) )->register();
		( new SnapshotAdminPage( $snapshot_repository, new PluginSnapshotService(), $repository ) )->register();
		( new Cleanup() )->register();
		( new SnapshotCleanup( $snapshot_repository ) )->register();
	}
}
