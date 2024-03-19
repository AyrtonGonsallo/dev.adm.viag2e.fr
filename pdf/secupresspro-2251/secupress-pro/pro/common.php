<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

add_action( 'init', 'secupress_init_license_check_cron' );
/**
 * Initiate the cron that will check for the license validity twice-daily.
 *
 * @since 1.0.3
 * @author Grégory Viguier
 */
function secupress_init_license_check_cron() {
	if ( ! wp_next_scheduled( 'secupress_license_check' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'secupress_license_check' );
	}
}


add_action( 'secupress_license_check', 'secupress_license_check_cron' );
/**
 * Cron that will check for the license validity.
 *
 * @since 1.0.3
 * @author Grégory Viguier
 */
function secupress_license_check_cron() {
	if ( ! secupress_is_pro() ) {
		return;
	}

	$url = SECUPRESS_WEB_MAIN . 'key-api/1.0/?' . http_build_query( array(
		'sp_action'  => 'check_pro_license',
		'user_email' => secupress_get_consumer_email(),
		'user_key'   => secupress_get_consumer_key(),
	) );

	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	$body = @json_decode( $body );

	if ( ! is_object( $body ) ) {
		return;
	}

	if ( ! empty( $body->success ) && ! empty( $body->data->site_is_pro ) ) {
		// The license is fine.
		return;
	}

	$options = get_site_option( SECUPRESS_SETTINGS_SLUG );
	$options = is_array( $options ) ? $options : array();
	unset( $options['site_is_pro'] );

	if ( ! empty( $body->data->error ) ) {
		// The error code returned by EDD.
		$options['license_error'] = esc_html( $body->data->error );
	}

	secupress_update_options( $options );
}

add_action( 'template_redirect', 'secupress_talk_to_me' );
/**
 * If plugin is active and license is + correct API (client) key is given, it will print the installed version
 *
 * @since 1.4.6
 * @author Julio Potier
 **/
function secupress_talk_to_me() {
	global $wp_version;
	if ( ! isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || false === strpos( $_SERVER['HTTP_X_REQUESTED_WITH'], 'SECUPRESS' ) ) {
		return;
	}
	$consumer_key   = secupress_get_option( 'consumer_key', '' );
	$consumer_email = secupress_get_option( 'consumer_email', '' );
	$api_key        = str_replace( 'SECUPRESS_API_KEY:', '', $_SERVER['HTTP_X_REQUESTED_WITH'] );
	if ( hash_equals( $consumer_key, $api_key ) ) {
		wp_send_json_success( [ // Need the registered licence info to prevent nulled infos in our backend.
								'license_key'      => $consumer_key,
								'license_email'    => $consumer_email,
								'spversion'        => SECUPRESS_VERSION,
								'wpversion'        => $wp_version,
								'msversion'        => (int) is_multisite(),
								'phpversion'       => phpversion(),
								'scanner_counts'   => secupress_get_scanner_counts(),
								'scanners_results' => secupress_get_scan_results(),
								'move_login_slug'  => secupress_get_module_option( 'move-login_slug-login', 'login', 'users-login' ),
							] );
	} elseif ( ! $consumer_key ) {
		wp_send_json_error( 'license_key' );
	}
}
