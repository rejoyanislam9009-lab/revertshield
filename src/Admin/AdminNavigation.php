<?php
/**
 * Shared WordPress-native administration navigation.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

/**
 * Renders capability-aware navigation across RevertShield admin screens.
 */
final class AdminNavigation {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ), 1 );
	}

	/**
	 * Render native WordPress nav tabs on RevertShield screens only.
	 *
	 * @return void
	 */
	public function render() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$tabs = array(
			array(
				'screen_id'  => 'tools_page_revertshield',
				'page'       => 'revertshield',
				'label'      => __( 'Dashboard', 'revertshield' ),
				'capability' => 'manage_options',
			),
			array(
				'screen_id'  => 'tools_page_revertshield-snapshots',
				'page'       => 'revertshield-snapshots',
				'label'      => __( 'Snapshots', 'revertshield' ),
				'capability' => 'update_plugins',
			),
			array(
				'screen_id'  => 'tools_page_revertshield-updates',
				'page'       => 'revertshield-updates',
				'label'      => __( 'Updates', 'revertshield' ),
				'capability' => 'update_plugins',
			),
			array(
				'screen_id'  => 'tools_page_revertshield-recovery',
				'page'       => 'revertshield-recovery',
				'label'      => __( 'Recovery', 'revertshield' ),
				'capability' => 'update_plugins',
			),
			array(
				'screen_id'  => 'tools_page_revertshield-operations',
				'page'       => 'revertshield-operations',
				'label'      => __( 'Operations', 'revertshield' ),
				'capability' => 'update_plugins',
			),
		);

		$screen_ids = wp_list_pluck( $tabs, 'screen_id' );
		if ( ! in_array( $screen->id, $screen_ids, true ) ) {
			return;
		}
		?>
		<div class="wrap revertshield-admin-navigation">
			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php echo esc_attr__( 'RevertShield navigation', 'revertshield' ); ?>">
				<?php foreach ( $tabs as $tab ) : ?>
					<?php if ( ! current_user_can( $tab['capability'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<?php $is_active = $screen->id === $tab['screen_id']; ?>
					<a
						class="nav-tab<?php echo $is_active ? ' nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'tools.php?page=' . $tab['page'] ) ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static accessibility attribute. ?>
					>
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}
}
