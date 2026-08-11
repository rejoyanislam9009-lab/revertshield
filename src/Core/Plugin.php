<?php
/**
 * Main plugin coordinator.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Admin\AdminPage;
use RevertShield\Admin\GuardedUpdateAdminPage;
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
use RevertShield\Update\GuardedPluginUpdateService;
use RevertShield\Update\SafeUpdateGate;

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
		$safe_update_gate    = new SafeUpdateGate( $snapshot_repository );
		$guarded_update      = new GuardedPluginUpdateService( $safe_update_gate, null, $health, $repository );

		( new ChangeObserver( $repository ) )->register();
		( new Settings() )->register();
		( new AdminPage( $repository, $health ) )->register();
		( new SnapshotAdminPage( $snapshot_repository, new PluginSnapshotService(), $repository ) )->register();
		( new GuardedUpdateAdminPage( $snapshot_repository, $guarded_update ) )->register();
		( new Cleanup() )->register();
		( new SnapshotCleanup( $snapshot_repository ) )->register();
	}
}
