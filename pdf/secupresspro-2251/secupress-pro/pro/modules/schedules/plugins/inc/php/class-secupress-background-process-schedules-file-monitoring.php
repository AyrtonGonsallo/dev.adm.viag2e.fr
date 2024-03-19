<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Background Schedules Scan class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Background_Process_Schedules_File_Monitoring extends SecuPress_Background_Process_File_Monitoring {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0';

	/**
	 * Suffix used to build the global process identifier.
	 *
	 * @var (string)
	 */
	protected $action = 'schedules_file_monitoring';


	/**
	 * Complete.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	protected function complete() {
		parent::complete();

		// Send the notification to the user.
		SecuPress_Schedules_File_Monitoring::get_instance()->maybe_send_notification();
	}
}
