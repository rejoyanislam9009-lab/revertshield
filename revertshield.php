<?php
/**
 * Plugin Name:       RevertShield
 * Description:       Records important WordPress changes and runs local health checks to make safer updates and troubleshooting easier.
 * Version:           0.8.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       revertshield
 * Domain Path:       /languages
 *
 * @package RevertShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REVERTSHIELD_VERSION', '0.8.0' );
define( 'REVERTSHIELD_FILE', __FILE__ );
define( 'REVERTSHIELD_PATH', plugin_dir_path( __FILE__ ) );
define( 'REVERTSHIELD_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'RevertShield\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = REVERTSHIELD_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( 'RevertShield\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RevertShield\\Core\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new RevertShield\Core\Plugin();
		$plugin->boot();
	}
);
