<?php
/**
 * Guarded plugin update execution.
 *
 * @package RevertShield
 */

namespace RevertShield\Update;

use RevertShield\Health\HealthChecker;
use RevertShield\Ledger\ChangeRepository;
use RevertShield\Policy\MaintenanceWindow;
use RevertShield\Snapshot\PluginSourceLocator;

/**
 * Executes one plugin update only after RevertShield safety preconditions pass.
 */
final class GuardedPluginUpdateService {
	/** @var SafeUpdateGate */
	private $gate;

	/** @var PluginSourceLocator */
	private $source_locator;

	/** @var HealthChecker */
	private $health;

	/** @var ChangeRepository */
	private $ledger;

	/** @var MaintenanceWindow */
	private $maintenance_window;

	/**
	 * Constructor.
	 *
	 * @param SafeUpdateGate|null      $gate               Optional safe-update gate.
	 * @param PluginSourceLocator|null $source_locator     Optional plugin locator.
	 * @param HealthChecker|null       $health             Optional health checker.
	 * @param ChangeRepository|null    $ledger             Optional change ledger.
	 * @param MaintenanceWindow|null   $maintenance_window Optional maintenance-window policy.
	 */
	public function __construct(
		SafeUpdateGate $gate = null,
		PluginSourceLocator $source_locator = null,
		HealthChecker $health = null,
		ChangeRepository $ledger = null,
		MaintenanceWindow $maintenance_window = null
	) {
		$this->gate               = $gate ? $gate : new SafeUpdateGate();
		$this->source_locator     = $source_locator ? $source_locator : new PluginSourceLocator();
		$this->health             = $health ? $health : new HealthChecker();
		$this->ledger             = $ledger ? $ledger : new ChangeRepository();
		$this->maintenance_window = $maintenance_window ? $maintenance_window : new MaintenanceWindow();
	}

	/**
	 * Execute one guarded plugin update and run a post-update site-health suite.
	 *
	 * Automatic restore is intentionally not performed here.
	 *
	 * @param string $plugin_file   Installed plugin basename.
	 * @param string $snapshot_uuid Verified snapshot UUID.
	 * @return array|\WP_Error Guarded update result or an error.
	 */
	public function execute( $plugin_file, $snapshot_uuid ) {
		$component = $this->source_locator->locate( $plugin_file );
		if ( is_wp_error( $component ) ) {
			return $component;
		}

		$plugin_file = $component['plugin_file'];
		if ( plugin_basename( REVERTSHIELD_FILE ) === $plugin_file ) {
			return new \WP_Error(
				'revertshield_guarded_self_update_disabled',
				__( 'Guarded self-updates are not enabled in this release.', 'revertshield' )
			);
		}

		if ( ! $this->maintenance_window->allows_now() ) {
			return new \WP_Error(
				'revertshield_guarded_update_outside_maintenance_window',
				__( 'Guarded plugin updates are currently outside the configured maintenance window.', 'revertshield' )
			);
		}

		$offer_before = $this->available_update( $plugin_file );
		if ( is_wp_error( $offer_before ) ) {
			return $offer_before;
		}

		$verified = $this->gate->validate( $snapshot_uuid, $plugin_file );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$offer_after = $this->available_update( $plugin_file );
		if ( is_wp_error( $offer_after ) ) {
			return $offer_after;
		}

		if ( $offer_before['new_version'] !== $offer_after['new_version'] ) {
			return new \WP_Error(
				'revertshield_guarded_update_offer_changed',
				__( 'The available plugin update changed during verification. Create a fresh snapshot and try again.', 'revertshield' )
			);
		}

		$before_version = isset( $component['plugin_data']['Version'] )
			? sanitize_text_field( $component['plugin_data']['Version'] )
			: '';

		$this->ledger->record(
			'guarded_update_started',
			'plugin',
			$plugin_file,
			array(
				'snapshot_uuid' => $snapshot_uuid,
				'from_version'  => $before_version,
				'to_version'    => $offer_after['new_version'],
			),
			'guarded_update'
		);

		$this->load_upgrader_classes();

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$updated  = $upgrader->upgrade(
			$plugin_file,
			array(
				'clear_update_cache' => true,
			)
		);

		if ( is_wp_error( $updated ) ) {
			$this->record_failure( $plugin_file, $snapshot_uuid, $updated->get_error_code(), $before_version, $offer_after['new_version'] );
			return $updated;
		}

		if ( true !== $updated ) {
			$error = new \WP_Error(
				'revertshield_guarded_update_failed',
				__( 'WordPress did not complete the guarded plugin update.', 'revertshield' )
			);
			$this->record_failure( $plugin_file, $snapshot_uuid, $error->get_error_code(), $before_version, $offer_after['new_version'] );
			return $error;
		}

		wp_clean_plugins_cache( false );
		$after_component = $this->source_locator->locate( $plugin_file );
		if ( is_wp_error( $after_component ) ) {
			$error = new \WP_Error(
				'revertshield_guarded_update_target_missing',
				__( 'The plugin could not be resolved after the update completed.', 'revertshield' )
			);
			$this->record_failure( $plugin_file, $snapshot_uuid, $error->get_error_code(), $before_version, $offer_after['new_version'] );
			return $error;
		}

		$after_version = isset( $after_component['plugin_data']['Version'] )
			? sanitize_text_field( $after_component['plugin_data']['Version'] )
			: '';

		if ( '' === $after_version || '' === $before_version || ! version_compare( $after_version, $before_version, '>' ) ) {
			$error = new \WP_Error(
				'revertshield_guarded_update_version_not_advanced',
				__( 'The plugin version did not advance after the update.', 'revertshield' )
			);
			$this->record_failure( $plugin_file, $snapshot_uuid, $error->get_error_code(), $before_version, $after_version );
			return $error;
		}

		$health               = $this->health->run_site_check();
		$event                = 'pass' === $health['status'] ? 'guarded_update_healthy' : 'guarded_update_unhealthy';
		$recovery_recommended = false;

		if ( 'pass' !== $health['status'] ) {
			$recovery_check       = $this->gate->validate( $snapshot_uuid, $plugin_file );
			$recovery_recommended = ! is_wp_error( $recovery_check );
		}

		$this->ledger->record(
			$event,
			'plugin',
			$plugin_file,
			array(
				'snapshot_uuid'        => $snapshot_uuid,
				'from_version'         => $before_version,
				'to_version'           => $after_version,
				'health_status'        => $health['status'],
				'health_check_type'    => isset( $health['check_type'] ) ? sanitize_key( $health['check_type'] ) : 'site_suite',
				'probe_count'          => isset( $health['probes'] ) && is_array( $health['probes'] ) ? count( $health['probes'] ) : 0,
				'failed_probe_count'   => isset( $health['failed_probes'] ) && is_array( $health['failed_probes'] ) ? count( $health['failed_probes'] ) : 0,
				'http_code'            => absint( $health['http_code'] ),
				'duration_ms'          => absint( $health['duration_ms'] ),
				'recovery_recommended' => $recovery_recommended ? 1 : 0,
			),
			'guarded_update'
		);

		return array(
			'plugin_file'          => $plugin_file,
			'snapshot_uuid'        => $snapshot_uuid,
			'from_version'         => $before_version,
			'to_version'           => $after_version,
			'health_status'        => $health['status'],
			'http_code'            => absint( $health['http_code'] ),
			'duration_ms'          => absint( $health['duration_ms'] ),
			'failed_probes'        => isset( $health['failed_probes'] ) && is_array( $health['failed_probes'] ) ? $health['failed_probes'] : array(),
			'recovery_recommended' => $recovery_recommended,
		);
	}

	/**
	 * Read the current WordPress update offer for one installed plugin.
	 *
	 * @param string $plugin_file Installed plugin basename.
	 * @return array|\WP_Error Update summary or an error.
	 */
	private function available_update( $plugin_file ) {
		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) || ! isset( $updates->response ) || ! is_array( $updates->response ) || ! isset( $updates->response[ $plugin_file ] ) ) {
			return new \WP_Error(
				'revertshield_guarded_update_unavailable',
				__( 'WordPress does not currently report an available update for this plugin.', 'revertshield' )
			);
		}

		$offer       = $updates->response[ $plugin_file ];
		$new_version = is_object( $offer ) && isset( $offer->new_version )
			? sanitize_text_field( (string) $offer->new_version )
			: '';

		if ( '' === $new_version ) {
			return new \WP_Error(
				'revertshield_guarded_update_invalid_offer',
				__( 'The WordPress plugin update offer is incomplete.', 'revertshield' )
			);
		}

		return array(
			'new_version' => $new_version,
		);
	}

	/**
	 * Load WordPress core upgrader classes without custom update code.
	 *
	 * @return void
	 */
	private function load_upgrader_classes() {
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		}
	}

	/**
	 * Record a normalized guarded-update failure without persisting raw messages.
	 *
	 * @param string $plugin_file    Plugin basename.
	 * @param string $snapshot_uuid  Snapshot UUID.
	 * @param string $error_code     Error code.
	 * @param string $before_version Previous plugin version.
	 * @param string $target_version Intended or observed target version.
	 * @return void
	 */
	private function record_failure( $plugin_file, $snapshot_uuid, $error_code, $before_version, $target_version ) {
		$this->ledger->record(
			'guarded_update_failed',
			'plugin',
			$plugin_file,
			array(
				'snapshot_uuid'  => $snapshot_uuid,
				'error_code'     => sanitize_key( $error_code ),
				'from_version'   => sanitize_text_field( $before_version ),
				'target_version' => sanitize_text_field( $target_version ),
			),
			'guarded_update'
		);
	}
}
