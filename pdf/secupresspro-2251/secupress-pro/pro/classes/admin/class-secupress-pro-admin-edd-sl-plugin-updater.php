<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/**
 * Allows plugins to use their own update API.
 * Based on EDD_SL_Plugin_Updater 1.6.12.
 *
 * @package SecuPress
 * @version 1.2
 * @since 1.0
 * @author Grégory Viguier
 */
class SecuPress_Pro_Admin_EDD_SL_Plugin_Updater extends SecuPress_EDD_SL_Plugin_Updater {

	const VERSION = '1.2';

	/**
	 * The API URL. The one from the parent class is private ಠ_ಠ.
	 *
	 * @var (string)
	 */
	protected $plugin_api_url = '';

	/**
	 * The API data. The one from the parent class is private ಠ_ಠ.
	 *
	 * @var (array)
	 */
	protected $plugin_api_data = array();

	/**
	 * I don't know, I don't care. I just want to be able to access it ಠ_ಠ.
	 *
	 * @var (string)
	 */
	protected $plugin_name = '';

	/**
	 * The plugin slug. The one from the parent class is private ಠ_ಠ.
	 *
	 * @var (string)
	 */
	protected $plugin_slug = '';

	/**
	 * I don't know, I don't care. I just want to be able to access it ಠ_ಠ.
	 *
	 * @var (string)
	 */
	protected $plugin_version = '';

	/**
	 * I don't know, I don't care. I just want to be able to access it ಠ_ಠ.
	 *
	 * @var (bool)
	 */
	protected $plugin_wp_override = false;

	/**
	 * I don't know, I don't care. I just want to be able to access it ಠ_ಠ.
	 *
	 * @var (bool)
	 */
	protected $plugin_beta = false;

	/**
	 * I don't know, I don't care. I just want to be able to access it ಠ_ಠ.
	 *
	 * @var (string)
	 */
	protected $plugin_cache_key = '';


	/**
	 * Class constructor.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (string) $_api_url     The URL pointing to the custom API endpoint.
	 * @param (string) $_plugin_file Path to the plugin file.
	 * @param (array)  $_api_data    Optional data to send with API calls.
	 */
	public function __construct( $_api_url, $_plugin_file, $_api_data = null ) {
		$this->plugin_api_url     = trailingslashit( $_api_url );
		$this->plugin_api_data    = $_api_data;
		$this->plugin_name        = plugin_basename( $_plugin_file );
		$this->plugin_slug        = basename( $_plugin_file, '.php' );
		$this->plugin_version     = $_api_data['version'];
		$this->plugin_wp_override = isset( $_api_data['wp_override'] ) ? (bool) $_api_data['wp_override'] : false;
		$this->plugin_beta        = ! empty( $_api_data['beta'] );
		$this->plugin_cache_key   = md5( serialize( $this->plugin_slug . $_api_data['license'] . $this->plugin_beta ) );

		parent::__construct( $_api_url, $_plugin_file, $_api_data );

	}


	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update API just when WordPress creates its update array, then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native WordPress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @since 1.0.3
	 * @author Grégory Viguier
	 * @uses plugin_api_request()
	 *
	 * @param (array) $_transient_data Update array build by WordPress.
	 *
	 * @return (array) Modified update array with custom plugin data.
	 */
	public function check_update( $_transient_data ) {
		global $pagenow;

		if ( ! is_object( $_transient_data ) ) {
			$_transient_data = new stdClass;
		}

		if ( ! empty( $_transient_data->response ) && ! empty( $_transient_data->response[ $this->plugin_name ] ) && false === $this->plugin_wp_override ) {
			return $_transient_data;
		}

		$version_info = $this->get_cached_version_info();

		if ( false === $version_info ) {
			$version_info = $this->plugin_api_request( 'plugin_latest_version', array( 'slug' => $this->plugin_slug, 'beta' => $this->plugin_beta ) );

			$this->set_version_info_cache( $version_info );
		}

		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {

			if ( version_compare( $this->plugin_version, $version_info->new_version ) < 0 ) {
				$_transient_data->response[ $this->plugin_name ] = $version_info;
			}

			$_transient_data->last_checked = current_time( 'timestamp' );
			$_transient_data->checked[ $this->plugin_name ] = $this->plugin_version;
		}

		return $_transient_data;
	}


	/**
	 * Show update nofication row -- needed for multisite subsites, because WP won't tell you otherwise!
	 *
	 * @param (string) $file   Don't care.
	 * @param (array)  $plugin Same.
	 */
	public function show_update_notification( $file, $plugin ) {
		if ( is_network_admin() ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( ! is_multisite() ) {
			return;
		}

		if ( $this->plugin_name !== $file ) {
			return;
		}

		// Remove our filter on the site transient.
		remove_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ), 10 );

		$update_cache = get_site_transient( 'update_plugins' );
		$update_cache = is_object( $update_cache ) ? $update_cache : new stdClass();

		if ( empty( $update_cache->response ) || empty( $update_cache->response[ $this->plugin_name ] ) ) {

			$version_info = $this->get_cached_version_info();

			if ( false === $version_info ) {
				$version_info = $this->plugin_api_request( 'plugin_latest_version', array( 'slug' => $this->plugin_slug, 'beta' => $this->plugin_beta ) );

				$this->set_version_info_cache( $version_info );
			}

			if ( ! is_object( $version_info ) ) {
				return;
			}

			if ( version_compare( $this->plugin_version, $version_info->new_version ) < 0 ) {
				$update_cache->response[ $this->plugin_name ] = $version_info;
			}

			$update_cache->last_checked = current_time( 'timestamp' );
			$update_cache->checked[ $this->plugin_name ] = $this->plugin_version;

			set_site_transient( 'update_plugins', $update_cache );
		} else {
			$version_info = $update_cache->response[ $this->plugin_name ];
		}

		// Restore our filter.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );

		if ( ! empty( $update_cache->response[ $this->plugin_name ] ) && version_compare( $this->plugin_version, $version_info->new_version ) < 0 ) {
			// Build a plugin list row, with update notification.
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
			echo '<tr class="plugin-update-tr" id="' . $this->plugin_slug . '-update" data-slug="' . $this->plugin_slug . '" data-plugin="' . $this->plugin_slug . '/' . $file . '">';
			echo '<td colspan="3" class="plugin-update colspanchange">';
			echo '<div class="update-message notice inline notice-warning notice-alt">';

			$changelog_link = self_admin_url( 'index.php?edd_sl_action=view_plugin_changelog&plugin=' . $this->plugin_name . '&slug=' . $this->plugin_slug . '&TB_iframe=true&width=772&height=911' );

			if ( empty( $version_info->download_link ) ) {
				printf(
					__( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s.', 'secupress' ),
					esc_html( $version_info->name ),
					'<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
					esc_html( $version_info->new_version ),
					'</a>'
				);
			} else {
				printf(
					__( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s or %5$supdate now%6$s.', 'secupress' ),
					esc_html( $version_info->name ),
					'<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
					esc_html( $version_info->new_version ),
					'</a>',
					'<a href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $this->plugin_name, 'upgrade-plugin_' . $this->plugin_name ) ) . '">',
					'</a>'
				);
			}

			do_action( "in_plugin_update_message-{$file}", $plugin, $version_info );

			echo '</div></td></tr>';
		}
	}


	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 * This improves and fixes the bugs of the parent version.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (false|object|array) $_data  The result object or array. Default false.
	 * @param (string)             $action The type of information being requested from the Plugin Install API.
	 * @param (object)             $args   Plugin API arguments.
	 *
	 * @return (object)
	 */
	public function plugins_api_filter( $_data, $action = '', $args = null ) {
		if ( 'plugin_information' !== $action ) {
			return $_data;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $_data;
		}

		$to_send = array(
			'slug'   => $this->plugin_slug,
			'is_ssl' => is_ssl(),
			'fields' => array(
				'banners' => array(),
				'reviews' => false,
			),
		);

		$cache_key = 'edd_api_request_' . md5( serialize( $this->plugin_slug . $this->plugin_api_data['license'] . $this->plugin_beta ) );

		// Get the transient where we store the api request for this plugin for 24 hours.
		$edd_api_request_transient = $this->get_cached_version_info( $cache_key );

		// If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
		if ( empty( $edd_api_request_transient ) ) {
			$api_response = $this->plugin_api_request( 'plugin_information', $to_send );

			// Expires in 3 hours.
			$this->set_version_info_cache( $api_response, $cache_key );

			if ( false !== $api_response ) {
				$_data = $api_response;
			}
		} else {
			$_data = $edd_api_request_transient;
		}

		// Convert sections into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->sections ) && ! is_array( $_data->sections ) ) {
			$_data->sections = (array) $_data->sections;
		}

		// Convert banners into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->banners ) && ! is_array( $_data->banners ) ) {
			$_data->banners = (array) $_data->banners;
		}

		return $_data;
	}


	/**
	 * Calls the API and, if successfull, returns the object delivered by the API.
	 * This is a duplicate of `parent::api_request()`, but not private ಠ_ಠ.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 *
	 * @param (string) $_action The requested action.
	 * @param (array)  $_data   Parameters for the API action.
	 *
	 * @return (false|object) The response object on success. False on failure.
	 */
	protected function plugin_api_request( $_action, $_data ) {
		global $wp_version;

		$data = array_merge( $this->plugin_api_data, $_data );

		if ( $data['slug'] !== $this->plugin_slug ) {
			return;
		}

		if ( trailingslashit( home_url() ) === $this->plugin_api_url ) {
			// Don't allow a plugin to ping itself.
			return false;
		}

		$api_params = array(
			'edd_action' => 'get_version',
			'license'    => ! empty( $data['license'] ) ? $data['license']   : '',
			'item_name'  => isset( $data['item_name'] ) ? $data['item_name'] : false,
			'item_id'    => isset( $data['item_id'] )   ? $data['item_id']   : false,
			'version'    => isset( $data['version'] )   ? $data['version']   : false,
			'slug'       => $data['slug'],
			'author'     => $data['author'],
			'url'        => home_url(),
			'beta'       => ! empty( $data['beta'] ),
		);

		$response = wp_remote_post( $this->plugin_api_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! $response || ! isset( $response->sections ) ) {
			return false;
		}

		$response           = (object) array_map( 'maybe_unserialize', (array) $response );
		$response->name     = SECUPRESS_PLUGIN_NAME;
		$response->sections = array_filter( (array) $response->sections );

		if ( ! empty( $response->new_version ) && empty( $response->version ) ) {
			$response->version = $response->new_version;
		}

		// Handle the white label.
		if ( secupress_is_white_label() ) {
			// Plugin name was handled 4 lines earlier.
			// Plugin URL.
			$response->homepage = secupress_get_option( 'wl_plugin_URI' );

			// Description. Well, use the short description.
			$response->sections['description'] = secupress_get_option( 'wl_description' );

			if ( ! $response->sections['description'] ) {
				unset( $response->sections['description'] );
			}

			// Contributors.
			$plugin_author = secupress_get_option( 'wl_author' );

			if ( $plugin_author ) {
				$response->contributors = array( $plugin_author => secupress_get_option( 'wl_author_URI' ) );
			} else {
				$response->contributors = array();
			}

			// Remove the Installation tab, it contains references to SecuPress.
			unset( $response->sections['installation'] );
		}
		// Make sure the contributors are well formated.
		elseif ( ! empty( $response->contributors ) && is_array( $response->contributors ) ) {
			/**
			 * "Should" be:
			 * array(
			 *     'wp_media'  => 'https://profiles.wordpress.org/wp_media',
			 *     'SecuPress' => 'https://profiles.wordpress.org/secupress',
			 *     ...
			 * )
			 * But currently is:
			 * array(
			 *     0 => 'wp_media',
			 *     1 => 'SecuPress',
			 *     ...
			 * )
			 */
			$contributors = array();

			foreach ( $response->contributors as $maybe_contributor => $maybe_url ) {
				if ( strpos( $maybe_url, 'http' ) === 0 ) {
					$contributors[ $maybe_contributor ] = $maybe_url;
				} else {
					$contributors[ $maybe_url ] = 'https://profiles.wordpress.org/' . strtolower( $maybe_url );
				}
			}

			$response->contributors = $contributors;
		}

		/**
		 * Triggered after a successful request.
		 *
		 * @since 1.0
		 *
		 * @param (object) $response The request response.
		 * @param (array)  $data     The data used for the request.
		 */
		do_action( 'secupress.updater.after_request_response', $response, $data );

		return $response;
	}
}
