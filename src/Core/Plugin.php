<?php
/**
 * Main plugin coordinator.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Admin\AdminNavigation;
use RevertShield\Admin\AdminNoticeCenter;
use RevertShield\Admin\AdminPage;
use RevertShield\Admin\GuardedUpdateAdminPage;
use RevertShield\Admin\MultisiteNotice;
use RevertShield\Admin\RecoveryAdminPage;
use RevertShield\Admin\Settings;
use RevertShield\Admin\SnapshotAdminPage;
use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeObserver;
use RevertShield\Ledger\ChangeRepository;
use RevertShield\Policy\MaintenanceWindow;
use RevertShield\Recovery\PluginRecoveryService;
use RevertShield\Recovery\RecoveryEligibility;
use RevertShield\Snapshot\PluginSnapshotService;
use RevertShield\Snapshot\SnapshotCleanup;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Support\Cleanup;
use RevertShield\Support\MultisiteProvisioner;
use RevertShield\Update\GuardedUpdateBatchService;
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
		Activator::ensure_current_site();

		$repository           = new ChangeRepository();
		$health               = new HealthChecker();
		$snapshot_repository  = new SnapshotRepository();
		$safe_update_gate     = new SafeUpdateGate( $snapshot_repository );
		$maintenance_window   = new MaintenanceWindow();
		$guarded_update       = new GuardedPluginUpdateService( $safe_update_gate, null, $health, $repository, $maintenance_window );
		$guarded_batch        = new GuardedUpdateBatchService( $guarded_update, $repository );
		$recovery_eligibility = new RecoveryEligibility( $snapshot_repository );
		$recovery_service     = new PluginRecoveryService( $recovery_eligibility, $snapshot_repository, null, null, null, $health, $repository );

		( new ChangeObserver( $repository ) )->register();
		( new Settings() )->register();
		( new AdminNavigation() )->register();
		( new AdminNoticeCenter() )->register();
		( new MultisiteNotice() )->register();
		( new MultisiteProvisioner() )->register();
		( new AdminPage( $repository, $health ) )->register();
		( new SnapshotAdminPage( $snapshot_repository, new PluginSnapshotService(), $repository ) )->register();
		( new GuardedUpdateAdminPage( $snapshot_repository, $guarded_update, $guarded_batch, $maintenance_window ) )->register();
		( new RecoveryAdminPage( $snapshot_repository, $recovery_service ) )->register();
		( new Cleanup() )->register();
		( new SnapshotCleanup( $snapshot_repository ) )->register();
	}
}
