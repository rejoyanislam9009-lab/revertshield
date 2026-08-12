<?php
/**
 * Manual plugin recovery administration screen.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

use RevertShield\Recovery\PluginRecoveryService;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotState;

/**
 * Provides explicit administrator-controlled plugin recovery.
 */
final class RecoveryAdminPage {
	/** @var SnapshotRepository */
	private $snapshots;

	/** @var PluginRecoveryService */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository    $snapshots Snapshot repository.
	 * @param PluginRecoveryService $service   Plugin recovery service.
	 */
	public function __construct( SnapshotRepository $snapshots, PluginRecoveryService $service ) {
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
		add_action( 'admin_post_revertshield_plugin_recovery', array( $this, 'handle_recovery' ) );
	}

	/**
	 * Register the recovery screen.
	 *
	 * @return void
	 */
	public function menu() {
		add_management_page(
			__( 'RevertShield Recovery', 'revertshield' ),
			__( 'RevertShield Recovery', 'revertshield' ),
			'update_plugins',
			'revertshield-recovery',
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
		if ( 'tools_page_revertshield-recovery' !== $hook_suffix ) {
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
	 * Execute one explicit nonce-protected plugin recovery.
	 *
	 * @return void
	 */
	public function handle_recovery() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to restore plugin files.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_plugin_recovery' );

		$confirmed = isset( $_POST['confirm_recovery'] ) ? sanitize_key( wp_unslash( $_POST['confirm_recovery'] ) ) : '';
		if ( 'yes' !== $confirmed ) {
			$this->redirect( 'failed', 'revertshield_recovery_confirmation_required' );
		}

		$plugin_file   = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';
		$snapshot_uuid = isset( $_POST['snapshot_uuid'] )
			? sanitize_text_field( wp_unslash( $_POST['snapshot_uuid'] ) )
			: '';

		$locked = $this->acquire_lock();
		if ( is_wp_error( $locked ) ) {
			$this->redirect( 'failed', $locked->get_error_code() );
		}

		$result = $this->service->execute( $plugin_file, $snapshot_uuid );
		$this->release_lock();

		if ( is_wp_error( $result ) ) {
			$this->redirect( 'failed', $result->get_error_code() );
		}

		$status = 'pass' === $result['health_status'] ? 'healthy' : 'unhealthy';
		$this->redirect( $status, '' );
	}

	/**
	 * Render the recovery administration screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$snapshots = $this->eligible_rows( $this->snapshots->recent( 100 ) );
		?>
		<div class="wrap revertshield-wrap">
			<div class="revertshield-hero">
				<div>
					<h1><?php echo esc_html__( 'Plugin Recovery', 'revertshield' ); ?></h1>
					<p><?php echo esc_html__( 'Manually restore one installed plugin from a matching, unexpired, independently verified RevertShield snapshot.', 'revertshield' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=revertshield-snapshots' ) ); ?>"><?php echo esc_html__( 'Verified Snapshots', 'revertshield' ); ?></a>
			</div>

			<?php $this->render_notice(); ?>

			<div class="notice notice-warning inline"><p>
				<strong><?php echo esc_html__( 'Recovery replaces plugin files.', 'revertshield' ); ?></strong>
				<?php echo esc_html__( 'RevertShield verifies the selected snapshot again, stages and verifies every file, preserves the current plugin files during the transaction, verifies the restored plugin exactly, and then runs a homepage health check. A failed health check is recorded and does not trigger another automatic restore.', 'revertshield' ); ?>
			</p></div>

			<section class="revertshield-card revertshield-ledger">
				<div class="revertshield-ledger-header">
					<div>
						<h2><?php echo esc_html__( 'Eligible recovery snapshots', 'revertshield' ); ?></h2>
						<span class="revertshield-muted"><?php echo esc_html__( 'Only ready, unexpired plugin snapshots are listed. Integrity is checked again immediately before recovery.', 'revertshield' ); ?></span>
					</div>
				</div>
				<div class="table-responsive">
					<table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'Plugin', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Snapshot', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Created', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Expires', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Manual recovery', 'revertshield' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $snapshots ) ) : ?>
							<tr><td colspan="5">
								<?php echo esc_html__( 'No ready, unexpired plugin snapshots are currently eligible for manual recovery.', 'revertshield' ); ?>
								<a href="<?php echo esc_url( admin_url( 'tools.php?page=revertshield-snapshots' ) ); ?>"><?php echo esc_html__( 'Create a verified snapshot.', 'revertshield' ); ?></a>
							</td></tr>
						<?php else : ?>
							<?php foreach ( $snapshots as $snapshot ) : ?>
								<tr>
									<td><code><?php echo esc_html( $snapshot['component_name'] ); ?></code></td>
									<td><code><?php echo esc_html( $snapshot['snapshot_uuid'] ); ?></code></td>
									<td><?php echo esc_html( get_date_from_gmt( $snapshot['created_at'], 'Y-m-d H:i:s' ) ); ?></td>
									<td><?php echo esc_html( get_date_from_gmt( $snapshot['expires_at'], 'Y-m-d H:i:s' ) ); ?></td>
									<td>
										<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
											<input type="hidden" name="action" value="revertshield_plugin_recovery">
											<input type="hidden" name="plugin_file" value="<?php echo esc_attr( $snapshot['component_name'] ); ?>">
											<input type="hidden" name="snapshot_uuid" value="<?php echo esc_attr( $snapshot['snapshot_uuid'] ); ?>">
											<?php wp_nonce_field( 'revertshield_plugin_recovery' ); ?>
											<p><label><input type="checkbox" name="confirm_recovery" value="yes"> <?php echo esc_html__( 'I understand this will replace the plugin files with this snapshot.', 'revertshield' ); ?></label></p>
											<?php submit_button( __( 'Restore This Snapshot', 'revertshield' ), 'secondary', 'submit', false ); ?>
										</form>
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
	 * Keep only ready, unexpired plugin snapshots and exclude RevertShield itself.
	 *
	 * @param array $rows Snapshot rows.
	 * @return array
	 */
	private function eligible_rows( array $rows ) {
		$eligible = array();
		$self     = plugin_basename( REVERTSHIELD_FILE );
		$now      = time();

		foreach ( $rows as $row ) {
			if (
				! isset( $row['state'], $row['component_type'], $row['component_name'], $row['expires_at'] ) ||
				SnapshotState::READY !== $row['state'] ||
				'plugin' !== $row['component_type'] ||
				$self === $row['component_name'] ||
				empty( $row['expires_at'] ) ||
				strtotime( $row['expires_at'] . ' UTC' ) <= $now
			) {
				continue;
			}

			$eligible[] = $row;
		}

		return $eligible;
	}

	/**
	 * Acquire a short-lived global recovery lock.
	 *
	 * @return true|\WP_Error True when locked or an error if another recovery is active.
	 */
	private function acquire_lock() {
		$option   = 'revertshield_recovery_lock';
		$now      = time();
		$existing = absint( get_option( $option, 0 ) );

		if ( $existing > 0 && $existing < ( $now - 900 ) ) {
			delete_option( $option );
		}

		if ( ! add_option( $option, $now, '', false ) ) {
			return new \WP_Error(
				'revertshield_recovery_already_running',
				__( 'Another RevertShield recovery operation is already running.', 'revertshield' )
			);
		}

		return true;
	}

	/**
	 * Release the global recovery lock.
	 *
	 * @return void
	 */
	private function release_lock() {
		delete_option( 'revertshield_recovery_lock' );
	}

	/**
	 * Render a recovery result notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( ! isset( $_GET['rs_recovery'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after nonce-protected action.
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['rs_recovery'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code   = isset( $_GET['rs_code'] ) ? sanitize_key( wp_unslash( $_GET['rs_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'healthy' === $status ) {
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Plugin recovery completed, restored-file integrity passed, and the homepage health check passed.', 'revertshield' ); ?></p></div>
			<?php
			return;
		}

		if ( 'unhealthy' === $status ) {
			?>
			<div class="notice notice-warning is-dismissible"><p><?php echo esc_html__( 'Plugin recovery completed and restored-file integrity passed, but the homepage health check failed. RevertShield recorded the result and did not perform another automatic restore.', 'revertshield' ); ?></p></div>
			<?php
			return;
		}

		?>
		<div class="notice notice-error is-dismissible"><p>
			<?php echo esc_html__( 'Plugin recovery did not complete safely.', 'revertshield' ); ?>
			<?php if ( '' !== $code ) : ?>
				<code><?php echo esc_html( $code ); ?></code>
			<?php endif; ?>
		</p></div>
		<?php
	}

	/**
	 * Redirect back to recovery screen with a bounded status result.
	 *
	 * @param string $status Recovery result status.
	 * @param string $code   Optional normalized error code.
	 * @return void
	 */
	private function redirect( $status, $code ) {
		$args = array(
			'page'        => 'revertshield-recovery',
			'rs_recovery' => sanitize_key( $status ),
		);

		if ( '' !== $code ) {
			$args['rs_code'] = sanitize_key( $code );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}
}
