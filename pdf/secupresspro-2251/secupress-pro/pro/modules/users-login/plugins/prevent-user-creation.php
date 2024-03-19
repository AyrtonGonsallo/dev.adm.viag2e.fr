<?php
/**
 * Module Name: Prevent User creation
 * Description: Do not allow attacks exploit flaws to add users on your website
 * Main Module: users_login
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

if ( get_option( 'users_can_register' ) ) {
	return;
}

add_filter( 'wp_pre_insert_user_data', 'secupress_wp_pre_insert_user_data', 10, 2 );
function secupress_wp_pre_insert_user_data( $data, $update ) {
	return $update ? $data : [];
}

add_filter( 'user_has_cap', '__user_has_cap' );
/**
 * Remove the create_users cap to anyone
 *
 * @since 1.4.9.5
 * @author Julio Potier
 *
 * @hook user_has_cap
 * @param (array) $caps The user's caps
 * @return (array) $caps The user's caps
 **/
function __user_has_cap( $caps ) {
	unset( $caps['create_users'] );
	return $caps;
}
