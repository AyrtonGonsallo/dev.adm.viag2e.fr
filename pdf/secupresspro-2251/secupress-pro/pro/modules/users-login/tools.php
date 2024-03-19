<?php
defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'secupress.plugin.move-login.deny_login_access', 'secupress_pro_move_login_deny_login_access' );
/**
 * Add custom behaviour for move login
 *
 * @since 2.0
 * @author Julio Potier
 *
 **/
function secupress_pro_move_login_deny_login_access() {

	$setting       = secupress_get_module_option( 'move-login_whattodo', 'sperror', 'users-login' );
	$error_message = '<p>' . __( 'This page does not exist, has moved or you are not allowed to access it.', 'secupress' ) . '</p>';
	$form          = true;
	switch ( $setting ) {
		case 'custom_page':
			$url  = secupress_get_module_option( 'move-login_custom_page_url', home_url(), 'users-login' );
			$page = wp_validate_redirect( $url, home_url() );
			wp_redirect( $page, 301 );
			die();
		break;

		case 'custom_error':
			$setting_message = wp_kses_post( secupress_get_module_option( 'move-login_custom_error_content', '', 'users-login' ) );
			$error_message   = ! empty( $setting_message ) ? $setting_message : $error_message;
		break;
	}
	echo '<style>';
	include( ABSPATH . WPINC . '/js/tinymce/skins/wordpress/wp-content.css' );
	echo '</style>';

	secupress_die( secupress_check_ban_ips_form( [ 'content'  => $error_message ] ), '', array( 'force_die' => true ) );

}
