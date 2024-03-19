<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Background Schedules Scan class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Background_Process_Schedules_Scan extends WP_Background_Process {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0';

	/**
	 * Prefix used to build the global process identifier.
	 *
	 * @var (string)
	 */
	protected $prefix = 'secupress';

	/**
	 * Suffix used to build the global process identifier.
	 *
	 * @var (string)
	 */
	protected $action = 'schedules_scan';


	/**
	 * Task.
	 *
	 * @param (mixed) $item Queue item to iterate over.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (mixed)
	 */
	protected function task( $item ) {
		if ( $item && is_string( $item ) ) {
			secupress_scanit( $item );
		}

		return false;
	}


	/**
	 * Complete.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	protected function complete() {
		parent::complete();

		// Update the date of the last One-click scan.
		$items  = array_filter( (array) get_site_option( SECUPRESS_SCAN_TIMES ) );
		$counts = secupress_get_scanner_counts();
		$item   = array(
			'percent' => round( $counts['good'] * 100 / $counts['total'] ),
			'grade'   => $counts['grade'],
			'time'    => time(),
		);

		array_push( $items, $item );
		$items = array_slice( $items, -5, 5 );
		update_site_option( SECUPRESS_SCAN_TIMES, $items );

		// Update the date of the last scheduled scan.
		update_site_option( 'secupress_scheduled_scanners_time', time() );

		// Keep the old scan report (grade + status) to be compared on step 4.
		if ( ! function_exists( 'secupress_set_old_report' ) ) {
			require_once( SECUPRESS_INC_PATH . 'admin/functions/admin.php' );
		}
		secupress_set_old_report();

		// Send the notification to the user.
		$items = array_values( array_reverse( $items ) );
		if ( ! isset( $items[1] ) || $items[0]['percent'] < $items[1]['percent'] ) {
			$grade = sprintf( __( 'Grade %s', 'secupress' ), '<span class="letter">' . $counts['grade'] . '</span>' );
			SecuPress_Schedules_Scan::get_instance()->maybe_send_notification( $grade );
		}
	}
}
