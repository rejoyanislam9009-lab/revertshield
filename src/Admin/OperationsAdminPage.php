<?php
/**
 * Operations and observability administration screen.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

use RevertShield\Health\ScheduledHealthCheck;
use RevertShield\Snapshot\SnapshotPinStore;
use RevertShield\Snapshot\SnapshotRepository;

/**
 * Provides scheduled-health and snapshot-protection controls.
 */
final class OperationsAdminPage {
	/** @var SnapshotRepository */
	private $snapshots;

	/** @var SnapshotPinStore */
	private $pins;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository $snapshots Snapshot repository.
	 * @param SnapshotPinStore   $pins      Snapshot pin registry.
	 */
	public function __construct( SnapshotRepository $snapshots, SnapshotPinStore $pins ) {
		$this->snapshots = $snapshots;
		$this->pins      = $pins;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_revertshield_snapshot_pin', array( $this, 'handle_snapshot_pin' ) );
		add_action( 'admin_post_revertshield_health_schedule', array( $this, 'handle_health_schedule' ) );
	}

	/**
	 * Add the operations screen.
	 *
	 * @return void
	 */
	public function menu() {
		add_management_page(
			__( 'RevertShield Operations', 'revertshield' ),
			__( 'RevertShield Operations', 'revertshield' ),
			'update_plugins',
			'revertshield-operations',
			array( $this, 'render' )
		);
	}

	/**
	 * Load shared styles on the operations screen.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		if ( 'tools_page_revertshield-operations' !== $hook_suffix ) {
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
	 * Pin or unpin a snapshot after explicit administrator authorization.
	 *
	 * @return void
	 */
	public function handle_snapshot_pin() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to protect snapshots.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_snapshot_pin' );

		$snapshot_uuid = isset( $_POST['snapshot_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot_uuid'] ) ) : '';
		$mode          = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$result        = 'unpin' === $mode ? $this->pins->unpin( $snapshot_uuid ) : $this->pins->pin( $snapshot_uuid );

		$args = array(
			'page'   => 'revertshield-operations',
			'rs_pin' => is_wp_error( $result ) ? 'fail' : ( 'unpin' === $mode ? 'unpinned' : 'pinned' ),
		);
		if ( is_wp_error( $result ) ) {
			$args['rs_code'] = sanitize_key( $result->get_error_code() );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Save scheduled-health settings and reconcile WordPress Cron.
	 *
	 * @return void
	 */
	public function handle_health_schedule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change scheduled health checks.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_health_schedule' );

		$interval                              = isset( $_POST['scheduled_health_interval'] ) ? absint( wp_unslash( $_POST['scheduled_health_interval'] ) ) : 24;
		$interval                              = in_array( $interval, array( 1, 6, 12, 24 ), true ) ? $interval : 24;
		$enabled                               = isset( $_POST['scheduled_health_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['scheduled_health_enabled'] ) ) ? 1 : 0;
		$settings                              = get_option( 'revertshield_settings', array() );
		$settings                              = is_array( $settings ) ? $settings : array();
		$settings['scheduled_health_enabled']  = $enabled;
		$settings['scheduled_health_interval'] = $interval;
		update_option( 'revertshield_settings', $settings, false );
		ScheduledHealthCheck::sync_schedule();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'revertshield-operations',
					'rs_schedule' => 'saved',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Render the operations screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$snapshots = $this->snapshots->recent( 50 );
		$schedule  = ScheduledHealthCheck::status();
		$next_run  = ! empty( $schedule['next_run'] ) ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $schedule['next_run'] ), 'Y-m-d H:i:s' ) : __( 'Not scheduled', 'revertshield' );
		?>
		<div class="wrap revertshield-wrap">
			<div class="revertshield-hero">
				<div>
					<h1><?php echo esc_html__( 'Operations & Observability', 'revertshield' ); ?></h1>
					<p><?php echo esc_html__( 'Protect important snapshots from retention cleanup and optionally run the existing local health suite on a bounded WordPress Cron schedule.', 'revertshield' ); ?></p>
				</div>
				<span class="revertshield-version"><?php echo esc_html( sprintf( /* translators: %s: plugin version. */ __( 'Version %s', 'revertshield' ), REVERTSHIELD_VERSION ) ); ?></span>
			</div>

			<?php $this->render_notices(); ?>

			<div class="revertshield-metrics">
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Protected snapshots', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value"><?php echo esc_html( number_format_i18n( $this->pins->count() ) ); ?></span>
				</div>
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Scheduled health', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value revertshield-metric-text"><?php echo esc_html( ! empty( $schedule['enabled'] ) ? __( 'Enabled', 'revertshield' ) : __( 'Disabled', 'revertshield' ) ); ?></span>
				</div>
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Next health run', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value revertshield-metric-text"><?php echo esc_html( $next_run ); ?></span>
				</div>
			</div>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<section class="revertshield-card">
					<h2><?php echo esc_html__( 'Scheduled local health checks', 'revertshield' ); ?></h2>
					<p><?php echo esc_html__( 'Disabled by default. When enabled, RevertShield runs the same homepage, REST API, and applicable ecosystem probes used by manual and post-change health decisions. Results remain local.', 'revertshield' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="revertshield_health_schedule">
						<?php wp_nonce_field( 'revertshield_health_schedule' ); ?>
						<p><label><input name="scheduled_health_enabled" type="checkbox" value="1" <?php checked( ! empty( $schedule['enabled'] ) ); ?>> <strong><?php echo esc_html__( 'Enable scheduled local health checks', 'revertshield' ); ?></strong></label></p>
						<p>
							<label for="revertshield-health-interval"><strong><?php echo esc_html__( 'Run every', 'revertshield' ); ?></strong></label>
							<select id="revertshield-health-interval" name="scheduled_health_interval">
								<?php foreach ( array( 1, 6, 12, 24 ) as $hours ) : ?>
									<option value="<?php echo esc_attr( (string) $hours ); ?>" <?php selected( absint( $schedule['interval'] ), $hours ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of hours. */ _n( '%d hour', '%d hours', $hours, 'revertshield' ), $hours ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<?php submit_button( __( 'Save Health Schedule', 'revertshield' ), 'secondary', 'submit', false ); ?>
					</form>
				</section>
			<?php endif; ?>

			<section class="revertshield-card revertshield-ledger">
				<div class="revertshield-ledger-header">
					<div>
						<h2><?php echo esc_html__( 'Snapshot protection', 'revertshield' ); ?></h2>
						<span class="revertshield-muted"><?php echo esc_html__( 'Pinned snapshots keep their verified objects beyond the normal retention date until explicitly unpinned.', 'revertshield' ); ?></span>
					</div>
				</div>
				<div class="table-responsive">
					<table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'State', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Plugin', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Snapshot', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Protection', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Action', 'revertshield' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $snapshots ) ) : ?>
							<tr><td colspan="5"><?php echo esc_html__( 'No snapshots available.', 'revertshield' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $snapshots as $snapshot ) : ?>
								<?php
								$uuid     = isset( $snapshot['snapshot_uuid'] ) ? sanitize_text_field( $snapshot['snapshot_uuid'] ) : '';
								$state    = isset( $snapshot['state'] ) ? sanitize_key( $snapshot['state'] ) : '';
								$pinned   = $this->pins->is_pinned( $uuid );
								$can_edit = ! in_array( $state, array( 'preparing', 'expired' ), true );
								?>
								<tr>
									<td><?php echo esc_html( ucfirst( $state ) ); ?></td>
									<td><strong><?php echo esc_html( $snapshot['component_name'] ); ?></strong></td>
									<td><code><?php echo esc_html( substr( $uuid, 0, 8 ) ); ?></code></td>
									<td><?php echo esc_html( $pinned ? __( 'Pinned', 'revertshield' ) : __( 'Standard retention', 'revertshield' ) ); ?></td>
									<td>
										<?php if ( $can_edit ) : ?>
											<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline">
												<input type="hidden" name="action" value="revertshield_snapshot_pin">
												<input type="hidden" name="snapshot_uuid" value="<?php echo esc_attr( $uuid ); ?>">
												<input type="hidden" name="mode" value="<?php echo esc_attr( $pinned ? 'unpin' : 'pin' ); ?>">
												<?php wp_nonce_field( 'revertshield_snapshot_pin' ); ?>
												<button class="button button-small" type="submit"><?php echo esc_html( $pinned ? __( 'Unpin', 'revertshield' ) : __( 'Pin', 'revertshield' ) ); ?></button>
											</form>
										<?php else : ?>
											<span class="revertshield-muted">-</span>
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
	 * Render short action notices.
	 *
	 * @return void
	 */
	private function render_notices() {
		if ( isset( $_GET['rs_schedule'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['rs_schedule'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after protected action.
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Scheduled health settings saved.', 'revertshield' ); ?></p></div>
			<?php
		}

		if ( ! isset( $_GET['rs_pin'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after protected action.
			return;
		}

		$status  = sanitize_key( wp_unslash( $_GET['rs_pin'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code    = isset( $_GET['rs_code'] ) ? sanitize_key( wp_unslash( $_GET['rs_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class   = 'fail' === $status ? 'notice-error' : 'notice-success';
		$message = 'pinned' === $status
			? __( 'Snapshot protected from retention cleanup.', 'revertshield' )
			: ( 'unpinned' === $status ? __( 'Snapshot returned to its original retention policy.', 'revertshield' ) : __( 'Snapshot protection change failed safely.', 'revertshield' ) );
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible"><p>
			<?php echo esc_html( $message ); ?>
			<?php if ( '' !== $code ) : ?>
				<?php echo esc_html( sprintf( /* translators: %s: internal error code. */ __( ' Error code: %s', 'revertshield' ), $code ) ); ?>
			<?php endif; ?>
		</p></div>
		<?php
	}
}
