<?php
/**
 * Database Backup: SecuPress_Backup_Db class
 *
 * @package SecuPress
 * @since 1.0
 */

defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Class extending the `SecuPress_Backup` class, allowing to do backups of the database.
 *
 * @since 1.0
 * @see SecuPress_Backup
 */
class SecuPress_Backup_Db extends SecuPress_Backup {

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
	protected $type = 'database';

	/**
	 * SQL file name.
	 *
	 * @var (string)
	 */
	protected $sql_file_name;


	/** Backup ================================================================================== */

	/**
	 * Perform a backup and put the file into the temporary folder. This method should be overriden.
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

		$this->create_sql_file();
		$this->add_file( $this->get_sql_file_path() );
		$this->close();
		$this->delete_file( $this->get_sql_file_path() ); // Delete the file AFTER closing the archive.

		return $this->get_tmp_backup_file_path();
	}


	/** SQL file ================================================================================ */

	/**
	 * Create the SQL file.
	 *
	 * @since 1.0
	 */
	protected function create_sql_file() {
		global $wpdb;

		$wp_tables     = secupress_get_wp_tables();
		$other_tables  = secupress_get_non_wp_tables();
		$chosen_tables = array();
		if ( $other_tables ) {
			$chosen_tables = get_site_option( 'secupress_database-backups_settings' );

			if ( is_array( $chosen_tables ) && isset( $chosen_tables['other_tables'] ) && is_array( $chosen_tables['other_tables'] ) ) {
				$chosen_tables = array_intersect( $other_tables, $chosen_tables['other_tables'] );
				$chosen_tables = array_values( $chosen_tables );
			} else {
				$chosen_tables = array_values( $other_tables );
			}

			// Skip our geoip table.
			if ( ! empty( $wpdb->prefix . 'secupress_geoips' ) ) {
				$chosen_tables = array_diff( $chosen_tables, array( $wpdb->prefix . 'secupress_geoips' ) );
			}
		}

		$filesystem = secupress_get_filesystem();
		$tables     = array_merge( $wp_tables, $chosen_tables );
		$sql_file   = $this->get_sql_file_path();
		secupress_backup_put_contents( $sql_file, "## SecuPress Backup ##\n\n" );
		foreach ( $tables as $table ) {
			$count = $wpdb->get_var( "SELECT count(*) FROM `$table`" ); // WPCS: unprepared SQL OK.
			if ( $count > 1000 ) {
				$loops = ceil( $count / 1000 );
				for ( $i = 0; $i < $loops; $i++ ) {
					$content = $this->get_db_tables_content( $table, $i );
					secupress_backup_put_contents( $sql_file, $content );
				}
			} else {
				$content = $this->get_db_tables_content( $table, 0 );
				secupress_backup_put_contents( $sql_file, $content );
			}
			unset( $content );
		}
	}


	/**
	 * Create a sql dump.
	 *
	 * @since 1.0
	 *
	 * @param (array) $tables An array of tables.
	 * @param (int)   $offset An offset.
	 *
	 * @return (string)
	 */
	protected function get_db_tables_content( $tables, $offset = 0 ) {
		global $wpdb;
		$buffer = '';

		if ( ! $tables ) {
			return $buffer;
		}

		if ( ! is_array( $tables ) ) {
			$tables = (array) $tables;
		}

		foreach ( $tables as $table ) {
			if ( 0 === $offset ) {
				$show_create_table = $wpdb->get_row( 'SHOW CREATE TABLE ' . $table, ARRAY_A ); // WPCS: unprepared SQL ok.

				$buffer .= "#---------------------------------------------------------->> \n\n";
				$buffer .= sprintf( "# Dump of table %s #\n", $table );
				$buffer .= "#---------------------------------------------------------->> \n\n";
				$buffer .= sprintf( 'DROP TABLE IF EXISTS %s;', $table );
				$buffer .= "\n\n" . $show_create_table['Create Table'] . ";\n\n";
			}
			$offset     = 1000 * $offset;
			$table_data = $wpdb->get_results( 'SELECT * FROM ' . $table . ' LIMIT ' . $offset . ', 1000', ARRAY_A ); // WPCS: unprepared SQL ok.

			if ( ! $table_data ) {
				continue;
			}

			$buffer .= 'INSERT INTO ' . $table . ' VALUES';

			foreach ( $table_data as $row ) {
				if ( ! isset( $values ) ) {
					$values = "\n(";
				} else {
					$values = ",\n(";
				}
				foreach ( $row as $key => $value ) {
					$values .= '"' . esc_sql( $value ) . '",';
				}
				$buffer .= rtrim( $values, ', ' ) . ')';
			}

			unset( $values );
			$buffer .= ";\n\n";
		}

		return $buffer;
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
	public function get_sql_file_path() {
		return static::$tmp_path . $this->get_sql_file_name();
	}


	/**
	 * Create a filename for the .sql file.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @return (string) A filename.
	 */
	public function get_sql_file_name() {
		global $wpdb;

		if ( empty( $this->sql_file_name ) ) {
			$this->sql_file_name = date( 'Y-m-d-H-i' ) . '.' . $this->type . '.' . $wpdb->prefix . '.' . uniqid() . '.sql';
		}

		return $this->sql_file_name;
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
		if ( empty( $this->file_name ) ) {
			$this->file_name = $this->get_sql_file_name() . '.zip';
		}

		return $this->file_name;
	}
}
