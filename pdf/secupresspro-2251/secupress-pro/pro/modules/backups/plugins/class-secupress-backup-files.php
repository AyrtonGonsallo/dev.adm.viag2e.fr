<?php
/**
 * Files Backup: SecuPress_Backup_Files class
 *
 * @package SecuPress
 * @since 1.0
 */

defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Class extending the `SecuPress_Backup` class, allowing to add recursively files and do backups.
 *
 * @since 1.0
 * @see SecuPress_Backup
 */
class SecuPress_Backup_Files extends SecuPress_Backup {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0';

	/**
	 * Backup type: must be provided by sub-class.
	 *
	 * @var (string)
	 */
	protected $type = 'files';

	/**
	 * Files or folders to ignore (paths are absolute).
	 *
	 * @var (array)
	 */
	protected $to_ignore;


	/** Backup ================================================================================== */

	/**
	 * Perform a backup and put the file into the temporary folder. This method should be over-ridden in a sub-class.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string|bool) The file path on success, false on failure.
	 */
	public function do_backup() {
		if ( $this->open( $this->get_tmp_backup_file_path(), static::CREATE ) !== true ) {
			return false;
		}

		secupress_maybe_increase_memory_limit();

		$this->add_directory( static::$abspath );
		$this->close();

		return $this->get_tmp_backup_file_path();
	}


	/** Work with the zip file ================================================================== */

	/**
	 * Add files recursively to the archive.
	 * Folders containing a file `.donotbackup` are ignored.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (string) $dir A folder path.
	 */
	public function add_directory( $dir ) {
		$this->get_files_to_ignore();

		$dir = untrailingslashit( wp_normalize_path( $dir ) );

		// Ignore some folders.
		if ( isset( $this->to_ignore[ $dir ] ) || file_exists( "$dir/.donotbackup" ) ) {
			return;
		}

		// Get files and folders.
		$files = glob( "$dir/{,.}*", GLOB_BRACE );
		$files = $files ? array_flip( $files ) : array();
		unset( $files[ "$dir/." ], $files[ "$dir/.." ], $files[ "$dir/.DS_Store" ], $files[ "$dir/Thumbs.db" ] );

		if ( ! $files ) {
			return;
		}

		// Add files to the archive.
		foreach ( $files as $filepath => $i ) {
			if ( is_dir( $filepath ) ) {
				$this->add_directory( $filepath );
				continue;
			}

			$filepath = wp_normalize_path( $filepath );

			// Ignore some files.
			if ( isset( $this->to_ignore[ $filepath ] ) ) {
				continue;
			}

			$this->add_file( $filepath );
		}
	}


	/** Getters ================================================================================= */

	/**
	 * Get a list of files and folders to ignore (paths are absolute).
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (array) Files or folders to ignore. Paths are used as keys and values.
	 */
	public function get_files_to_ignore() {
		if ( isset( $this->to_ignore ) ) {
			return $this->to_ignore;
		}

		// No, you won't backup your backups.
		$backups_path    = untrailingslashit( secupress_get_parent_backups_path() );
		$this->to_ignore = array( $backups_path => $backups_path );

		$ignore = get_site_option( 'secupress_file-backups_settings' );

		if ( ! is_array( $ignore ) || empty( $ignore['ignored_directories'] ) ) {
			return $this->to_ignore;
		}

		$ignore['ignored_directories'] = explode( "\n", $ignore['ignored_directories'] );

		foreach ( $ignore['ignored_directories'] as $relative_path ) {
			$relative_path = static::$abspath . trim( wp_normalize_path( $relative_path ), '/' );
			$this->to_ignore[ $relative_path ] = $relative_path;
		}

		return $this->to_ignore;
	}
}
