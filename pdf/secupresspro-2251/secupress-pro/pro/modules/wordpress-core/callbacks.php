<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/** --------------------------------------------------------------------------------------------- */
/** ON MODULE SETTINGS SAVE ===================================================================== */
/** --------------------------------------------------------------------------------------------- */

add_filter( 'secupress_wordpress-core_settings_callback', 'secupress_pro_wpcore_settings_callback', 10, 2 );
/**
 *  Sanitize and validate Pro settings.
 *
 * @since 2.0
 *
 * @param (array) $settings  The module settings.
 */
function secupress_pro_wpcore_settings_callback( $settings ) {
	$modulenow = 'wordpress-core';
	if ( ! empty( $settings['database_db_prefix'] ) ) {
		$res     = secupress_change_db_prefix( $settings['database_db_prefix'], $settings['database_tables_selection'] );
		$message = __( 'Database Prefix unchanged.', 'secupress' ) . ' ';
		$type    = 'error';
		switch ( $res ) {
			case -1:
				$message .= __( 'New prefix was the same.', 'secupress' );
			break;

			case -2:
				$message .= __( 'New prefix is too short.', 'secupress' );
			break;

			case -3:
				$message .= sprintf( __( '%s doesn’t have the rights to alter database.', 'secupress' ), SECUPRESS_PLUGIN_NAME );
			break;

			case -4:
				$message .= sprintf( __( 'Can’t change the <code>$table_prefix</code> in <code>%s</code> file.', 'secupress' ), secupress_get_wpconfig_filename() );
			break;

			case -5:
				$message .= __( 'Tried to rename the tables but something went wrong.', 'secupress' );
			break;

			default:
				$message = sprintf( __( 'Database Prefix changed: %s', 'secupress' ), '<code>' . esc_html( $res ) . '</code>' );
				$type    = 'updated';
			break;
		}
		secupress_add_transient_notice( $message, $type );

		if ( 'error' !== $type ) {
			wp_safe_redirect( secupress_admin_url( 'modules', 'wordpress-core' ) );
			die();
		}
	}
	return $settings;
}
