<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

if ( ! function_exists( 'wp_set_password' ) ) :
	/**
	 * Updates the user's password with a new encrypted one.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 * @global (object) $wpdb WordPress database abstraction object.
	 *
	 * @param (string) $password The plaintext new user password.
	 * @param (int)    $user_id  User ID.
	 */
	function wp_set_password( $password, $user_id ) {
		global $wpdb;

		$old_hash = get_userdata( $user_id )->user_pass;

		$hash = wp_hash_password( $password );
		$wpdb->update( $wpdb->users, array( 'user_pass' => $hash, 'user_activation_key' => '' ), array( 'ID' => $user_id ) );

		wp_cache_delete( $user_id, 'users' );

		if ( ! hash_equals( $old_hash, $hash ) && ! wp_check_password( $password, $old_hash, $user_id ) ) {
			/**
			 * Triggers right after the user password has changed.
			 *
			 * @since 1.0
			 *
			 * @param (int) $user_id  User ID.
			 */
			do_action( 'secupress.pluggable.user_password_changed', $user_id );
		}
	}
endif;
