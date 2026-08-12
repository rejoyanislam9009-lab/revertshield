<?php
/**
 * RevertShield-scoped admin notice management.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

/**
 * Enqueues the notice manager only on RevertShield administration screens.
 */
final class AdminNoticeCenter {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Load the RevertShield notice manager on RevertShield screens only.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		$hooks = array(
			'tools_page_revertshield',
			'tools_page_revertshield-snapshots',
			'tools_page_revertshield-updates',
			'tools_page_revertshield-recovery',
		);

		if ( ! in_array( $hook_suffix, $hooks, true ) ) {
			return;
		}

		wp_enqueue_script(
			'revertshield-admin-notices',
			REVERTSHIELD_URL . 'assets/js/admin-notices.js',
			array(),
			REVERTSHIELD_VERSION,
			true
		);
	}
}
