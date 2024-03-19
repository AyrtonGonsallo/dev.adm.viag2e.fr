<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ON MODULE SETTINGS SAVE ===================================================================== */
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
function secupress_pro_alerts_settings_callback( $settings ) {
	$modulenow = 'alerts';
	$activate  = secupress_get_submodule_activations( $modulenow );
	$settings  = $settings && is_array( $settings ) ? $settings : array();

	if ( isset( $settings['sanitized'] ) ) {
		return $settings;
	}
	$settings['sanitized'] = 1;

	// Types of Notification.
	secupress_types_of_notification_settings_callback( $modulenow, $settings );

	// Event Alerts.
	secupress_event_alerts_settings_callback( $modulenow, $settings, $activate );

	// Daily Reporting.
	secupress_daily_reporting_settings_callback( $modulenow, $settings, $activate );

	return $settings;
}


/**
 * Types of Notification Callback.
 *
 * @since 1.0
 *
 * @param (string) $modulenow Current module.
 * @param (array)  $settings  The module settings, passed by reference.
 */
function secupress_types_of_notification_settings_callback( $modulenow, &$settings ) {
	// Types.
	if ( empty( $settings['notification-types_types'] ) || ! is_array( $settings['notification-types_types'] ) ) {
		unset( $settings['notification-types_types'] );
		$types = array();
	} else {
		$types = array_flip( secupress_alert_types_labels() );
		$settings['notification-types_types'] = array_intersect( $settings['notification-types_types'], $types );
		$types = array_flip( $settings['notification-types_types'] );
	}

	/**
	 * Types credentials.
	 */

	// Emails.
	$all_emails = array();

	if ( ! empty( $settings['notification-types_emails'] ) ) {
		$settings['notification-types_emails'] = explode( "\n", $settings['notification-types_emails'] );
		$settings['notification-types_emails'] = array_map( 'trim', $settings['notification-types_emails'] );
		$settings['notification-types_emails'] = array_map( 'is_email', $settings['notification-types_emails'] );
		$settings['notification-types_emails'] = array_filter( $settings['notification-types_emails'] );
		$settings['notification-types_emails'] = array_flip( array_flip( $settings['notification-types_emails'] ) );
		natcasesort( $settings['notification-types_emails'] );
		$all_emails = $settings['notification-types_emails'];
		$settings['notification-types_emails'] = implode( "\n", $settings['notification-types_emails'] );
		secupress_send_notification_validation( 'emails' );
	}

	if ( empty( $settings['notification-types_emails'] ) ) {
		unset( $settings['notification-types_emails'] );
	}

	/**
	 * Filter the notification types.
	 * @since 2.0
	 * @author Julio Potier
	 *
	 * @param (array) $types
	 */
	$types = apply_filters( 'secupress.notifications.types', [ 'notification-types_sms', 'notification-types_push', 'notification-types_slack', 'notification-types_twitter' ] );

	foreach ( $types as $type ) {
		if ( ! empty( $settings[ $type ] ) ) {
			$settings[ $type ] = sanitize_text_field( $settings[ $type ] );
			secupress_send_notification_validation( str_replace( 'notification-types_', '', $type ), [ 'url' => $settings[ $type ] ] );
		} else {
			unset( $settings[ $type ] );
			secupress_set_option( $type, null );
		}
	}
}


/**
 * Event Alerts Callback.
 *
 * @since 1.0
 *
 * @param (string)     $modulenow Current module.
 * @param (array)      $settings  The module settings, passed by reference.
 * @param (bool|array) $activate  Used to (de)activate plugins.
 */
function secupress_event_alerts_settings_callback( $modulenow, &$settings, $activate ) {
	// Activate/deactivate.
	secupress_manage_submodule( $modulenow, 'event-alerts', ! empty( $activate['event-alerts_activated'] ) );
	// Frequency.
	if ( empty( $settings['event-alerts_frequency'] ) || ! is_numeric( $settings['event-alerts_frequency'] ) ) {
		$settings['event-alerts_frequency'] = 15;
	} else {
		$settings['event-alerts_frequency'] = secupress_minmax_range( $settings['event-alerts_frequency'], 5, 60 );
	}
}


/**
 * Daily Reporting Callback.
 *
 * @since 1.0
 *
 * @param (string)     $modulenow Current module.
 * @param (array)      $settings  The module settings, passed by reference.
 * @param (bool|array) $activate  Used to (de)activate plugins.
 */
function secupress_daily_reporting_settings_callback( $modulenow, &$settings, $activate ) {
	// Activate/deactivate.
	if ( secupress_is_pro() ) {
		secupress_manage_submodule( $modulenow, 'daily-reporting', ! empty( $activate['daily-reporting_activated'] ) );
	} else {
		secupress_deactivate_submodule( $modulenow, array( 'daily-reporting' ) );
	}
}
