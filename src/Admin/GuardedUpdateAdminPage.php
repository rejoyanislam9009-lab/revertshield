<?php
/**
 * Guarded plugin update administration screen.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

use RevertShield\Policy\MaintenanceWindow;
use RevertShield\Snapshot\SnapshotRepository;
use RevertShield\Snapshot\SnapshotState;
use RevertShield\Update\GuardedPluginUpdateService;
use RevertShield\Update\GuardedUpdateBatchService;

/**
 * Provides authorized guarded plugin update operations.
 */
final class GuardedUpdateAdminPage {
	/** @var SnapshotRepository */
	private $snapshots;

	/** @var GuardedPluginUpdateService */
	private $service;

	/** @var GuardedUpdateBatchService */
	private $batch;

	/** @var MaintenanceWindow */
	private $maintenance_window;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository              $snapshots          Snapshot repository.
	 * @param GuardedPluginUpdateService      $service            Guarded update service.
	 * @param GuardedUpdateBatchService|null  $batch              Optional guarded batch service.
	 * @param MaintenanceWindow|null          $maintenance_window Optional maintenance-window policy.
	 */
	public function __construct(
		SnapshotRepository $snapshots,
		GuardedPluginUpdateService $service,
		GuardedUpdateBatchService $batch = null,
		MaintenanceWindow $maintenance_window = null
	) {
		$this->snapshots          = $snapshots;
		$this->service            = $service;
		$this->batch              = $batch ? $batch : new GuardedUpdateBatchService( $service );
		$this->maintenance_window = $maintenance_window ? $maintenance_window : new MaintenanceWindow();
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
		add_action( 'admin_post_revertshield_guarded_plugin_batch', array( $this, 'handle_batch' ) );
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

		$plugin_file   = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';
		$snapshot_uuid = isset( $_POST['snapshot_uuid'] )
			? sanitize_text_field( wp_unslash( $_POST['snapshot_uuid'] ) )
			: '';

		$result = $this->service->execute( $plugin_file, $snapshot_uuid );
		if ( is_wp_error( $result ) ) {
			$this->redirect( 'failed', $result->get_error_code() );
		}

		$status    = 'pass' === $result['health_status'] ? 'healthy' : 'unhealthy';
		$recommend = ! empty( $result['recovery_recommended'] ) ? $snapshot_uuid : '';
		$this->redirect( $status, '', 0, $recommend );
	}

	/**
	 * Execute a bounded guarded update batch.
	 *
	 * @return void
	 */
	public function handle_batch() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to update plugins.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_guarded_plugin_batch' );

		$raw_items = array();
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested batch values are unslashed and sanitized immediately before use.
		if ( isset( $_POST['batch_items'] ) && is_array( $_POST['batch_items'] ) ) {
			$raw_items = wp_unslash( $_POST['batch_items'] );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$items = array();
		foreach ( array_slice( $raw_items, 0, 20 ) as $raw_item ) {
			if ( ! is_array( $raw_item ) || empty( $raw_item['selected'] ) ) {
				continue;
			}

			$items[] = array(
				'plugin_file'   => isset( $raw_item['plugin_file'] ) ? sanitize_text_field( $raw_item['plugin_file'] ) : '',
				'snapshot_uuid' => isset( $raw_item['snapshot_uuid'] ) ? sanitize_text_field( $raw_item['snapshot_uuid'] ) : '',
			);
		}

		$result = $this->batch->execute( $items );
		if ( is_wp_error( $result ) ) {
			$this->redirect( 'batch-failed', $result->get_error_code() );
		}

		if ( 'complete' === $result['status'] ) {
			$this->redirect( 'batch-complete', '', absint( $result['completed_count'] ) );
		}

		$recommend = ! empty( $result['recovery_recommended'] ) ? sanitize_text_field( $result['recovery_snapshot'] ) : '';
		$this->redirect( 'batch-paused', sanitize_key( $result['error_code'] ), absint( $result['completed_count'] ), $recommend );
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

		$updates    = get_plugin_updates();
		$snapshots  = $this->snapshots->recent( 100 );
		$self       = plugin_basename( REVERTSHIELD_FILE );
		$window     = $this->maintenance_window->status();
		$batch_rows = array();

		if ( isset( $updates[ $self ] ) ) {
			unset( $updates[ $self ] );
		}

		uasort(
			$updates,
			static function ( $left, $right ) {
				$left_data  = (array) $left;
				$right_data = (array) $right;
				$left_name  = isset( $left_data['Name'] ) ? $left_data['Name'] : '';
				$right_name = isset( $right_data['Name'] ) ? $right_data['Name'] : '';
				return strcasecmp( $left_name, $right_name );
			}
		);

		foreach ( $updates as $plugin_file => $plugin ) {
			$eligible = $this->eligible_snapshots( $snapshots, $plugin_file );
			if ( empty( $eligible ) ) {
				continue;
			}

			$batch_rows[] = array(
				'plugin_file' => $plugin_file,
				'plugin'      => (array) $plugin,
				'snapshots'   => $eligible,
			);
		}
		?>
		<div class="wrap revertshield-wrap">
			<div class="revertshield-hero">
				<div>
					<h1><?php echo esc_html__( 'Guarded Plugin Updates', 'revertshield' ); ?></h1>
					<p><?php echo esc_html__( 'Run WordPress plugin updates only with matching verified snapshots. RevertShield revalidates each update offer and runs the multi-probe site-health suite after every successful update.', 'revertshield' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=revertshield-snapshots' ) ); ?>"><?php echo esc_html__( 'Verified Snapshots', 'revertshield' ); ?></a>
			</div>

			<?php $this->render_notice(); ?>

			<div class="notice notice-warning inline"><p>
				<strong><?php echo esc_html__( 'Recovery remains explicit and manual.', 'revertshield' ); ?></strong>
				<?php echo esc_html__( 'If the site-health suite fails, RevertShield stops the current batch, records the result, and may recommend an independently verified recovery snapshot. It never starts recovery automatically.', 'revertshield' ); ?>
			</p></div>

			<div class="notice <?php echo ! empty( $window['enabled'] ) && empty( $window['allowed'] ) ? 'notice-warning' : 'notice-info'; ?> inline"><p>
				<?php if ( empty( $window['enabled'] ) ) : ?>
					<?php echo esc_html__( 'Maintenance window policy is disabled. Guarded updates may run at any time.', 'revertshield' ); ?>
				<?php else : ?>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: start time, 2: end time, 3: open/closed state. */
							__( 'Maintenance window: %1$s–%2$s. The window is currently %3$s.', 'revertshield' ),
							$window['start'],
							$window['end'],
							! empty( $window['allowed'] ) ? __( 'open', 'revertshield' ) : __( 'closed', 'revertshield' )
						)
					);
					?>
				<?php endif; ?>
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
								$plugin_data = (array) $plugin;
								$update_data = isset( $plugin_data['update'] ) ? (array) $plugin_data['update'] : array();
								$eligible    = $this->eligible_snapshots( $snapshots, $plugin_file );
								$current     = isset( $plugin_data['Version'] ) ? sanitize_text_field( $plugin_data['Version'] ) : '';
								$new_version = isset( $update_data['new_version'] ) ? sanitize_text_field( $update_data['new_version'] ) : '';
								$name        = isset( $plugin_data['Name'] ) ? sanitize_text_field( $plugin_data['Name'] ) : $plugin_file;
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
														<?php $label = $this->snapshot_label( $snapshot ); ?>
														<option value="<?php echo esc_attr( $snapshot['snapshot_uuid'] ); ?>"><?php echo esc_html( $label ); ?></option>
													<?php endforeach; ?>
												</select>
												<?php submit_button( __( 'Run Guarded Update', 'revertshield' ), 'primary', 'submit', false, empty( $window['allowed'] ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
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

			<section class="revertshield-card revertshield-ledger revertshield-batch-card">
				<div class="revertshield-ledger-header">
					<div>
						<h2><?php echo esc_html__( 'Pause-on-failure guarded batch', 'revertshield' ); ?></h2>
						<span class="revertshield-muted"><?php echo esc_html__( 'Select up to 20 eligible updates. RevertShield runs them sequentially and pauses immediately on the first failure or unhealthy site-health result.', 'revertshield' ); ?></span>
					</div>
				</div>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="revertshield_guarded_plugin_batch">
				<?php wp_nonce_field( 'revertshield_guarded_plugin_batch' ); ?>
				<div class="table-responsive">
					<table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'Run', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Plugin', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Verified snapshot', 'revertshield' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $batch_rows ) ) : ?>
							<tr><td colspan="3"><?php echo esc_html__( 'No currently reported update has an eligible verified snapshot for batch execution.', 'revertshield' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $batch_rows as $index => $row ) : ?>
								<?php $name = isset( $row['plugin']['Name'] ) ? sanitize_text_field( $row['plugin']['Name'] ) : $row['plugin_file']; ?>
								<tr>
									<td><input type="checkbox" name="batch_items[<?php echo esc_attr( (string) $index ); ?>][selected]" value="1" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plugin name. */ __( 'Include %s in guarded batch', 'revertshield' ), $name ) ); ?>"></td>
									<td><strong><?php echo esc_html( $name ); ?></strong><input type="hidden" name="batch_items[<?php echo esc_attr( (string) $index ); ?>][plugin_file]" value="<?php echo esc_attr( $row['plugin_file'] ); ?>"></td>
									<td>
										<select name="batch_items[<?php echo esc_attr( (string) $index ); ?>][snapshot_uuid]">
											<?php foreach ( $row['snapshots'] as $snapshot ) : ?>
												<option value="<?php echo esc_attr( $snapshot['snapshot_uuid'] ); ?>"><?php echo esc_html( $this->snapshot_label( $snapshot ) ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
				<?php if ( ! empty( $batch_rows ) ) : ?>
					<div class="revertshield-batch-submit">
						<?php submit_button( __( 'Run Selected Guarded Updates', 'revertshield' ), 'primary', 'submit', false, empty( $window['allowed'] ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
					</div>
				<?php endif; ?>
			</form>
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
	 * Create a bounded snapshot selector label.
	 *
	 * @param array $snapshot Snapshot metadata.
	 * @return string
	 */
	private function snapshot_label( array $snapshot ) {
		return sprintf(
			/* translators: 1: short snapshot ID, 2: snapshot creation date, 3: snapshot size. */
			__( '%1$s · %2$s · %3$s', 'revertshield' ),
			substr( $snapshot['snapshot_uuid'], 0, 8 ),
			get_date_from_gmt( $snapshot['created_at'], 'Y-m-d H:i:s' ),
			size_format( absint( $snapshot['size_bytes'] ), 1 )
		);
	}

	/**
	 * Render a short result notice after a protected admin action.
	 *
	 * @return void
	 */
	private function render_notice() {
		$status    = isset( $_GET['rs_update'] ) ? sanitize_key( wp_unslash( $_GET['rs_update'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after a nonce-protected admin action.
		$batch     = isset( $_GET['rs_batch'] ) ? sanitize_key( wp_unslash( $_GET['rs_batch'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code      = isset( $_GET['rs_code'] ) ? sanitize_key( wp_unslash( $_GET['rs_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count     = isset( $_GET['rs_completed'] ) ? absint( wp_unslash( $_GET['rs_completed'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$recommend = isset( $_GET['rs_recovery_snapshot'] ) ? sanitize_text_field( wp_unslash( $_GET['rs_recovery_snapshot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $status ) {
			if ( 'healthy' === $status ) {
				$notice_class = 'notice-success';
				$message      = __( 'Guarded plugin update completed and the site-health suite passed.', 'revertshield' );
			} elseif ( 'unhealthy' === $status ) {
				$notice_class = 'notice-error';
				$message      = __( 'The plugin update completed, but the site-health suite failed. RevertShield recorded the incident and did not perform an automatic restore.', 'revertshield' );
			} else {
				$notice_class = 'notice-error';
				$message      = __( 'The guarded plugin update did not complete. RevertShield stopped without attempting an automatic restore.', 'revertshield' );
			}
			$this->notice_markup( $notice_class, $message, $code, $recommend );
		}

		if ( '' !== $batch ) {
			if ( 'complete' === $batch ) {
				$this->notice_markup(
					'notice-success',
					sprintf(
						/* translators: %d: number of completed guarded updates. */
						_n( 'Guarded batch completed %d update and all health checks passed.', 'Guarded batch completed %d updates and all health checks passed.', $count, 'revertshield' ),
						$count
					),
					'',
					''
				);
			} elseif ( 'paused' === $batch ) {
				$this->notice_markup(
					'notice-warning',
					sprintf(
						/* translators: %d: number of completed guarded updates. */
						__( 'Guarded batch paused after %d completed update(s). No later update in the batch was started.', 'revertshield' ),
						$count
					),
					$code,
					$recommend
				);
			} else {
				$this->notice_markup( 'notice-error', __( 'The guarded batch could not start safely.', 'revertshield' ), $code, '' );
			}
		}
	}

	/**
	 * Output one managed RevertShield notice.
	 *
	 * @param string $notice_class WordPress notice class.
	 * @param string $message      User-facing message.
	 * @param string $code         Optional error code.
	 * @param string $recommend    Optional recommended recovery snapshot UUID.
	 * @return void
	 */
	private function notice_markup( $notice_class, $message, $code, $recommend ) {
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible"><p>
			<?php echo esc_html( $message ); ?>
			<?php if ( '' !== $code ) : ?>
				<?php echo ' ' . esc_html( sprintf( /* translators: %s: internal error code. */ __( 'Error code: %s', 'revertshield' ), $code ) ); ?>
			<?php endif; ?>
			<?php if ( '' !== $recommend ) : ?>
				<?php
				$url = add_query_arg(
					array(
						'page'                 => 'revertshield-recovery',
						'rs_recommend'         => '1',
						'rs_recovery_snapshot' => $recommend,
					),
					admin_url( 'tools.php' )
				);
				?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Review recommended recovery snapshot', 'revertshield' ); ?></a>
			<?php endif; ?>
		</p></div>
		<?php
	}

	/**
	 * Redirect back to the guarded update screen.
	 *
	 * @param string $status    Result status.
	 * @param string $code      Optional error code.
	 * @param int    $completed Optional completed batch count.
	 * @param string $recommend Optional recommended recovery snapshot UUID.
	 * @return void
	 */
	private function redirect( $status, $code, $completed = 0, $recommend = '' ) {
		$is_batch = 0 === strpos( $status, 'batch-' );
		$args     = array( 'page' => 'revertshield-updates' );

		if ( $is_batch ) {
			$args['rs_batch']     = str_replace( 'batch-', '', sanitize_key( $status ) );
			$args['rs_completed'] = absint( $completed );
		} else {
			$args['rs_update'] = sanitize_key( $status );
		}

		if ( '' !== $code ) {
			$args['rs_code'] = sanitize_key( $code );
		}

		if ( '' !== $recommend ) {
			$args['rs_recommend']         = '1';
			$args['rs_recovery_snapshot'] = sanitize_text_field( $recommend );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}
}
