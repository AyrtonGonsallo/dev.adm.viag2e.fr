<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ON FORM SUBMIT ============================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Callback to filter, sanitize, validate and de/activate submodules.
 *
 * @since 1.0
 *
 * @param (array) $settings The module settings.
 *
 * @return (array) The sanitized and validated settings.
 */
function secupress_pro_schedules_settings_callback( $settings ) {
	$modulenow = 'schedules';
	$activate  = secupress_get_submodule_activations( $modulenow );
	$settings  = $settings && is_array( $settings ) ? $settings : array();

	if ( isset( $settings['sanitized'] ) ) {
		return $settings;
	}
	$settings['sanitized'] = 1;

	// Backups.
	secupress_schedules_backups_settings_callback( $modulenow, $settings );

	// Scanners.
	secupress_schedules_scanners_settings_callback( $modulenow, $settings, $activate );

	// File Monitoring.
	secupress_schedules_file_monitoring_settings_callback( $modulenow, $settings, $activate );

	return $settings;
}


/**
 * Schedules Backups.
 *
 * @since 1.0
 *
 * @param (string) $modulenow Current module.
 * @param (array)  $settings  The module settings, passed by reference.
 */
function secupress_schedules_backups_settings_callback( $modulenow, &$settings ) {
	$types = array( 'db', 'files' );

	if ( ! empty( $settings['schedules-backups_type'] ) && is_array( $settings['schedules-backups_type'] ) ) {
		$settings['schedules-backups_type'] = array_intersect( $types, $settings['schedules-backups_type'] );
	} else {
		unset( $settings['schedules-backups_type'] );
	}

	if ( empty( $settings['schedules-backups_type'] ) || empty( $settings['schedules-backups_periodicity'] ) ) {
		unset( $settings['schedules-backups_type'], $settings['schedules-backups_periodicity'] );
		// Deactivation.
		secupress_deactivate_submodule( $modulenow, 'schedules-backups' );
	} else {
		$settings['schedules-backups_periodicity'] = max( 1, (int) $settings['schedules-backups_periodicity'] );
		// Activation.
		secupress_activate_submodule( $modulenow, 'schedules-backups' );
	}

	$settings['schedules-backups_email'] = ! empty( $settings['schedules-backups_email'] ) ? sanitize_email( $settings['schedules-backups_email'] ) : '';

	if ( ! $settings['schedules-backups_email'] ) {
		unset( $settings['schedules-backups_email'] );
	}
}


/**
 * Schedules Scanners.
 *
 * @since 1.0
 *
 * @param (string) $modulenow Current module.
 * @param (array)  $settings  The module settings, passed by reference.
 */
function secupress_schedules_scanners_settings_callback( $modulenow, &$settings ) {
	if ( empty( $settings['schedules-scan_periodicity'] ) ) {
		unset( $settings['schedules-scan_periodicity'] );
		// Deactivation.
		secupress_deactivate_submodule( $modulenow, 'schedules-scan' );
	} else {
		$settings['schedules-scan_periodicity'] = max( 1, min( 7, (int) $settings['schedules-scan_periodicity'] ) );
		// Activation.
		secupress_activate_submodule( $modulenow, 'schedules-scan' );
	}

	$settings['schedules-scan_email'] = ! empty( $settings['schedules-scan_email'] ) ? sanitize_email( $settings['schedules-scan_email'] ) : '';

	if ( ! $settings['schedules-scan_email'] ) {
		unset( $settings['schedules-scan_email'] );
	}
}


/**
 * Schedules File Monitoring.
 *
 * @since 1.0
 *
 * @param (string) $modulenow Current module.
 * @param (array)  $settings  The module settings, passed by reference.
 */
function secupress_schedules_file_monitoring_settings_callback( $modulenow, &$settings ) {
	if ( empty( $settings['schedules-file-monitoring_periodicity'] ) ) {
		unset( $settings['schedules-file-monitoring_periodicity'] );
		// Deactivation.
		secupress_deactivate_submodule( $modulenow, 'schedules-file-monitoring' );
	} else {
		$settings['schedules-file-monitoring_periodicity'] = max( 1, min( 7, (int) $settings['schedules-file-monitoring_periodicity'] ) );
		// Activation.
		secupress_activate_submodule( $modulenow, 'schedules-file-monitoring' );
	}

	$settings['schedules-file-monitoring_email'] = ! empty( $settings['schedules-file-monitoring_email'] ) ? sanitize_email( $settings['schedules-file-monitoring_email'] ) : '';

	if ( ! $settings['schedules-file-monitoring_email'] ) {
		unset( $settings['schedules-file-monitoring_email'] );
	}
}
