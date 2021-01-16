<?php

/**
 *
 * @link              http://mone27.net
 * @since             1.0.0
 * @package           custom-action-link
 *
 * @wordpress-plugin
 * Plugin Name:       Action link
 * Plugin URI:        http://example.com/plugin-name-uri/
 * Description:       Custom action link management. Using data from a contact form generate a unique link that when clicked adds a user to pmpro
 * Version:           0.1.0
 * Author:            Simone Massaro
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Quick and Dirty configuration of main parameters. Check includes/custom-action-link-send for mail templates
if (!defined('ACL_FORM_ID')){
    define('ACL_FORM_ID', '16');
}

if (!defined('MEMBERSHIP_LEVEL')){
    define('MEMBERSHIP_LEVEL', '1');
}

if (!defined('ADMIN_EMAIL')){
    define('ADMIN_EMAIL', 'web@ifsa.net');
}

if (!defined('CACL_TABLE_NAME')){
    global $wpdb;
    define('CACL_TABLE_NAME', $wpdb->prefix."action_link_db");
}




/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'automator_action_link_VERSION', '0.1.0' );

/**
 * The code that runs during plugin activation.
 */
function activate_custom_action_link() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/custom-action-link-activator.php';
	cacl_activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_custom_action_link() {
	//require_once plugin_dir_path( __FILE__ ) . 'includes/class-custom-action-link-deactivator.php';
	//automator_action_link_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_custom_action_link' );
register_deactivation_hook( __FILE__, 'deactivate_custom_action_link' );

/** Import the main files */
require_once plugin_dir_path( __FILE__ ) . 'includes/custom-action-link-send.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/custom-action-link-receive.php';

cacl_send_setup_hooks();
cacl_receive_setup_hooks();


