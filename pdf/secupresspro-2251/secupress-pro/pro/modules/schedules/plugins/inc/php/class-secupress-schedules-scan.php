<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Schedules Scan class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Schedules_Scan extends SecuPress_Schedules {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0';

	/**
	 * The reference to *Singleton* instance of this class.
	 *
	 * @var (object)
	 */
	protected static $_instance;

	/**
	 * Name of the sub-module.
	 *
	 * @var (string)
	 */
	protected $submodule = 'schedules-scan';

	/**
	 * Name of the cron that triggers the event.
	 *
	 * @var (string)
	 */
	protected $cron_name = 'secupress_schedules_scan';

	/**
	 * Time the cron will trigger the event.
	 *
	 * @var (string)
	 */
	protected $cron_time = '01:00';

	/**
	 * SecuPress_Background_Process_Schedules_Scan instance.
	 *
	 * @var (object)
	 */
	protected $background_process;


	/** Init ==================================================================================== */

	/**
	 * Launch main hooks.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	protected function _init() {
		parent::_init();

		secupress_require_class_async();

		require_once( SECUPRESS_PRO_MODULES_PATH . 'schedules/plugins/inc/php/class-secupress-background-process-schedules-scan.php' );

		$this->background_process = new SecuPress_Background_Process_Schedules_Scan;
	}


	/** Cron ==================================================================================== */

	/**
	 * Perform the scan.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (bool) True on success, false on failure.
	 */
	public function do_event() {
		$scanners = secupress_get_scanners();

		foreach ( $scanners as $module_scanners ) {
			foreach ( $module_scanners as $scanner ) {
				$this->background_process->push_to_queue( $scanner );
			}
		}

		$this->background_process->save()->dispatch();
		return true;
	}


	/** Notification Tools ====================================================================== */

	/**
	 * Get some strings for the email notification.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (array) $args An array of arguments with at least:
	 *                - (bool)  $success      Whether the operation succeeded or not.
	 *                - (array) $message_data An array of data to use in the message (via `vsprintf()` for example).
	 *
	 * @return (array)
	 */
	protected function get_email_strings( $args = array() ) {
		$counts = secupress_get_scanner_counts();
		$grade  = sprintf( __( 'Grade %s', 'secupress' ), '<span class="letter">' . $counts['grade'] . '</span>' );
		return array(
			/** Translators: %s is the blog name. */
			'subject' => sprintf( __( '[%s] Security Scan done: %s', 'secupress' ), '###SITENAME###', $grade ),
			'message' => __( 'The scheduled security scan of your site has succeeded!', 'secupress' ) . "\n\n" . $counts['text'] . "\n\n" . $counts['subtext'],
		);
	}
}
