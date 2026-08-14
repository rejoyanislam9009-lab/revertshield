<?php
/**
 * Plugin Name: N8 LiveChat Pro
 * Plugin URI:  https://github.com/rejoyanislam9009-lab/revertshield
 * Description: Advanced live chat and support inbox for WordPress with agents, departments, typing indicators, attachments, tags, SLA automation, CSAT, analytics, notifications, and signed webhooks.
 * Version:     0.2.0
 * Author:      N8
 * License:     GPL-2.0-or-later
 * Text Domain: n8-livechat-pro
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'N8LC_VERSION', '0.2.0' );
define( 'N8LC_FILE', __FILE__ );
define( 'N8LC_DIR', plugin_dir_path( __FILE__ ) );
define( 'N8LC_URL', plugin_dir_url( __FILE__ ) );

require_once N8LC_DIR . 'includes/class-n8lc-db.php';
require_once N8LC_DIR . 'includes/class-n8lc-security.php';
require_once N8LC_DIR . 'includes/class-n8lc-availability.php';
require_once N8LC_DIR . 'includes/class-n8lc-automation.php';
require_once N8LC_DIR . 'includes/class-n8lc-webhooks.php';
require_once N8LC_DIR . 'includes/class-n8lc-rest.php';
require_once N8LC_DIR . 'includes/class-n8lc-admin.php';
require_once N8LC_DIR . 'includes/class-n8lc-widget.php';
require_once N8LC_DIR . 'includes/class-n8lc-core.php';

register_activation_hook( __FILE__, array( 'N8LC_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'N8LC_Core', 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function () {
        N8LC_Core::instance()->boot();
    }
);
