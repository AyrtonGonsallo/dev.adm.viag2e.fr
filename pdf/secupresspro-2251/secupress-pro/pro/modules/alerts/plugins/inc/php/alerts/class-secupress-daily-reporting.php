<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Daily Reporting class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Daily_Reporting extends SecuPress_Alerts {

	const VERSION = '1.0.1';

	/**
	 * The reference to *Singleton* instance of this class.
	 *
	 * @var (object)
	 */
	protected static $_instance;

	/**
	 * Alert type (Event Alerts, Daily Reporting...).
	 *
	 * @var (array)
	 */
	protected $alert_type = 'daily-reporting';

	/**
	 * Name of the option that stores the alerts.
	 *
	 * @var (string)
	 */
	protected $option_name = 'secupress_daily_reporting_alerts';

	/**
	 * Name of the cron that triggers the alerts.
	 *
	 * @var (string)
	 */
	protected $cron_name = 'secupress_daily_reporting';

	/**
	 * If true, it means notifications will be sent on shutdown (our cron is running).
	 *
	 * @var (bool)
	 */
	protected $trigger_notifications = false;


	/** Init ==================================================================================== */

	/**
	 * Launch main hooks.
	 *
	 * @since 1.0
	 */
	protected function _init() {
		parent::_init();

		// (Re)Schedule our event.
		if ( ! wp_next_scheduled( $this->cron_name ) ) {
			wp_schedule_event( $this->cron_time(), 'daily', $this->cron_name );
		}

		add_action( $this->cron_name, array( $this, 'trigger_notifications' ) );

		// Deactivation.
		add_action( 'secupress.pro.plugins.deactivation',                     array( $this, 'deactivation' ) );
		add_action( 'secupress.modules.deactivate_submodule_daily-reporting', array( $this, 'deactivation' ) );
	}


	/**
	 * Deschedule the cron at plugin or submodule deactivation.
	 *
	 * @since 1.0
	 */
	public function deactivation() {
		if ( wp_next_scheduled( $this->cron_name ) ) {
			wp_clear_scheduled_hook( $this->cron_name );
		}

		// Force to send today notifications (so they won't be sent next time the submodule is activated). It will also delete the option in the DB.
		$this->trigger_notifications();
	}


	/** Cron ==================================================================================== */

	/**
	 * Set the `$trigger_notifications` property to true.
	 * By doing this, we allow `maybe_notify()` to send the notifications on shutdown.
	 *
	 * @since 1.0
	 */
	public function trigger_notifications() {
		$this->trigger_notifications = true;
	}


	/**
	 * Get the time to schedule the cron.
	 *
	 * @since 1.0
	 */
	public function cron_time() {
		return secupress_get_next_cron_timestamp( '00:00', 'plugin.daily_reporting' );
	}


	/** Notifications =========================================================================== */

	/**
	 * Send notifications if needed, store the remaining ones.
	 * Mix new alerts with old ones, then choose which ones should be sent:
	 * - the new alerts with the "important" attribute,
	 * - the old alerts whom the delay is exceeded.
	 *
	 * @since 1.0
	 */
	public function maybe_notify() {
		$alerts = $this->get_stored_alerts();
		$alerts = $this->merge_alerts( $alerts );

		if ( ! $this->trigger_notifications ) {
			// Store new alerts.
			$this->store_alerts( $alerts );
			return;
		}

		// Notify.
		$this->delete_stored_alerts();
		$this->notify( $alerts );
	}


	/**
	 * Get some strings for the email notification.
	 *
	 * @since 1.0
	 *
	 * @return (array)
	 */
	protected function get_email_strings() {
		$count = $this->get_alerts_number();

		return array(
			/** Translators: %s is the blog name. */
			'subject'        => sprintf( _n( '[%s] New important event on your site', '[%s] New important events on your site', $count, 'secupress' ), '###SITENAME###' ),
			'before_message' => _n( 'An important event happened on your site today:', 'Some important events happened on your site today:', $count, 'secupress' ),
			'after_message'  => '',
		);
	}
}
