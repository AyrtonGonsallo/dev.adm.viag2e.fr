<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** MALWARE SCANNER ============================================================================= */
/** ----------------------------------------------------------------------------------------------*/

add_action( 'admin_post_secupress_toggle_file_scan', 'secupress_toggle_file_scan_ajax_post_cb' );
/**
 * Set a transient to be read later to launch an async job.
 *
 * @since 1.0
 * @see secupress_process_file_monitoring_tasks()
 * @author Julio Potier
 */
function secupress_toggle_file_scan_ajax_post_cb() {
	if ( empty( $_GET['turn'] ) ) {
		secupress_admin_die();
	}

	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_toggle_file_scan' );

	if ( 'on' === $_GET['turn'] ) {
		secupress_file_monitoring_get_instance()->do_database_scan();
		secupress_file_monitoring_get_instance()->do_file_scan();
	} else {
		secupress_file_monitoring_get_instance()->stop_file_scan();
	}

	wp_redirect( esc_url_raw( wp_get_referer() ) );
	die();
}


add_action( 'wp_ajax_secupress_action_on_scanned_files',    'secupress_action_on_scanned_files_ajax_post_cb' );
add_action( 'admin_post_secupress_action_on_scanned_files', 'secupress_action_on_scanned_files_ajax_post_cb' );
/**
 * Will handle the correct action to do, triggered by 3 different submit.
 *
 * @since 1.0.3
 * @author Julio Potier
 */
function secupress_action_on_scanned_files_ajax_post_cb() {
	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_action_on_scanned_files' );

	if ( empty( $_POST['files'] ) || ( ! isset( $_POST['submit-delete-files'] ) && ! isset( $_POST['submit-recover-diff-files'] ) ) ) {
		secupress_admin_die();
	}

	if ( isset( $_POST['submit-delete-files'] ) ) {
		secupress_delete_scanned_files_ajax_post_cb( $_POST['files'] );
	} elseif ( isset( $_POST['submit-recover-diff-files'] ) ) {
		secupress_recover_diff_files_ajax_post_cb( $_POST['files'] );
	}
}


/**
 * Will handle the deletion for non core WordPress files.
 *
 * @since 1.0
 * @since 1.0.3 Not an admin-ajax/post call anymore, see `secupress_action_on_scanned_files()`.
 * @author Julio Potier
 *
 * @param (array) $files Contains $_POST['files'] values.
 */
function secupress_delete_scanned_files_ajax_post_cb( $files ) {
	global $wp_version;

	$files_not_from_wp = secupress_file_scanner_get_files_not_from_wp();

	if ( ! $files_not_from_wp ) {
		secupress_admin_die();
	}

	$full_filetree = secupress_file_scanner_get_full_filetree();

	$files      = array_map( 'wp_normalize_path', $files );
	$files      = array_intersect( $files, $files_not_from_wp );
	$filesystem = secupress_get_filesystem();

	foreach ( $files as $file ) {
		if ( $filesystem->delete( ABSPATH . $file ) ) {
			unset( $full_filetree[ $wp_version ][ $file ] );
		}
	}

	secupress_file_scanner_store_full_filetree( $full_filetree );

	secupress_admin_send_response_or_redirect( 1 );
}


add_action( 'wp_ajax_secupress_diff_file',    'secupress_diff_file_ajax_post_cb' );
add_action( 'admin_post_secupress_diff_file', 'secupress_diff_file_ajax_post_cb' );
/**
 * Will display the differences between 2 files from WP Core, using WP core classes.
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_diff_file_ajax_post_cb() {
	global $wp_version;

	if ( empty( $_GET['file'] ) ) {
		secupress_admin_die();
	}

	$file = wp_normalize_path( $_GET['file'] );

	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_diff_file-' . $file );

	$wp_core_files_hashes = get_site_option( SECUPRESS_WP_CORE_FILES_HASHES );

	if ( ! isset( $wp_core_files_hashes[ $wp_version ]['checksums'][ $file ] ) ) {
		secupress_admin_die();
	}

	$content  = '';
	$response = wp_remote_get( esc_url( "https://core.svn.wordpress.org/tags/$wp_version/$file" ) );

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$text    = secupress_text_diff( wp_remote_retrieve_body( $response ), file_get_contents( ABSPATH . $file ), array( 'title' => $file ) );
		$content = $text ? $text : $content;
	}

	if ( $content ) {
		secupress_action_page(
			__( 'File Differences', 'secupress' ),
			$content,
			array( 'head' => '<link rel="stylesheet" type="text/css" href="' . esc_url( admin_url( 'css/revisions.css' ) ) . '">' )
		);
	} else {
		$filesystem = secupress_get_filesystem();

		secupress_action_page(
			__( 'File Differences', 'secupress' ),
			'<h3>' . __( 'The differences can’t be displayed, the whole file will be displayed instead.', 'secupress' ) . '</h3><pre>' . esc_html( $filesystem->get_contents( ABSPATH . $file ) ) . '</pre>',
			array( 'head' => '<link rel="stylesheet" type="text/css" href="' . esc_url( admin_url( 'css/revisions.css' ) ) . '">' )
		);
	}
}


/**
 * Will download WP Core files that are different from the original.
 *
 * @since 1.0
 * @since 1.0.3 Not an admin-ajax/post call anymore, see `secupress_action_on_scanned_files()`.
 * @author Julio Potier
 *
 * @param (array) $files Contains $_POST['files'] values.
 */
function secupress_recover_diff_files_ajax_post_cb( $files ) {
	global $wp_version; // //// Async.

	$full_filetree        = secupress_file_scanner_get_full_filetree( true );
	$wp_core_files_hashes = get_site_option( SECUPRESS_WP_CORE_FILES_HASHES );

	if ( false === $full_filetree || false === $wp_core_files_hashes || ! isset( $wp_core_files_hashes[ $wp_version ]['checksums'], $full_filetree[ $wp_version ] ) ) {
		secupress_admin_die();
	}

	$wp_core_files_hashes = $wp_core_files_hashes[ $wp_version ]['checksums'];
	$abspath              = wp_normalize_path( ABSPATH );
	$filesystem           = secupress_get_filesystem();

	foreach ( $files as $file ) {
		$file = wp_normalize_path( $file );

		if ( ! $filesystem->exists( $abspath . $file ) || ! isset( $wp_core_files_hashes[ $file ] ) ) {
			continue;
		}

		$response = wp_remote_get( "https://core.svn.wordpress.org/tags/$wp_version/$file" );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$content = wp_remote_retrieve_body( $response );
			$filesystem->put_contents( $abspath . $file, $content );
			$full_filetree[ $wp_version ][ $file ] = md5( $content );
		}
	}

	secupress_file_scanner_store_full_filetree( $full_filetree );

	secupress_admin_send_response_or_redirect( 1 );
}


add_action( 'wp_ajax_secupress_recover_missing_files',    'secupress_recover_missing_files_ajax_post_cb' );
add_action( 'admin_post_secupress_recover_missing_files', 'secupress_recover_missing_files_ajax_post_cb' );
/**
 * Will download missing files from WP Core.
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_recover_missing_files_ajax_post_cb() {
	global $wp_version; // //// Async.

	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_recover_missing_files' );

	if ( empty( $_POST['files'] ) ) {
		secupress_admin_die();
	}

	$full_filetree        = secupress_file_scanner_get_full_filetree( true );
	$wp_core_files_hashes = get_site_option( SECUPRESS_WP_CORE_FILES_HASHES );

	if ( false === $full_filetree || false === $wp_core_files_hashes || ! isset( $wp_core_files_hashes[ $wp_version ]['checksums'], $full_filetree[ $wp_version ] ) ) {
		secupress_admin_die();
	}

	$wp_core_files_hashes   = array_flip( array_filter( array_flip( $wp_core_files_hashes[ $wp_version ]['checksums'] ), 'secupress_filter_no_content' ) );
	$missing_from_root_core = array_diff_key( $wp_core_files_hashes, $full_filetree[ $wp_version ] );
	$abspath                = wp_normalize_path( ABSPATH );
	$filesystem             = secupress_get_filesystem();

	foreach ( $_POST['files'] as $file ) {
		$file = wp_normalize_path( $file );

		if ( $filesystem->exists( $abspath . $file ) && ! isset( $missing_from_root_core[ $file ] ) ) {
			continue;
		}

		$response = wp_remote_get( "https://core.svn.wordpress.org/tags/$wp_version/$file" );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$content = wp_remote_retrieve_body( $response );
			$filesystem->put_contents( $abspath . $file, $content );
			$full_filetree[ $wp_version ][ $file ] = md5( $content );
		}
	}

	secupress_file_scanner_store_full_filetree( $full_filetree );

	secupress_admin_send_response_or_redirect( 1 );
}


add_action( 'wp_ajax_secupress_old_files',    'secupress_old_files_ajax_post_cb' );
add_action( 'admin_post_secupress_old_files', 'secupress_old_files_ajax_post_cb' );
/**
 * Will delete old WP core files still present in this installation.
 *
 * @since 1.0
 * @author Julio Potier
 */
function secupress_old_files_ajax_post_cb() {
	global $wp_version;

	secupress_check_user_capability();
	secupress_check_admin_referer( 'secupress_old_files' );

	if ( empty( $_POST['files'] ) ) {
		secupress_admin_die();
	}

	$wp_old_files = secupress_file_scanner_get_old_wp_files();

	if ( ! $wp_old_files ) {
		secupress_admin_die();
	}

	$full_filetree = secupress_file_scanner_get_full_filetree();
	$abspath       = wp_normalize_path( ABSPATH );
	$filesystem    = secupress_get_filesystem();

	foreach ( $_POST['files'] as $file ) {
		$file = wp_normalize_path( $file );

		if ( ! $filesystem->exists( $abspath . $file ) || ! isset( $wp_old_files[ $file ] ) ) {
			continue;
		}

		if ( $filesystem->delete( $abspath . $file ) ) {
			unset( $full_filetree[ $wp_version ][ $file ] );
		}
	}

	secupress_file_scanner_store_full_filetree( $full_filetree );

	secupress_admin_send_response_or_redirect( 1 );
}
