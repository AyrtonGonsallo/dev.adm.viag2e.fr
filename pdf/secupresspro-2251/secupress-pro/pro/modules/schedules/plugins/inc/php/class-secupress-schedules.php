<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Schedules class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Schedules extends SecuPress_Singleton {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0.1';

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
	protected $submodule;

	/**
	 * Name of the cron that triggers the event.
	 *
	 * @var (string)
	 */
	protected $cron_name;

	/**
	 * Time the cron will trigger the event.
	 *
	 * @var (string)
	 */
	protected $cron_time;


	/** Init ==================================================================================== */

	/**
	 * Launch main hooks.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	protected function _init() {
		// Add our custom schedule.
		add_filter( 'cron_schedules', array( $this, 'add_recurrence' ) );

		// If schedule has changed (user setting), unschedule (and reschedule later).
		$prev_schedule = wp_get_schedule( $this->cron_name );

		if ( $prev_schedule && $this->get_recurrence() !== $prev_schedule ) {
			wp_clear_scheduled_hook( $this->cron_name );
		}

		// (Re)Schedule our event.
		if ( ! wp_next_scheduled( $this->cron_name ) ) {
			wp_schedule_event( $this->cron_time(), $this->get_recurrence(), $this->cron_name );
		}

		add_action( $this->cron_name, array( $this, 'do_event' ) );

		// Deactivation.
		add_action( 'secupress.pro.plugins.deactivation',                         array( $this, 'deactivation' ) );
		add_action( 'secupress.modules.deactivate_submodule_' . $this->submodule, array( $this, 'deactivation' ) );
	}


	/**
	 * Deschedule the cron at plugin or sub-module deactivation.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	public function deactivation() {
		if ( wp_next_scheduled( $this->cron_name ) ) {
			wp_clear_scheduled_hook( $this->cron_name );
		}
	}


	/** Cron ==================================================================================== */

	/**
	 * Add our custom recurrence to the default cron schedules.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (array) $new_schedules An array of non-default cron schedules. Default empty.
	 *
	 * @return (array) An array of cron schedules.
	 */
	public function add_recurrence( $new_schedules ) {
		$recurrence = $this->get_recurrence();

		if ( 'daily' !== $recurrence && empty( $new_schedules[ $recurrence ] ) ) {
			$days = $this->get_interval_days();
			$new_schedules[ $recurrence ] = array( 'interval' => $days * DAY_IN_SECONDS, 'display' => sprintf( _n( 'Every %s Day', 'Every %s Days', $days, 'secupress' ), number_format_i18n( $days ) ) );
		}

		return $new_schedules;
	}


	/**
	 * Perform the cron event.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (bool) True on success, false on failure.
	 */
	public function do_event() {
		die( 'Method SecuPress_Schedules::do_event() must be over-ridden in a sub-class.' );
		return false;
	}


	/** Cron Tools ============================================================================== */

	/**
	 * Get the time to schedule the cron.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	public function cron_time() {
		return secupress_get_next_cron_timestamp( $this->cron_time, 'plugin.' . $this->submodule );
	}


	/**
	 * Get the value of the recurrence we'll use.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string) Like "daily" or "every-3-days".
	 */
	public function get_recurrence() {
		$days = $this->get_interval_days();
		return 1 === $days ? 'daily' : "every-{$days}-days";
	}


	/**
	 * Get the cron interval in days. This is what the user set.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (int) A number of days.
	 */
	public function get_interval_days() {
		return (int) secupress_get_module_option( $this->submodule . '_periodicity', 1, 'schedules' );
	}


	/** Notification ============================================================================ */

	/**
	 * Send a notification.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (array) $args An array of arguments with at least:
	 *                - (bool)  $success      Whether the operation succeeded or not.
	 *                - (array) $message_data An array of data to use in the message (via `vsprintf()` for example).
	 *
	 * @return (bool) True on success, false on failure.
	 */
	public function maybe_send_notification( $args = array() ) {
		$to = $this->get_notification_email();

		if ( ! $to ) {
			return false;
		}

		$args = array_merge( array(
			'success'      => true,
			'message_data' => array(),
		), $args );

		$strings = $this->get_email_strings( $args );

		// Subject.
		$subject = $strings['subject'];

		// Message.
		$message = $strings['message'];

		// Let this email in HTML
		add_filter(	'secupress.mail.headers', 'secupress_mail_html_headers' );
		// Go!
		return secupress_send_mail( $to, $subject, $message );
	}


	/** Notification Tools ====================================================================== */

	/**
	 * Get the email address used for the notifications. This is what the user set.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string|bool) An email address. False on failure.
	 */
	public function get_notification_email() {
		static $email;

		if ( ! isset( $email ) ) {
			$email = secupress_get_module_option( $this->submodule . '_email', '', 'schedules' );
			$email = $email ? is_email( $email ) : false;
		}

		return $email;
	}


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
		if ( $args['success'] ) {
			$array = [
				/** Translators: %s is the blog name. */
				'subject' => sprintf( __( '[%s] Operation succeeded', 'secupress' ), '###SITENAME###' ),
				'message' => __( 'The scheduled operation of your site has succeeded!', 'secupress' ),
			];
			if ( isset( $args['message_data'] ) ) {
				$array['message'] .= "\n" . $args['message_data'];
			}
			return $array;
		} else {

			$array = [
				/** Translators: %s is the blog name. */
				'subject' => sprintf( __( '[%s] Operation failed', 'secupress' ), '###SITENAME###' ),
				'message' => __( 'The scheduled operation of your site has failed.', 'secupress' ),
			];
			if ( isset( $args['message_data'] ) ) {
				$array['message'] .= "\n" . $args['message_data'];
			}
		}
		return $array;
	}
}
