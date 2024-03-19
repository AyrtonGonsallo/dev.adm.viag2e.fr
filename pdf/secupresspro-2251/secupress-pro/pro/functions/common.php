<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** REQUIRE FILES =============================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Return the path to a class.
 *
 * @since 1.0
 *
 * @param (string) $prefix          Only one possible value so far: "scan".
 * @param (string) $class_name_part The classes name is built as follow: "SecuPress_{$prefix}_{$class_name_part}".
 *
 * @return (string) Path of the class.
 */
function secupress_pro_class_path( $prefix, $class_name_part = '' ) {
	$folders = array(
		'scan'      => 'scanners',
		'singleton' => 'common',
		'logs'      => 'common',
		'log'       => 'common',
	);

	$prefix = strtolower( str_replace( '_', '-', $prefix ) );
	$folder = isset( $folders[ $prefix ] ) ? $folders[ $prefix ] : $prefix;

	$class_name_part = strtolower( str_replace( '_', '-', $class_name_part ) );
	$class_name_part = $class_name_part ? '-' . $class_name_part : '';

	return SECUPRESS_PRO_CLASSES_PATH . $folder . '/class-secupress-pro-' . $prefix . $class_name_part . '.php';
}


/**
 * Require a class.
 *
 * @since 1.0
 *
 * @param (string) $prefix          Only one possible value so far: "scan".
 * @param (string) $class_name_part The classes name is built as follow: "SecuPress_{$prefix}_{$class_name_part}".
 */
function secupress_pro_require_class( $prefix, $class_name_part = '' ) {
	$path = secupress_pro_class_path( $prefix, $class_name_part );

	if ( $path ) {
		require_once( $path );
	}
}


/** --------------------------------------------------------------------------------------------- */
/** CRON ======================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Get the timestamp of the next (cron) date for the given hour.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (string) $cron_hour Time when the cron callback should be triggered (WordPress time), formated like `hh:mn` (hour:minute).
 * @param (string) $hook_id   An ID used as part of the hook that filters `$cron_hour`.
 *
 * @return (int) Timestamp.
 */
function secupress_get_next_cron_timestamp( $cron_hour = '00:00', $hook_id = '' ) {
	$hook_id = $hook_id ? trim( $hook_id, '.' ) . '.' : '';
	/**
	 * Filter the time at which the cron is triggered (WordPress time).
	 *
	 * @param (string) $cron_hour A 24H formated time: `hour:minute`. Default is midnight: `00:00`.
	 */
	$cron_hour = apply_filters( 'secupress.' . $hook_id . 'cron_time', $cron_hour );

	$current_hour_int = (int) date( 'Gis' );
	$cron_hour_int    = (int) str_replace( ':', '', $cron_hour . '00' );
	$cron_hour        = explode( ':', $cron_hour );
	$cron_minute      = (int) $cron_hour[1];
	$cron_hour        = (int) $cron_hour[0];
	$offset           = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;

	if ( $cron_hour_int <= $current_hour_int ) {
		// The cron time is passed, we need to schedule the event tomorrow.
		return mktime( $cron_hour, $cron_minute, 0, (int) date( 'n' ), (int) date( 'j' ) + 1 ) - $offset;
	}

	// We haven't passed the cron time yet, schedule the event today.
	return mktime( $cron_hour, $cron_minute, 0 ) - $offset;
}


/** --------------------------------------------------------------------------------------------- */
/** SCHEDULES =================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Get the time of the next scheduled backup (WordPress time).
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (int|bool) A timestamp, or false if the sub-module is deactivated.
 */
function secupress_get_next_scheduled_backup() {
	if ( ! secupress_is_pro() ) {
		return false;
	}

	if ( ! secupress_is_submodule_active( 'schedules', 'schedules-backups' ) ) {
		return false;
	}

	$timestamp = wp_next_scheduled( 'secupress_schedules_backups' );

	return $timestamp ? ( $timestamp + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) : false;
}


/**
 * Get the time of the last scheduled scan (WordPress time).
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (int|bool) A timestamp, or false if no previous scheduled scan.
 */
function secupress_get_last_scheduled_scan() {
	$timestamp = (int) get_site_option( 'secupress_scheduled_scanners_time' );

	return $timestamp ? ( $timestamp + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) : false;
}


/**
 * Get the time of the next scheduled scan (WordPress time).
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (int|bool) A timestamp, or false if the sub-module is deactivated.
 */
function secupress_get_next_scheduled_scan() {
	if ( ! secupress_is_pro() ) {
		return false;
	}

	if ( ! secupress_is_submodule_active( 'schedules', 'schedules-scan' ) ) {
		return false;
	}

	$timestamp = wp_next_scheduled( 'secupress_schedules_scan' );

	return $timestamp ? ( $timestamp + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) : false;
}


/**
 * Get the time of the next scheduled file monitoring (WordPress time).
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (int|bool) A timestamp, or false if the sub-module is deactivated.
 */
function secupress_get_next_scheduled_file_monitoring() {
	if ( ! secupress_is_pro() ) {
		return false;
	}

	if ( ! secupress_is_submodule_active( 'schedules', 'schedules-file-monitoring' ) ) {
		return false;
	}

	$timestamp = wp_next_scheduled( 'secupress_schedules_file_monitoring' );

	return $timestamp ? ( $timestamp + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) : false;
}


/** --------------------------------------------------------------------------------------------- */
/** VARIOUS ===================================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Parse an array of files, by key or value.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (array) $file_tree An array of files.
 *
 * @return (array) Files sorted by paths.
 */
function secupress_parse_paths( $file_tree ) {
	$result = array();

	foreach ( $file_tree as $key => $value ) {
		$parts   = explode( '/', $key );
		$current = &$result;

		for ( $i = 1, $max = count( $parts ); $i < $max; $i++ ) {
			if ( ! isset( $current[ $parts[ $i - 1 ] ] ) ) {
				$current[ $parts[ $i - 1 ] ] = array();
			}
			$current = &$current[ $parts[ $i - 1 ] ];
		}

		$current[] = $value;
	}

	return $result;
}

/**
 * Enqueue and Print CSS, JS, PHP, HTML to get the wpLink dialog in our sertings for url field type
 *
 * @see /wp-includes/js/wplink.js
 * @since 1.4.9
 * @author Julio Potier
 *
 * @hook admin_footer
 **/
function secupress_pro_enqueue_wplink_dialog() {
	// We need the editor buttons styles
	wp_enqueue_style( 'editor-buttons' );
	// and native wplink script here
	wp_enqueue_script( 'wplink' );
	// and native wplink script here
	$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$version = $suffix ? '1.0' : time();
	// Our wplink JS code
	wp_enqueue_script( 'secupress-wplink', SECUPRESS_ADMIN_JS_URL . 'secupress-wplink' . $suffix . '.js', 'jQuery', $version, true );
	// some i18n
	wp_localize_script( 'secupress-wplink', 'secupresswplink', [ 'insert' => __( 'Insert/edit link' ), 'home_url' => esc_url( home_url() ) ] );
}
