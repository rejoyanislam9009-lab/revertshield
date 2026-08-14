<?php
/**
 * Minimal runtime regression test for the N8 LiveChat Pro recovery bootstrap.
 * This intentionally simulates core and activation failures without WordPress.
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ . '/fake-wordpress/' );
define( 'N8LC_FILE', dirname( __DIR__ ) . '/n8-livechat-pro/n8-livechat-pro.php' );
define( 'N8LC_VERSION', '0.4.1' );

$wp_version = '6.8.3';
$GLOBALS['n8lc_test_options']     = array();
$GLOBALS['n8lc_test_actions']     = array();
$GLOBALS['n8lc_test_deactivated'] = false;

function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['n8lc_test_actions'][ $hook ][] = $callback; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['n8lc_test_options'] ) ? $GLOBALS['n8lc_test_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['n8lc_test_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['n8lc_test_options'][ $key ] ); return true; }
function is_admin() { return true; }
function current_user_can( $capability ) { return true; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return strip_tags( (string) $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function sanitize_file_name( $value ) { return basename( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function deactivate_plugins( $plugin, $silent = false ) { $GLOBALS['n8lc_test_deactivated'] = true; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( 'WP_DIE: ' . $message ); }

class N8LC_Core {
    public static function instance() { return new self(); }
    public function boot() { throw new RuntimeException( 'simulated core runtime failure' ); }
    public static function deactivate() {}
}

class N8LC_DB {
    public static function activate() { throw new RuntimeException( 'simulated activation database failure' ); }
}

class N8LC_Platform {
    public static function instance() { return new self(); }
    public function hooks() {}
}

class N8LC_Privacy { public static function instance() { return new self(); } public function hooks() {} }
class N8LC_Knowledge { public static function instance() { return new self(); } public function hooks() {} }
class N8LC_Health { public static function instance() { return new self(); } public function hooks() {} }
class N8LC_Shortcodes { public static function instance() { return new self(); } public function hooks() {} }

require dirname( __DIR__ ) . '/n8-livechat-pro/includes/class-n8lc-bootstrap.php';

N8LC_Bootstrap::boot();
$error = get_option( N8LC_Bootstrap::ERROR_OPTION, array() );
if ( empty( $error ) || 'core' !== $error['module'] ) {
    fwrite( STDERR, "Recovery guard did not capture the simulated core failure.\n" );
    exit( 2 );
}

try {
    N8LC_Bootstrap::activate();
} catch ( RuntimeException $exception ) {
    if ( 0 !== strpos( $exception->getMessage(), 'WP_DIE:' ) ) {
        throw $exception;
    }
}

$error = get_option( N8LC_Bootstrap::ERROR_OPTION, array() );
if ( empty( $GLOBALS['n8lc_test_deactivated'] ) || empty( $error ) || 'activation' !== $error['module'] ) {
    fwrite( STDERR, "Recovery guard did not safely stop/deactivate the simulated activation failure.\n" );
    exit( 3 );
}

echo "N8LC_RECOVERY_GUARD_OK\n";
