<?php
/**
 * Snapshot administration screen.
 *
 * @package RevertShield
 */

namespace RevertShield\Admin;

use RevertShield\Ledger\ChangeRepository;
use RevertShield\Snapshot\PluginSnapshotService;
use RevertShield\Snapshot\SnapshotRepository;

/**
 * Provides authorized manual snapshot operations and snapshot history.
 */
final class SnapshotAdminPage {
	/** @var SnapshotRepository */
	private $snapshots;

	/** @var PluginSnapshotService */
	private $service;

	/** @var ChangeRepository */
	private $ledger;

	/**
	 * Constructor.
	 *
	 * @param SnapshotRepository    $snapshots Snapshot repository.
	 * @param PluginSnapshotService $service   Snapshot creation service.
	 * @param ChangeRepository      $ledger    Change ledger.
	 */
	public function __construct( SnapshotRepository $snapshots, PluginSnapshotService $service, ChangeRepository $ledger ) {
		$this->snapshots = $snapshots;
		$this->service   = $service;
		$this->ledger    = $ledger;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_revertshield_create_snapshot', array( $this, 'handle_create_snapshot' ) );
		add_action( 'admin_post_revertshield_snapshot_retention', array( $this, 'handle_snapshot_retention' ) );
	}

	/**
	 * Add the snapshot management screen.
	 *
	 * @return void
	 */
	public function menu() {
		add_management_page(
			__( 'RevertShield Snapshots', 'revertshield' ),
			__( 'RevertShield Snapshots', 'revertshield' ),
			'update_plugins',
			'revertshield-snapshots',
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
		if ( 'tools_page_revertshield-snapshots' !== $hook_suffix ) {
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
	 * Create one verified plugin snapshot from an authorized admin request.
	 *
	 * @return void
	 */
	public function handle_create_snapshot() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to create plugin snapshots.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_create_snapshot' );

		$plugin_file = isset( $_POST['plugin_file'] )
			? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) )
			: '';

		$result = $this->service->create( $plugin_file );

		if ( is_wp_error( $result ) ) {
			$this->ledger->record(
				'snapshot_failed',
				'plugin',
				$plugin_file,
				array( 'error_code' => $result->get_error_code() ),
				'snapshot'
			);

			$this->redirect_snapshot( 'fail', $result->get_error_code() );
		}

		$this->ledger->record(
			'snapshot_created',
			'plugin',
			$plugin_file,
			array(
				'snapshot_uuid' => $result['snapshot_uuid'],
				'size_bytes'    => $result['size_bytes'],
				'object_count'  => $result['object_count'],
			),
			'snapshot'
		);

		$this->redirect_snapshot( 'pass', '' );
	}

	/**
	 * Persist the bounded snapshot retention policy.
	 *
	 * @return void
	 */
	public function handle_snapshot_retention() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to change snapshot retention.', 'revertshield' ) );
		}

		check_admin_referer( 'revertshield_snapshot_retention' );

		$days = isset( $_POST['snapshot_retention_days'] )
			? absint( wp_unslash( $_POST['snapshot_retention_days'] ) )
			: 7;
		$days = max( 1, min( 90, $days ) );

		$settings                            = get_option( 'revertshield_settings', array() );
		$settings                            = is_array( $settings ) ? $settings : array();
		$settings['snapshot_retention_days'] = $days;
		update_option( 'revertshield_settings', $settings, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'revertshield-snapshots',
					'rs_settings' => 'saved',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Render the snapshot administration screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins   = get_plugins();
		$snapshots = $this->snapshots->recent( 25 );
		$count     = $this->snapshots->count();
		$settings  = get_option( 'revertshield_settings', array() );
		$retention = isset( $settings['snapshot_retention_days'] ) ? absint( $settings['snapshot_retention_days'] ) : 7;
		$retention = max( 1, min( 90, $retention ) );

		uasort(
			$plugins,
			static function ( $left, $right ) {
				$left_name  = isset( $left['Name'] ) ? $left['Name'] : '';
				$right_name = isset( $right['Name'] ) ? $right['Name'] : '';
				return strcasecmp( $left_name, $right_name );
			}
		);
		?>
		<div class="wrap revertshield-wrap">
			<div class="revertshield-hero">
				<div>
					<h1><?php echo esc_html__( 'Verified Plugin Snapshots', 'revertshield' ); ?></h1>
					<p><?php echo esc_html__( 'Create a local, integrity-verified plugin file snapshot before risky maintenance. Snapshot creation does not update or restore a plugin.', 'revertshield' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( admin_url( 'tools.php?page=revertshield' ) ); ?>"><?php echo esc_html__( 'Back to Dashboard', 'revertshield' ); ?></a>
			</div>

			<?php $this->render_notices(); ?>

			<div class="revertshield-metrics">
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Snapshot records', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
				</div>
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Snapshot retention', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value"><?php echo esc_html( sprintf( /* translators: %d: retention days. */ _n( '%d day', '%d days', $retention, 'revertshield' ), $retention ) ); ?></span>
				</div>
				<div class="revertshield-metric">
					<span class="revertshield-metric-label"><?php echo esc_html__( 'Recovery', 'revertshield' ); ?></span>
					<span class="revertshield-metric-value revertshield-metric-text"><?php echo esc_html__( 'Not enabled', 'revertshield' ); ?></span>
				</div>
			</div>

			<div class="revertshield-grid">
				<section class="revertshield-card">
					<h2><?php echo esc_html__( 'Create verified snapshot', 'revertshield' ); ?></h2>
					<p><?php echo esc_html__( 'RevertShield will inventory and hash the selected installed plugin, check local storage capacity, copy content as extensionless SHA-256 objects, and independently verify the result.', 'revertshield' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="revertshield-snapshot-form">
						<input type="hidden" name="action" value="revertshield_create_snapshot">
						<?php wp_nonce_field( 'revertshield_create_snapshot' ); ?>
						<label for="revertshield-plugin-file"><strong><?php echo esc_html__( 'Installed plugin', 'revertshield' ); ?></strong></label>
						<select id="revertshield-plugin-file" name="plugin_file" required>
							<option value=""><?php echo esc_html__( 'Select a plugin', 'revertshield' ); ?></option>
							<?php foreach ( $plugins as $plugin_file => $plugin_data ) : ?>
								<?php
								$name    = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin_file;
								$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
								$label   = '' !== $version ? $name . ' — ' . $version : $name;
								?>
								<option value="<?php echo esc_attr( $plugin_file ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Create Verified Snapshot', 'revertshield' ), 'primary', 'submit', false ); ?>
					</form>
					<p class="description"><?php echo esc_html__( 'Large plugins may take longer to hash and copy. RevertShield fails closed if files change during capture, storage is insufficient, or integrity verification fails.', 'revertshield' ); ?></p>
				</section>

				<section class="revertshield-card">
					<h2><?php echo esc_html__( 'Snapshot retention', 'revertshield' ); ?></h2>
					<p><?php echo esc_html__( 'Expired snapshot files are removed in bounded daily cleanup batches. Their lightweight metadata history remains marked as expired.', 'revertshield' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="revertshield_snapshot_retention">
						<?php wp_nonce_field( 'revertshield_snapshot_retention' ); ?>
						<label for="revertshield-snapshot-retention"><strong><?php echo esc_html__( 'Keep snapshot files for', 'revertshield' ); ?></strong></label><br>
						<input id="revertshield-snapshot-retention" name="snapshot_retention_days" type="number" min="1" max="90" value="<?php echo esc_attr( (string) $retention ); ?>"> <?php echo esc_html__( 'days', 'revertshield' ); ?>
						<?php submit_button( __( 'Save Retention', 'revertshield' ), 'secondary', 'submit', false ); ?>
					</form>
					<p class="description"><?php echo esc_html__( 'Changing retention affects newly created snapshots and does not extend snapshots that already have an expiration time.', 'revertshield' ); ?></p>
				</section>
			</div>

			<section class="revertshield-card revertshield-ledger">
				<div class="revertshield-ledger-header">
					<div>
						<h2><?php echo esc_html__( 'Recent snapshots', 'revertshield' ); ?></h2>
						<span class="revertshield-muted"><?php echo esc_html__( 'The newest 25 snapshot records. Expired snapshots retain metadata history but no recovery objects.', 'revertshield' ); ?></span>
					</div>
				</div>
				<div class="table-responsive">
					<table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'State', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Plugin', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Size', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Created', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Expires', 'revertshield' ); ?></th>
							<th><?php echo esc_html__( 'Snapshot', 'revertshield' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( empty( $snapshots ) ) : ?>
							<tr><td colspan="6"><?php echo esc_html__( 'No snapshots created yet.', 'revertshield' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $snapshots as $snapshot ) : ?>
								<?php $expires_label = empty( $snapshot['expires_at'] ) ? '—' : get_date_from_gmt( $snapshot['expires_at'], 'Y-m-d H:i:s' ); ?>
								<tr>
									<td><span class="revertshield-snapshot-state revertshield-snapshot-state-<?php echo esc_attr( sanitize_key( $snapshot['state'] ) ); ?>"><?php echo esc_html( ucfirst( sanitize_key( $snapshot['state'] ) ) ); ?></span></td>
									<td><strong><?php echo esc_html( $snapshot['component_name'] ); ?></strong></td>
									<td><?php echo esc_html( size_format( absint( $snapshot['size_bytes'] ), 1 ) ); ?></td>
									<td><?php echo esc_html( get_date_from_gmt( $snapshot['created_at'], 'Y-m-d H:i:s' ) ); ?></td>
									<td><?php echo esc_html( $expires_label ); ?></td>
									<td><code><?php echo esc_html( substr( $snapshot['snapshot_uuid'], 0, 8 ) ); ?></code></td>
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
	 * Render notices returned from protected admin actions.
	 *
	 * @return void
	 */
	private function render_notices() {
		if ( isset( $_GET['rs_settings'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['rs_settings'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after a nonce-protected action.
			?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Snapshot retention saved.', 'revertshield' ); ?></p></div>
			<?php
		}

		if ( ! isset( $_GET['rs_snapshot'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status after a nonce-protected action.
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['rs_snapshot'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code   = isset( $_GET['rs_code'] ) ? sanitize_key( wp_unslash( $_GET['rs_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class  = 'pass' === $status ? 'notice-success' : 'notice-error';
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible"><p>
			<?php
			if ( 'pass' === $status ) {
				echo esc_html__( 'Verified plugin snapshot created successfully.', 'revertshield' );
			} else {
				echo esc_html__( 'Snapshot creation failed safely. No plugin update or restore was performed.', 'revertshield' );
				if ( '' !== $code ) {
					echo ' ' . esc_html( sprintf( /* translators: %s: internal error code. */ __( 'Error code: %s', 'revertshield' ), $code ) );
				}
			}
			?>
		</p></div>
		<?php
	}

	/**
	 * Redirect back to the snapshot screen with a short status code.
	 *
	 * @param string $status Result status.
	 * @param string $code   Optional error code.
	 * @return void
	 */
	private function redirect_snapshot( $status, $code ) {
		$args = array(
			'page'        => 'revertshield-snapshots',
			'rs_snapshot' => 'pass' === $status ? 'pass' : 'fail',
		);

		if ( '' !== $code ) {
			$args['rs_code'] = sanitize_key( $code );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}
}
