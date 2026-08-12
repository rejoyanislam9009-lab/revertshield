<?php
/**
 * Multisite safety-mode administration notice.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

/**
 * Explains the intentionally bounded Multisite behavior on RevertShield pages.
 */
final class MultisiteNotice {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Render a persistent warning only on RevertShield screens.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! is_multisite() || ! $this->is_revertshield_screen() ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html__( 'RevertShield Multisite safety mode:', 'revertshield' ); ?></strong>
				<?php echo esc_html__( 'Health history and snapshots remain site-scoped. Guarded plugin updates and plugin-file recovery are disabled because installed plugin files are shared across the network and network-wide health validation is not available yet.', 'revertshield' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Whether the current admin screen belongs to RevertShield.
	 *
	 * @return bool
	 */
	private function is_revertshield_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		return in_array(
			$screen->id,
			array(
				'tools_page_revertshield',
				'tools_page_revertshield-snapshots',
				'tools_page_revertshield-updates',
				'tools_page_revertshield-recovery',
			),
			true
		);
	}
}
