<?php
/**
 * Main plugin coordinator.
 *
 * @package RevertShield
 */

namespace RevertShield\Core;

use RevertShield\Admin\AdminPage;
use RevertShield\Admin\Settings;
use RevertShield\Database\Migrator;
use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeObserver;
use RevertShield\Ledger\ChangeRepository;
use RevertShield\Support\Cleanup;

final class Plugin {
	/**
	 * Boot plugin services.
	 *
	 * @return void
	 */
	public function boot() {
		Migrator::maybe_upgrade();

		$repository = new ChangeRepository();
		$health     = new HealthChecker();

		( new ChangeObserver( $repository ) )->register();
		( new Settings() )->register();
		( new AdminPage( $repository, $health ) )->register();
		( new Cleanup() )->register();
	}
}
