<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** MIGRATE / UPGRADE =========================================================================== */
/** --------------------------------------------------------------------------------------------- */

add_filter( 'secupress.prevent_first_install', 'secupress_pro_maybe_migrate_mono_to_multi' );
/**
 * When switching a monosite installation to multisite, migrate options to the sitemeta table.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (bool) $prevent True to prevent triggering first install hooks. False otherwise.
 *
 * @return (bool) True if some options have been imported: prevent triggering first install hooks. False otherwise.
 */
function secupress_pro_maybe_migrate_mono_to_multi( $prevent ) {
	if ( ! is_multisite() ) {
		return $prevent;
	}

	$modules    = secupress_get_modules();
	$has_values = false;

	foreach ( $modules as $module => $atts ) {
		$value = get_option( "secupress_{$module}_settings" );

		if ( false !== $value ) {
			add_site_option( "secupress_{$module}_settings", $value );
			$has_values = true;
		}

		delete_option( "secupress_{$module}_settings" );
	}

	$options = array( SECUPRESS_SETTINGS_SLUG, SECUPRESS_SCAN_TIMES, SECUPRESS_BAN_IP, 'secupress_captcha_keys' );

	foreach ( SecuPress_Scanner_Results::get_scanners() as $scan_name => $class_name_part ) {
		$options[] = SecuPress_Scanner_Results::SCAN_OPTION_PREFIX . $scan_name;
		$options[] = SecuPress_Scanner_Results::FIX_OPTION_PREFIX . $scan_name;
	}

	foreach ( $options as $option ) {
		$value = get_option( $option );

		if ( false !== $value ) {
			add_site_option( $option, $value );
			delete_option( $option );
			$has_values = true;
		}
	}

	return $has_values || $prevent;
}


add_action( 'secupress_pro.upgrade', 'secupress_pro_new_upgrade', 10, 2 );
/**
 * What to do when SecuPress is updated, depending on versions.
 *
 * @since 1.2.3
 *
 * @param (string) $new_pro_version    The version being upgraded to.
 * @param (string) $actual_pro_version The previous version.
 */
function secupress_pro_new_upgrade( $new_pro_version, $actual_pro_version ) {
	global $wp_version, $wpdb;

	// < 1.2.3
	if ( version_compare( $actual_pro_version, '1.2.3' ) < 0 ) {
		$hashes = get_site_option( SECUPRESS_WP_CORE_FILES_HASHES );

		if ( ! empty( $hashes[ $wp_version ]['checksums'] ) && is_array( $hashes[ $wp_version ]['checksums'] ) ) {
			// Exclude Akismet from WP core hashes.
			$akismet = 'wp-content/plugins/akismet/';
			$updated = false;

			foreach ( $hashes[ $wp_version ]['checksums'] as $filename => $hash ) {
				if ( strpos( $filename, $akismet ) === 0 ) {
					unset( $hashes[ $wp_version ]['checksums'][ $filename ] );
					$updated = true;
				}
			}

			if ( $updated ) {
				update_site_option( SECUPRESS_WP_CORE_FILES_HASHES, $hashes );
			}
		}
	}

	// < 1.2.5
	if ( version_compare( $actual_pro_version, '1.2.5' ) < 0 ) {
		// The smart white-list has been updated.
		delete_site_option( SECUPRESS_FULL_FILETREE );
		secupress_delete_site_transient( SECUPRESS_FULL_FILETREE );
	}

	// < 1.4.3
	if ( version_compare( $actual_pro_version, '1.4.3', '<' ) ) {
		secupress_deactivate_submodule( 'users-login', 'nonlogintimeslot' );
		secupress_remove_old_plugin_file( SECUPRESS_PRO_MODULES_PATH . 'users-login/plugins/nonlogintimeslot.php' );
	}

	// < 1.4.5
	if ( version_compare( $actual_pro_version, '1.4.5', '<' ) ) {
		secupress_remove_old_plugin_file( SECUPRESS_PRO_MODULES_PATH . 'antispam/callbacks.php' );
	}

	// < 1.4.9
	if ( version_compare( $actual_pro_version, '1.4.9', '<' ) ) {
		secupress_deactivate_submodule( 'sensitive-data', array( 'page-protect', 'profile-protect', 'options-protect' ) );
		secupress_remove_old_plugin_file( SECUPRESS_PRO_MODULES_PATH . 'sensitive-data/plugins/options-protect.php' );
		secupress_remove_old_plugin_file( SECUPRESS_PRO_MODULES_PATH . 'sensitive-data/plugins/profile-protect.php' );
		secupress_remove_old_plugin_file( SECUPRESS_PRO_MODULES_PATH . 'sensitive-data/plugins/page-protect.php' );

		wp_clear_scheduled_hook( 'secupress_geoips_update_data' );
		if ( '-1' !== secupress_get_module_option( 'geoip-system_type', '-1', 'firewall' ) ) {
			// Alter the table and drop the end_ip column
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}secupress_geoips DROP end_ip" );
			secupress_geoips_update_datas();
		}
		// Save new option name
		$whitelist = secupress_get_module_option( 'banned-ips_whitelist', '', 'logs' );
		if ( $whitelist ) {
			$whitelist = explode( "\n", $whitelist );
			$whitelist = array_flip( $whitelist );
			add_site_option( SECUPRESS_WHITE_IP, $whitelist );
		}
		delete_site_option( 'secupress_logs_settings' );

		// Remove the IPs from htaccess in case of.
		if ( secupress_write_in_htaccess() ) {
			secupress_write_htaccess( 'ban_ip' );
		}

	}
}


/** --------------------------------------------------------------------------------------------- */
/** UPDATE ====================================================================================== */
/** --------------------------------------------------------------------------------------------- */

add_action( 'admin_init', 'secupress_pro_updater', 0 );
/**
 * Handle the plugin updates.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_pro_updater() {
	// Check if the licence key exists.
	add_action( 'secupress.updater.after_request_response', 'secupress_updater_check_licence' );

	require_once( SECUPRESS_PRO_CLASSES_PATH . 'vendors/edd-software-licensing/SecuPress_EDD_SL_Plugin_Updater.php' );
	secupress_pro_require_class( 'admin', 'edd-sl-plugin-updater' );

	// Setup the updater.
	$edd_updater = new SecuPress_Pro_Admin_EDD_SL_Plugin_Updater(
		SECUPRESS_WEB_MAIN,
		SECUPRESS_FILE,
		array(
			'version'   => SECUPRESS_PRO_VERSION,
			'license'   => secupress_get_consumer_key(),
			'item_name' => 'SecuPress',
			'author'    => 'SecuPress.me',
			'url'       => home_url(),
		)
	);
}


/**
 * Inspect the updater response to check if the licence key exists.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (object) $request The request response.
 */
function secupress_updater_check_licence( $request ) {
	if ( ! isset( $request->license_check ) || $request->license_check ) {
		// All is fine, or the license key is not activated yet (update won't be possible in that case).
		return;
	}

	// If `$request->license_check` is set and empty, the licence key doesn't exist.
	$options = get_site_option( SECUPRESS_SETTINGS_SLUG );
	$options = is_array( $options ) ? $options : array();
	unset( $options['site_is_pro'] );
	secupress_update_options( $options );
}


add_filter( 'plugin_row_meta', 'secupress_pro_add_plugin_row_meta', 2, 3 );
/**
 * Filter the array of row meta in the Plugins list table to add back the "View details" link to SecuPress Pro.
 *
 * @since 1.2.2
 * @author Grégory Viguier
 *
 * @param (array)  $plugin_meta An array of the plugin's metadata, including the version, author, author URI, and plugin URI.
 * @param (string) $plugin_file Path to the plugin file, relative to the plugins directory.
 * @param (array)  $plugin_data An array of plugin data.
 *
 * @return (array) The array of the plugin's metadata.
 */
function secupress_pro_add_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data ) {
	static $plugin_basename;

	if ( ! isset( $plugin_basename ) ) {
		$plugin_basename = plugin_basename( SECUPRESS_FILE );
	}

	if ( $plugin_basename !== $plugin_file ) {
		return $plugin_meta;
	}

	if ( isset( $plugin_data['slug'] ) || ! current_user_can( 'install_plugins' ) ) {
		return $plugin_meta;
	}

	$info_link = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
		esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . dirname( $plugin_basename ) . '&TB_iframe=true&width=600&height=550' ) ),
		esc_attr( sprintf( __( 'More information about %s', 'secupress' ), $plugin_data['Name'] ) ),
		esc_attr( $plugin_data['Name'] ),
		__( 'View details', 'secupress' )
	);

	if ( ! $plugin_meta ) {
		$plugin_meta['sp-pro-info'] = $info_link;
		return $plugin_meta;
	}

	$tmp_plugin_meta = array();

	foreach ( $plugin_meta as $i => $link ) {
		unset( $plugin_meta[ $i ] );

		if ( strpos( $link, __( 'Visit plugin site', 'secupress' ) ) ) {
			$tmp_plugin_meta['sp-pro-info'] = $info_link;
			$tmp_plugin_meta[ $i ]          = $link;
			return array_merge( $tmp_plugin_meta, $plugin_meta );
		}
		$tmp_plugin_meta[ $i ] = $link;
	}

	$tmp_plugin_meta['sp-pro-info'] = $info_link;
	return $tmp_plugin_meta;
}
