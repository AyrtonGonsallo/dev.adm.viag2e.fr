<?php
/**
 * Module Name: Strong Passwords
 * Description: Force the users to use a strong password.
 * Main Module: users_login
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

use ZxcvbnPhp\Zxcvbn;

/** --------------------------------------------------------------------------------------------- */
/** INIT ======================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

add_action( 'admin_init', 'secupress_pro_strong_passwords_init' );
/**
 * Plugin init.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_strong_passwords_init() {
	global $pagenow;
	$user = wp_get_current_user();
	if ( 'profile.php' !== $pagenow || ! secupress_is_affected_role( 'users-login', 'password-policy', $user ) ) {
		return;
	}

	add_action( 'admin_print_scripts',        'secupress_pro_strong_passwords_scripts',        40 );	// 40: hook after default scripts are registered (20).
	add_action( 'admin_print_styles',         'secupress_pro_strong_passwords_styles',         40 );	// 40: hook after default styles are registered (20).
	add_filter( 'show_password_fields',       'secupress_pro_strong_passwords_prepare_field',  0 );
	add_action( 'user_profile_update_errors', 'secupress_pro_strong_passwords_check_strength', 10, 3 );
}


/** --------------------------------------------------------------------------------------------- */
/** JS / CSS ==================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Enqueue plugin JS scripts.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_strong_passwords_scripts() {
	global $wp_scripts;

	// Remove WP "zxcvbn" and "password-strength-meter".
	wp_deregister_script( 'zxcvbn-async' );
	wp_deregister_script( 'password-strength-meter' );

	// Keep the original "user-profile" because it does other things than dealing with the passwords. We'll change its dependencies.
	if ( ! empty( $wp_scripts->registered['user-profile'] ) ) {
		$deps   = array_flip( $wp_scripts->registered['user-profile']->deps );
		unset( $deps['password-strength-meter'] );
		$deps   = array_flip( $deps );
		$deps[] = 'secupress-password-strength-meter';
		$wp_scripts->registered['user-profile']->deps = $deps;
	}

	// Our scripts.
	$suffix   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$version  = $suffix ? SECUPRESS_PRO_VERSION : time();
	$base_url = SECUPRESS_PRO_URL . 'modules/users-login/plugins/inc/';

	// "zxcvbn".
	wp_enqueue_script( 'secupress-zxcvbn-async', "{$base_url}js/zxcvbn-async{$suffix}.js", array(), '4.3.0' );

	wp_localize_script( 'secupress-zxcvbn-async', '_zxcvbnSettings', array(
		'src' => "{$base_url}js/zxcvbn.min.js",
	) );

	// "password-strength-meter".
	wp_enqueue_script( 'secupress-password-strength-meter', "{$base_url}js/password-strength-meter{$suffix}.js", array( 'jquery', 'secupress-zxcvbn-async' ), $version, true );

	wp_localize_script( 'secupress-password-strength-meter', 'pwsL10n', array(
		'short'    => _x( 'Very weak', 'password strength', 'secupress' ),
		'bad'      => _x( 'Weak', 'password strength', 'secupress' ),
		'good'     => _x( 'Medium', 'password strength', 'secupress' ),
		'strong'   => _x( 'Strong', 'password strength', 'secupress' ),
		'mismatch' => _x( 'Mismatch', 'password mismatch', 'secupress' ),
	) );

	// "user-profile".
	wp_enqueue_script( 'secupress-user-profile', "{$base_url}js/user-profile{$suffix}.js", array( 'jquery', 'secupress-password-strength-meter', 'wp-util' ), $version, true );

	wp_localize_script( 'secupress-user-profile', 'userProfileL10n', array(
		'warn'     => __( 'Your new password has not been saved.', 'secupress' ),
		'show'     => __( 'Show', 'secupress' ),
		'hide'     => __( 'Hide', 'secupress' ),
		'cancel'   => __( 'Cancel', 'secupress' ),
		'ariaShow' => __( 'Show password', 'secupress' ),
		'ariaHide' => __( 'Hide password', 'secupress' ),
	) );
}


/**
 * Enqueue plugin styles.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_strong_passwords_styles() {
	$suffix   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$version  = $suffix ? SECUPRESS_PRO_VERSION : time();
	$base_url = SECUPRESS_PRO_URL . 'modules/users-login/plugins/inc/';

	wp_enqueue_style( 'secupress-strong-passwords', "{$base_url}css/strong-passwords{$suffix}.css", array( 'forms' ), $version );
}


/** --------------------------------------------------------------------------------------------- */
/** FIELD ======================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Start the process that will add the password field.
 * Basically it does a `ob_start()` and launches the hooks that will `ob_get_clean()`.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (bool) $show Whether to show the password fields. Default true.
 *
 * @return (bool) Unchanged value of `$show`.
 */
function secupress_pro_strong_passwords_prepare_field( $show ) {
	global $pagenow;

	if ( $show && 'profile.php' === $pagenow && IS_PROFILE_PAGE ) {
		ob_start();

		add_action( 'show_user_profile', 'secupress_pro_strong_passwords_add_field', -2 );
	}

	return $show;
}


/**
 * End the process that will add the password field.
 * Get the password fields with `ob_get_clean()` and add the new field.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_strong_passwords_add_field() {
	global $admin_title;

	$content = ob_get_clean();
	$sep     = '<tr class="user-sessions-wrap hide-if-no-js">';

	ob_start();
	?>
</table>

<h2><?php _e( 'Account Management', 'secupress' ); ?></h2>
<table class="form-table <?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
	<tr id="password" class="secupress-user-pass1-wrap">
		<th><label for="secupress-pass1"><?php _e( 'New Password', 'secupress' ); ?></label></th>
		<td>
			<input class="hidden" value=" " /><!-- #24364 workaround -->
			<button type="button" class="button button-secondary wp-generate-pw hide-if-no-js"><?php _e( 'Generate Password', 'secupress' ); ?></button>
			<div class="wp-pwd hide-if-js">
				<span class="password-input-wrapper">
					<input type="password" name="pass1" id="secupress-pass1" class="regular-text" value="" autocomplete="off" data-pw="<?php echo esc_attr( wp_generate_password( 24 ) ); ?>" aria-describedby="secupress-pass-strength-result" />
				</span>
				<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password', 'secupress' ); ?>">
					<span class="dashicons dashicons-hidden"></span>
					<span class="text"><?php _e( 'Hide', 'secupress' ); ?></span>
				</button>
				<button type="button" class="button button-secondary wp-cancel-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Cancel password change', 'secupress' ); ?>">
					<span class="text"><?php _e( 'Cancel', 'secupress' ); ?></span>
				</button>
				<div style="display:none" id="secupress-pass-strength-result" aria-live="polite"></div>
			</div>
			<input type="hidden" name="secupress-pass-data[]" value="<?php echo esc_attr( secupress_get_current_url() ); ?>" />
			<input type="hidden" name="secupress-pass-data[]" value="<?php echo esc_attr( $admin_title ); ?>" />
			<p class="pw-weak hide-if-js" tabindex="0"><?php _e( 'You must choose a strong password.', 'secupress' ); ?></p>
		</td>
	</tr>
	<tr class="secupress-user-pass2-wrap hide-if-js">
		<th scope="row"><label for="secupress-pass2"><?php _e( 'Repeat New Password', 'secupress' ); ?></label></th>
		<td>
			<input name="pass2" type="password" id="secupress-pass2" class="regular-text" value="" autocomplete="off" />
			<p class="description"><?php _e( 'Type your new password again.', 'secupress' ); ?></p>
		</td>
	</tr>
	<?php
	$field = ob_get_clean();

	// Sessions management is displayed.
	if ( false !== strpos( $content, $sep ) ) {
		$content    = explode( $sep, $content, 2 );
		$content[0] = $field;
		$content    = implode( $sep, $content );
	}
	// No sessions management.
	elseif ( preg_match( '@^(.*</tr>\s*)</table>.*$@Us', $content, $matches ) ) {
		$content = str_replace( $matches[1], $field, $content );
	}

	echo $content;
}


/** --------------------------------------------------------------------------------------------- */
/** CALLBACKS =================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Check the new password strength.
 * If not strong, trigger an error.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (object) $errors WP_Error object, passed by reference.
 * @param (bool)   $update Whether this is a user update.
 * @param (object) $user   WP_User object, passed by reference.
 */
function secupress_pro_strong_passwords_check_strength( $errors, $update, $user ) {
	if ( empty( $_POST['pass1'] ) || empty( $user->user_pass ) || ! isset( $user->ID ) ) { // WPCS: CSRF ok.
		return;
	}

	// Collect all the strings we want to blacklist.
	$blacklist   = array();
	$user_data   = array( 'user_login' => 1, 'first_name' => 1, 'last_name' => 1, 'nickname' => 1, 'display_name' => 1, 'user_email' => 1, 'user_url' => 1, 'description' => 1 );
	$user_data   = array_intersect_key( (array) $user, $user_data );
	$user_data[] = get_bloginfo( 'name' );
	$user_data[] = get_bloginfo( 'admin_email' );

	// Page title and URL.
	if ( ! empty( $_POST['secupress-pass-data'] ) && is_array( $_POST['secupress-pass-data'] ) ) { // WPCS: CSRF ok.
		$user_data = array_merge( $user_data, $_POST['secupress-pass-data'] ); // WPCS: CSRF ok.
	}

	// Strip out non-alphanumeric characters and convert each word to an individual entry.
	foreach ( $user_data as $i => $data ) {
		if ( $data ) {
			$data      = preg_replace( '@\W@', ' ', $data );
			$data      = explode( ' ', $data );
			$blacklist = array_merge( $blacklist, $data );
		}
	}

	if ( $blacklist ) {
		foreach ( $blacklist as $i => $data ) {
			if ( '' === $data || strlen( $data ) < 4 ) {
				unset( $blacklist[ $i ] );
			}
		}
	}

	if ( $blacklist ) {
		$blacklist = array_flip( array_flip( $blacklist ) );
	}

	// Test the password.
	spl_autoload_register( 'secupress_pro_strong_passwords_autoload' );

	$zxcvbn   = new Zxcvbn();
	$strength = $zxcvbn->passwordStrength( $_POST['pass1'], $blacklist ); // WPCS: CSRF ok.

	if ( 4 !== $strength['score'] ) {
		$errors->add( 'pass', __( '<strong>Error</strong>: Please enter a strong password.', 'secupress' ), array( 'form-field' => 'pass1' ) );
	}
}


add_action( 'wp_ajax_secupress-generate-password', 'secupress_pro_strong_passwords_generate_password_ajax_cb' );
/**
 * When the "Cancel" button is clicked, a new password is sent with ajax.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_strong_passwords_generate_password_ajax_cb() {
	wp_send_json_success( wp_generate_password( 24 ) );
}


/** --------------------------------------------------------------------------------------------- */
/** TOOLS ======================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Autoloader for `Zxcvbn`.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (string) $class_id The class identifier, like `ZxcvbnPhp\Zxcvbn`.
 */
function secupress_pro_strong_passwords_autoload( $class_id ) {
	$class_id  = str_replace( '\\', '/', $class_id );
	$class_id  = trim( $class_id, '/' );
	$file_path = __DIR__ . '/inc/php/' . $class_id . '.php';

	if ( file_exists( $file_path ) ) {
		require_once( $file_path );
	}
}
