<?php
/**
 * Backup: SecuPress_Backup class
 *
 * @package SecuPress
 * @since 1.0
 */

defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Class extending the `ZipArchive` class, used for backups.
 *
 * @since 1.0
 * @see ZipArchive
 */
class SecuPress_Backup extends ZipArchive {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0';

	/**
	 * Normalized ABSPATH.
	 *
	 * @var (string)
	 */
	protected static $abspath;

	/**
	 * Path to the temporary folder.
	 *
	 * @var (string)
	 */
	protected static $tmp_path;

	/**
	 * Backup type: must be over-ridden in a sub-class.
	 *
	 * @var (string)
	 */
	protected $type = 'backup';

	/**
	 * Backup file name.
	 *
	 * @var (string)
	 */
	protected $file_name;

	/**
	 * Absolute path to the temporary backup file.
	 *
	 * @var (string)
	 */
	protected $tmp_file_path;


	/** Public methods ========================================================================== */

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	public function __construct() {
		if ( ! isset( static::$abspath ) ) {
			static::$abspath  = wp_normalize_path( ABSPATH );
			static::$tmp_path = secupress_get_temporary_backups_path();
		}
	}


	/** Backup ================================================================================== */

	/**
	 * Perform a backup. This method must be over-ridden in a sub-class.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string|bool) The file path on success, false on failure.
	 */
	public function do_backup() {
		die( 'Method SecuPress_Backup::do_backup() must be over-ridden in a sub-class.' );
		return false;
	}


	/** Work with the zip file ================================================================== */

	/**
	 * An evolved wrapper for the method `addFile()`.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (string) $filepath The path to the file to add.
	 * @param (string) $filename If supplied, this is the local name inside the ZIP archive that will override the `$filepath`.
	 *
	 * @return (bool) True on success, false on failure.
	 */
	public function add_file( $filepath, $filename = null ) {
		if ( ! wp_is_writable( $filepath ) ) {
			return false;
		}

		if ( ! isset( $filename ) ) {
			$filename = str_replace( static::$abspath, '', $filepath );
		}

		return $this->addFile( $filepath, $filename );
	}


	/**
	 * Use WP Filesystem to move a file.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (string) $source      The path to the file to move.
	 * @param (string) $destination The path to where the file should be moved to.
	 * @param (bool)   $overwrite   Set to true to overwrite the destination.
	 *
	 * @return (bool) True on success, false on failure.
	 */
	public function move_file( $source, $destination, $overwrite = false ) {
		if ( ! file_exists( $source ) ) {
			return false;
		}

		return secupress_get_filesystem()->move( $source, $destination, $overwrite );
	}


	/**
	 * Use WP Filesystem to delete a file.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (string) $file      The path to the file to delete.
	 * @param (bool)   $recursive Optional. If set True delete recursively.
	 * @param (string) $type      Type of deletion. "f" for files.
	 *
	 * @return (bool) True on success, false on failure.
	 */
	public function delete_file( $file, $recursive = false, $type = false ) {
		return secupress_get_filesystem()->delete( $file, $recursive, $type );
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
		return $this->get_tmp_backup_file_path();
	}


	/**
	 * Get the absolute path to the temporary backup file.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string) A path.
	 */
	public function get_tmp_backup_file_path() {
		if ( empty( $this->tmp_file_path ) ) {
			$this->tmp_file_path = static::$tmp_path . $this->get_backup_file_name();
		}

		return $this->tmp_file_path;
	}


	/**
	 * Create a filename for backup.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string) A filename.
	 */
	public function get_backup_file_name() {
		if ( ! empty( $this->file_name ) ) {
			return $this->file_name;
		}

		$this->file_name = preg_replace( '@^https?://@', '', untrailingslashit( home_url() ) );
		$this->file_name = str_replace( array( '.', '/' ), array( '@', '#' ), $this->file_name );
		$this->file_name = date( 'Y-m-d-H-i' ) . '.' . $this->type . '.' . $this->file_name . '.' . uniqid() . '.zip';

		return $this->file_name;
	}
}
