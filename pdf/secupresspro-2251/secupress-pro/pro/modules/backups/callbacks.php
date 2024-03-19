<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ON FORM SUBMIT ============================================================================== */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Callback to filter, sanitize.
 *
 * @since 1.0
 *
 * @param (array) $settings The module settings.
 *
 * @return (array) The sanitized and validated settings.
 */
function secupress_pro_backups_settings_callback( $settings ) {
	$modulenow = 'backups-storage';
	$locations = secupress_backups_storage_labels();
	$settings  = $settings && is_array( $settings ) ? $settings : array();

	if ( isset( $settings['sanitized'] ) ) {
		return $settings;
	}
	$settings['sanitized'] = 1;

	if ( ! isset( $settings[ $modulenow . '_location' ] ) || ! isset( $locations[ $settings[ $modulenow . '_location' ] ] ) ) {
		unset( $settings[ $modulenow . '_location' ] );
	}

	return $settings;
}


add_action( 'wp_ajax_secupress_backup_files',    'secupress_backup_files_ajax_post_cb' );
add_action( 'admin_post_secupress_backup_files', 'secupress_backup_files_ajax_post_cb' );
/**
 * Will do a files backup.
 *
 * @since 1.0
 */
function secupress_backup_files_ajax_post_cb() {
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_backup_files' );

	// Sanitize and save the ignored directories.
	$abspath   = wp_normalize_path( ABSPATH );
	$ignored   = ! empty( $_POST['ignored_directories'] ) ? $_POST['ignored_directories'] : '';
	$ignored   = explode( "\n", $ignored );
	$ignored   = array_filter( $ignored );
	$ignored   = array_merge( array(
		WP_CONTENT_DIR . '/cache',
		WP_CONTENT_DIR . '/backups',
	), $ignored );
	$ignored   = array_flip( $ignored );
	$dirs      = array();

	foreach ( $ignored as $directory => $i ) {
		$directory = wp_normalize_path( trim( $directory ) );
		$directory = str_replace( $abspath, '', $directory );
		$directory = sanitize_text_field( $directory );
		$directory = untrailingslashit( $directory );

		if ( empty( $dirs[ $directory ] ) && ! preg_match( '@^/?\.?\.?$@', $directory ) ) {
			$dirs[ $directory ] = 1;
		}
	}

	$ignored = implode( "\n", array_keys( $dirs ) );

	update_site_option( 'secupress_file-backups_settings', array( 'ignored_directories' => $ignored ) );

	// Backup.
	if ( ! class_exists( 'ZipArchive' ) ) {
		secupress_admin_die( __( 'Class <code>ZipArchive</code> is missing, the zip archive cannot be created.', 'secupress' ) );
	}

	$backup_file = secupress_do_files_backup();

	if ( ! $backup_file ) {
		secupress_admin_die( __( 'No backup file created.', 'secupress' ) );
	}

	$backup_files = secupress_get_backup_file_list();

	secupress_admin_send_response_or_redirect( array(
		'ignoredFiles' => $ignored,
		'elemRow'      => secupress_print_backup_file_formated( $backup_file, false ),
		'countText'    => sprintf( _n( '%s available Backup', '%s available Backups', count( $backup_files ), 'secupress' ), number_format_i18n( count( $backup_files ) ) ),
	) );
}


add_action( 'wp_ajax_secupress_backup_db',    'secupress_backup_db_ajax_post_cb' );
add_action( 'admin_post_secupress_backup_db', 'secupress_backup_db_ajax_post_cb' );
/**
 * Will do a DB backup.
 *
 * @since 1.0
 */
function secupress_backup_db_ajax_post_cb() {
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_backup_db' );

	// Sanitize and save the tables to backup.
	$other_tables  = secupress_get_non_wp_tables();
	$chosen_tables = array();

	if ( $other_tables ) {
		$chosen_tables = ! empty( $_POST['other_tables'] ) && is_array( $_POST['other_tables'] ) ? $_POST['other_tables'] : array();
		$chosen_tables = array_intersect( $other_tables, $chosen_tables );
		$chosen_tables = array_values( $chosen_tables );
	}

	update_site_option( 'secupress_database-backups_settings', array( 'other_tables' => $chosen_tables ) );

	// Backup.
	if ( ! class_exists( 'ZipArchive' ) ) {
		secupress_admin_die( __( 'Class <code>ZipArchive</code> is missing, the zip archive cannot be created.', 'secupress' ) );
	}

	$backup_file = secupress_do_db_backup();

	if ( ! $backup_file ) {
		secupress_admin_die( __( 'No backup file created.', 'secupress' ) );
	}

	$backup_files = secupress_get_backup_file_list();

	secupress_admin_send_response_or_redirect( array(
		'elemRow'   => secupress_print_backup_file_formated( $backup_file, false ),
		'countText' => sprintf( _n( '%s available Backup', '%s available Backups', count( $backup_files ), 'secupress' ), number_format_i18n( count( $backup_files ) ) ),
	) );
}


add_action( 'admin_post_secupress_download_backup', 'secupress_download_backup_ajax_post_cb' );
/**
 * Will download a requested backup.
 * No need any AJAX support here.
 *
 * @since 1.0
 */
function secupress_download_backup_ajax_post_cb() {
	if ( ! isset( $_GET['file'] ) ) {
		secupress_admin_die();
	}

	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_download_backup-' . $_GET['file'] );

	$file = glob( secupress_get_local_backups_path() . '*' . $_GET['file'] . '*.{zip,sql}', GLOB_BRACE );

	if ( ! $file ) {
		secupress_admin_die();
	}

	if ( ini_get( 'zlib.output_compression' ) ) {
		ini_set( 'zlib.output_compression', 'Off' );
	}

	ob_start();

	$file = reset( $file );
	$size = filesize( $file );

	header( $_SERVER['SERVER_PROTOCOL'] . ' 200 OK' );
	header( 'Expires: 0' );
	header( 'Pragma: public' );
	header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
	header( 'Cache-Control: private', false );
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . basename( str_replace( array( '@', '#' ), '-', $file ) ) . '"' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $file ) ) . ' GMT' );
	header( 'Content-Length: ' . $size );
	header( 'Connection: close' );
	ob_end_clean();

	if ( $size < 64 * 1024 ) {
		readfile( $file );
	} else {
		secupress_time_limit( 0 );
		$fp = fopen( $file, 'rb' );

		while ( ! feof( $fp ) ) {
			echo fread( $fp, 64 * 1024 );
			flush();
		}
		fclose( $fp );
	}
	die();
}


add_action( 'wp_ajax_secupress_delete_backup',    'secupress_delete_backup_ajax_post_cb' );
add_action( 'admin_post_secupress_delete_backup', 'secupress_delete_backup_ajax_post_cb' );
/**
 * Will delete a specified backup file.
 *
 * @since 1.0
 */
function secupress_delete_backup_ajax_post_cb() {
	if ( ! isset( $_GET['file'] ) ) {
		secupress_admin_die();
	}

	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_delete_backup-' . $_GET['file'] );

	$files = glob( secupress_get_local_backups_path() . '*' . $_GET['file'] . '*.{zip,sql}', GLOB_BRACE );

	if ( ! $files ) {
		secupress_admin_die();
	}

	@array_map( 'unlink', $files );

	$backup_files = secupress_get_backup_file_list();

	secupress_admin_send_response_or_redirect( array(
		'countText' => sprintf( _n( '%s available Backup', '%s available Backups', count( $backup_files ), 'secupress' ), number_format_i18n( count( $backup_files ) ) ),
	) );
}


add_action( 'wp_ajax_secupress_delete_backups',    'secupress_delete_backups_ajax_post_cb' );
add_action( 'admin_post_secupress_delete_backups', 'secupress_delete_backups_ajax_post_cb' );
/**
 * Will delete all the backups.
 *
 * @since 1.0
 */
function secupress_delete_backups_ajax_post_cb() {
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_delete_backups' );

	$files = glob( secupress_get_local_backups_path() . '*.{zip,sql}', GLOB_BRACE );

	if ( ! $files ) {
		secupress_admin_die();
	}

	@array_map( 'unlink', $files );

	secupress_admin_send_response_or_redirect( 1 );
}


/** --------------------------------------------------------------------------------------------- */
/** TOOLS ======================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

/**
 * List the existing backup files (`.zip`, `.sql`).
 *
 * @since 1.0
 *
 * @return (array|bool) An array of file paths. False on error.
 */
function secupress_get_backup_file_list() {
	return glob( secupress_get_local_backups_path() . '*.{zip,sql}', GLOB_BRACE );
}


/**
 * Print an HTML markup for a backup: creation date, download link, deletion link, etc.
 *
 * @since 1.0
 *
 * @param (string) $file The file path.
 * @param (bool)   $echo Return or echo the markup.
 *
 * @return (string)
 */
function secupress_print_backup_file_formated( $file, $echo = true ) {

	list( $_date, $_type, $_prefix, $file_uniqid ) = explode( '.', esc_html( basename( $file ) ) );

	$_date   = strtotime( substr_replace( $_date, ':', 13, 1 ) ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	$_date   = date_i18n( __( 'M jS Y', 'secupress' ) . ' ' . __( 'G:i', 'secupress' ), $_date, true );
	$_prefix = str_replace( array( '@', '#' ), array( '.', '/' ), $_prefix );

	switch ( $_type ) :
		case 'database' :
			$_type = __( 'Database', 'secupress' );
		break;

		case 'files' :
			$_type = __( 'Files', 'secupress' );
		break;

		default :
			$_type = ucwords( $_type );
	endswitch;

	$download_url = esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_download_backup&file=' . $file_uniqid ), 'secupress_download_backup-' . $file_uniqid ) );
	$delete_url   = esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_delete_backup&file=' . $file_uniqid ), 'secupress_delete_backup-' . $file_uniqid ) );

	$file_format  = sprintf( '<p class="secupress-large-row">%s <strong>%s</strong> <em>(%s)</em>', $_type, $_prefix, $_date );
	$file_format .= sprintf( '<span><a href="%s">%s</a> | <a href="%s" class="a-delete-backup" data-file-uniqid="%s">%s</a></span></p>', $download_url, _x( 'Download', 'verb', 'secupress' ), $delete_url, $file_uniqid, __( 'Delete', 'secupress' ) );

	if ( ! $echo ) {
		return $file_format;
	}

	echo $file_format;
}
