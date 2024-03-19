<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


add_action( 'secupress.plugins.loaded', 'secupress_file_monitoring_get_instance' );
/**
 * Get the file monitoring instance.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (object) The `SecuPress_File_Monitoring` instance.
 */
function secupress_file_monitoring_get_instance() {
	if ( ! class_exists( 'SecuPress_File_Monitoring' ) ) {
		require_once( SECUPRESS_PRO_MODULES_PATH . 'file-system/plugins/inc/php/file-monitoring/class-secupress-file-monitoring.php' );
	}

	return SecuPress_File_Monitoring::get_instance();
}


/**
 * Check if the file contains any malware from our signatures list.
 * Also checks against the smart white-list.
 *
 * @since 2.0 Check filesize on PHP only + change default size value
 * @since 1.3   Skip non-php files larger than 30 MB.
 * @since 1.2.4 Usage of the smart white-list only, not the self white-list.
 * @since 1.0.3 Usage of the smart white-list.
 * @since 1.0
 * @author Julio Potier
 * @author Grégory Viguier
 *
 * @param (string) $filepath A file path.
 *
 * @return (string) HTML markup with "Possible malware found" if the file is detected as a malware. An empty string otherwize.
 */
function secupress_check_malware( $filepath ) {
	static $malware_tests;
	static $uploads_dir;
	static $size_limit;
	static $init_done = false;

	$filesystem = secupress_get_filesystem();
	$contents   = '';

	if ( ! isset( $malware_tests ) ) {
		$malware_tests = $filesystem->get_contents( SECUPRESS_PRO_INC_PATH . 'data/malware-keywords.data' );
		$malware_tests = str_rot13( $malware_tests );
		$malware_tests = json_decode( $malware_tests, true );
	}

	if ( ! isset( $uploads_dir ) ) {
		$uploads_dir = wp_upload_dir( null, false );
		$uploads_dir = basename( $uploads_dir['basedir'] );
		$uploads_dir = wp_normalize_path( "/{$uploads_dir}/" );
	}

	if ( ! isset( $size_limit ) ) {
		$size_limit = function_exists( 'ini_get' ) ? (int) ini_get( 'upload_max_filesize' ) * MB_IN_BYTES : 0;
		$size_limit = max( $size_limit, 2 * MB_IN_BYTES );

		/**
		 * Filter the file size limit: non-php files larger than this value won't be tested for malware.
		 *
		 * @since 2.0 Default size is (int) ini_get( 'upload_max_filesize' ) or 2 MB
		 * @since 1.3
		 * @author Grégory Viguier
		 *
		 * @param (int) $size_limit File size limit in Bytes. (x * MB_IN_BYTES)
		 */
		$size_limit = apply_filters( 'secupress.file-monitoring.size_limit', $size_limit );
	}

	$result   = [];
	$file_ext = pathinfo( $filepath, PATHINFO_EXTENSION );
	$php_exts = [ 'php' => 1, 'php1' => 1, 'php2' => 1, 'php3' => 1, 'php4' => 1, 'php5' => 1, 'php6' => 1, 'php7' => 1, 'php8' => 1, 'phtml' => 1 ];

	$file_size = @filesize( ABSPATH . $filepath );

	if ( isset( $php_exts[ $file_ext ] ) && ( ! $file_size || $file_size > $size_limit ) ) {
		$result[] = sprintf( __( 'Filesize is over the limit of %sMB.', 'secupress' ), round( $size_limit / MB_IN_BYTES ) );
	} else {
		$contents = $filesystem->get_contents( ABSPATH . $filepath );
	}

	if ( $file_size < 1000 ) {
		// The file is empty.
		return '';
	}

	if ( 'wp-config.php' === trim( basename( $filepath ), '/' ) && strpos( $contents, 'include' ) !== false ) {
		$result[] = sprintf( __( 'include</code> Your site should also be infected. Check it or ask an expert, file: <code>%s', 'secupress' ), ABSPATH . $filepath );
	}

	if ( ! $init_done ) {
		$init_done = true;
		secupress_time_limit( 0 );
	}


	foreach ( $malware_tests as $test ) {
		if ( 'php' !== trim( $test[0] ) && trim( $test[0] ) !== $file_ext ) {
			continue;
		}

		if ( 'php' === trim( $test[0] ) && ! isset( $php_exts[ $file_ext ] ) ) {
			continue;
		}

		if ( isset( $test[1]['str'], $test[1]['count'] ) ) {
			if ( substr_count( $contents, $test[1]['str'] ) >= $test[1]['count'] ) {
				$result[] = esc_html( $test[1]['str'] );
			}
			continue;
		}

		$actual_count = 0;

		foreach ( $test[1] as $text ) {
			if ( $text && strpos( $contents, $text ) !== false ) {
				++$actual_count;
			}
		}

		if ( $actual_count && count( $test[1] ) === $actual_count ) {
			$result = array_merge( $result, array_map( 'esc_html', $test[1] ) );
		}
	}

	if ( in_array( $file_ext, $php_exts ) && $file_size > 128 ) {
		$filepath = wp_normalize_path( $filepath );

		if ( apply_filters( 'secupress.malware_scan.uploads_tag', false ) && ( strpos( $filepath, '/blogs.dir' ) !== false || strpos( $filepath . '/', $uploads_dir ) !== false ) ) {
			/** Translators: %s is a file extension. */
			$result[] = sprintf( __( '%s in uploads', 'secupress' ), '<code>.php</code>' );
		}
	}

	if ( $result ) {
		return sprintf( ' <span class="secupress-inline-alert"><span class="screen-reader-text">%1$s</span></span><div class="secupress-toggle-me %2$s hide-if-js"><strong>%3$s</strong>%4$s<hr></div>',
						__( 'Possible malware found', 'secupress' ),
						sanitize_html_class( $filepath ),
						_n( 'Found Signature: ', 'Found Signatures: ', count( $result ), 'secupress' ),
						str_replace( '<code></code>', '', '<code>' . implode( '</code>, <code>', $result ) . '</code>' )
				);
	}

	return '';
}


/**
 * Get the result of the file scanner.
 *
 * @since 1.2.4 Remove "old WP files" from the "files that are not part of the WordPress installation".
 * @since 1.0
 * @author Julio Potier
 * @author Grégory Viguier
 *
 * @return (array|bool) An array of files, categorized by the reason they are listed. False if never scanned.
 */
function secupress_file_scanner_get_result() {
	global $wp_version;

	$full_filetree       = secupress_file_scanner_get_full_filetree( true );
	$wp_core_file_hashes = secupress_file_scanner_get_wp_core_file_hashes();
	if ( false === $full_filetree || false === $wp_core_file_hashes ) {
		return false;
	}

	if ( ! $wp_core_file_hashes || empty( $full_filetree[ $wp_version ] ) ) {
		return array();
	}

	$result = array(
		/**
		 * Files that are not part of the WordPress installation.
		 */
		'not-wp-files'      => secupress_file_scanner_get_files_not_from_wp(),
		/**
		 * Missing files from WP Core.
		 */
		'missing-wp-files'  => secupress_file_scanner_get_files_missing_from_wp(),
		/**
		 * Old WP files.
		 */
		'old-wp-files'      => secupress_file_scanner_get_old_wp_files(),
		/**
		 * Modified WP Core files.
		 */
		'modified-wp-files' => secupress_file_scanner_get_modified_wp_files(),
		/**
		 * DB Scanner.
		 */
		'database-wp'       => secupress_get_database_scanner(),
	);

	if ( $result['not-wp-files'] && $result['old-wp-files'] ) {
		// Remove "old WP files" from the "files that are not part of the WordPress installation".
		$result['not-wp-files'] = array_diff_key( $result['not-wp-files'], $result['old-wp-files'] );
	}

	return $result;
}


/**
 * Get the files that are not part of the WordPress installation.
 * Also checks against the self white-list.
 * Warning: it can also return "files that are not in WordPress core anymore". See `secupress_file_scanner_get_old_wp_files()`.
 *
 * @since 1.0
 * @author Julio Potier
 * @author Grégory Viguier
 *
 * @return (array|bool) An array of files. False if never scanned.
 */
function secupress_file_scanner_get_files_not_from_wp() {
	global $wp_version;
	static $wp_content_dir;

	$full_filetree       = secupress_file_scanner_get_full_filetree( true );
	$wp_core_file_hashes = secupress_file_scanner_get_wp_core_file_hashes();

	if ( false === $full_filetree || false === $wp_core_file_hashes ) {
		return false;
	}

	if ( ! $wp_core_file_hashes || empty( $full_filetree[ $wp_version ] ) ) {
		return array();
	}

	if ( ! isset( $wp_content_dir ) ) {
		$wp_content_dir = str_replace( realpath( ABSPATH ) . '/', '' , realpath( WP_CONTENT_DIR ) );
	}

	/**
	 * Filter the list of WordPress core file paths.
	 *
	 * @since 1.0
	 *
	 * @param (array) $wp_core_file_hashes The list of WordPress core file paths.
	 */
	$wp_core_file_hashes = apply_filters( 'secupress.plugin.file_scanner.wp_core_file_hashes', $wp_core_file_hashes );

	// Add these since it's not in the zip but depends from WordPress.
	$wp_core_file_hashes['.htaccess']                      = '.htaccess';
	$wp_core_file_hashes['web.config']                     = 'web.config';
	$wp_core_file_hashes[ $wp_content_dir . '/debug.log' ] = $wp_content_dir . '/debug.log';


	$full_filetree       = $full_filetree[ $wp_version ];
	$diff_from_root_core = array_diff_key( $full_filetree, $wp_core_file_hashes );

	$output = array();

	if ( $diff_from_root_core ) {
		$filesystem = secupress_get_filesystem();

		foreach ( $diff_from_root_core as $diff_file => $hash ) {
			if ( $filesystem->exists( ABSPATH . $diff_file ) ) {
				$output[ $diff_file ] = $diff_file;
			}
		}
	}

	return $output;
}


/**
 * Get the files that are missing from WordPress core.
 *
 * @since 1.0
 * @author Julio Potier
 * @author Grégory Viguier
 *
 * @return (array|bool) An array of files. False if never scanned.
 */
function secupress_file_scanner_get_files_missing_from_wp() {
	global $wp_version, $_old_files;

	$full_filetree       = secupress_file_scanner_get_full_filetree( true );
	$wp_core_file_hashes = secupress_file_scanner_get_wp_core_file_hashes();

	if ( false === $full_filetree || false === $wp_core_file_hashes || count( $full_filetree ) < count( $wp_core_file_hashes ) ) {
		return false;
	}

	if ( ! $wp_core_file_hashes || empty( $full_filetree[ $wp_version ] ) ) {
		return array();
	}

	$wp_core_file_hashes    = array_flip( array_filter( array_flip( $wp_core_file_hashes ), 'secupress_filter_no_content' ) );
	$missing_from_root_core = array_diff_key( $wp_core_file_hashes, $full_filetree[ $wp_version ] );
	unset( $missing_from_root_core['wp-config-sample.php'], $missing_from_root_core['readme.html'], $missing_from_root_core['license.txt'] );

	$output = array();

	if ( $missing_from_root_core ) {
		$output = array_keys( $missing_from_root_core );
		$output = array_combine( $output, $output );
	}

	return $output;
}


/**
 * Get the files that are not in WordPress core anymore.
 *
 * @since 1.0
 * @author Julio Potier
 * @author Grégory Viguier
 *
 * @return (array) An array of files.
 */
function secupress_file_scanner_get_old_wp_files() {
	global $_old_files;

	require_once( ABSPATH . 'wp-admin/includes/update-core.php' );

	$output = array();

	if ( $_old_files ) {
		$filesystem = secupress_get_filesystem();

		foreach ( $_old_files as $file ) {
			if ( $filesystem->exists( ABSPATH . $file ) ) {
				$output[ $file ] = $file;
			}
		}
	}

	return $output;
}

/**
 * Shorthand to run the database scanner
 *
 * @since 2.0
 * @author Julio Potier
 *
 **/
function secupress_do_database_scanner() {
	secupress_file_monitoring_get_instance()->do_database_scan();
}


/**
 * Get the stored SQL queries containing possible malware as JS/HTML
 *
 * @since 2.0
 * @author Julio Potier
 *
 * @see SecuPress_File_Monitoring::do_database_scan()
 *
 * @global $wpdb
 * @return (array)
 **/
function secupress_get_database_scanner() {
	return get_site_option( SECUPRESS_DATABASE_MALWARES, [] );
}

/**
 * Return some keywords tagged as malware in post content
 *
 * @since 2.1 //:ptth
 * @since 2.0
 * @author Julio Potier
 *
 * @param (string) $key     Desired key if needed
 * @param (string) $context 'raw' will be not filtered, other values will be esc_html()
 * @return (array)
 **/
function secupress_get_database_malware_keywords( $key = '', $context = 'raw' ) {
	/**
	* Filter the searched words, + means you should find all these ones and - means not these ones,
	* dont use only - but you can only use +
	* @param (array)
	* @return (array)
	*/
	$keywords = apply_filters( 'secupress.database_scanner', [
				'eval(decodeURIComponent'   => [ '+' => [ 'eval(decodeURIComponent' ] ],
				'eval( decodeURIComponent'  => [ '+' => [ 'eval( decodeURIComponent' ] ],
				'eval (decodeURIComponent'  => [ '+' => [ 'eval (decodeURIComponent' ] ],
				'eval ( decodeURIComponent' => [ '+' => [ 'eval ( decodeURIComponent' ] ],
				'XMLHttpRequest'            => [ '+' => [ 'new XMLHttpRequest' ] ],
				'atob'                      => [ '+' => [ '<script', 'atob(' ] ],
				'iframe'                    => [ '+' => [ "document.write('<iframe " ] ],
				'ptth'                      => [ '+' => [ "//:ptth" ] ],
				'Threads'                   => [ '+' => [ 'eval', 'Client.Anonymous', 'throttle', 'setNumThreads', 'setAutoThreadsEnabled', 'FORCE_MULTI_TAB' ] ],
				] );
	if ( isset( $keywords[ $key ] ) ) {
		if ( 'raw' !== $context ) {
			$keywords[ $key ]['+'] = array_map( 'esc_html', $keywords[ $key ]['+'] );
			if ( isset( $keywords[ $key ]['-'] ) ) {
				$keywords[ $key ]['-'] = array_map( 'esc_html', $keywords[ $key ]['-'] );
			}
		}
		return $keywords[ $key ];
	}
	return $keywords;
}
/**
 * Get the files from WordPress core that have been modified.
 *
 * @since 1.0
 * @author Julio Potier
 * @author Grégory Viguier
 *
 * @return (array|bool) An array of files. False if never scanned.
 */
function secupress_file_scanner_get_modified_wp_files() {
	global $wp_version, $_old_files;

	$full_filetree       = secupress_file_scanner_get_full_filetree( true );
	$wp_core_file_hashes = secupress_file_scanner_get_wp_core_file_hashes();

	if ( false === $full_filetree || false === $wp_core_file_hashes ) {
		return false;
	}

	if ( ! $wp_core_file_hashes || empty( $full_filetree[ $wp_version ] ) ) {
		return array();
	}

	/** This filter is documented in inc/modules/file-system/tools.php */
	$wp_core_file_hashes = apply_filters( 'secupress.plugin.file_scanner.wp_core_file_hashes', $wp_core_file_hashes );
	$full_filetree       = $full_filetree[ $wp_version ];
	$filesystem          = secupress_get_filesystem();

	$output = array();

	foreach ( $wp_core_file_hashes as $file => $hash ) {
		if ( isset( $full_filetree[ $file ] ) && ! hash_equals( $hash, $full_filetree[ $file ] ) && $filesystem->exists( ABSPATH . $file ) ) {
			$output[ $file ] = $file;
		}
	}

	return $output;
}


/**
 * Get the site's files tree from the database.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (bool) $raw Set to true to get the "raw" decompressed data.
 *
 * @return (array|bool) An array of files. False if never scanned.
 */
function secupress_file_scanner_get_full_filetree( $raw = false ) {
	global $wp_version;

	$full_filetree = secupress_decompress_data( get_site_option( SECUPRESS_FULL_FILETREE ) );

	if ( $raw ) {
		return $full_filetree;
	}

	$full_filetree = is_array( $full_filetree ) ? $full_filetree : array();

	if ( ! isset( $full_filetree[ $wp_version ] ) || ! is_array( $full_filetree[ $wp_version ] ) ) {
		$full_filetree[ $wp_version ] = array();
	}

	return $full_filetree;
}


/**
 * Store the site's files tree into the database.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @param (array) $full_filetree An array of files.
 */
function secupress_file_scanner_store_full_filetree( $full_filetree ) {
	update_site_option( SECUPRESS_FULL_FILETREE, secupress_compress_data( $full_filetree ), false );
}


/**
 * Delete the site's files tree from the database.
 *
 * @since 1.0
 * @author Grégory Viguier
 */
function secupress_file_scanner_delete_full_filetree() {
	delete_site_option( SECUPRESS_FULL_FILETREE );
}


/**
 * Get the WordPress core hashes.
 *
 * @since 1.0
 * @author Grégory Viguier
 *
 * @return (array|bool) An array of hashes. False if never scanned.
 */
function secupress_file_scanner_get_wp_core_file_hashes() {
	global $wp_version;

	$hashes = get_site_option( SECUPRESS_WP_CORE_FILES_HASHES );

	if ( false === $hashes ) {
		return false;
	}

	if ( empty( $hashes[ $wp_version ]['checksums'] ) || ! is_array( $hashes[ $wp_version ]['checksums'] ) ) {
		return array();
	}

	return $hashes[ $wp_version ]['checksums'];
}


/**
 * Used in `array_filter()`: return true if the given path is not in the `wp-content` folder.
 *
 * @since 1.0
 *
 * @param (string) $item A file path.
 *
 * @return (bool)
 */
function secupress_filter_no_content( $item ) {
	static $wp_content;

	if ( ! isset( $wp_content ) ) {
		$wp_content = basename( WP_CONTENT_DIR );
		$wp_content = "/{$wp_content}/";
	}

	$item = str_replace( '\\', '/', $item );

	return strpos( "/{$item}/", $wp_content ) === false;
}
