<?php
/**
 * Module Name: Schedules File Monitoring
 * Description: Schedule File Monitoring.
 * Main Module: schedules
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

require_once( SECUPRESS_PRO_MODULES_PATH . 'schedules/plugins/inc/php/class-secupress-schedules.php' );
require_once( SECUPRESS_PRO_MODULES_PATH . 'schedules/plugins/inc/php/class-secupress-schedules-file-monitoring.php' );
SecuPress_Schedules_File_Monitoring::get_instance();
