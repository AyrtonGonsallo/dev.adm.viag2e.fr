<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
* @since 2.1 switch from software77.net to download.maxmind.com due to shutdown
*/

add_filter( 'http_request_args', 'secupress_bypass_limit', 10, 2 );
/**
 * Used to bypass the limitation download since on shared host, multiple sites could share the same IP, we fake one.
 *
 * @param (array)  $r HTTP requests arguments.
 * @param (string) $url The requested URL.
 * @since 2.1 Update URL
 * @since 1.4.6
 * @author Julio potier
 * @return (array) $r HTTP requests arguments
 **/
function secupress_bypass_limit( $r, $url ) {
	if ( strpos( $url, 'https://download.maxmind.com/app/geoip_download' ) === 0 ) {
		$r['headers']['X-Forwarded-For'] = long2ip( rand( 0, PHP_INT_MAX ) );
	}
	return $r;
}

// function secupress_geoips_update_datafiles() // Deprecated since v2.1

/**
 * Update a v4/v6 file for the GeoIP database on demand
 *
 * @return (bool) True if new file has been updated
 * @since 2.1 Deprecated argument (v4+v6 are merged) + new data provider
 * @since 1.4.9 $type param, see secupress_geoips_update_datafiles()
 * @since 1.4.6
 * @author Julio Potier
 **/
function secupress_geoips_update_datafile( $deprecated = '' ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.1', '' );
	}

	$downloads = secupress_geoips_get_downloads();

	// SecuPress is actually donating to this site/service each month to permit the usage in the Pro version.
	if ( ! function_exists( 'download_url' ) ) {
		require( ABSPATH . 'wp-admin/includes/file.php' );
	}
	$SECUPRESS_GEOIP_KEY = apply_filters( 'secupress.plugin.geoip.api_key', str_rot13( SECUPRESS_GEOIP_KEY ) );

	$sha256 = wp_remote_get( 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country-CSV&license_key=' . $SECUPRESS_GEOIP_KEY . '&suffix=zip.sha256' );
	if ( is_wp_error( $sha256 ) || 200 !== wp_remote_retrieve_response_code( $sha256 ) ) {
		if ( ! defined( 'DOING_CRON' ) ) {
			secupress_add_transient_notice( sprintf( __( 'GeoIP file has not been created: %s', 'secupress' ), sprintf( __( 'Download Error Line#%d', 'secupress' ), __LINE__ ) ) );
		}
		return false;
	}

	$zip = download_url( 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country-CSV&license_key=' . $SECUPRESS_GEOIP_KEY . '&suffix=zip' );
	if ( is_wp_error( $zip ) ) {
		if ( ! defined( 'DOING_CRON' ) ) {
			secupress_add_transient_notice( sprintf( __( 'GeoIP file has not been created: %s', 'secupress' ), sprintf( __( 'Download Error Line#%d', 'secupress' ), __LINE__ ) ) );
		}
		return false;
	}

	list( $tmp_hash, $filename ) = explode( '  ', wp_remote_retrieve_body( $sha256 ) );
	if ( ! hash_equals( hash_file( 'sha256', $zip ), $tmp_hash ) ) {
		@unlink( $zip );
		if ( ! defined( 'DOING_CRON' ) ) {
			secupress_add_transient_notice( sprintf( __( 'GeoIP file has not been created: %s', 'secupress' ), sprintf( __( 'Download Error Line#%d', 'secupress' ), __LINE__ ) ) );
		}
		return false;
	}

	WP_Filesystem();
	$destination       = wp_upload_dir();
	$destination_path  = $destination['path'];
	$unzipfile         = unzip_file( $zip, $destination_path );
	$destination_path .= '/' . str_replace( '.zip', '', trim( $filename ) );
	@unlink( $zip );

	if ( is_wp_error( $unzipfile ) ) {
		if ( ! defined( 'DOING_CRON' ) ) {
			secupress_add_transient_notice( sprintf( __( 'GeoIP file has not been created: %s', 'secupress' ), __( 'Unzip Error.', 'secupress' ) ) );
		}
		return false;
	}

	if ( ! file_exists( $destination_path . $downloads['v4'] ) || ! file_exists( $destination_path . $downloads['v6'] ) ) {
		if ( ! defined( 'DOING_CRON' ) ) {
			secupress_add_transient_notice( sprintf( __( 'GeoIP file has not been created: %s', 'secupress' ), __( 'Missing File.', 'secupress' ) ) );
		}
		return false;
	}
	
	// Add the countries to this foreach loop at FIRST position, and only here, do not add it in the main function.
	$downloads = [ 'countries' => '/GeoLite2-Country-Locations-en.csv' ] + $downloads;
	// Create each data file depending on IP type
	foreach ( $downloads as $type => $type_file ) {
		$lines   = file( $destination_path . $type_file );
		$content = secupress_geoips_parse_file( $lines, $type );
		@unlink( SECUPRESS_PRO_INC_PATH . 'data/geoips-' . $type . '.data' );
		file_put_contents( SECUPRESS_PRO_INC_PATH . 'data/geoips-' . $type . '.data', $content );
	}

	secupress_geoip_clean_zip( $destination_path );

	return true;
}

/**
 * Clean the unzipped files
 *
 * @since 2.1
 * @author Julio Potier
 **/
function secupress_geoip_clean_zip( $destination_path ) {
	$files = [ 	
				$destination_path . '/README.txt',
				$destination_path . '/LICENSE.txt',
				$destination_path . '/COPYRIGHT.txt',
				$destination_path . '/GeoLite2-Country-Blocks-IPv4.csv',
				$destination_path . '/GeoLite2-Country-Blocks-IPv6.csv',
				$destination_path . '/GeoLite2-Country-Locations-de.csv',
				$destination_path . '/GeoLite2-Country-Locations-en.csv',
				$destination_path . '/GeoLite2-Country-Locations-es.csv',
				$destination_path . '/GeoLite2-Country-Locations-fr.csv',
				$destination_path . '/GeoLite2-Country-Locations-ja.csv',
				$destination_path . '/GeoLite2-Country-Locations-pt-BR.csv',
				$destination_path . '/GeoLite2-Country-Locations-ru.csv',
				$destination_path . '/GeoLite2-Country-Locations-zh-CN.csv',
			];
	@array_map( 'unlink', $files );
	@rmdir( $destination_path );
}


/**
 * Parse files content from download.maxmind.com
 *
 * @since 2.1 Compat with new provider
 * @since 1.4.9
 * @author Julio Potier
 *
 * @param (array) $lines Each line contains the IP info
 * @param (string) $type v4 or v6
 * @return
 **/
function secupress_geoips_parse_file( $lines, $type ) {
	static $country_codes;

	$content = '';
	switch ( $type ) {
		case 'countries':
			foreach ( $lines as $line ) {
				if ( 'g' === $line[0] ) { // geoname_id
					$content = [];
					continue;
				}
				list( $geoname_id, $locale_code, $continent_code, $continent_name, $country_iso_code ) = explode( ',', $line );
				$content[ $geoname_id ] = $country_iso_code;
			}
			$content = json_encode( $content );
		break;

		case 'v4':
			if ( ! isset( $country_codes ) ) {
				$country_codes = file_get_contents( SECUPRESS_PRO_INC_PATH . 'data/geoips-countries.data' );
				if ( $country_codes ) {
					$country_codes = json_decode( $country_codes, true );
				}
			}
			foreach ( $lines as $line ) {
				if ( 'n' === $line[0] ) { // network
					continue;
				}
				$parts    = explode( ',', $line );
				list( $begin, $mask ) = explode( '/', $parts[0] );
				$begin    = '"' . ip2long( $begin ) . '"';
				$code     = isset( $country_codes[ $parts[1] ] ) ? '"' . $country_codes[ $parts[1] ] . '"' : false;
				$code     = ! $code ? '"' . $country_codes[ $parts[2] ] . '"' : $code;
				$code     = $code ? $code : '"KO4"';
				$content .= "$begin,$code\n";
			}
		break;

		case 'v6':
			if ( ! isset( $country_codes ) ) {
				$country_codes = file_get_contents( SECUPRESS_PRO_INC_PATH . 'data/geoips-countries.data' );
				if ( $country_codes ) {
					$country_codes = json_decode( $country_codes, true );
				}
			}
			foreach ( $lines as $line ) {
				if ( 'n' === $line[0] ) { // network
					continue;
				}
				$parts    = explode( ',', $line );
				list( $ip, $mask ) = explode( '/', $parts[0] );
				if ( strpos( $ip, '::' ) !== false ) {
					$ip = str_replace( '::', '', $ip );
					$ip = $ip . str_repeat( ':0', 7 - substr_count( $ip, ':' ) );
				}
				$begin    = '"' . secupress_ipv6_numeric( $ip ) . '"';
				$code     = isset( $country_codes[ $parts[1] ] ) ? '"' . $country_codes[ $parts[1] ] . '"' : false;
				$code     = ! $code ? '"' . $country_codes[ $parts[2] ] . '"' : $code;
				$code     = $code ?? '"KO6"';
				$content .= "$begin,$code\n";
			}
		break;
	}
	return $content;
}

/**
 * Update the database GeoIPs content with the given $queries
 *
 * @param (string) $queries SQL queries to be updated.
 * @since 1.4.6
 * @author Julio Potier
 **/
function secupress_geoips_update_database( $queries ) {
	global $wpdb;

	$queries = explode( "\n", $queries );
	$queries = array_chunk( $queries, 1000 );
	foreach ( $queries as $query ) {
		array_pop( $query );
		$query = rtrim( rtrim( implode( "),\n(", $query ) ), ',' );
		$wpdb->query( "INSERT INTO {$wpdb->prefix}secupress_geoips (begin_ip, country_code) VALUES ($query)" ); // WPCS: unprepared SQL ok.
	}
}

/**
 * Update the file + database
 *
 * @author Julio Potier
 * @since 2.1 Only 1 $result (v4+v6 are now merged)
 * @since 1.4.6
 * @return (bool) Bool if ok
 **/
function secupress_geoips_update_datas() {
	$result = secupress_geoips_update_data();

	secupress_set_option( 'geoips_last_update', date_i18n( get_option( 'date_format' ), time() ) );

	return $result;
}

/**
 * Update the file + database
 *
 * @return (bool) Bool if ok
 * @since 2.1 Deprecated argument
 * @since 1.4.6
 * @author Julio Potier
 **/
function secupress_geoips_update_data( $deprecated = '' ) {
	global $wpdb;

	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.1', '' );
	}

	if ( ! secupress_geoips_update_datafile() ) {
		return false;
	}

	$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}secupress_geoips" );

	$downloads = secupress_geoips_get_downloads();
	foreach ( $downloads as $type => $type_file ) {
		$filename = SECUPRESS_PRO_INC_PATH . 'data/geoips-' . $type . '.data';
		$queries  = file_exists( $filename ) ? file_get_contents( $filename ) : false;
		@unlink( $filename );
		if ( $queries ) {
			secupress_geoips_update_database( $queries );
		} else {
			return false;
		}
	}
	@unlink( SECUPRESS_PRO_INC_PATH . 'data/geoips-countries.data' );
	return true;
}

/**
 * Declare the ID and filename from download.maxmind.com
 *
 * @since 2.1 New filenames from new provider
 * @since 1.4.9
 * @author Julio Potier
 *
 * @return (array) The files needed to get the ipv4 and ipv6 content
 **/
function secupress_geoips_get_downloads() {
	return [ 'v4' => '/GeoLite2-Country-Blocks-IPv4.csv', 'v6' => '/GeoLite2-Country-Blocks-IPv6.csv' ];
}
