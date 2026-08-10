<?php
/**
 * Admin interface.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeRepository;

final class AdminPage {
	/** @var ChangeRepository */
	private $repository;

	/** @var HealthChecker */
	private $health;

	/**
	 * Constructor.
	 *
	 * @param ChangeRepository $repository Change repository.
	 * @param HealthChecker    $health     Health checker.
	 */
	public function __construct( ChangeRepository $repository, HealthChecker $health ) {
		$this->repository = $repository;
		$this->health     = $health;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_revertshield_health_check', array( $this, 'handle_health_check' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( REVERTSHIELD_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function menu() {
		add_management_page(
			__( 'RevertShield', 'revertshield' ),
			__( 'RevertShield', 'revertshield' ),
			'manage_options',
			'revertshield',
			array( $this, 'render' )
		);
	}

	/**
	 * Add a quick link from the Plugins screen.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$settings_url = admin_url( 'tools.php?page=revertshield' );
		$link         = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Dashboard', 'revertshield' ) . '</a>';

		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Load styles on plugin screen only.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		if ( 'tools_page_revertshield' !== $hook_suffix ) {
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
	 * Run a manual health check.
	 *
	 * @return void
	 */
	public function handle_health_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run this check.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_health_check' );
		$result = $this->health->run_homepage_check();

		$redirect = add_query_arg(
			array(
				'page'   => 'revertshield',
				'rs_run' => 'pass' === $result['status'] ? 'pass' : 'fail',
			),
			admin_url( 'tools.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render admin screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$entries       = $this->repository->recent( 50 );
		$event_count   = $this->repository->count();
		$health_count  = $this->health->count();
		$latest        = $this->health->latest();
		$settings      = get_option( 'revertshield_settings', array() );
		$retention     = isset( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : 90;
		$health_status = $latest ? sanitize_key( $latest['status'] ) : 'idle';
		?>
		<div class="wrap revertshield-wrap">
			<div class="revertshield-hero">
				<div>
					<h1><?php echo esc_html__( 'RevertShield', 'revertshield' ); ?></h1>
					<p><?php echo esc_html__( 'Track maintenance changes, verify site health, and build a safer recovery trail without sending telemetry.', 'revertshield' ); ?></p>
				</div>
				<span class="revertshield-version"><?php echo esc_html( sprintf( /* translators: %s: plugin version. */ __( 'Version %s', 'revertshield' ), REVERTSHIELD_VERSION ) ); ?></span>
			</div>

			<?php if ( isset( $_GET['rs_run'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after nonce-protected action. ?>
				<?php $run_status = sanitize_key( wp_unslash( $_GET['rs_run'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice <?php echo 'pass' === $run_status ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p>
					<?php echo esc_html( 'pass' === $run_status ? __( 'Homepage health check passed.', 'revertshield' ) : __( 'Homepage health check failed. Review the latest result below.', 'revertshield' ) ); ?>
				</p></div>
			<?php endif; ?>

			<div class="revertshield-metrics">
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Recorded events', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value"><?php echo esc_html( number_format_i18n( $event_count ) ); ?></span>
				</div>
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Health runs', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value"><?php echo esc_html( number_format_i18n( $health_count ) ); ?></span>
				</div>
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Retention', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value"><?php echo esc_html( sprintf( /* translators: %d: number of retention days. */ _n( '%d day', '%d days', $retention, 'revertshield' ), $retention ) ); ?></span>
				</div>
			</div>

			<div class="revertshield-grid">
				<section class="revertshield-card">
					<h2><?php echo esc_html__( 'Site health', 'revertshield' ); ?></h2>
					<span class="revertshield-status revertshield-status-<?php echo esc_attr( in_array( $health_status, array( 'pass', 'fail' ), true ) ? $health_status : 'idle' ); ?>">
						<?php echo esc_html( $this->health_status_label( $health_status ) ); ?>
					</span>

					<?php if ( $latest ) : ?>
						<p class="revertshield-health-detail">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: HTTP status code, 2: response time in milliseconds. */
									__( 'HTTP %1$d in %2$d ms', 'revertshield' ),
									absint( $latest['http_code'] ),
									absint( $latest['duration_ms'] )
								)
							);
							?>
						</p>
						<p class="description"><?php echo esc_html( get_date_from_gmt( $latest['created_at'], 'Y-m-d H:i:s' ) ); ?></p>
						<?php if ( ! empty( $latest['message'] ) ) : ?>
							<p class="description"><?php echo esc_html( $latest['message'] ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p class="revertshield-health-detail"><?php echo esc_html__( 'No health check has been run yet.', 'revertshield' ); ?></p>
					<?php endif; ?>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="revertshield_health_check">
						<?php wp_nonce_field( 'revertshield_health_check' ); ?>
						<?php submit_button( __( 'Run Homepage Check', 'revertshield' ), 'secondary', 'submit', false ); ?>
					</form>
				</section>

				<section class="revertshield-card">
					<h2><?php echo esc_html__( 'Data controls', 'revertshield' ); ?></h2>
					<form action="options.php" method="post">
						<?php settings_fields( 'revertshield_settings_group' ); ?>
						<p class="revertshield-settings-row">
							<label for="revertshield-retention"><strong><?php echo esc_html__( 'Retention days', 'revertshield' ); ?></strong></label><br>
							<input id="revertshield-retention" name="revertshield_settings[retention_days]" type="number" min="1" max="3650" value="<?php echo esc_attr( (string) $retention ); ?>">
						</p>
						<p class="revertshield-settings-row"><label><input name="revertshield_settings[log_option_names]" type="checkbox" value="1" <?php checked( ! empty( $settings['log_option_names'] ) ); ?>> <?php echo esc_html__( 'Record changes to selected critical option names. Option values are never stored by this observer.', 'revertshield' ); ?></label></p>
						<p class="revertshield-settings-row"><label><input name="revertshield_settings[delete_on_uninstall]" type="checkbox" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>> <?php echo esc_html__( 'Delete RevertShield tables and settings when the plugin is uninstalled.', 'revertshield' ); ?></label></p>
						<?php submit_button( __( 'Save Settings', 'revertshield' ) ); ?>
					</form>
				</section>
			</div>

			<section class="revertshield-card revertshield-ledger">
				<div class="revertshield-ledger-header">
					<div>
						<h2><?php echo esc_html__( 'Recent changes', 'revertshield' ); ?></h2>
						<span class="revertshield-muted"><?php echo esc_html__( 'The newest 50 locally recorded maintenance events.', 'revertshield' ); ?></span>
					</div>
				</div>
				<div class="table-responsive">
					<table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'Time', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Event', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Object', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Actor', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Source', 'revertshield' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $entries ) ) : ?>
							<tr><td colspan="5"><?php echo esc_html__( 'No changes recorded yet.', 'revertshield' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $entries as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( get_date_from_gmt( $entry['created_at'], 'Y-m-d H:i:s' ) ); ?></td>
									<td><span class="revertshield-event"><?php echo esc_html( $entry['event_type'] ); ?></span></td>
									<td><span class="revertshield-object"><?php echo esc_html( $entry['object_name'] ); ?></span><br><span class="revertshield-muted"><?php echo esc_html( $entry['object_type'] ); ?></span></td>
									<td><?php echo esc_html( $this->actor_label( absint( $entry['actor_id'] ) ) ); ?></td>
									<td><?php echo esc_html( $entry['source'] ); ?></td>
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
	 * Human-readable health status.
	 *
	 * @param string $status Health status.
	 * @return string
	 */
	private function health_status_label( $status ) {
		if ( 'pass' === $status ) {
			return __( 'Healthy', 'revertshield' );
		}

		if ( 'fail' === $status ) {
			return __( 'Needs attention', 'revertshield' );
		}

		return __( 'Not checked', 'revertshield' );
	}

	/**
	 * Resolve an actor ID for display without persisting extra personal data.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function actor_label( $user_id ) {
		if ( 0 === $user_id ) {
			return __( 'System', 'revertshield' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return sprintf( /* translators: %d: WordPress user ID. */ __( 'User #%d', 'revertshield' ), $user_id );
		}

		return $user->display_name;
	}
}
