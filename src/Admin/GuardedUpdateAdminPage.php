<?php
/**
 * Guarded plugin update administration screen.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotState;
use RevertShield\Update\GuardedPluginUpdateService;

/**
 * Provides authorized guarded plugin update operations.
 */
final class GuardedUpdateAdminPage {
	/** @var SnapshotRepository */
	private $snapshots;

	/** @var GuardedPluginUpdateService */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository         $snapshots Snapshot repository.
	 * @param GuardedPluginUpdateService $service   Guarded update service.
	 */
	public function __construct( SnapshotRepository $snapshots, GuardedPluginUpdateService $service ) {
		$this->snapshots = $snapshots;
		$this->service   = $service;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_revertshield_guarded_plugin_update', array( $this, 'handle_update' ) );
	}

	/**
	 * Register the guarded update screen.
	 *
	 * @return void
	 */
	public function menu() {
		add_management_page(
			__( 'RevertShield Guarded Updates', 'revertshield' ),
			__( 'RevertShield Updates', 'revertshield' ),
			'update_plugins',
			'revertshield-updates',
			array( $this, 'render' )
		);
	}

	/**
	 * Load shared RevertShield admin styles.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		if ( 'tools_page_revertshield-updates' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'revertshield-admin',
			REVERTSHIELD_URL . 'assets/css/admin.css',
			array(),
			REVERTSHIELD_VERSION
		);
	}

	/**
	 * Execute one nonce-protected guarded plugin update.
	 *
	 * @return void
	 */
	public function handle_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to update plugins.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_guarded_plugin_update' );

		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';
		$snapshot_uuid = isset( $_POST['snapshot_uuid'] )
			? sanitize_text_field( wp_unslash( $_POST['snapshot_uuid'] ) )
			: '';

		$result = $this->service->execute( $plugin_file, $snapshot_uuid );
		if ( is_wp_error( $result ) ) {
			$this->redirect( 'failed', $result->get_error_code() );
		}

		$status = 'pass' === $result['health_status'] ? 'healthy' : 'unhealthy';
		$this->redirect( $status, '' );
	}

	/**
	 * Render the guarded plugin update screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates   = get_plugin_updates();
		$snapshots = $this->snapshots->recent( 100 );
		$self      = plugin_basename( REVERTSHIELD_FILE );

		if ( isset( $updates[ $self ] ) ) {
			unset( $updates[ $self ] );
		}

		uasort(
			$updates,
			static function ( $left, $right ) {
				$left_name  = isset( $left->Name ) ? $left->Name : '';
				$right_name = isset( $right->Name ) ? $right->Name : '';
				return strcasecmp( $left_name, $right_name );
			}
		);
		?>
		<div class="wrap revertshield-wrap">
			<div class="revertshield-hero">
				<div>
					<h1><?php echo esc_html__( 'Guarded Plugin Updates', 'revertshield' ); ?></h1>
					<p><?php echo esc_html__( 'Run a WordPress plugin update only when a matching RevertShield snapshot is ready, unexpired, and independently verified. A homepage health check runs after a successful update.', 'revertshield' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=revertshield-snapshots' ) ); ?>"><?php echo esc_html__( 'Verified Snapshots', 'revertshield' ); ?></a>
			</div>

			<?php $this->render_notice(); ?>

			<div class="notice notice-warning inline"><p>
				<strong><?php echo esc_html__( 'Recovery is not automatic in this release.', 'revertshield' ); ?></strong>
				<?php echo esc_html__( 'If the post-update health check fails, RevertShield records the unhealthy result and stops. It does not restore files automatically.', 'revertshield' ); ?>
			</p></div>

			<section class="revertshield-card revertshield-ledger">
				<div class="revertshield-ledger-header">
					<div>
						<h2><?php echo esc_html__( 'Available guarded updates', 'revertshield' ); ?></h2>
						<span class="revertshield-muted"><?php echo esc_html__( 'Only updates currently reported by WordPress are shown. Create a fresh verified snapshot before running an update.', 'revertshield' ); ?></span>
					</div>
				</div>
				<div class="table-responsive">
					<table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'Plugin', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Version', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Guarded action', 'revertshield' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $updates ) ) : ?>
							<tr><td colspan="3"><?php echo esc_html__( 'WordPress currently reports no plugin updates that RevertShield can run.', 'revertshield' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $updates as $plugin_file => $plugin ) : ?>
								<?php
								$eligible    = $this->eligible_snapshots( $snapshots, $plugin_file );
								$current     = isset( $plugin->Version ) ? sanitize_text_field( $plugin->Version ) : '';
								$new_version = isset( $plugin->update->new_version ) ? sanitize_text_field( $plugin->update->new_version ) : '';
								$name        = isset( $plugin->Name ) ? sanitize_text_field( $plugin->Name ) : $plugin_file;
								?>
								<tr>
									<td><strong><?php echo esc_html( $name ); ?></strong><br><span class="revertshield-muted"><code><?php echo esc_html( $plugin_file ); ?></code></span></td>
									<td><?php echo esc_html( $current ); ?> &rarr; <?php echo esc_html( $new_version ); ?></td>
									<td>
										<?php if ( empty( $eligible ) ) : ?>
											<p class="revertshield-muted"><?php echo esc_html__( 'No eligible snapshot. Create a new verified snapshot first.', 'revertshield' ); ?></p>
											<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=revertshield-snapshots' ) ); ?>"><?php echo esc_html__( 'Create Snapshot', 'revertshield' ); ?></a>
										<?php else : ?>
											<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="revertshield-guarded-update-form">
												<input type="hidden" name="action" value="revertshield_guarded_plugin_update">
												<input type="hidden" name="plugin_file" value="<?php echo esc_attr( $plugin_file ); ?>">
												<?php wp_nonce_field( 'revertshield_guarded_plugin_update' ); ?>
												<label class="screen-reader-text" for="revertshield-snapshot-<?php echo esc_attr( substr( $eligible[0]['snapshot_uuid'], 0, 8 ) ); ?>"><?php echo esc_html__( 'Verified snapshot', 'revertshield' ); ?></label>
												<select id="revertshield-snapshot-<?php echo esc_attr( substr( $eligible[0]['snapshot_uuid'], 0, 8 ) ); ?>" name="snapshot_uuid" required>
													<?php foreach ( $eligible as $snapshot ) : ?>
														<?php
														$label = sprintf(
															/* translators: 1: short snapshot ID, 2: snapshot creation date, 3: snapshot size. */
															__( '%1$s · %2$s · %3$s', 'revertshield' ),
															substr( $snapshot['snapshot_uuid'], 0, 8 ),
															get_date_from_gmt( $snapshot['created_at'], 'Y-m-d H:i:s' ),
															size_format( absint( $snapshot['size_bytes'] ), 1 )
														);
														?>
														<option value="<?php echo esc_attr( $snapshot['snapshot_uuid'] ); ?>"><?php echo esc_html( $label ); ?></option>
													<?php endforeach; ?>
												</select>
												<?php submit_button( __( 'Run Guarded Update', 'revertshield' ), 'primary', 'submit', false ); ?>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Filter recent snapshot metadata to eligible ready snapshots for a plugin.
	 *
	 * The final execution gate independently reloads and verifies the selected
	 * snapshot, so this UI filter is informational rather than authoritative.
	 *
	 * @param array  $snapshots   Recent snapshot rows.
	 * @param string $plugin_file Installed plugin basename.
	 * @return array
	 */
	private function eligible_snapshots( array $snapshots, $plugin_file ) {
		$eligible = array();
		$now      = time();

		foreach ( $snapshots as $snapshot ) {
			if (
				! isset( $snapshot['component_type'], $snapshot['component_name'], $snapshot['state'], $snapshot['snapshot_uuid'], $snapshot['expires_at'] ) ||
				'plugin' !== $snapshot['component_type'] ||
				SnapshotState::READY !== $snapshot['state'] ||
				wp_normalize_path( $plugin_file ) !== wp_normalize_path( $snapshot['component_name'] ) ||
				empty( $snapshot['expires_at'] ) ||
				strtotime( $snapshot['expires_at'] . ' UTC' ) <= $now
			) {
				continue;
			}

			$eligible[] = $snapshot;
		}

		return $eligible;
	}

	/**
	 * Render a short result notice after a protected admin action.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( ! isset( $_GET['rs_update'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after a nonce-protected admin action.
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['rs_update'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code   = isset( $_GET['rs_code'] ) ? sanitize_key( wp_unslash( $_GET['rs_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'healthy' === $status ) {
			$class   = 'notice-success';
			$message = __( 'Guarded plugin update completed and the homepage health check passed.', 'revertshield' );
		} elseif ( 'unhealthy' === $status ) {
			$class   = 'notice-error';
			$message = __( 'The plugin update completed, but the homepage health check failed. RevertShield recorded the incident and did not perform an automatic restore.', 'revertshield' );
		} else {
			$class   = 'notice-error';
			$message = __( 'The guarded plugin update did not complete. RevertShield stopped without attempting an automatic restore.', 'revertshield' );
		}
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible"><p>
			<?php echo esc_html( $message ); ?>
			<?php if ( '' !== $code ) : ?>
				<?php echo ' ' . esc_html( sprintf( /* translators: %s: internal error code. */ __( 'Error code: %s', 'revertshield' ), $code ) ); ?>
			<?php endif; ?>
		</p></div>
		<?php
	}

	/**
	 * Redirect back to the guarded update screen.
	 *
	 * @param string $status Result status.
	 * @param string $code   Optional error code.
	 * @return void
	 */
	private function redirect( $status, $code ) {
		$args = array(
			'page'      => 'revertshield-updates',
			'rs_update' => sanitize_key( $status ),
		);

		if ( '' !== $code ) {
			$args['rs_code'] = sanitize_key( $code );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}
}
