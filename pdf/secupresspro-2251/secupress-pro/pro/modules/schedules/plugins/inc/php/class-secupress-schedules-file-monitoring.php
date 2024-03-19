<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Schedules File Monitoring class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Schedules_File_Monitoring extends SecuPress_Schedules {

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
	protected $submodule = 'schedules-file-monitoring';

	/**
	 * Name of the cron that triggers the event.
	 *
	 * @var (string)
	 */
	protected $cron_name = 'secupress_schedules_file_monitoring';

	/**
	 * Time the cron will trigger the event.
	 *
	 * @var (string)
	 */
	protected $cron_time = '01:30';


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
		return secupress_file_monitoring_get_instance()->do_database_scan();
		return secupress_file_monitoring_get_instance()->do_file_scan();
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
		if ( ! $args['success'] ) {
			return array(
				/** Translators: %s is the blog name. */
				'subject' => sprintf( __( '[%s] File Monitoring failed', 'secupress' ), '###SITENAME###' ),
				'message' => __( 'The scheduled file monitoring of your site couldn’t be launched, it was already running at the time.', 'secupress' ),
			);
		}

		$files   = secupress_file_scanner_get_result();
		$message = __( 'The scheduled file monitoring of your site has succeeded!', 'secupress' ) . '<br/>';

		if ( ! array_filter( $files ) ) {
			$message .= __( 'There is nothing special to report.', 'secupress' );
		} else {
			/**
			 * Files that are not part of the WordPress installation.
			 */
			if ( ! empty( $files['not-wp-files'] ) ) {
				$message .= _n( 'The following file is not from WordPress core:', 'The following files are not from WordPress core:', count( $files['not-wp-files'] ), 'secupress' );
				$message .= '<ol>';
				foreach ( $files['not-wp-files'] as $file ) {
					$message .= '<li><code>' . esc_html( $file ) . '</code>' . secupress_check_malware( $file ) . '</li>';
				}
				$message .= '</ol>';
			}

			/**
			 * Missing files from WP Core.
			 */
			if ( ! empty( $files['missing-wp-files'] ) ) {
				$files['missing-wp-files'] = array_map( 'esc_html', $files['missing-wp-files'] );

				$message .= _n( 'The following file is missing from WordPress core:', 'The following files are missing from WordPress core:', count( $files['missing-wp-files'] ), 'secupress' );
				$message .= '<ol><li><code>' . implode( '</code></li><li><code>', $files['missing-wp-files'] ) . '</code></li></ol>';
			}

			/**
			 * Old WP files.
			 */
			if ( ! empty( $files['old-wp-files'] ) ) {
				$message .= _n( 'The following file is an old WordPress core file:', 'The following files are old WordPress core files:', count( $files['old-wp-files'] ), 'secupress' );
				$message .= '<ol>';
				foreach ( $files['old-wp-files'] as $file ) {
					$message .= '<li><code>' . esc_html( $file ) . '</code>' . secupress_check_malware( $file ) . '</li>';
				}
				$message .= '</ol>';
			}

			/**
			 * Modified WP Core files.
			 */
			if ( ! empty( $files['modified-wp-files'] ) ) {
				$message .= _n( 'The following core file has been modified:', 'The following core files have been modified:', count( $files['modified-wp-files'] ), 'secupress' );
				$message .= '<ol>';
				foreach ( $files['modified-wp-files'] as $file ) {
					$message .= '<li><code>' . esc_html( $file ) . '</code>' . secupress_check_malware( $file ) . '</li>';
				}
				$message .= '</ol>';
			}
			/** Translators: %s is a "Malware Scan" link. */
			$message .= sprintf( __( 'For more details and maybe take actions, go to the %s page.', 'secupress' ), '<a href="' . esc_url( secupress_admin_url( 'modules', 'file-system' ) ) . '">' . __( 'Malware Scan', 'secupress' ) . '</a>' );
		}

		return array(
			/** Translators: %s is the blog name. */
			'subject' => sprintf( __( '[%s] File Monitoring done', 'secupress' ), '###SITENAME###' ),
			'message' => $message,
		);
	}
}
