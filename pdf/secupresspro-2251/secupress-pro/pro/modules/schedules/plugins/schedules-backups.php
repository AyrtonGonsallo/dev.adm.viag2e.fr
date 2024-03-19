<?php
/**
 * Module Name: Schedules Backups
 * Description: Schedule backups - database and/or files.
 * Main Module: schedules
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

require_once( SECUPRESS_PRO_MODULES_PATH . 'schedules/plugins/inc/php/class-secupress-schedules.php' );
require_once( SECUPRESS_PRO_MODULES_PATH . 'schedules/plugins/inc/php/class-secupress-schedules-backups.php' );
SecuPress_Schedules_Backups::get_instance();
