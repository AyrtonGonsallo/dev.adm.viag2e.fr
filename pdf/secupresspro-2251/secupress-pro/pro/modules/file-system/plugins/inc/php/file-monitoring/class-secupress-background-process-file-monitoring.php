<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Background File Monitoring class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Background_Process_File_Monitoring extends WP_Background_Process {

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
	protected $action = 'file_monitoring';


	/**
	 * Task.
	 *
	 * @since 1.0
	 *
	 * @param (string) $item Queue item to iterate over.
	 *
	 * @return (string|bool) Next in queue or false.
	 */
	protected function task( $item ) {
		if ( ! $item || ! is_string( $item ) || ! method_exists( $this, $item ) ) {
			return false;
		}
		return $this->$item();
	}


	/**
	 * Remove everything from the queue.
	 *
	 * @since 1.0
	 */
	public function delete_queue() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $this->identifier . '_batch_%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // WPCS: unprepared SQL ok.

		// Empty the list of folders to scan.
		secupress_delete_site_transient( SECUPRESS_FULL_FILETREE );
		// If we stop before the end, the files tree will be incomplete, we'll have lots of "missing files", so we delete it to prevent this problem.
		secupress_file_scanner_delete_full_filetree();
	}


	/**
	 * Is process running.
	 * Check whether the current process is already running in a background process.
	 *
	 * @since 1.0
	 *
	 * @return (bool)
	 */
	public function is_monitoring_running() {
		return $this->is_process_running();
	}


	/**
	 * Will store the wp core files hashes (md5), first from the w.org api, then from the .zip from w.org too.
	 *
	 * @since 1.0
	 */
	public function get_wp_hashes() {
		global $wp_version, $wp_local_package;

		if ( false !== ( $result = get_site_option( SECUPRESS_WP_CORE_FILES_HASHES ) ) && ! empty( $result[ $wp_version ]['checksums'] ) && is_array( $result[ $wp_version ]['checksums'] ) ) {
			return $result[ $wp_version ];
		}

		update_site_option( SECUPRESS_WP_CORE_FILES_HASHES, array( $wp_version => array() ), false );

		$result = array( $wp_version => array() );
		$locale = isset( $wp_local_package ) ? $wp_local_package : 'en_US';
		$urls   = array(
			$locale => 'http://api.wordpress.org/core/checksums/1.0/?locale=' . $locale . '&version=' . $wp_version,
		);

		if ( 'en_US' !== $locale ) {
			$urls['en_US'] = 'http://api.wordpress.org/core/checksums/1.0/?locale=en_US&version=' . $wp_version;
		}

		foreach ( $urls as $locale => $url ) {

			$response = wp_remote_get( $url );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$result[ $wp_version ] = json_decode( wp_remote_retrieve_body( $response ), true );
			}

			if ( ! empty( $result[ $wp_version ]['checksums'] ) && is_array( $result[ $wp_version ]['checksums'] ) ) {
				$result[ $wp_version ]['locale'] = $locale;
				$result[ $wp_version ]['url'] = $url;
				break;
			}
		}

		$plugins = 'wp-content/plugins/'; // Until we test plugins, exclude them from the core ////.
		$themes  = 'wp-content/themes/'; // Until we test themes, exclude them from the core ////.

		if ( empty( $result[ $wp_version ]['checksums'] ) || ! is_array( $result[ $wp_version ]['checksums'] ) ) {
			$file     = "http://wordpress.org/wordpress-$wp_version-no-content.zip";
			$file_md5 = "http://wordpress.org/wordpress-$wp_version.zip.md5"; ////
			$response = wp_remote_get( $file_md5 ); ////

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );

				$zip_md5  = wp_remote_retrieve_body( $response ); ////
				$tmpfname = download_url( $file );

				if ( ! is_wp_error( $tmpfname ) && is_readable( $tmpfname ) ) {
					$file = $tmpfname;
					$zip  = zip_open( $file );

					$result[ $wp_version ]['checksums'] = array();

					if ( is_resource( $zip ) ) {
						while ( $zip_entry = zip_read( $zip ) ) {
							zip_entry_open( $zip, $zip_entry, 'r' );
							$zfile = zip_entry_read( $zip_entry, zip_entry_filesize( $zip_entry ) );
							list( $wp, $filename ) = explode( '/', zip_entry_name( $zip_entry ), 2 );

							if ( $filename && strpos( $filename, $akismet ) !== 0 ) {
								$md5tmp = md5( $zfile );
								$result[ $wp_version ]['checksums'][ $filename ] = $md5tmp;
							}
							zip_entry_close( $zip_entry );
						}
						zip_close( $zip );
					}
					$filesystem = secupress_get_filesystem();
					$filesystem->delete( $tmpfname );
				}
			} else {
				$result[ $wp_version ]['checksums'] = $response;
			}
		} else {
			foreach ( $result[ $wp_version ]['checksums'] as $filename => $hash ) {
				if ( strpos( $filename, $plugins ) === 0 || strpos( $filename, $themes ) === 0 ) {
					unset( $result[ $wp_version ]['checksums'][ $filename ] );
				}
			}
		}

		update_site_option( SECUPRESS_WP_CORE_FILES_HASHES, $result );
	}


	/**
	 * Will store the possible dists file from wp core files hashes (md5), first from the w.org API, then from the .zip from w.org too.
	 *
	 * @since 1.0
	 *
	 * @param (string) $type ////.
	 * @param (string) $path ////.
	 * @param (string) $pre  ////.
	 */
	public function fix_dists( $type = 'branches', $path = '', $pre = '' ) {
		global $wp_version, $wp_local_package;
		static $wp_files_hashes;
		static $flag = false;

		if ( ! isset( $wp_files_hashes ) ) {
			update_site_option( SECUPRESS_FIX_DISTS, array( $wp_version => array() ), false );
		}

		$branch   = 'branches' === $type ? substr( $wp_version, 0, 3 ) : $wp_version;
		$i18n_url = $path ? $path : "http://i18n.svn.wordpress.org/$wp_local_package/$type/$branch/dist/";
		$response = wp_remote_get( $i18n_url );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {

			$links = strip_tags( wp_remote_retrieve_body( $response ), '<a>' );
			preg_match_all( '/>(.*)<\/a>/', $links, $links );

			if ( isset( $links[1] ) ) {

				$links = $links[1];
				unset( $links[0], $links[ count( $links ) ] );

				foreach ( $links as $dist ) {
					secupress_time_limit( 0 );

					if ( '/' !== substr( $dist, -1 ) ) {

						$response = wp_remote_get( $i18n_url . $dist );

						if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {

							$content = wp_remote_retrieve_body( $response );
							$wp_files_hashes[ $wp_version ][ $pre . $dist ] = md5( $content );
						}
					} else {
						$this->fix_dists( $type, $i18n_url . $dist, $pre . $dist );
					}
				}
			}
		} elseif ( $flag !== $path  ) {
			$this->fix_dists( 'tags', $path, $pre );
			$flag = $path;
		}

		update_site_option( SECUPRESS_FIX_DISTS, $wp_files_hashes, false );
	}


	/**
	 * Init for `get_full_tree()`.
	 *
	 * @since 1.0
	 *
	 * @return (string) Next in queue.
	 */
	public function scan_full_tree() {
		// Be sure to delete everything before starting.
		secupress_file_scanner_delete_full_filetree();
		secupress_set_site_transient( SECUPRESS_FULL_FILETREE, array( ABSPATH ) );

		return 'get_full_tree';
	}


	/**
	 * Wrapper for `get_file_tree()` with full recursivity.
	 *
	 * @since 1.0
	 *
	 * @return (string|bool) 'get_full_tree' while scanning, then false when finished.
	 */
	public function get_full_tree() {
		if ( $this->get_file_tree() ) {
			// There are still files to fetch.
			return 'get_full_tree';
		}

		// OK, we're done.
		return false;
	}


	/**
	 * Will store the wp core files hashes (md5), first from the w.org api, then from the .zip from w.org too.
	 *
	 * @since 1.0
	 *
	 * @param (array) $args An array of arguments.
	 *                      (array) $ext_filter An array of file extensions: only files with those extensions will be fetched. Empty to get everything.
	 *                      (array) $ignore     An array of folders and files to ignore (absolute path).
	 *
	 * @return (bool) True if there are still some folders to scan. False otherwise.
	 */
	public function get_file_tree( $args = array() ) {
		global $wp_version;
		static $abspath;
		static $init_done = false;
		static $hashes;

		if ( ! $init_done ) {
			$init_done = true;
			secupress_maybe_increase_memory_limit();
		}

		if ( ! isset( $abspath ) ) {
			$abspath = trailingslashit( wp_normalize_path( ABSPATH ) );
		}

		if ( ! isset( $hashes ) ) {
			$hashes = secupress_file_scanner_get_wp_core_file_hashes();
		}

		// Folders to scan.
		$paths = secupress_get_site_transient( SECUPRESS_FULL_FILETREE );

		if ( ! $paths ) {
			// The end, nothing more to scan.
			secupress_delete_site_transient( SECUPRESS_FULL_FILETREE );
			return false;
		}

		$paths = array_filter( (array) $paths, 'is_dir' );

		if ( ! $paths ) {
			// The fuck?
			secupress_delete_site_transient( SECUPRESS_FULL_FILETREE );
			return false;
		}

		// Files already fetched.
		$result = secupress_file_scanner_get_full_filetree( true );

		// Arguments.
		$ext_filter = ! empty( $args['ext_filter'] ) ? array_flip( array_map( 'strtolower', (array) $args['ext_filter'] ) ) : array();
		$ignore     = ! empty( $args['ignore'] )     ? array_flip( (array) $args['ignore'] )                                : array();

		// One folder at a time.
		$dir   = array_shift( $paths );
		$files = scandir( $dir );
		$files = array_diff( $files, array( '.', '..' ) );

		$update_result = false;

		if ( $files ) {
			$wp_hashes = get_site_option( SECUPRESS_WP_CORE_FILES_HASHES );
			$wp_hashes = ! empty( $wp_hashes[ $wp_version ]['checksums'] ) && is_array( $wp_hashes[ $wp_version ]['checksums'] ) ? $wp_hashes[ $wp_version ]['checksums'] : array();
			$new_paths = array();

			foreach ( $files as $file_or_dir_name ) {
				// We must not use `realpath()`, otherwise `str_replace( $abspath,...` will fail for symlinked files.
				$file_or_dir = wp_normalize_path( $dir . '/' . $file_or_dir_name );

				if ( isset( $ignore[ $file_or_dir ] ) ) {
					// This file or directory must be ignored.
					continue;
				}

				if ( ! is_file( $file_or_dir ) ) {
					// Probably a directory.
					$new_paths[] = $file_or_dir;
					continue;
				}

				if ( $ext_filter ) {
					$file_ext = strtolower( pathinfo( $file_or_dir, PATHINFO_EXTENSION ) );

					if ( ! isset( $ext_filter[ $file_ext ] ) ) {
						// This file extension is not in our list.
						continue;
					}
				}

				$key = str_replace( $abspath, '', $file_or_dir );

				if ( empty( $hashes[ $key ] ) && ! secupress_check_malware( $key ) ) {
					// If not a WP core file, keep only files that are possibly a malware.
					continue;
				}

				// A new file to store.
				$update_result = true;
				// If the file is not listed in the WP hashes, no need to calculate the md5.
				$result[ $wp_version ][ $key ] = ! empty( $wp_hashes[ $key ] ) ? md5_file( $file_or_dir ) : $file_or_dir;
			}

			// Put new paths at the beginning. Not really important, it simply feels more logic.
			$paths = array_merge( $new_paths, $paths );
		}

		if ( $update_result ) {
			secupress_file_scanner_store_full_filetree( $result );
		}

		if ( $paths ) {
			// We have still folders to scan.
			secupress_set_site_transient( SECUPRESS_FULL_FILETREE, $paths );
			return true;
		}

		// The end, nothing more to scan.
		secupress_delete_site_transient( SECUPRESS_FULL_FILETREE );
		return false;
	}
}
