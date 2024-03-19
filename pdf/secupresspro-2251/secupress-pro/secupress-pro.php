<?php
/**
 * Plugin Name: SecuPress Pro — WordPress Security
 * Plugin URI: https://secupress.me
 * Description: More than a plugin, the guarantee of a protected website by experts.
 * Author: SecuPress
 * Author URI: https://secupress.me
 * Version: 2.2.5.1
 * Code Name: Python (Mark XX)
 * Network: true
 * Contributors: SecuPress, juliobox, GregLone
 * License: GPLv2
 * Domain Path: /languages/
 * Requires at least: 4.9
 * Requires PHP: 5.6
 * Copyright 2012-2024 SecuPress
 */

update_site_option( 'secupress_settings', [ 'site_is_pro' => 1, 'consumer_email' => 'activated@null.market', 'consumer_key' => 'activated' ] );

defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/** --------------------------------------------------------------------------------------------- */
/** DEAL WITH THE FREE VERSION ================================================================== */
/** ----------------------------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'pro/classes/admin/class-secupress-pro-admin-remove-free-plugin.php' );

SecuPress_Pro_Admin_Remove_Free_Plugin::get_instance( __FILE__ );


/** --------------------------------------------------------------------------------------------- */
/** DEFINES ===================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

// Common constants
define( 'SECUPRESS_FILE', __FILE__ );
require_once( plugin_dir_path( __FILE__ ) . 'defines.php' );

// Pro
define( 'SECUPRESS_HTTP_LOGS'                 , 'secupress_http_logs' );
define( 'SECUPRESS_PRO_INC_PATH'              , SECUPRESS_PATH . 'pro' . DIRECTORY_SEPARATOR );
define( 'SECUPRESS_PRO_ADMIN_PATH'            , SECUPRESS_PRO_INC_PATH . 'admin' . DIRECTORY_SEPARATOR );
define( 'SECUPRESS_PRO_CLASSES_PATH'          , SECUPRESS_PRO_INC_PATH . 'classes' . DIRECTORY_SEPARATOR );
define( 'SECUPRESS_PRO_MODULES_PATH'          , SECUPRESS_PRO_INC_PATH . 'modules' . DIRECTORY_SEPARATOR );
define( 'SECUPRESS_PRO_ADMIN_SETTINGS_MODULES', SECUPRESS_PRO_ADMIN_PATH . 'modules' . DIRECTORY_SEPARATOR );
define( 'SECUPRESS_PRO_URL'                   , plugin_dir_url( SECUPRESS_FILE ) . 'pro/' );
define( 'SECUPRESS_GEOIP_KEY'                 , 'r7oPMv4qfptCJkJX' );
define( 'SECUPRESS_PRO_VERSION'               , SECUPRESS_VERSION );


/** --------------------------------------------------------------------------------------------- */
/** INIT ======================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

require_once( SECUPRESS_INC_PATH     . 'secupress.php' );
require_once( SECUPRESS_PRO_INC_PATH . 'functions/pluggable.php' );
require_once( SECUPRESS_PRO_INC_PATH . 'activation.php' );


add_action( 'secupress.loaded', 'secupress_pro_init', 0 );
/**
 * Load the pro version after the free version.
 *
 * @since 1.0
 */
function secupress_pro_init() {
	// Make sure Poedit keeps our plugin headers.
	/** Translators: Plugin Name of the plugin/theme */
	__( 'SecuPress Pro — WordPress Security', 'secupress' );
	/** Translators: Description of the plugin/theme */
	__( 'More than a plugin, the guarantee of a protected website by experts.', 'secupress' );

	// Functions.
	secupress_pro_load_functions();

	// Hooks.
	require_once( SECUPRESS_PRO_INC_PATH . 'common.php' );

	if ( ! is_admin() ) {
		return;
	}

	// Hooks.
	require_once( SECUPRESS_PRO_ADMIN_PATH . 'migrate.php' );

	if ( ! secupress_is_pro() ) {
		return;
	}

	if ( is_admin() ) {
		// Free downgrade.
		SecuPress_Pro_Admin_Free_Downgrade::get_instance();
	}

	require_once( SECUPRESS_PRO_ADMIN_PATH . 'admin.php' );
	require_once( SECUPRESS_PRO_ADMIN_PATH . 'ajax-post-callbacks.php' );
}


/**
 * Include files that contain our functions.
 *
 * @since 1.3
 * @author Grégory Viguier
 */
function secupress_pro_load_functions() {
	static $done = false;

	if ( $done ) {
		return;
	}
	$done = true;

	/**
	 * Require our functions.
	 */
	require_once( SECUPRESS_PRO_INC_PATH . 'functions/deprecated.php' );
	require_once( SECUPRESS_PRO_INC_PATH . 'functions/common.php' );

	if ( ! is_admin() ) {
		return;
	}

	// The Free downgrade class.
	secupress_pro_require_class( 'Admin', 'Free_Downgrade' );
}
