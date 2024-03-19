<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ACTIVATE ==================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

register_activation_hook( SECUPRESS_FILE, 'secupress_pro_activation' );
/**
 * Tell WP what to do when the plugin is activated.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_activation() {
	// Make sure we have our toys.
	secupress_load_functions();
	secupress_pro_load_functions();

	// Activate the Pro sub-modules (they are not included yet).
	if ( secupress_is_pro() ) {
		secupress_activate_pro_submodules( true );
	}

	/**
	 * Fires on SecuPress Pro activation.
	 *
	 * @since 1.0
	 */
	do_action( 'secupress.pro.activation' );

	/**
	 * As this activation hook appens before our sub-modules are loaded (and the page is reloaded right after that),
	 * this transient will trigger a custom activation hook in `secupress_load_plugins()`.
	 */
	set_site_transient( 'secupress_pro_activation', 1 );
}


add_action( 'secupress.pro.plugins.activation', 'secupress_pro_maybe_set_rules_on_activation', 10000 );
/**
 * Maybe set rules to add in `.htaccess` or `web.config` file on SecuPress Pro activation.
 *
 * @since 1.0.3
 */
function secupress_pro_maybe_set_rules_on_activation() {
	global $is_apache, $is_iis7, $is_nginx;

	if ( ! $is_apache && ! $is_iis7 && ! $is_nginx ) {
		// System not supported.
		return;
	}

	$rules = array();

	/**
	 * Rules that must be added to the `.htaccess`, `web.config`, or `nginx.conf` file on SecuPress Pro activation.
	 *
	 * @since 1.0.3
	 *
	 * @param (array) $rules An array of rules with the modules marker as key and rules (string) as value. For IIS7 it's an array of arguments (each one containing a row with the rules).
	 */
	$rules = apply_filters( 'secupress.pro.plugins.activation.write_rules', $rules );

	if ( $rules ) {
		// We store the rules, they will be merged and written in `secupress_maybe_write_rules_on_activation()`.
		$cached_rules = secupress_cache_data( 'plugins-activation-write_rules' );
		$cached_rules = is_array( $cached_rules ) ? $cached_rules : array();
		$rules        = array_merge( $cached_rules, $rules );
		secupress_cache_data( 'plugins-activation-write_rules', $rules );
	}
}

add_action( 'secupress.modules.activate_submodule_passwordless', 'secupress_passwordless_activation_validation_mail' );
/**
 * Send the validation email to be sure that PasswordLess can be activated now
 *
 * @since 1.3.3
 */
function secupress_passwordless_activation_validation_mail() {
	global $current_user;

	$url     = str_replace( '&amp;', '&', wp_nonce_url( admin_url( 'admin-post.php?action=secupress_passwordless_confirmation' ), 'secupress_passwordless_confirmation' ) );
	$subject = __( '[###SITENAME###] PasswordLess 2FA: Validation required', 'secupress' );
	$message = sprintf( __( 'Hello %1$s,

You just activated the double authentication module "PasswordLess".

To activate this module, confirm your action by clicking the link below:
%2$s

Regards,
All at ###SITENAME###
###SITEURL###', 'secupress' ),
						$current_user->display_name,
						$url
	);
	/**
	 * Filter the mail subject
	 * @param (string) $subject
	 * @param (WP_User) $current_user
	 * @since 2.2
	 */
	$subject = apply_filters( 'secupress.mail.passwordless_activation.subject', $subject, $current_user );
	/**
	 * Filter the mail message
	 * @param (string) $message
	 * @param (WP_User) $current_user
	 * @param (string) $url
	 * @since 2.2
	 */
	$message = apply_filters( 'secupress.mail.passwordless_activation.message', $message, $current_user, $url );

	secupress_send_mail( $current_user->user_email, $subject, $message );
	remove_filter( 'locale', 'get_user_locale' );
}

/** --------------------------------------------------------------------------------------------- */
/** DEACTIVATE ================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

register_deactivation_hook( SECUPRESS_FILE, 'secupress_pro_deactivation' );
/**
 * Plugin deactivation.
 *
 * @since 1.0
 * @since 1.0.3 Unschedule our events.
 */
function secupress_pro_deactivation() {
	// Make sure we have our toys.
	secupress_load_functions();
	secupress_pro_load_functions();

	// Unschedule our cron events.
	$cron_names = array(
		'secupress_license_check',
		'secupress_daily_reporting',
		'secupress_schedules_backups',
		'secupress_schedules_scan',
		'secupress_schedules_scan_cron',
		'secupress_schedules_file_monitoring',
		'secupress_file_monitoring_cron',
		'secupress_fightspam_retest_cron',
	);

	foreach ( $cron_names as $cron_name ) {
		if ( wp_next_scheduled( $cron_name ) ) {
			wp_clear_scheduled_hook( $cron_name );
		}
	}

	/**
	 * Fires on SecuPress Pro deactivation.
	 *
	 * @since 1.0.3
	 */
	do_action( 'secupress.pro.deactivation' );

	/**
	 * Fires on SecuPress Pro deactivation.
	 *
	 * @since 1.0.3
	 *
	 * @param (array) $args        An empty array to mimic the `$args` parameter from `secupress_deactivate_submodule()`.
	 * @param (bool)  $is_inactive False to mimic the `$is_inactive` parameter from `secupress_deactivate_submodule()`.
	 */
	do_action( 'secupress.pro.plugins.deactivation', array(), false );
}


add_action( 'secupress.pro.plugins.deactivation', 'secupress_maybe_remove_rules_on_pro_deactivation', 10000 );
/**
 * Maybe remove rules from `.htaccess` or `web.config` file on SecuPress Pro deactivation.
 * Unlike SecuPress Free, we remove only rules originated from the Pro plugin.
 *
 * @since 1.0
 */
function secupress_maybe_remove_rules_on_pro_deactivation() {
	global $is_apache, $is_iis7, $is_nginx;

	$free_plugin_file = 'deactivate_' . plugin_basename( trim( SECUPRESS_FILE ) );

	if ( doing_filter( $free_plugin_file ) || did_action( $free_plugin_file ) ) {
		// SecuPress Free already removed everything.
		return;
	}

	$rules = array();

	/** This filter is descripbed in inc/admin/activation.php. */
	$rules = apply_filters( 'secupress.pro.plugins.activation.write_rules', $rules );

	if ( ! $rules ) {
		return;
	}

	// Apache.
	if ( $is_apache ) {
		$home_path = secupress_get_home_path();
		$file_path = $home_path . '.htaccess';
		// In fact, we don't need the rules, only the markers.
		$markers   = array_keys( $rules );

		if ( ! file_exists( $file_path ) ) {
			// RLY?
			return;
		}

		if ( ! wp_is_writable( $file_path ) ) {
			// If the file is not writable, display a message.
			$message  = sprintf( __( '%s:', 'secupress' ), SECUPRESS_PLUGIN_NAME ) . ' ';

			if ( count( $markers ) === 1 ) {
				$marker   = reset( $markers );
				$message .= sprintf(
					/** Translators: 1 is a file name, 2 and 3 are small parts of code. */
					__( 'It seems your %1$s file is not writable, you have to edit it manually. Please remove all rules between %2$s and %3$s.', 'secupress' ),
					'<code>.htaccess</code>',
					"<code># BEGIN SecuPress $marker</code>",
					'<code># END SecuPress</code>'
				);
			} else {
				$message .= sprintf(
					/** Translators: %s is a file name. */
					__( 'It seems your %s file is not writable, you have to edit it manually. Please remove all rules between the following markers:', 'secupress' ),
					'<code>.htaccess</code>'
				);

				$message .= ' <ul>';

				foreach ( $markers as $marker ) {
					$message .= '<li>' . sprintf( __( '%1$s and %2$s', 'secupress' ), "<code># BEGIN SecuPress $marker</code>", '<code># END SecuPress</code>' ) . '</li>';
				}

				$message .= '</ul>';
			}

			secupress_create_deactivation_notice_muplugin( 'apache_remove_rules_pro', $message );
		}

		// Get the whole content of the file.
		$file_content = file_get_contents( $file_path );

		if ( ! $file_content ) {
			// Nothing? OK.
			return;
		}

		// Remove old content.
		$pattern      = implode( '|', $markers );
		$pattern      = "/# BEGIN SecuPress ($pattern)\n(.*)# END SecuPress\s*?/isU";
		$file_content = preg_replace( $pattern, '', $file_content );

		// Save the file.
		$wp_filesystem = secupress_get_filesystem();
		$wp_filesystem->put_contents( $file_path, $file_content, FS_CHMOD_FILE );
		return;
	}

	// IIS7.
	if ( $is_iis7 ) {
		$home_path = secupress_get_home_path();
		$file_path = $home_path . 'web.config';

		if ( ! file_exists( $file_path ) ) {
			// RLY?
			return;
		}

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;

		if ( false === $doc->load( $file_path ) ) {
			// If the file is not writable, display a message.
			$message = sprintf( __( '%s:', 'secupress' ), SECUPRESS_PLUGIN_NAME ) . ' ';
			// In fact, we don't need the rules, only the markers.
			$markers = array_keys( $rules );

			if ( count( $markers ) === 1 ) {
				$marker   = reset( $markers );
				$message .= sprintf(
					/** Translators: 1 is a file name, 2 is a small part of code. */
					__( 'It seems your %1$s file is not writable, you have to edit it manually. Please remove all nodes with %2$s.', 'secupress' ),
					'<code>web.config</code>',
					"<code>SecuPress $marker</code>"
				);
			} else {
				foreach ( $markers as $i => $marker ) {
					$markers[ $i ] = "<code>SecuPress $marker</code>";
				}

				$message .= sprintf(
					/** Translators: 1 is a file name, 2 is a list of small parts of code. */
					__( 'It seems your %1$s file is not writable, you have to edit it manually. Please remove all nodes with the following markers: %2$s', 'secupress' ),
					'<code>web.config</code>',
					wp_sprintf( '%l', $markers )
				);
			}

			secupress_create_deactivation_notice_muplugin( 'iis7_remove_rules_pro', $message );
		}

		// Remove old content.
		$xpath  = new DOMXPath( $doc );
		$edited = false;

		foreach ( $rules as $marker => $args ) {
			$args = wp_parse_args( $args, array(
				'path'      => '',
				'attribute' => 'name',
			) );

			$attribute = $args['attribute'];
			$path      = $args['path'];
			$path_end  = ! $path && strpos( ltrim( $nodes_string ), '<rule ' ) === 0 ? '/rewrite/rules/rule' : '';
			$path      = '/configuration/system.webServer' . ( $path ? '/' . trim( $path, '/' ) : '' ) . $path_end;

			$old_nodes = $xpath->query( "$path/*[starts-with(@$attribute,'SecuPress $marker')]" );

			if ( $old_nodes->length > 0 ) {
				$edited = true;

				foreach ( $old_nodes as $old_node ) {
					$old_node->parentNode->removeChild( $old_node );
				}
			}
		}

		// Save the file.
		if ( $edited ) {
			$doc->formatOutput = true;
			saveDomDocument( $doc, $file_path );
		}
		return;
	}

	// Nginx.
	if ( $is_nginx ) {
		// Since we can't edit the file, display a message.
		$message = sprintf( __( '%s:', 'secupress' ), SECUPRESS_PLUGIN_NAME ) . ' ';
		// In fact, we don't need the rules, only the markers.
		$markers = array_keys( $rules );

		if ( count( $markers ) === 1 ) {
			$marker   = reset( $markers );
			$message .= sprintf(
				/** Translators: 1 and 2 are small parts of code, 3 is a file name. */
				__( 'Your server runs <strong>Ngnix</strong>, you have to edit the configuration file manually. Please remove all rules between %1$s and %2$s from the %3$s file.', 'secupress' ),
				"<code># BEGIN SecuPress $marker</code>",
				'<code># END SecuPress</code>',
				'<code>nginx.conf</code>'
			);
		} else {
			$message .= sprintf(
				/** Translators: %s is a file name. */
				__( 'Your server runs <strong>Ngnix</strong>, you have to edit the configuration file manually. Please remove all rules between the following markers from the %1$s file:', 'secupress' ),
				'<code>.htaccess</code>'
			);

			$message .= ' <ul>';

			foreach ( $markers as $marker ) {
				$message .= '<li>' . sprintf( __( '%1$s and %2$s', 'secupress' ), "<code># BEGIN SecuPress $marker</code>", '<code># END SecuPress</code>' ) . '</li>';
			}

			$message .= '</ul>';
		}

		secupress_create_deactivation_notice_muplugin( 'nginx_remove_rules_pro', $message );
	}
}


add_action( 'secupress.pro.plugins.deactivation', 'secupress_deactivate_pro_submodules_on_pro_deactivation', 10000 );
/**
 * Deactivate (silently) all Pro sub-modules.
 *
 * @since 1.0.3
 */
function secupress_deactivate_pro_submodules_on_pro_deactivation() {
	secupress_deactivate_pro_submodules( true );
}

add_action( 'secupress.modules.deactivate_submodule_passwordless', 'secupress_passwordless_activation_validation_remove_option' );
/**
 * Remove the option to prevent Passwordless to be active without the email link validation
 *
 * @since 1.3.3
 */
function secupress_passwordless_activation_validation_remove_option() {
	$options = get_site_option( SECUPRESS_SETTINGS_SLUG );
	unset( $options['secupress_passwordless_activation_validation'] );
	secupress_update_options( $options );
}

/** --------------------------------------------------------------------------------------------- */
/** (DE)ACTIVATE THE PRO SUB-MODULES WHEN THE "IS PRO" STATUS CHANGES =========================== */
/** ----------------------------------------------------------------------------------------------*/

if ( is_multisite() ) {
	add_action( 'update_site_option_' . SECUPRESS_SETTINGS_SLUG, 'secupress_pro_network_maybe_deactivate_pro_submodules', 10, 3 );
} else {
	add_action( 'update_option_' . SECUPRESS_SETTINGS_SLUG, 'secupress_pro_maybe_deactivate_pro_submodules', 10, 2 );
}
/**
 * After our main network option has been successfully updated, test if the value of `site_is_pro` changed.
 * If it did, activate or deactivate the Pro sub-modules accordingly.
 *
 * @since 1.0.3
 * @author Grégory Viguier
 *
 * @param (string) $option    Name of the network option.
 * @param (mixed)  $value     Current value of the network option.
 * @param (mixed)  $old_value Old value of the network option.
 */
function secupress_pro_network_maybe_deactivate_pro_submodules( $option, $value, $old_value ) {
	secupress_pro_maybe_deactivate_pro_submodules( $old_value, $value );
}


/**
 * After our main option has been successfully updated, test if the value of `site_is_pro` changed.
 * If it did, activate or deactivate the Pro sub-modules accordingly.
 *
 * @since 1.0.3
 * @author Grégory Viguier
 *
 * @param (mixed) $old_value Old value of the option.
 * @param (mixed) $value     Current value of the option.
 */
function secupress_pro_maybe_deactivate_pro_submodules( $old_value, $value ) {
	$old_is_pro = ! empty( $old_value['site_is_pro'] );
	$new_is_pro = ! empty( $value['site_is_pro'] );

	if ( $old_is_pro === $new_is_pro ) {
		return;
	}

	if ( $old_is_pro ) {
		// 1 => 0: deactivate.
		secupress_deactivate_pro_submodules();
	} else {
		// 0 => 1: activate.
		secupress_activate_pro_submodules();
	}
}


/**
 * Deactivate the Pro sub-modules and store the list of those sub-modules in an option.
 *
 * @since 1.0.3
 * @author Grégory Viguier
 *
 * @param (bool) $silently If true, does not trigger the deactivation hooks.
 */
function secupress_deactivate_pro_submodules( $silently = false ) {
	$modules = secupress_get_active_pro_submodules();

	if ( ! $modules ) {
		return;
	}

	// Make sure we have our toys.
	secupress_load_functions();
	secupress_pro_load_functions();

	foreach ( $modules as $module => $submodules ) {
		// Deactivate the Pro sub-modules.
		if ( $silently ) {
			secupress_deactivate_submodule_silently( $module, $submodules );
		} else {
			secupress_deactivate_submodule( $module, $modules[ $module ] );
		}
	}

	// Some Pro sub-modules have been deactivated: keep track of them.
	update_site_option( 'secupress_pro_active_submodules', $modules );
}


/**
 * Activate the Pro sub-modules and delete the option that stored the list of those sub-modules.
 *
 * @since 1.0.3
 * @author Grégory Viguier
 *
 * @param (bool) $silently If true, does not trigger the deactivation hooks.
 */
function secupress_activate_pro_submodules( $silently = false ) {
	$modules = get_site_option( 'secupress_pro_active_submodules' );

	if ( ! $modules || ! is_array( $modules ) ) {
		return;
	}

	// Make sure we have our toys.
	secupress_load_functions();
	secupress_pro_load_functions();

	foreach ( $modules as $module => $submodules ) {
		foreach ( $submodules as $i => $submodule ) {
			// Activate the Pro sub-module.
			if ( $silently ) {
				secupress_activate_submodule_silently( $module, $submodule );
			} else {
				secupress_activate_submodule( $module, $submodule );
			}
		}
	}

	delete_site_option( 'secupress_pro_active_submodules' );
}
