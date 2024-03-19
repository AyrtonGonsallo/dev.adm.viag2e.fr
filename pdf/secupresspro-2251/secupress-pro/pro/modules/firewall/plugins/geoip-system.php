<?php
/**
 * Module Name: GeoIP Management
 * Description: Whitelist or blacklist countries to visit your website.
 * Main Module: firewall
 * Author: SecuPress
 * Version: 1.0.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'secupress.pro.plugins.activation',                                     'secupress_geoip_activation' );
add_action( 'secupress.modules.activate_submodule_' . basename( __FILE__, '.php' ), 'secupress_geoip_activation' );
/**
 * Create our geoip table that contains every IP addresses around the world around the woOorld.
 * Set the option that the table is installed.
 *
 * @since 1.0
 */
function secupress_geoip_activation() {
	global $wpdb;

	// If the table exists, bail out.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}secupress_geoips'" ) === $wpdb->prefix . 'secupress_geoips' ) {
		return;
	}

	$charset_collate = $wpdb->get_charset_collate();

	// Create the table and fill in the data.
	$sql = "CREATE TABLE {$wpdb->prefix}secupress_geoips (
		id int(10) unsigned NOT NULL AUTO_INCREMENT,
		begin_ip bigint(20) DEFAULT NULL,
		country_code varchar(3) DEFAULT NULL,
		PRIMARY KEY (id),
		KEY begin_ip (begin_ip)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// If the table doens't exists, bail out.
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}secupress_geoips'" ) !== $wpdb->prefix . 'secupress_geoips' ) {
		return;
	}

	secupress_geoips_update_datas();

	if ( ! wp_next_scheduled( 'secupress_geoips_update_datas' ) ) {
		wp_schedule_event( time(), 'daily', 'secupress_geoips_update_datas' );
	}

	update_option( 'secupress_geoip_installed', 1 );
}

add_action( 'secupress_geoips_update_datas', 'secupress_geoips_update_datas' );


add_action( 'secupress.pro.plugins.deactivation',                                     'secupress_geoip_deactivation' );
add_action( 'secupress.modules.deactivate_submodule_' . basename( __FILE__, '.php' ), 'secupress_geoip_deactivation' );
/**
 * Drop our table.
 * Delete our option.
 *
 * @since 1.0
 */
function secupress_geoip_deactivation() {
	global $wpdb;

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}secupress_geoips'" ) === $wpdb->prefix . 'secupress_geoips' ) {
		$wpdb->query( "DROP TABLE {$wpdb->prefix}secupress_geoips" );
	}

	wp_clear_scheduled_hook( 'secupress_geoips_update_datas' );

	delete_option( 'secupress_geoip_installed' );
}


add_action( 'secupress.plugins.loaded', 'secupress_geoip_check_country' );
/**
 * Get the country code and check if we need to block this IP address.
 *
 * @since 2.0 Use SECUPRESS_ALLOW_GEOIP_ACCESS
 * @since 1.0
 */
function secupress_geoip_check_country() {
	if ( ! get_option( 'secupress_geoip_installed' ) || ( defined( 'SECUPRESS_ALLOW_GEOIP_ACCESS' ) && SECUPRESS_ALLOW_GEOIP_ACCESS ) ) {
		return;
	}
	$ip = secupress_get_ip();
	// The IP address may be whitelisted.
	if ( secupress_ip_is_whitelisted( $ip ) ) {
		return;
	}

	// Let real SEO bots bypass the country blocking
	if ( ! secupress_get_module_option( 'geoip-system_seo-bypass', null, 'firewall' ) && secupress_check_bot_ip() ) {
		return;
	}

	$country_code = secupress_geoip2country( $ip );
	if ( is_null( $country_code ) ) {
		return; // Not found? Not blocked!
	}

	$is_whitelist = secupress_get_module_option( 'geoip-system_type', -1, 'firewall' ) === 'whitelist';
	$countries    = array_flip( secupress_get_module_option( 'geoip-system_countries', array(), 'firewall' ) );

	if ( ( isset( $countries[ $country_code ] ) && ! $is_whitelist ) || ( ! isset( $countries[ $country_code ] ) && $is_whitelist ) ) {
		/**
		* Let you do what you want when an IP is blocked because of GeoIP feature.
		**/
		do_action( 'secupress.geoip.blocked', $ip, $country_code );
		secupress_block( 'GIP' );
	}
}


/**
 * Get the country code of a given IP.
 *
 * @since 1.4.5 Handle 32 bits systems.
 * @since 1.0
 *
 * @param (string) $ip An IP address.
 *
 * @return (string|null) A country code. Null if find nothing.
 **/
function secupress_geoip2country( $ip ) {
	global $wpdb;

	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		$ip2long = sprintf( '%u', ip2long( $ip ) );
	} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		$ip2long = secupress_ipv6_numeric( $ip );
	} else {
		return null;
	}

	return $wpdb->get_var( $wpdb->prepare( "SELECT country_code FROM {$wpdb->prefix}secupress_geoips WHERE begin_ip <= %s AND begin_ip > 0 ORDER BY begin_ip DESC LIMIT 1", $ip2long ) ); // WPCS: unprepared SQL OK.
}
