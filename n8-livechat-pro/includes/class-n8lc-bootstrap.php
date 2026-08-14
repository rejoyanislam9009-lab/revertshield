<?php

defined( 'ABSPATH' ) || exit;

/**
 * Recovery-safe plugin bootstrap.
 *
 * Runtime failures in optional/professional modules must never take the whole
 * WordPress dashboard down. Activation failures are stopped cleanly and the
 * plugin is deactivated while preserving its data for repair/retry.
 */
final class N8LC_Bootstrap {
    const ERROR_OPTION = 'n8lc_boot_error';
    const MIN_PHP      = '7.4';
    const MIN_WP       = '6.5';

    public static function register() {
        register_activation_hook( N8LC_FILE, array( __CLASS__, 'activate' ) );
        register_deactivation_hook( N8LC_FILE, array( 'N8LC_Core', 'deactivate' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'boot' ) );
    }

    public static function activate() {
        $requirements = self::requirements();
        if ( ! $requirements['ok'] ) {
            self::record_error( 'requirements', $requirements['message'] );
            self::deactivate_self();
            self::activation_stop( $requirements['message'] );
            return;
        }

        try {
            N8LC_DB::activate();
            delete_option( self::ERROR_OPTION );
        } catch ( Throwable $error ) {
            self::capture_exception( 'activation', $error );
            self::deactivate_self();
            self::activation_stop(
                __( 'N8 LiveChat Pro could not finish activation safely. The plugin was automatically deactivated so the WordPress dashboard remains available. Check the N8 LiveChat recovery notice or the server PHP error log, then retry after the issue is corrected.', 'n8-livechat-pro' )
            );
        }
    }

    public static function boot() {
        $requirements = self::requirements();
        if ( ! $requirements['ok'] ) {
            self::record_error( 'requirements', $requirements['message'] );
            self::register_notice();
            return;
        }

        if ( ! self::run_module( 'core', array( N8LC_Core::instance(), 'boot' ) ) ) {
            self::register_notice();
            return;
        }

        $modules = array(
            'platform'   => array( N8LC_Platform::instance(), 'hooks' ),
            'privacy'    => array( N8LC_Privacy::instance(), 'hooks' ),
            'knowledge'  => array( N8LC_Knowledge::instance(), 'hooks' ),
            'health'     => array( N8LC_Health::instance(), 'hooks' ),
            'shortcodes' => array( N8LC_Shortcodes::instance(), 'hooks' ),
        );

        foreach ( $modules as $module => $callback ) {
            // If the professional platform previously failed on this server,
            // do not retry its schema work on public traffic. Administrators
            // can safely trigger a retry simply by loading wp-admin.
            $previous = get_option( self::ERROR_OPTION, array() );
            if ( 'platform' === $module && ! is_admin() && is_array( $previous ) && isset( $previous['module'] ) && 'platform' === $previous['module'] ) {
                continue;
            }
            self::run_module( $module, $callback );
        }

        self::register_notice();
    }

    public static function run_module( $module, $callback ) {
        try {
            call_user_func( $callback );
            $previous = get_option( self::ERROR_OPTION, array() );
            if ( is_array( $previous ) && isset( $previous['module'] ) && sanitize_key( $module ) === $previous['module'] ) {
                delete_option( self::ERROR_OPTION );
            }
            return true;
        } catch ( Throwable $error ) {
            self::capture_exception( $module, $error );
            return false;
        }
    }

    public static function capture_exception( $module, $error ) {
        $message = $error instanceof Throwable ? $error->getMessage() : __( 'Unknown runtime error.', 'n8-livechat-pro' );
        $file    = $error instanceof Throwable ? basename( $error->getFile() ) : '';
        $line    = $error instanceof Throwable ? absint( $error->getLine() ) : 0;

        self::record_error( $module, $message, $file, $line );

        if ( function_exists( 'error_log' ) ) {
            error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                sprintf(
                    'N8 LiveChat Pro recovery guard [%s]: %s in %s:%d',
                    sanitize_key( $module ),
                    wp_strip_all_tags( (string) $message ),
                    $file,
                    $line
                )
            );
        }
    }

    private static function record_error( $module, $message, $file = '', $line = 0 ) {
        update_option(
            self::ERROR_OPTION,
            array(
                'module'  => sanitize_key( $module ),
                'message' => sanitize_text_field( wp_strip_all_tags( (string) $message ) ),
                'file'    => sanitize_file_name( (string) $file ),
                'line'    => absint( $line ),
                'time'    => time(),
                'version' => N8LC_VERSION,
            ),
            false
        );
    }

    public static function requirements() {
        global $wp_version;
        $wp = isset( $wp_version ) ? (string) $wp_version : '';

        if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
            return array(
                'ok'      => false,
                'message' => sprintf(
                    /* translators: 1: required PHP version, 2: current PHP version. */
                    __( 'N8 LiveChat Pro requires PHP %1$s or newer. This server is running PHP %2$s.', 'n8-livechat-pro' ),
                    self::MIN_PHP,
                    PHP_VERSION
                ),
            );
        }

        if ( $wp && version_compare( $wp, self::MIN_WP, '<' ) ) {
            return array(
                'ok'      => false,
                'message' => sprintf(
                    /* translators: 1: required WordPress version, 2: current WordPress version. */
                    __( 'N8 LiveChat Pro requires WordPress %1$s or newer. This site is running WordPress %2$s.', 'n8-livechat-pro' ),
                    self::MIN_WP,
                    $wp
                ),
            );
        }

        return array( 'ok' => true, 'message' => '' );
    }

    private static function register_notice() {
        if ( is_admin() ) {
            add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
        }
    }

    public static function admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $error = get_option( self::ERROR_OPTION, array() );
        if ( ! is_array( $error ) || empty( $error['message'] ) ) {
            return;
        }

        $details = sprintf(
            '%s%s%s',
            isset( $error['module'] ) ? '[' . sanitize_key( $error['module'] ) . '] ' : '',
            sanitize_text_field( $error['message'] ),
            ! empty( $error['file'] ) ? ' (' . sanitize_file_name( $error['file'] ) . ':' . absint( $error['line'] ) . ')' : ''
        );

        echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'N8 LiveChat Pro Recovery Guard:', 'n8-livechat-pro' ) . '</strong> ' . esc_html( $details ) . '</p>';
        echo '<p>' . esc_html__( 'WordPress was kept online. Resolve the reported module/server issue, then reload this page; the platform schema repair will retry safely for administrators.', 'n8-livechat-pro' ) . '</p></div>';
    }

    private static function deactivate_self() {
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( function_exists( 'deactivate_plugins' ) ) {
            deactivate_plugins( plugin_basename( N8LC_FILE ), true );
        }
    }

    private static function activation_stop( $message ) {
        if ( function_exists( 'wp_die' ) ) {
            wp_die(
                esc_html( $message ),
                esc_html__( 'N8 LiveChat Pro activation stopped safely', 'n8-livechat-pro' ),
                array( 'back_link' => true )
            );
        }
    }
}
