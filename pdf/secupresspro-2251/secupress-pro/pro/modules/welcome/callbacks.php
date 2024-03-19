<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Deal with the settings import.
 *
 * @since 1.0.3
 * @author Julio Potier
 */
function secupress_settings_import_callback() {
	// Make all security tests.
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_welcome_settings-options' );

	$import = ! empty( $_FILES['import'] ) && is_array( $_FILES['import'] ) && isset( $_FILES['import']['type'], $_FILES['import']['name'] ) ? $_FILES['import'] : array();
	$regex  = '/security-settings-(.*)-20\d{2}-\d{2}-\d{2}-[a-f0-9]{13}\.txt/';

	if ( ! $import || 'text/plain' !== $import['type'] || ! preg_match( $regex, $import['name'] ) ) {
		secupress_add_settings_error( 'general', 'settings_updated', __( 'The file was empty.', 'secupress' ), 'error' );
		set_transient( 'settings_errors', secupress_get_settings_errors(), 30 );
		$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
		wp_redirect( esc_url_raw( $goback ) );
		exit;
	}

	$file_name       = $import['name'];
	$_post_action    = $_POST['action'];
	$_POST['action'] = 'wp_handle_sideload';
	$file            = wp_handle_sideload( $import, array( 'mimes' => array( 'txt' => 'text/plain' ) ) );
	$_POST['action'] = $_post_action;
	if ( ! isset( $file['file'] ) ) {
		return;
	}
	$filesystem      = secupress_get_filesystem();
	$settings        = $filesystem->get_contents( $file['file'] );
	$settings        = maybe_unserialize( $settings );

	$filesystem->put_contents( $file['file'], '' );
	$filesystem->delete( $file['file'] );

	if ( is_array( $settings ) ) {
		$settings = array_map( 'maybe_unserialize', $settings );
		array_map( 'update_site_option', array_keys( $settings ), $settings );
		set_site_transient( 'secupress_activation', 1 );
		set_site_transient( 'secupress_pro_activation', 1 );
		secupress_load_plugins();
		secupress_add_settings_error( 'general', 'settings_updated', __( 'Settings imported and saved.', 'secupress' ), 'updated' );
	} else {
		secupress_add_settings_error( 'general', 'settings_updated', __( 'Error: settings could not be imported.', 'secupress' ), 'error' );
	}

	set_transient( 'settings_errors', secupress_get_settings_errors(), 30 );

	/**
	 * Redirect back to the settings page that was submitted.
	 */
	$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
	wp_redirect( esc_url_raw( $goback ) );
	exit;
}
