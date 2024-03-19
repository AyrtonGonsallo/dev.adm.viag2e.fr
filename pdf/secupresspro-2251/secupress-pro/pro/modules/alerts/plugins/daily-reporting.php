<?php
/**
 * Module Name: Daily Reporting
 * Description: Receive a summary of important events every day.
 * Main Module: alerts
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** INCLUDE AND INITIATE ======================================================================== */
/** ----------------------------------------------------------------------------------------------*/

require_once( SECUPRESS_PRO_MODULES_PATH . 'alerts/plugins/inc/php/alerts/class-secupress-alerts.php' );
require_once( SECUPRESS_PRO_MODULES_PATH . 'alerts/plugins/inc/php/alerts/class-secupress-daily-reporting.php' );

SecuPress_Daily_Reporting::get_instance();
