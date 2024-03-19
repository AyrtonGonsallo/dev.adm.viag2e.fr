<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ADMIN POST / AJAX CALLBACKS FOR THE SETTINGS PAGE =========================================== */
/** ----------------------------------------------------------------------------------------------*/

add_action( 'admin_post_secupress_update_global_settings_wl', 'secupress_global_settings_white_label_ajax_post_cb' );
/**
 * Deal with the white label.
 *
 * @since 1.0.3
 * @author Grégory Viguier
 */
function secupress_global_settings_white_label_ajax_post_cb() {
	// Make all security tests.
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_update_global_settings_wl' );

	if ( ! defined( 'WP_SWL' ) || ! WP_SWL ) {
		wp_die(
			'<h1>' . __( 'Something went wrong.', 'secupress' ) . '</h1>' .
			/** Translators: %s is a php constant name. */
			'<p>' . sprintf( __( 'Sorry, you must define the %s constant first.', 'secupress' ), '<code>WP_SWL</code>' ) . '</p>',
			403
		);
	}

	// Previous values.
	$old_values = get_site_option( SECUPRESS_SETTINGS_SLUG );
	$old_values = is_array( $old_values ) ? $old_values : array();
	$old_email  = ! empty( $old_values['consumer_email'] ) ? sanitize_email( $old_values['consumer_email'] )    : '';
	$old_key    = ! empty( $old_values['consumer_key'] )   ? sanitize_text_field( $old_values['consumer_key'] ) : '';
	unset( $old_values['sanitized'], $old_values['wl_plugin_name'], $old_values['wl_plugin_URI'], $old_values['wl_author'], $old_values['wl_author_URI'], $old_values['wl_description'] );

	if ( ! $old_email || ! $old_key || empty( $old_values['site_is_pro'] ) ) {
		secupress_add_settings_error( 'general', 'settings_updated', __( 'Sorry, you must activate your Pro license to use the White Label.', 'secupress' ), 'error' );
		set_transient( 'settings_errors', secupress_get_settings_errors(), 30 );
		$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
		wp_redirect( esc_url_raw( $goback ) );
		exit;
	}

	// New values.
	$values = ! empty( $_POST['secupress_settings'] ) && is_array( $_POST['secupress_settings'] ) ? $_POST['secupress_settings'] : array();
	$values = secupress_array_merge_intersect( $values, array(
		'wl_plugin_name' => '',
		'wl_plugin_URI'  => '',
		'wl_description' => '',
		'wl_author'      => '',
		'wl_author_URI'  => '',
	) );

	$values['wl_plugin_name'] = sanitize_text_field( $values['wl_plugin_name'] );

	if ( $values['wl_plugin_name'] && 'SecuPress' !== $values['wl_plugin_name'] ) {
		$values['wl_plugin_URI'] = esc_url_raw( $values['wl_plugin_URI'] );
		$values['wl_author']     = sanitize_text_field( $values['wl_author'] );
		$values['wl_author_URI'] = esc_url_raw( $values['wl_author_URI'] );

		foreach ( array( 'wl_plugin_URI', 'wl_author', 'wl_author_URI' ) as $key ) {
			if ( ! $values[ $key ] ) {
				unset( $values[ $key ] );
			}
		}

		if ( $values['wl_description'] ) {
			$values['wl_description'] = str_replace( "\n", '##SECUPRESS_LINEBREAK_PLACEHOLDER##', $values['wl_description'] );
			$values['wl_description'] = sanitize_text_field( $values['wl_description'] );
			$values['wl_description'] = str_replace( '##SECUPRESS_LINEBREAK_PLACEHOLDER##', "\n", $values['wl_description'] );
		} else {
			unset( $values['wl_description'] );
		}
	} else {
		unset( $values['wl_plugin_name'], $values['wl_plugin_URI'], $values['wl_author'], $values['wl_author_URI'], $values['wl_description'] );
	}

	$values = array_merge( $old_values, $values );

	// Finally, save.
	secupress_update_options( $values );

	// Trick the referrer for the redirection.
	$old_slug = 'page=' . SECUPRESS_PLUGIN_SLUG . '_settings';
	$new_slug = ! empty( $values['wl_plugin_name'] ) ? sanitize_title( $values['wl_plugin_name'] ) : 'secupress';
	$new_slug = 'page=' . $new_slug . '_settings';

	if ( $old_slug !== $new_slug ) {
		$_REQUEST['_wp_http_referer'] = str_replace( $old_slug, $new_slug, wp_get_raw_referer() );
	}

	/**
	 * Handle settings errors and return to settings page.
	 */
	// If no settings errors were registered add a general 'updated' message.
	if ( ! secupress_get_settings_errors( 'general' ) ) {
		secupress_add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
	}
	set_transient( 'settings_errors', secupress_get_settings_errors(), 30 );

	/**
	 * Redirect back to the settings page that was submitted.
	 */
	$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
	wp_redirect( esc_url_raw( $goback ) );
	exit;
}


add_action( 'admin_post_secupress_export', 'secupress_export_ajax_post_cb' );
/**
 * This function will force the direct download of the plugin's options, compressed.
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_export_ajax_post_cb() {
	global $wpdb;

	// Make all security tests.
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_export' );

	$website_clean   = sanitize_title_with_dashes( str_replace( [ 'http://', 'https://', '.' ], [ '', '', '-' ], home_url() ) );
	$filename        = sprintf( 'security-settings-(%s)-%s-%s.txt', $website_clean, date( 'Y-m-d' ), uniqid() );

	if ( is_multisite() ) {
		$options = $wpdb->get_results( 'SELECT meta_key, meta_value FROM ' . $wpdb->sitemeta . ' WHERE meta_key LIKE "%secupress%"', OBJECT_K );
		$options = wp_list_pluck( $options, 'meta_value' );
	} else {
		$options = $wpdb->get_results( 'SELECT option_name, option_value FROM ' . $wpdb->options . ' WHERE option_name LIKE "%secupress%"', OBJECT_K );
		$options = wp_list_pluck( $options, 'option_value' );
	}

	// Remove our current IP is present.
	$options[ SECUPRESS_BAN_IP ] = ! empty( $options[ SECUPRESS_BAN_IP ] ) ? unserialize( $options[ SECUPRESS_BAN_IP ] ) : array();

	unset(
		$options[ SECUPRESS_FULL_FILETREE ],
		$options[ SECUPRESS_WP_CORE_FILES_HASHES ],
		$options['secupress_step1_report'],
		$options[ SECUPRESS_SCAN_TIMES ],
		$options[ SECUPRESS_BAN_IP ][ secupress_get_ip() ]
	);

	if ( empty( $options[ SECUPRESS_BAN_IP ] ) ) {
		unset( $options[ SECUPRESS_BAN_IP ] );
	} else {
		$options[ SECUPRESS_BAN_IP ] = serialize( $options[ SECUPRESS_BAN_IP ] );
	}

	foreach ( SecuPress_Scanner_Results::get_scanners() as $scan_name => $class_name_part ) {
		unset(
			$options[ SecuPress_Scanner_Results::SCAN_OPTION_PREFIX . $scan_name ],
			$options[ SecuPress_Scanner_Results::FIX_OPTION_PREFIX . $scan_name ]
		);
	}

	foreach ( SecuPress_Scanner_Results::get_scanners_for_ms_sites() as $scan_name => $class_name_part ) {
		unset( $options[ SecuPress_Scanner_Results::MS_OPTION_PREFIX . $scan_name ] );
	}

	$options = maybe_serialize( $options );

	nocache_headers();
	@header( 'Content-Type: text/plain' );
	@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	@header( 'Content-Transfer-Encoding: binary' );
	@header( 'Content-Length: ' . strlen( $options ) );
	@header( 'Connection: close' );
	echo $options;
	exit();
}

add_action( 'admin_post_secupress_passwordless_confirmation', 'secupress_passwordless_confirmation_ajax_post_cb' );
/**
 * Will confirm the activation of PasswordLess module by adding an option into ours.
 *
 * @return void
 * @author Julio potier
 * @since 1.3.2
 **/
function secupress_passwordless_confirmation_ajax_post_cb() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! check_ajax_referer( 'secupress_passwordless_confirmation', '_wpnonce' ) ) {
		wp_nonce_ays( '' );
	}

	$options = get_site_option( SECUPRESS_SETTINGS_SLUG );
	$options['secupress_passwordless_activation_validation'] = true;
	secupress_update_options( $options );

	wp_redirect( secupress_admin_url( 'modules', 'users-login' ) );
	die();
}
/** --------------------------------------------------------------------------------------------- */
/** OTHER ADMIN POST / AJAX CALLBACKS =========================================================== */
/** ----------------------------------------------------------------------------------------------*/

add_action( 'admin_post_secupress_export_pdf', 'secupress_export_pdf_ajax_post_cb' );
/**
 * Export the security scan as PDF
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_export_pdf_ajax_post_cb() {
	// Make all security tests.
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_export_pdf' );

	if ( ! class_exists( 'FPDF' ) ) {
		require_once( SECUPRESS_PRO_CLASSES_PATH . 'vendors/pdf/fpdf.php' );
	}
	secupress_pro_require_class( 'admin', 'scan-report-pdf' );

	$pdf = new SecuPress_Pro_Admin_Scan_Report_PDF();
	$pdf->AliasNbPages();
	$pdf->AddPage();
	$pdf->SetFont( 'Arial', '', 12 );
	$pdf->grade();
	$pdf->print_modules();
	$pdf->Output( 'D', SECUPRESS_PLUGIN_SLUG . '-security-report-' . date( 'Y-m-d-H-i-s', current_time( 'timestamp' ) ) . '.pdf' );
}

add_action( 'admin_post_secupress_geoips_update_data', 'secupress_geoips_update_data_ajax_post_cb' );
/**
 * Update the file + database for GeoIP module
 *
 * @since 1.4.6
 * @author Julio Potier
 */
function secupress_geoips_update_data_ajax_post_cb() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! check_ajax_referer( 'secupress_geoips_update_data' ) ) {
		wp_nonce_ays( '' );
	}
	if ( is_null( secupress_get_module_option( 'geoip-system_type', null, 'firewall' ) ) ) {
		secupress_add_transient_notice( __( 'GeoIP database not updated.', 'secupress' ) );
		wp_safe_redirect( wp_get_referer() );
		die();
	}
	if ( secupress_geoips_update_datas() ) {
		secupress_add_transient_notice( __( 'GeoIP database updated.', 'secupress' ) );
	} else {
		secupress_add_transient_notice( __( 'GeoIP database not updated.', 'secupress' ) );
	}
	wp_safe_redirect( wp_get_referer() );
	die();
}
