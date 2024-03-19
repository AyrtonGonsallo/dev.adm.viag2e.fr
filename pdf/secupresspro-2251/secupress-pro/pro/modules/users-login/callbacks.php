<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ON MODULE SETTINGS SAVE ===================================================================== */
/** --------------------------------------------------------------------------------------------- */

add_filter( 'secupress_users-login_settings_callback', 'secupress_pro_move_login_settings_callback', 10, 2 );
/**
 *  Move Login Pro plugin. Sanitize and validate Pro settings.
 *
 * @since 1.4.9
 *
 * @param (array) $settings  The module settings.
 */
function secupress_pro_move_login_settings_callback( $settings, $activate ) {
	if ( isset( $activate['move-login_activated'] ) ) {
		$allowed_slugs                               = [ 'sperror' => 1, 'custom_error' => 1, 'custom_page' => 1 ];
		$settings['move-login_whattodo']             = isset( $allowed_slugs[ $settings['move-login_whattodo'] ] ) ? $settings['move-login_whattodo'] : 'sperror';
		$settings['move-login_custom_error_content'] = wp_kses_post( wpautop( $settings['move-login_custom_error_content'] ) );
		$settings['move-login_custom_page_url']      = isset( $settings['move-login_custom_page_url'] ) ? wp_validate_redirect( $settings['move-login_custom_page_url'], home_url() ) : home_url();
	}
	return $settings;
}
