<?php
/**
 * Module Name: Sessions Control
 * Description: Control user sessions.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 1.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

/**
 * Copy/paste of the retrieve_password() function from WP Core but with a parameter.
 *
 * @param (string) $user_login Either a username or email.
 * @return (object|bool) $errors|true
 * @author Julio Potier
 **/
function secupress_retrieve_password( $user_login ) {
	global $wpdb, $wp_hasher;

	$errors = new WP_Error();

	$user_data = get_user_by( 'login', sanitize_user( $user_login ) );

	/**
	 * Fires before errors are returned from a password reset request.
	 *
	 * @since 2.1.0
	 * @since 4.4.0 Added the `$errors` parameter.
	 *
	 * @param WP_Error $errors A WP_Error object containing any errors generated
	 *						 by using invalid credentials.
	 */
	do_action( 'lostpassword_post', $errors );

	if ( $errors->get_error_code() ) {
		return $errors;
	}

	if ( ! $user_data ) {
		$errors->add( 'invalidcombo', __( '<strong>Error</strong>: Invalid username or email.', 'secupress' ) );
		return $errors;
	}

	// Redefining user_login ensures we return the right case in the email.
	$user_login = $user_data->user_login;
	$user_email = $user_data->user_email;
	$key        = get_password_reset_key( $user_data );

	if ( is_wp_error( $key ) ) {
		return $key;
	}

	if ( is_multisite() ) {
		$blogname = $GLOBALS['current_site']->site_name;
	} else {
		/*
		 * The blogname option is escaped with esc_html on the way into the database
		 * in sanitize_option we want to reverse this for the plain text arena of emails.
		 */
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}

	$message  = __( 'Someone requested that the password be reset for the following account:' ) . "\r\n\r\n";
	$message .= network_home_url( '/' ) . "\r\n";
	$message .= esc_html( $blogname ) . "\r\n\r\n";
	$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
	$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
	$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
	$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . ">\r\n";

	$title = sprintf( __( '[%s] Password Reset' ), $blogname );

	/**
	 * Filter the subject of the password reset email.
	 *
	 * @since 2.8.0
	 * @since 4.4.0 Added the `$user_login` and `$user_data` parameters.
	 *
	 * @param string  $title	  Default email title.
	 * @param string  $user_login The username for the user.
	 * @param WP_User $user_data  WP_User object.
	 */
	$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

	/**
	 * Filter the message body of the password reset mail.
	 *
	 * @since 2.8.0
	 * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
	 *
	 * @param string  $message	Default mail message.
	 * @param string  $key		The activation key.
	 * @param string  $user_login The username for the user.
	 * @param WP_User $user_data  WP_User object.
	 */
	$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

	if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
		wp_die( __( 'The email could not be sent.', 'secupress' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.', 'secupress' ) );
	}

	return true;
}

add_action( 'admin_post_resetpassword', 'secupress_admin_post_resetpassword' );
/**
 * Will send the reset link
 *
 * @since 1.4.3
 * @author Julio Potier
 **/
function secupress_admin_post_resetpassword() {

	// Validate the nonce for this action.
	$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
	check_admin_referer( 'reset-user-password_' . $user_id );

	// Verify user capabilities.
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		secupress_add_transient_notice( __( 'Password has not been reset and email has not been sent.', 'secupress' ), 'error', 'secupress-resetpassword' );
		wp_redirect( wp_get_referer() );
		die();
	}

	// Send the password reset link.
	$user	= get_userdata( $user_id );
	$result = secupress_retrieve_password( $user->user_login );

	if ( ! $result ) {
		secupress_add_transient_notice( __( 'Password has not been reset and email has not been sent.', 'secupress' ), 'error', 'secupress-resetpassword' );
		wp_redirect( wp_get_referer() );
		die();
	}

	secupress_add_transient_notice( __( 'Password has been reset and email has been sent.', 'secupress' ), 'updated', 'secupress-resetpassword' );
	wp_redirect( wp_get_referer() );
	die();
}

add_filter( 'user_row_actions', 'secupress_add_reset_link', 10, 2 );
/**
 * Will add a reset action link in user's row
 *
 * @return (array)  $actions
 * @param (array)   $actions Actions from WP, we can add/remove.
 * @param (WP_User) $user_object The actual user in the table iteration loop.
 * @author Julio Potier
 **/
function secupress_add_reset_link( $actions, $user_object ) {
	$actions['reset_link'] = '<a class="resetpassword" href="' . wp_nonce_url( admin_url( 'admin-post.php?action=resetpassword&amp;user_id=' . $user_object->ID ), 'reset-user-password_' . $user_object->ID ) . '">' . __( 'Reset password' ) . '</a>';
	return $actions;
}

/** --------------------------------------------------------------------------------------------- */
/** INIT ======================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

add_action( 'admin_init', 'secupress_pro_sessions_control_init' );
/**
 * Plugin init.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_init() {
	global $pagenow;


	if ( ! current_user_can( secupress_pro_sessions_control_get_capability() ) ) {
		return;
	}

	add_action( 'admin_post_secupress-destroy-user-sessions', 'secupress_pro_sessions_control_destroy_user_sessions_cb' );
	add_action( 'admin_post_secupress-destroy-all-sessions',  'secupress_pro_sessions_control_destroy_all_sessions_cb' );
	add_action( 'all_admin_notices',          'secupress_pro_sessions_control_display_notice' );

	if ( 'users.php' !== $pagenow ) {
		return;
	}
	add_action( 'admin_print_styles',         'secupress_pro_sessions_control_print_styles', 100 );
	add_action( 'admin_print_scripts',        'secupress_pro_sessions_control_enqueue_scripts' );

	add_action( 'restrict_manage_users',      'secupress_pro_sessions_control_display_destroy_all_button' );
	add_filter( 'manage_users_columns',       'secupress_pro_sessions_control_users_column_title' );
	add_filter( 'manage_users_custom_column', 'secupress_pro_sessions_control_users_column_content', 10, 3 );

}


/** --------------------------------------------------------------------------------------------- */
/** CSS / JS ==================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Print plugin CSS.
 * The column width in the users list table can be "translated" to fit the buttons width.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_print_styles() {
	$width = _x( '13', 'sessions column width in "em"', 'secupress' );

	echo '<style type="text/css">
	.tablenav [name="changeit"], .tablenav [name="changeit2"]{margin-right:16px}
	.secupress-destroy-all-sessions{top:-2px;position:relative;display:inline-block !important}
	.manage-column.column-secupress-sessions{width:' . $width . 'em}
</style>' . "\n";
}


/**
 * Enqueue plugin JS scripts.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_enqueue_scripts() {
	$suffix   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$version  = $suffix ? SECUPRESS_PRO_VERSION : time();
	$base_url = plugin_dir_url( SECUPRESS_FILE ) . 'inc/modules/users-login/plugins/inc/';

	wp_enqueue_script( 'secupress-sessions-control', "{$base_url}js/sessions-control{$suffix}.js", array( 'jquery', 'common', 'secupress-wordpress-js' ), $version, true );

	wp_localize_script( 'secupress-sessions-control', 'spSessionsControlL10n', array(
		'hasDismissibleNotices' => secupress_wp_version_is( '4.4' ),
		'userId'                => get_current_user_id(),
		'currentUserCellText'   => __( 'You are only logged in here', 'secupress' ),
		'otherUsersCellText'    => __( 'Disconnected', 'secupress' ),
		'destroySessionsText'   => __( 'Destroy sessions', 'secupress' ),
		'bulkNonce'             => wp_create_nonce( 'secupress-destroy-user-sessions-' . get_current_user_id() ),
	) );
}


/** --------------------------------------------------------------------------------------------- */
/** USERS LIST ================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Display a "Destroy all sessions" button before and after the users list.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_display_destroy_all_button() {
	$url     = add_query_arg( [ 'action' => 'secupress-destroy-all-sessions' ], self_admin_url( 'admin-post.php' ) );
	$url     = wp_nonce_url( $url, 'secupress-destroy-all-sessions' );

	echo '<a href="' . esc_url( $url ) . '" class="button secupress-destroy-all-sessions">' . __( 'Destroy all sessions', 'secupress' ) . '</a>';
}


/**
 * Filter the users list table columns to add our.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (array) $columns An array of columns.
 *
 * @return (array)
 */
function secupress_pro_sessions_control_users_column_title( $columns ) {
	$columns['secupress-sessions'] = __( 'Sessions', 'secupress' );
	return $columns;
}


/**
 * Filter the display output of our column content in the users list table.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (string) $output      Column output.
 * @param (string) $column_name Column name.
 * @param (int)    $user_id     ID of the currently-listed user.
 *
 * @return (string) The column content.
 */
function secupress_pro_sessions_control_users_column_content( $output, $column_name, $user_id ) {
	if ( 'secupress-sessions' !== $column_name ) {
		return $output;
	}

	$sessions_inst = WP_Session_Tokens::get_instance( $user_id );
	$all_sessions  = $sessions_inst->get_all();

	// No sessions.
	if ( ! $all_sessions ) {
		return '<em>' . __( 'Disconnected', 'secupress' ) . '</em>';
	}

	$count_sessions = count( $all_sessions );
	$id             = "user-{$user_id}-details";

	if ( get_current_user_id() === $user_id && 1 === $count_sessions ) {
		return '<em>' . __( 'You are only logged in here', 'secupress' ) . '</em>';
	}

	if ( get_current_user_id() === $user_id ) {
		$label  = sprintf( __( 'You have %s sessions', 'secupress' ), $count_sessions );
		$button = __( 'Destroy other sessions', 'secupress' );
	} else {
		$label  = sprintf( _n( '%s session', '%s sessions', $count_sessions, 'secupress' ), $count_sessions );
		$button = _n( 'Destroy session', 'Destroy all sessions', $count_sessions, 'secupress' );
	}

	if ( get_current_user_id() === $user_id ) {
		$class = 'thickbox button button-secondary current-user';
	} else {
		$class = 'thickbox button button-secondary';
	}
	$output .= '<a name="' . __( 'Sessions Details', 'secupress' ) . '" href="#TB_inline?height=300&amp;width=400&amp;inlineId=' . $id . '" class="' . $class . '">' . $label . '</a>' . "\n";

	$url = add_query_arg( [ 'action' => 'secupress-destroy-user-sessions', 'user' => $user_id ], self_admin_url( 'admin-post.php' ) );
	$url = wp_nonce_url( $url, 'secupress-destroy-user-sessions' );

	$output .= '<div class="hide-if-js" id="' . $id . '">';
	$output .= '<div class="secupress-thickbox-fixed" style="width:0px"><a href="' . esc_url( $url ) . '" class="button button-small secupress-button secupress-button-secondary secupress-destroy-sessions">' . $button . '</a></div><hr>';
	foreach ( $all_sessions as $i => $session ) {
		$session['login']      = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $session['login'] );
		$session['expiration'] = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $session['expiration'] );
		$output .= '<p><b>' . __( 'Session #', 'secupress' ) . ( $i + 1 ) . '</b><br>';
		$output .= sprintf( __( 'Logged-in since <em><abbr title="%5$s">%1$s</abbr></em><br>Expires in <em><abbr title="%6$s">%2$s</abbr></em><br>IP: <code>%3$s</code><br>User-Agent: <code>%4$s</code>.', 'secupress' ),
						$session['login'],
						$session['expiration'],
						$session['ip'],
						$session['ua'],
						date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $session['login'] ) ),
						date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $session['expiration'] ) )
				)
		. "</p><hr>\n";
	}
	$output .= '</div>';

	return $output;
}

add_action( 'admin_print_footer_scripts-users.php', 'add_thickbox' );

/** --------------------------------------------------------------------------------------------- */
/** ACTION CALLBACKS ============================================================================ */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Callback to destroy all sessions for a user (no JS).
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_destroy_user_sessions_cb() {


	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'secupress-destroy-user-sessions' ) ) {
		wp_die( __( 'Something went wrong.', 'secupress' ) );
	}

	$user = isset( $_GET['user'] ) ? get_userdata( (int) $_GET['user'] ) : 0;

	if ( ! secupress_is_user( $user ) ) {
		// Asked for only one user, let's display an error message.
		wp_safe_redirect( esc_url_raw( add_query_arg( array( 'update' => 'err_destroy_sessions_user' ), $redirect ) ) );
		die();
	}

	$sessions = WP_Session_Tokens::get_instance( $user->ID );

	if ( get_current_user_id() === $user->ID ) {
		$sessions->destroy_others( wp_get_session_token() );
	} else {
		$sessions->destroy_all();
	}

	// Send a response.
	$args     = [ 'update' => 'destroyed_sessions', 'id' => $user->ID ];
	$redirect = 'users.php' . ( ! empty( $_GET['paged'] ) ? '?paged=' . (int) $_GET['paged'] : '' );

	wp_safe_redirect( esc_url_raw( add_query_arg( $args, $redirect ) ) );
	die();
}


/**
 * Callback to destroy all sessions for all users (no JS).
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_destroy_all_sessions_cb() {
	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'secupress-destroy-all-sessions' ) ) {
		wp_die( __( 'Something went wrong.', 'secupress' ) );
	}

	// Get current user session.
	$sessions_inst   = WP_Session_Tokens::get_instance( get_current_user_id() );
	$token_to_keep   = wp_get_session_token();
	$session_to_keep = $sessions_inst->get( $token_to_keep );

	// Armageddon.
	/** This filter is documented in /wp-includes/session.php */
	$manager = apply_filters( 'session_token_manager', 'WP_User_Meta_Session_Tokens' );
	call_user_func( array( $manager, 'destroy_all_for_all_users' ) );

	// Recreate current session.
	$sessions_inst->update( $token_to_keep, $session_to_keep );

	// Send a response.
	$redirect = 'users.php' . ( ! empty( $_GET['paged'] ) ? '?paged=' . (int) $_GET['paged'] : '' );
	wp_safe_redirect( esc_url_raw( add_query_arg( array( 'update' => 'destroyed_all_sessions' ), $redirect ) ) );
	die();
}


/**
 * Display a notice after destroying a user sessions (no JS).
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_display_notice() {
	if ( empty( $_GET['update'] ) ) {
		return;
	}

	switch ( $_GET['update'] ) :
		case 'err_destroy_sessions_user' :
			// Failed to get the user.
			echo '<div id="secupress-sessions-control-notice" class="error notice is-dismissible"><p>' . __( 'Error: wrong user.', 'secupress' ) . '</p></div>';
			break;

		case 'destroyed_sessions' :
			// Several users.
			if ( empty( $_GET['id'] ) ) {
				echo '<div id="secupress-sessions-control-notice" class="updated notice is-dismissible"><p>' . __( 'Selected users have been logged out.', 'secupress' ) . '</p></div>';
				break;
			}

			// Only one user.
			$user = get_userdata( (int) $_GET['id'] );

			if ( ! secupress_is_user( $user ) ) {
				return;
			}

			if ( get_current_user_id() === $user->ID ) {
				$message = __( 'You are now logged out everywhere else.' );
			} else {
				$message = sprintf( __( '<strong>%s</strong> has been logged out.' ), $user->display_name );
			}

			echo '<div id="secupress-sessions-control-notice" class="updated notice is-dismissible"><p>' . $message . '</p></div>';
			break;

		case 'destroyed_all_sessions' :
			// All users.
			echo '<div id="secupress-sessions-control-notice" class="updated notice is-dismissible"><p>' . __( 'You are now logged out everywhere else and all other users have been logged out.', 'secupress' ) . '</p></div>';
			break;
	endswitch;
}


add_action( 'wp_ajax_secupress-destroy-user-sessions', 'secupress_pro_sessions_control_destroy_user_sessions_ajax_cb' );
/**
 * Callback to destroy all sessions for a user (JS).
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_destroy_user_sessions_ajax_cb() {
	if ( empty( $_GET['users'] ) || ! is_array( $_GET['users'] ) ) {
		wp_send_json_error();
	}

	$users = array_filter( array_map( 'absint', $_GET['users'] ) );
	$users = array_flip( array_flip( $users ) );

	if ( ! $users ) {
		wp_send_json_error();
	}

	if ( ! current_user_can( secupress_pro_sessions_control_get_capability() ) || ! current_user_can( 'list_users' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to access this page.', 'secupress' ) ) );
	}

	if ( false === check_ajax_referer( 'secupress-destroy-user-sessions-' . get_current_user_id() ) ) {
		wp_send_json_error( array( 'message' => __( 'Something went wrong.', 'secupress' ) ) ); // WP i18n.
	}

	$single_user = count( $users ) === 1;

	foreach ( $users as $i => $user ) {
		$user = get_userdata( $user );

		if ( ! secupress_is_user( $user ) ) {
			if ( $single_user ) {
				// Asked for only one user, let's display an error message.
				wp_send_json_error( array( 'message' => __( 'Error: wrong user.', 'secupress' ) ) );
			}

			unset( $users[ $i ] );
			continue;
		}

		$sessions = WP_Session_Tokens::get_instance( $user->ID );

		if ( get_current_user_id() === $user->ID ) {
			$sessions->destroy_others( wp_get_session_token() );
		} else {
			$sessions->destroy_all();
		}
	}

	$single_user = count( $users ) === 1;

	// Send a response.
	if ( ! $single_user ) {
		$message = __( 'Selected users have been logged out.', 'secupress' );
	} elseif ( get_current_user_id() === $user->ID ) {
		$message = __( 'You are now logged out everywhere else.' );
	} else {
		$message = sprintf( __( '%s has been logged out.' ), $user->display_name );
	}
	wp_send_json_success( array( 'message' => $message, 'ids' => array_values( $users ) ) );
}


add_action( 'wp_ajax_secupress-destroy-all-sessions', 'secupress_pro_sessions_control_destroy_all_sessions_ajax_cb' );
/**
 * Callback to destroy all sessions for all users (JS).
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_sessions_control_destroy_all_sessions_ajax_cb() {
	if ( ! current_user_can( secupress_pro_sessions_control_get_capability() ) || ! current_user_can( 'list_users' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to access this page.', 'secupress' ) ) );
	}

	if ( false === check_ajax_referer( 'secupress-destroy-all-sessions-' . get_current_user_id() ) ) {
		wp_send_json_error( array( 'message' => __( 'Something went wrong.', 'secupress' ) ) ); // WP i18n.
	}

	// Get current user session.
	$sessions_inst   = WP_Session_Tokens::get_instance( get_current_user_id() );
	$token_to_keep   = wp_get_session_token();
	$session_to_keep = $sessions_inst->get( $token_to_keep );

	// Armageddon.
	/** This filter is documented in /wp-includes/session.php */
	$manager = apply_filters( 'session_token_manager', 'WP_User_Meta_Session_Tokens' );
	call_user_func( array( $manager, 'destroy_all_for_all_users' ) );

	// Recreate current session.
	$sessions_inst->update( $token_to_keep, $session_to_keep );

	// Send a response.
	wp_send_json_success( array( 'message' => __( 'You are now logged out everywhere else and all other users have been logged out.', 'secupress' ) ) );
}


/** --------------------------------------------------------------------------------------------- */
/** !TOOLS ====================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Get the user capability or role to use this plugin.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (string) A user capability or role.
 */
function secupress_pro_sessions_control_get_capability() {
	$capability = secupress_get_capability();
	/**
	 * Filter the user capability or role.
	 *
	 * @since 1.0
	 *
	 * @param (string) $capability A user capability or role.
	 */
	return apply_filters( 'secupress_pro.plugin.sessions_control.capability', $capability );
}
