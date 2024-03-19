<?php
/**
 * Files Backup: SecuPress_Backup_Files_Local class
 *
 * @package SecuPress
 * @since 1.0
 */

defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Class extending the `SecuPress_Backup_Files` class, allowing to create local backups.
 *
 * @since 1.0
 * @see SecuPress_Backup_Files
 */
class SecuPress_Backup_Files_Local extends SecuPress_Backup_Files {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0';

	/**
	 * Absolute path to the backup file.
	 *
	 * @var (string)
	 */
	protected $file_path;


	/** Backup ================================================================================== */

	/**
	 * Perform a backup and put the file into a specific folder.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string|bool) The file path on success, false on failure.
	 */
	public function do_backup() {
		if ( ! parent::do_backup() ) {
			return false;
		}

		// Move the file.
		$success = $this->move_file( $this->get_tmp_backup_file_path(), $this->get_backup_file_path(), true );

		return $success ? $this->get_backup_file_path() : false;
	}


	/** Getters ================================================================================= */

	/**
	 * Get the absolute path to the backup file.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string) A path.
	 */
	public function get_backup_file_path() {
		if ( ! empty( $this->file_path ) ) {
			return $this->file_path;
		}

		$this->file_path = secupress_get_local_backups_path() . $this->get_backup_file_name();

		return $this->file_path;
	}
}
