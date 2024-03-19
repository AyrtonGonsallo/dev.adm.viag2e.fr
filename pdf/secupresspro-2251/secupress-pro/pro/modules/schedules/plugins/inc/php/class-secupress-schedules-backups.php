<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Schedules Backups class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Schedules_Backups extends SecuPress_Schedules {

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
	protected $submodule = 'schedules-backups';

	/**
	 * Name of the cron that triggers the event.
	 *
	 * @var (string)
	 */
	protected $cron_name = 'secupress_schedules_backups';

	/**
	 * Time the cron will trigger the event.
	 *
	 * @var (string)
	 */
	protected $cron_time = '00:30';


	/** Cron ==================================================================================== */

	/**
	 * Perform the backup.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (bool) True on success, false on failure.
	 */
	public function do_event() {
		$def_types = array( 'db', 'files' );
		$types     = secupress_get_module_option( $this->submodule . '_type', array(), 'schedules' );
		$types     = is_array( $types ) ? $types : array();
		$types     = array_intersect( $def_types, $types );
		$types     = $types ? $types : $def_types;
		$types     = array_flip( $types );
		$result    = true;

		if ( isset( $types['db'] ) ) {
			$result = $result && secupress_do_db_backup();
		}

		if ( isset( $types['files'] ) ) {
			$result = $result && secupress_do_files_backup();
		}

		$this->maybe_send_notification( array(
			'success'      => $result,
			'message_data' => $types,
		) );

		return false;
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

		if ( isset( $args['message_data']['db'], $args['message_data']['files'] ) ) {
			$types = __( 'database and files', 'secupress' );
		} elseif ( isset( $args['message_data']['db'] ) ) {
			$types = __( 'database only', 'secupress' );
		} else {
			$types = __( 'files only', 'secupress' );
		}

		if ( $args['success'] ) {
			return array(
				/** Translators: %s is the blog name. */
				'subject' => sprintf( __( '[%s] Backup succeeded', 'secupress' ), '###SITENAME###' ),
				/** Translators: %s is what kind of things are backuped. */
				'message' => sprintf( __( 'The scheduled backup of your site (%s) has succeeded!', 'secupress' ), $types ),
			);
		}

		return array(
			/** Translators: %s is the blog name. */
			'subject' => sprintf( __( '[%s] Backup failed', 'secupress' ), '###SITENAME###' ),
			/** Translators: %s is what kind of things are backuped. */
			'message' => sprintf( __( 'The scheduled backup of your site (%s) has failed.', 'secupress' ), $types ),
		);
	}
}
