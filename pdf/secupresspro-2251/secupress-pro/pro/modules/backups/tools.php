<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Get temporary backups folder path.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (bool) $relative Set to true to get the path relative to the site's root.
 *
 * @return (string) The absolute (or relative) path to the temporary backups folder.
 */
function secupress_get_temporary_backups_path( $relative = false ) {
	static $abs_path;
	static $rel_path;

	if ( ! isset( $abs_path ) ) {
		$abs_path = secupress_get_parent_backups_path() . 'secupress-' . secupress_generate_hash( 'backups-tmp', 8, 8 ) . '-tmp/';
		$rel_path = str_replace( rtrim( wp_normalize_path( ABSPATH ), '/' ), '', $abs_path );
	}

	return $relative ? $rel_path : $abs_path;
}


/**
 * Get the path to the folder containing the local backups.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (bool) $relative Set to true to get the path relative to the site's root.
 *
 * @return (string) The absolute (or relative) path to the folder containing the local backups.
 */
function secupress_get_local_backups_path( $relative = false ) {
	static $abs_path;
	static $rel_path;

	if ( ! isset( $abs_path ) ) {
		$abs_path = secupress_get_hashed_folder_name( 'backup', secupress_get_parent_backups_path() );
		$rel_path = str_replace( rtrim( wp_normalize_path( ABSPATH ), '/' ), '', $abs_path );
	}

	return $relative ? $rel_path : $abs_path;
}


/**
 * Perform a backup of the files.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (bool) True on success, false on failure.
 */
function secupress_do_files_backup() {
	if ( ! class_exists( 'ZipArchive' ) || ! secupress_pre_backup() ) {
		return false;
	}

	require_once( SECUPRESS_PRO_MODULES_PATH . 'backups/plugins/class-secupress-backup.php' );
	require_once( SECUPRESS_PRO_MODULES_PATH . 'backups/plugins/class-secupress-backup-files.php' );

	$backup_storage = secupress_get_module_option( 'backups-storage_location', 'local', 'backups' );

	if ( 'local' === $backup_storage ) {
		require_once( SECUPRESS_PRO_MODULES_PATH . 'backups/plugins/class-secupress-backup-files-local.php' );

		$zip = new SecuPress_Backup_Files_Local;
	} else {
		// DropBox, etc ////.
		return false;
	}

	return $zip->do_backup();
}


/**
 * Perform a backup of the database.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (bool) True on success, false on failure.
 */
function secupress_do_db_backup() {
	if ( ! class_exists( 'ZipArchive' ) || ! secupress_pre_backup() ) {
		return false;
	}

	require_once( SECUPRESS_PRO_MODULES_PATH . 'backups/plugins/class-secupress-backup.php' );
	require_once( SECUPRESS_PRO_MODULES_PATH . 'backups/plugins/class-secupress-backup-db.php' );

	$backup_storage = secupress_get_module_option( 'backups-storage_location', 'local', 'backups' );

	if ( 'local' === $backup_storage ) {
		require_once( SECUPRESS_PRO_MODULES_PATH . 'backups/plugins/class-secupress-backup-db-local.php' );

		$zip = new SecuPress_Backup_Db_Local;
	} else {
		// DropBox, etc ////.
		return false;
	}

	return $zip->do_backup();
}


/**
 * Prepare the backups folder: create the folder if it doesn't exist and place a `.htaccess` file inside, denying access to.
 *
 * @since 1.0
 *
 * @return (bool) True if the folder is writable and the `.htaccess` file exists.
 */
function secupress_pre_backup() {
	global $is_apache, $is_nginx, $is_iis7;

	$backups_dir = secupress_get_parent_backups_path();
	$backup_dir  = secupress_get_local_backups_path();
	$tmp_dir     = secupress_get_temporary_backups_path();
	$filesystem  = secupress_get_filesystem();

	secupress_mkdir_p( $backup_dir );
	secupress_mkdir_p( $tmp_dir );

	if ( $is_apache ) {
		$file = '.htaccess';
	} elseif ( $is_iis7 ) {
		$file = 'web.config';
	} elseif ( $is_nginx ) {
		return wp_is_writable( $backup_dir ) && wp_is_writable( $tmp_dir );
	} else {
		return false;
	}

	$file = $backups_dir . $file;

	if ( $filesystem->exists( $file ) ) {
		return wp_is_writable( $backup_dir ) && wp_is_writable( $tmp_dir );
	}

	$filesystem->put_contents( $file, secupress_backup_get_protection_content() );

	return wp_is_writable( $backup_dir ) && wp_is_writable( $tmp_dir ) && $filesystem->exists( $file );
}


/**
 * Get rules to be added to a `.htaccess`/`nginx.conf`/`web.config` file to protect the backups folder.
 *
 * @since 1.0
 *
 * @return (string) The rules to insert.
 */
function secupress_backup_get_protection_content() {
	global $is_apache, $is_nginx, $is_iis7;

	$file_content = '';

	if ( $is_apache ) {
		// Apache.
		$file_content = "Order allow,deny\nDeny from all";
	} elseif ( $is_iis7 ) {
		/*
		 * IIS7.
		 * https://www.iis.net/configreference/system.webserver/security/authorization
		 * https://technet.microsoft.com/en-us/library/cc772441%28v=ws.10%29.aspx
		 */
		$file_content = '<?xml version="1.0" encoding="utf-8" ?>
<configuration>
  <system.webServer>
    <security>
      <authorization>
        <remove users="*" roles="" verbs="" />
        <add accessType="Deny" users="*" roles="" verbs="" />
      </authorization>
    </security>
  </system.webServer>
</configuration>';
	} elseif ( $is_nginx ) {
		// Nginx.
		$backup_dir   = secupress_get_local_backups_path( true );
		$path         = secupress_get_rewrite_bases();
		$path         = $path['home_from'] . rtrim( dirname( $backup_dir ), '/' );
		$file_content = "
server {
	location ~* $path {
		deny all;
	}
}";
	}

	return $file_content;
}

/**
 * Put contents with 'ab' (the end of) param instead of 'wb' (the begining of)
 *
 * @since 1.4.6
 * @param (string) $file The backup file.
 * @param (string) $contents The backup content.
 * @author Julio Potier
 **/
function secupress_backup_put_contents( $file, $contents ) {
	$fp = @fopen( $file, 'ab' );

	if ( ! $fp ) {
		return false;
	}
	mbstring_binary_safe_encoding();
	$data_length = strlen( $contents );
	$bytes_written = fwrite( $fp, $contents );
	reset_mbstring_encoding();
	fclose( $fp );
}
