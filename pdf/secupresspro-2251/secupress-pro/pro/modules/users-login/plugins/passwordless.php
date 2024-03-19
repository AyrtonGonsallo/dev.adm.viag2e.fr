<?php
/**
 * Module Name: PasswordLess Double Authentication
 * Description: When you try to log in, you'll receive an email containing a validation link, without clicking on it, you can't log in.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 1.1.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

if ( ! secupress_get_option( 'secupress_passwordless_activation_validation' ) ) {
	return;
}

// EMERGENCY BYPASS!
if ( defined( 'SECUPRESS_ALLOW_LOGIN_ACCESS' ) && SECUPRESS_ALLOW_LOGIN_ACCESS ) {
	return;
}

add_filter( 'authenticate', 'secupress_passwordless_login_authenticate', SECUPRESS_INT_MAX - 10, 3 );
/**
 * Send an email with the new unique login link.
 *
 * @since 2.2.1 If your role is affected by PasswordLess, force it.
 * @since 1.0
 * @author Julio Potier
 *
 * @param (object) $raw_user A WP_Error object if user is not correctly authenticated, a WP_User object if he is.
 * @param (string) $username A username or email address.
 * @param (string) $password User password.
 *
 * @return (object) A WP_Error or WP_User object.
 */
function secupress_passwordless_login_authenticate( $raw_user, $username, $password ) {
	static $running = false;

	// Errors from other plugins.
	if ( is_wp_error( $raw_user ) ) {
		// Remove WP errors related to empty fields.
		unset( $raw_user->errors['empty_username'], $raw_user->errors['empty_password'] );

		if ( $raw_user->errors ) {
			// There are still errors, don't go further.
			$running = false;
			return $raw_user;
		}
	}

	if ( $running || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return $raw_user;
	}
	$running = true;

	// Make sure to process only credentials provided by the login form.
	$username = ! empty( $_POST['log'] ) && $username === $_POST['log'] ? $username : ''; // WPCS: CSRF ok.
	$password = ! empty( $_POST['pwd'] ) && $password === $_POST['pwd'] ? $password : ''; // WPCS: CSRF ok.

	if ( ! $username && ! $password || $username && $password ) {
		$running = false;
		if ( ! secupress_is_affected_role( 'users-login', 'double-auth', $raw_user ) ) {
			$running = false;
			return $raw_user;
		} else {
			$error_msg = __( 'You cannot login using your password, please just fill your login or email address.', 'secupress' );
			$wp_error  = new WP_Error( 'authentication_failed', $error_msg );

			$running = false;
			return $wp_error;
		}
	}
	if ( $username && isset( $_POST['passwordless_get_magik_link'] ) ) {
		if ( ! secupress_is_user( $raw_user ) ) {
			$by           = is_email( $username ) ? 'email' : 'login';
			$temp_user    = get_user_by( $by, $username );
			// if the login was a fake email like "foobar@example.com", try now with a login.
			if ( ! secupress_is_user( $temp_user ) && is_email( $username ) ) {
				$raw_user = get_user_by( 'login', $username );
			} else {
				$raw_user = $temp_user;
			}
		}
	}

	$result = secupress_passwordless_send_link( $raw_user, $_REQUEST );
	if ( $result['success'] ) {
		if ( $result['mailsent'] ) {
			// PasswordLess ok!
			wp_redirect( esc_url_raw( add_query_arg( 'action', 'passwordless_autologin', wp_login_url() ) ) );
			die();
		}
	} else {
		// id, username or email does not exists
		$errors = new WP_Error();
		// Display a vague error message.
		$errors->add( 'invalid_username', __( '<strong>Error</strong>: Invalid username or email.', 'secupress' ) );
		return $errors;
	}

	return $raw_user; // Should not happen
}

/**
 * Use this to send a passwordless link to anyone if the module is activated.
 *
 * @param (WP_User|int|string) $raw_user
 * @param (array) $args [ 'redirect_to' => (string), 'rememberme' => 0|1, 'sendmail' => (bool) ]
 * @author Julio Potier
 * @since 2.2.1
 * @return (array)
 **/
function secupress_passwordless_send_link( $raw_user, $args = false ) {
	// Step 1 for everybody: if not a user yet, try to find them by ID, username or email.
	if ( ! secupress_is_user( $raw_user ) ) {
		$by           = is_email( $raw_user ) ? 'email' : ( is_numeric( $raw_user ) ? 'id' : 'login' );
		$temp_user    = get_user_by( $by, $raw_user );
		// if the login was a fake email like "foobar@example.com", try now with a login.
		if ( ! secupress_is_user( $temp_user ) && is_email( $raw_user ) ) {
			$raw_user = get_user_by( 'login', $raw_user );
		} else {
			$raw_user = $temp_user;
		}
		// Authentication failed.
		if ( ! secupress_is_user( $raw_user ) ) {
			return [ 'success' => false, 'is_user' => false ];
		}
	}

	$defaults = [ 'redirect_to' => '', 'rememberme' => 0, 'sendmail' => true ];
	$args     = wp_parse_args( $args, $defaults );

	// // Step 1 succeeded: generate a token.
	remove_all_filters( 'random_password' );
	$key = wp_generate_password( 32, false );
	update_user_meta( $raw_user->ID, 'secupress_passwordless_token', $key );

	/**
	 * Filter the delay of validity of the email
	 *
	 * @since 2.0
	 *
	 * @param (int) $timing In minutes
	 */
	$timing = apply_filters( 'secupress.plugin.passwordless.timing', 10 );
	update_user_meta( $raw_user->ID, 'secupress_passwordless_timeout', time() + ( $timing * MINUTE_IN_SECONDS ) );
	update_user_meta( $raw_user->ID, 'secupress_passwordless_rememberme', (int) $args['rememberme'] );

	$token_url  = admin_url( 'admin-post.php?action=passwordless_autologin&token=' . $key . ( $args['redirect_to'] ? '&redirect_to=' . rawurlencode( $args['redirect_to'] ) : '' ) );

	if ( ! $args['sendmail'] ) {
		return [ 'success' => true, 'is_user' => true, 'WP_User' => $raw_user, 'mailsent' => false, 'token_url' => $token_url ];
	}
	
	$subject    = sprintf( __( '[%s] Your Magic Link for a Secure Login (2FA)', 'secupress' ), '###SITENAME###' );
	/**
	 * Filter the subject of the mail sent to the user.
	 *
	 * @since 2.0 $raw_user parameter
	 * @since 1.0
	 *
	 * @param (string) $subject The email subject.
	 * @param (WP_User) $raw_user The user
	 */
	$subject    = apply_filters( 'secupress.plugin.passwordless_email_subject', $subject, $raw_user );
	$body       = sprintf(
		/** Translators: 1 is a user name, 2 is a URL, 3 is an email address. */
		__( 'Hello %1$s,

You recently tried to log in on ###SITEURL###.

If this is correct, please click on the following Magic Link to really log in:
%2$s

You can safely ignore and delete this email if you do not want to take this action.

Regards,
All at ###SITENAME###
###SITEURL###', 'secupress' ),
		esc_html( $raw_user->display_name ),
		esc_url_raw( $token_url ),
		esc_html( $raw_user->user_email )
	);

	/**
	 * Filter the body of the mail sent to the user.
	 * You can use ###ADMIN_EMAIL###, ###SITENAME###, ###SITEURL### as placeholder if needed.
	 *
	 * @since 2.0 $rawuser and $token_url parameters
	 * @since 1.0
	 *
	 * @param (string) $body The email body.
	 * @param (WP_User) $raw_user The user
	 * @param (string) $token_url The url to log-in
	 */
	$body = apply_filters( 'secupress.plugin.passwordless_email_message', $body, $raw_user, $token_url );
	
	secupress_send_mail( $raw_user->user_email, $subject, $body );

	// 'mailsent' => true, even if not really sent, this is to separate from the case where we do not want to send it.
	return [ 'success' => true, 'is_user' => true, 'WP_User' => $raw_user, 'mailsent' => true, 'token_url' => $token_url ];
}

add_action( 'login_head', 'secupress_passwordless_buffer_start_login' );
/**
 * Start the buffer if we are on the login page.
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_passwordless_buffer_start_login() {
	if ( ! isset( $_GET['action'] ) || 'login' === $_GET['action'] ) {
		ob_start( 'secupress_passwordless_hide_password_field_ob' );
	}
}

/**
 * Dirty function to create a md5 in JS
 *
 * @since 2.0
 * @author Julio Potier
 *
 * @return (string) A md5 string
 **/
add_action( 'wp_ajax_nopriv_md5',
	function( $t ) {
		if ( isset( $_GET['e'] ) ) {
			die( md5( trim( $_GET['e'] ) ) );
		} else {
			die( '-1' );
		}
	}
);

add_action( 'login_footer', 'secupress_passwordless_buffer_stop', 1000 );
/**
 * End the buffer if we are on the login page + not passwordless.
 *
 * @since 2.0 load gravatar if possible
 * @since 1.0
 * @author Julio Potier
 */
function secupress_passwordless_buffer_stop() {
	if ( isset( $_GET['action'] ) && 'login' !== $_GET['action'] && 'notpasswordless' !== $_GET['action'] ) {
		return;
	}
	?>
	<script>
	jQuery('#user_login').on('keyup',
		function() {
			var val = jQuery('#user_login').val();
			var md5email = jQuery('#md5email').length;
			var md5val   = md5email ? jQuery('#md5email').val() : '';
			if ( val.includes('@') ) { // Works only for email logins.
				var url = _wpUtilSettings.ajax.url + '?action=md5&e=' + val;
				var ajax = jQuery.getJSON( url )
				.always( function(data) {
					jQuery('.login h1 a').css({ "background-image": 'url(https://0.gravatar.com/avatar/' + data.responseText + '?s:180)', "border-radius": '100%'});
				});
			} else if ( md5email && 32 === md5val.length ) {
				jQuery('.login h1 a').css({ "background-image": 'url(https://0.gravatar.com/avatar/' + md5val + '?s:180)', "border-radius": '100%'});
			} else {
				jQuery('.login h1 a').css({ "background-image": ''});
			}
		}
	).trigger('keyup');

	jQuery('.hide-if-js').hide();
	jQuery('#wp-submit').val(jQuery('#wp-submit').data('newvalue')).css('margin-bottom', '10px');
	jQuery('.forgetmenot').insertAfter('.submit');
	<?php 
	$sp_roles = secupress_get_module_option( 'double-auth_affected_role', [], 'users-login' );

	if ( count( $sp_roles ) ) { // Not everyone is affected, let the choice
	?>
	jQuery('#loginwithpassword').css('display', 'inline-block');
	jQuery('#loginwithpassword button:first').on('click', function(e){
		jQuery(this).parent().remove();
		jQuery('.forgetmenot').insertBefore('.submit');
		jQuery('.user-pass-wrap').show().find('input').prop('disabled', false).focus();
		jQuery('[name=passwordless_get_magik_link]').remove();
		jQuery('#wp-submit').val(jQuery('#wp-submit').data('origvalue')).css('margin-bottom', '0px');
	});
	<?php } ?>
	</script>
	<?php

	ob_end_flush();

	// Focus the password field.
	if ( ! isset( $_GET['action'] ) || 'notpasswordless' !== $_GET['action'] ) {
		return;
	}

	?>
<script type="text/javascript">
function secupress_attempt_focus() {
	setTimeout( function() {
		try {
			d = document.getElementById( 'user_pass' );
			d.focus();
			d.select();
		} catch( e ) {}
	}, 300 );
}

secupress_attempt_focus();

if ( typeof wpOnload === 'function' ) {
	wpOnload();
}
</script>
	<?php
}


/**
 * Alter the buffer content to hide the password label and field.
 *
 * @since 2.2.1 New UI/UX
 * @since 1.0
 * @author Julio Potier
 *
 * @param (string) $buffer Contains the login page between the action `login_head` and `login_footer`.
 *
 * @return (string)
 */
function secupress_passwordless_hide_password_field_ob( $buffer ) {
	$sp_roles = secupress_get_module_option( 'double-auth_affected_role', [], 'users-login' );

	$buffer   = str_replace( '<div class="user-pass-wrap">', '<div class="user-pass-wrap hide-if-js"><input type="hidden" name="passwordless_get_magik_link" value="1" />', $buffer );
	$buffer   = str_replace( 'value="' . esc_attr__( 'Log In' ) . '"', 'data-origvalue="' . esc_attr__( 'Log In' ) . '" data-newvalue="' . esc_attr__( 'Get my Magic Link', 'secupress' ) . '" value="' . esc_attr__( 'Log In' ) . '"', $buffer );
	if ( count( $sp_roles ) ) { // Not everyone is affected, let the choice
		$buffer   = str_replace( '</form>', sprintf( '<div id="loginwithpassword" style="display:none;line-height:2.35em">— %1$s <button type="button" class="button-secondary button-link" style="font-weight:600">%2$s</button></div>', _x( 'or', 'or Log in using a password', 'secupress' ), __( 'Log In using a password', 'secupress' ) ) . '</form>', $buffer );
	}
	return $buffer;
}

add_action( 'login_form_passwordless_autologin', 'secupress_passwordless_autologin_validation' );
/**
 * Modify the login header page message for our action
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_passwordless_autologin_validation() {
	login_header( __( 'PasswordLess', 'secupress' ), '<p class="message">' . __( 'Check your e-mail containing the confirmation link.', 'secupress' ) . '</p>' );
	login_footer();
	die();
}


add_action( 'admin_post_passwordless_autologin',        'secupress_passwordless_autologin' );
add_action( 'admin_post_nopriv_passwordless_autologin', 'secupress_passwordless_autologin' );
/**
 * Automatically log-in a user with the correct token.
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_passwordless_autologin() {
	$user_id            = 0;
	$fallback_error_msg = sprintf( __( 'This link is not valid for this user, please try to <a href="%s">log-in again</a>.', 'secupress' ), esc_url( wp_login_url( '', true ) ) );

	if ( empty( $_GET['token'] ) ) {
		$token = '';

		/**
		 * Triggers an action when an autologin action from the module has failed.
		 *
		 * @since 1.0
		 *
		 * @param (int)    $user_id    The user ID.
		 * @param (string) $token      The security token.
		 * @param (string) $error_code The error code.
		 */
		do_action( 'secupress.plugin.passwordless.autologin_error', $user_id, $token, 'no token' );

		secupress_die( $fallback_error_msg, '', array( 'force_die' => true ) );
	}

	// Get the user with the given token.
	$token   = $_GET['token'];
	// Prevent plugins to filter the users (like "Advanced Access Manager" does …)
	remove_all_actions( 'pre_get_users' );
	// Get the only user with that token
	$user_id = get_users( array(
		'meta_key'   => 'secupress_passwordless_token',
		'meta_value' => $token,
		'fields'     => 'ID',
		'number'     => 2, // 2, not 1!
	) );
	$user_id = count( $user_id ) === 1 ? (int) reset( $user_id ) : 0;
	$user    = $user_id ? get_user_by( 'id', $user_id ) : false;

	if ( ! secupress_is_user( $user ) ) {
		/** This action is documented in inc/modules/users-login/plugins/passwordless.php. */
		do_action( 'secupress.plugin.passwordless.autologin_error', $user_id, $token, 'no user' );

		secupress_die( $fallback_error_msg, '', array( 'force_die' => true ) );
	}

	// Test token validity period.
	$requested_redirect_to = ! empty( $_GET['redirect_to'] ) ? rawurldecode( $_GET['redirect_to'] ) : '';
	$time                  = get_user_meta( $user_id, 'secupress_passwordless_timeout', true );
	$rememberme            = get_user_meta( $user_id, 'secupress_passwordless_rememberme', true );

	delete_user_meta( $user_id, 'secupress_passwordless_token' );
	delete_user_meta( $user_id, 'secupress_passwordless_rememberme' );
	delete_user_meta( $user_id, 'secupress_passwordless_timeout' );

	if ( $time < time() ) {
		// The 10 minutes limit has passed.
		/** This action is documented in inc/modules/users-login/plugins/passwordless.php. */
		do_action( 'secupress.plugin.passwordless.autologin_error', $user_id, $token, 'expired token' );

		$message = sprintf( __( 'This link is now expired, please try to <a href="%s">log-in again</a>.', 'secupress' ), esc_url( wp_login_url( $requested_redirect_to, true ) ) );
		secupress_die( $message, '', array( 'force_die' => true ) );
	}

	// Log in and redirect the user.
	$secure_cookie = is_ssl();
	$secure_args   = array(
		'user_login'    => $user->user_login,
		'user_password' => time(), // We don't have the real password, just pass something.
	);

	/** This filter is documented in wp-includes/user.php. */
	$secure_cookie = apply_filters( 'secure_signon_cookie', $secure_cookie, $secure_args );

	wp_set_auth_cookie( $user_id, (bool) $rememberme, $secure_cookie );

	if ( $requested_redirect_to ) {
		$redirect_to = $requested_redirect_to;
		// Redirect to https if user wants ssl.
		if ( $secure_cookie && false !== strpos( $redirect_to, 'wp-admin' ) ) {
			$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
		}
	} else {
		$redirect_to = admin_url( 'index.php' );
	}

	// Add 'index.php" to prevent infinite loop of login.
	if ( $redirect_to === admin_url() ) {
		$redirect_to = admin_url( 'index.php' );
	}

	/** This filter is documented in wp-login.php. */
	$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );

	/**
	 * Triggers an action when an autologin action from the module is a success.
	 *
	 * @since 1.0
	 *
	 * @param (int)    $user_id The user ID.
	 * @param (string) $token   The security token.
	 */
	do_action( 'secupress.plugin.passwordless.autologin_success', $user_id, $token );

	wp_safe_redirect( esc_url_raw( $redirect_to ) );
	die();
}

add_filter( 'mepr-validate-login', 'secupress_passwordless_support_memberpress' );
/**
 * Support PasswordLess 2FA for MemberPress plugin
 *
 * @param (array) $errors
 * @author Julio Potier 
 * @since 2.2 
 * @return (array) $errors
 **/
function secupress_passwordless_support_memberpress( $errors ) {
	if ( ! isset( $_SERVER['REQUEST_METHOD'], $_POST['log'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return $errors;
	}

	// Check for login by email address
	$by   = is_email( $_POST['log'] ) ? 'email' : 'login'; 
	$user = get_user_by( $by, $_POST['log'] );

	if ( ! is_wp_error( $user ) && secupress_is_affected_role( 'users-login', 'double-auth', $user ) ) {
		$message  = apply_filters( 'secupress.passwordless.memberpress_message', __( 'You can not login using this page.', 'secupress' ) );
		$errors[] = $message;
	}

	return $errors;
}

add_filter( 'user_row_actions', 'secupress_passwordless_add_magiclink', 10, 2 );
/**
 * Add a "magic link" link for users
 *
 * @param (array) $actions
 * @param (WP_User) $user_object
 * @author Julio Potier
 * @since 2.2.1
 * @return (array) $actions
 **/
function secupress_passwordless_add_magiclink( $actions, $user_object ) {
	if ( get_current_user_id() !== $user_object->ID && current_user_can( 'edit_user', $user_object->ID ) ) {
		$actions['passwordless_magiclink'] = '<a class="resetpassword" href="' . wp_nonce_url( admin_url( 'admin-post.php?action=send_passwordless_magiclink&uid=' . $user_object->ID ), 'passwordless_magiclink_' . $user_object->ID ) . '">' . __( 'Send PasswordLess Magic Link', 'secupress' ) . '</a>';
	}
	return $actions;
}

add_action( 'admin_post_send_passwordless_magiclink', 'secupress_passwordless_send_magiclink' );
/**
 * Send the magic link even is the user is not affected by its role
 *
 * @author Julio Potier
 * @since 2.2.1
 * @return void
 **/
function secupress_passwordless_send_magiclink() {
	if ( ! isset( $_GET['_wpnonce'], $_GET['uid'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'passwordless_magiclink_' . $_GET['uid'] ) ) {
		wp_die( __( 'Magic Link not sent.', 'secupress' ) );
	}
	$result = secupress_passwordless_send_link( $_GET['uid'] );
	wp_redirect( admin_url( 'users.php?message=passwordless_' . [ 'ko', 'ok' ][ (int) $result['success'] ] ) );
	die();
}

add_action( 'admin_head-users.php', 'secupress_passwordless_messages' );
/**
 * Manage the feedback message from magic link admin area
 *
 * @author Julio Potier
 * @since 2.2.1
 * @return void
 **/
function secupress_passwordless_messages() {
	if ( ! isset( $_GET['message'] ) ) {
		return;
	}
	switch ( $_GET['message'] ) {
		case 'passwordless_ko':
			echo '<div class="error"><p>' . __( 'Magic Link not sent.', 'secupress' ) . '</p></div>';
		break;
		case 'passwordless_ok':
			echo '<div class="updated"><p>' . __( 'Magic Link has been sent.', 'secupress' ) . '</p></div>';
		break;
	}
}
