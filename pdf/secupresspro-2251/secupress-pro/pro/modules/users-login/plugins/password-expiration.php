<?php
/**
 * Module Name: Password Lifetime
 * Description: Ask users to change their password after it is expired.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** HOOKS ======================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

add_action( 'user_profile_update_errors', 'secupress_pro_password_expiration_get_raw_password', 10, 3 );
/**
 * Fires before user profile update errors are returned in `edit_user()`.
 * Since `wp_insert_user()` won't allow it, we use this hook to get the raw value of the new pass.
 *
 * @since 1.0
 *
 * @param (object) $errors WP_Error object, passed by reference.
 * @param (bool)   $update Whether this is a user update.
 * @param (object) $user   WP_User object, passed by reference.
 */
function secupress_pro_password_expiration_get_raw_password( $errors, $update, $user ) {
	$pass = ! empty( $user->user_pass ) ? $user->user_pass : null;
	secupress_cache_data( 'new-password', $pass );
}


add_action( 'profile_update', 'secupress_pro_password_expiration_profile_update', 10, 2 );
/**
 * When the password of an existing user is updated, keep track of the date.
 *
 * @since 1.0
 * @author Greg
 *
 * @param (int)    $user_id       User ID.
 * @param (object) $old_user_data Object containing user's data prior to update.
 */
function secupress_pro_password_expiration_profile_update( $user_id, $old_user_data ) {
	$new_user_data = get_userdata( $user_id );

	if ( hash_equals( $new_user_data->user_pass, $old_user_data->user_pass ) ) {
		return;
	}

	if ( $new_pass = secupress_cache_data( 'new-password' ) ) {
		// Compare new password with old hash.
		$is_same_pass = wp_check_password( $new_pass, $old_user_data->user_pass, $user_id );
		secupress_cache_data( 'new-password', null );
	} else {
		// Hashes can be different for a same password. Sadly we can't do anything without the new or old unencrypted password.
		$is_same_pass = false;
	}

	if ( ! $is_same_pass ) {
		secupress_pro_password_expiration_update_date( $user_id );
	}
}


add_action( 'user_register',                             'secupress_pro_password_expiration_update_date' );
add_action( 'secupress.pluggable.user_password_changed', 'secupress_pro_password_expiration_update_date' );
/**
 * When a new user is registered or when a user's password is changed, keep track of the date.
 *
 * @since 1.0
 * @author Greg
 *
 * @param (int) $user_id User ID.
 */
function secupress_pro_password_expiration_update_date( $user_id ) {
	update_user_meta( $user_id, 'secupress_password_update_date', time() );
}


add_action( 'admin_init', 'secupress_pro_password_expiration_maybe_display_notice' );
/**
 * Display a notice to the user if his/her password expired.
 *
 * @since 1.0
 * @author Greg
 */
function secupress_pro_password_expiration_maybe_display_notice() {
	$user = wp_get_current_user();

	if ( ! secupress_is_affected_role( 'users-login', 'password-policy', $user ) ) {
		return;
	}

	if ( ! secupress_pro_password_expiration_is_user_password_expired( $user ) ) {
		return;
	}

	$message  = '<p>' . sprintf( __( '%s:', 'secupress' ), '<strong>' . SECUPRESS_PLUGIN_NAME . '</strong>' ) . ' ';
	$message .= sprintf( __( 'Your password expired, please <a href="%s">choose a new one</a>.', 'secupress' ), esc_url( self_admin_url( 'profile.php' ) ) . '#password' );
	$message .= '</p>';

	secupress_add_notice( $message, 'error', '' );
}


/** --------------------------------------------------------------------------------------------- */
/** TOOLS ======================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Tell if a user's password expired.
 *
 * @since 1.0
 * @author Greg
 *
 * @param (object|int) $user WP_User object or user ID.
 *
 * @return (bool) True if expired. False otherwise.
 */
function secupress_pro_password_expiration_is_user_password_expired( $user ) {
	if ( ! secupress_is_user( $user ) ) {
		$user = get_userdata( $user );
	}

	if ( ! secupress_is_user( $user ) ) {
		return false;
	}

	$expiration = secupress_get_module_option( 'password-policy_password_expiration', 30, 'users-login' );
	$date       = time() - $expiration * DAY_IN_SECONDS;
	$meta       = get_user_meta( $user->ID, 'secupress_password_update_date', true );

	if ( $meta ) {
		return $meta < $date;
	}

	// Fallback for users without the meta.
	$registered = mysql2date( 'U', $user->user_registered );

	return $registered < $date;
}
