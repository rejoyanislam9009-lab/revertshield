<?php
/**
 * WordPress change observers.
 *
 * @package RevertShield
 */

namespace RevertShield\Ledger;

/**
 * Observes high-value WordPress maintenance events.
 */
final class ChangeObserver {
	/**
	 * Ledger persistence service.
	 *
	 * @var ChangeRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param ChangeRepository $repository Repository.
	 */
	public function __construct( ChangeRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'activated_plugin', array( $this, 'plugin_activated' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'plugin_deactivated' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'theme_switched' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'upgrade_completed' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'option_updated' ), 10, 3 );
	}

	/**
	 * Record plugin activation.
	 *
	 * @param string $plugin       Plugin basename.
	 * @param bool   $network_wide Network activation.
	 * @return void
	 */
	public function plugin_activated( $plugin, $network_wide ) {
		$this->repository->record(
			'plugin_activated',
			'plugin',
			$plugin,
			array( 'network_wide' => $network_wide ? 'yes' : 'no' )
		);
	}

	/**
	 * Record plugin deactivation.
	 *
	 * @param string $plugin       Plugin basename.
	 * @param bool   $network_wide Network deactivation.
	 * @return void
	 */
	public function plugin_deactivated( $plugin, $network_wide ) {
		$this->repository->record(
			'plugin_deactivated',
			'plugin',
			$plugin,
			array( 'network_wide' => $network_wide ? 'yes' : 'no' )
		);
	}

	/**
	 * Record theme switch.
	 *
	 * @param string    $new_name  New theme display name.
	 * @param \WP_Theme $new_theme New theme object.
	 * @param \WP_Theme $old_theme Old theme object.
	 * @return void
	 */
	public function theme_switched( $new_name, $new_theme, $old_theme ) {
		$this->repository->record(
			'theme_switched',
			'theme',
			$new_theme->get_stylesheet(),
			array(
				'new_name'  => $new_name,
				'old_theme' => $old_theme instanceof \WP_Theme ? $old_theme->get_stylesheet() : '',
			)
		);
	}

	/**
	 * Record completed updates and installs.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Upgrade metadata.
	 * @return void
	 */
	public function upgrade_completed( $upgrader, $options ) {
		unset( $upgrader );

		$type   = isset( $options['type'] ) ? sanitize_key( $options['type'] ) : 'unknown';
		$action = isset( $options['action'] ) ? sanitize_key( $options['action'] ) : 'update';
		$names  = array();

		if ( isset( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$names = array_map( 'sanitize_text_field', $options['plugins'] );
		} elseif ( isset( $options['plugin'] ) ) {
			$names[] = sanitize_text_field( $options['plugin'] );
		} elseif ( isset( $options['themes'] ) && is_array( $options['themes'] ) ) {
			$names = array_map( 'sanitize_text_field', $options['themes'] );
		} elseif ( isset( $options['theme'] ) ) {
			$names[] = sanitize_text_field( $options['theme'] );
		}

		if ( empty( $names ) ) {
			$names[] = $type;
		}

		foreach ( $names as $name ) {
			$this->repository->record(
				'upgrader_' . $action,
				$type,
				$name,
				array( 'bulk' => ! empty( $options['bulk'] ) ? 'yes' : 'no' ),
				'upgrader'
			);
		}
	}

	/**
	 * Record selected critical option names without persisting values.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public function option_updated( $option, $old_value, $value ) {
		unset( $old_value, $value );

		$settings = get_option( 'revertshield_settings', array() );
		if ( empty( $settings['log_option_names'] ) ) {
			return;
		}

		$allowlist = array(
			'admin_email',
			'blogname',
			'blogdescription',
			'default_role',
			'home',
			'permalink_structure',
			'siteurl',
			'timezone_string',
		);

		/**
		 * Filter the option names whose changes are recorded.
		 *
		 * Values are never stored by the core observer.
		 *
		 * @param string[] $allowlist Option names.
		 */
		$allowlist = apply_filters( 'revertshield_tracked_option_names', $allowlist );

		if ( in_array( $option, $allowlist, true ) ) {
			$this->repository->record( 'option_updated', 'option', $option );
		}
	}
}
