<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Class that handle the free plugin removal.
 *
 * @package SecuPress
 * @version 1.0
 * @since 1.3
 * @author Grégory Viguier
 */
class SecuPress_Pro_Admin_Remove_Free_Plugin {

	const VERSION = '1.0';

	/**
	 * Path to this plugin (to the main file).
	 *
	 * @var (string)
	 */
	protected $this_plugin_path;

	/**
	 * The reference to *Singleton* instance of this class.
	 *
	 * @var (object)
	 */
	protected static $_instance;


	/**
	 * Get the *Singleton* instance of this class.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @param (string) $plugin_path Path to this plugin.
	 *
	 * @return (object) The *Singleton* instance.
	 */
	final public static function get_instance( $plugin_path = false ) {
		if ( ! isset( static::$_instance ) ) {
			static::$_instance = new SecuPress_Pro_Admin_Remove_Free_Plugin( $plugin_path );
		}

		return static::$_instance;
	}


	/**
	 * Set instance properties and launch the whole process.
	 * Private constructor to prevent creating a new instance of the *Singleton* via the `new` operator from outside of this class.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @param (string) $plugin_path Path to this plugin.
	 */
	final private function __construct( $plugin_path ) {
		if ( ! $plugin_path ) {
			return;
		}

		$this->this_plugin_path = $plugin_path;

		if ( is_admin() ) {
			add_action( 'load-plugins.php',      array( $this, 'prevent_free_plugin_action' ), 0 );
			add_action( 'wp_ajax_update-plugin', array( $this, 'prevent_ajax_plugin_update' ), 0 );
		}

		if ( ! static::get_free_plugin_path() ) {
			// No Free plugin.
			return;
		}

		if ( defined( 'SECUPRESS_FILE' ) ) {
			// The Free plugin has been included, we can delete it right now.
			$this->maybe_delete_free_plugin();
			return;
		}

		/**
		 * The Free plugin has not been included yet.
		 * If we delete it now it will trigger php warnings because `wp_get_active_and_valid_plugins()` will still try to include it, even if we deactivate it and delete it before that.
		 * So we delay this deletion a bit.
		 */
		add_action( 'plugins_loaded', array( $this, 'maybe_delete_free_plugin' ), -1 );

		/**
		 * But if we keep it that way, the sky will fall (along with a fatal error).
		 */
		$this->make_free_plugin_dummy();
	}


	/** The main stuff ========================================================================== */

	/**
	 * Maybe delete the Free plugin.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	public function maybe_delete_free_plugin() {
		if ( $this->delete_free_plugin() ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		$plugin_file = static::get_free_plugin_basename();

		add_action( 'admin_print_styles-plugins.php',                   array( $this, 'plugins_list_styles' ) );
		add_action( 'admin_print_footer_scripts',                       array( $this, 'plugins_list_scripts' ) );
		add_action( 'all_admin_notices',                                array( $this, 'display_free_plugin_notice' ) );
		add_filter( "network_admin_plugin_action_links_{$plugin_file}", array( $this, 'remove_free_plugin_action_links' ), SECUPRESS_INT_MAX );
		add_filter( "plugin_action_links_{$plugin_file}",               array( $this, 'remove_free_plugin_action_links' ), SECUPRESS_INT_MAX );
		add_action( "after_plugin_row_{$plugin_file}",                  array( $this, 'add_warning_after_free_plugin_row' ), 0 );
	}


	/**
	 * Delete the free plugin, without triggering the deactivation and uninstallation hooks.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @return (bool) True on success. False on failure.
	 */
	protected function delete_free_plugin() {
		// "Deactivate" the plugin.
		if ( defined( 'SECUPRESS_FREE_IS_FINE' ) && SECUPRESS_FREE_IS_FINE ) {
			return true;
		}
		$this->deactivate_free_plugin();
		add_action( 'activated_plugin', array( $this, 'maybe_deactivate_free_plugin' ) );

		// Delete the plugin.
		$filesystem     = static::get_filesystem();
		$plugin_path    = static::get_free_plugin_path();
		$plugin_dir     = dirname( $plugin_path );
		$update_plugins = get_site_transient( 'update_plugins' );

		$deleted = $filesystem->delete( $plugin_dir, true );

		if ( $deleted ) {
			clearstatcache( true, $plugin_path );
		}

		if ( $update_plugins && is_object( $update_plugins ) && isset( $update_plugins->response[ $plugin_path ] ) ) {
			unset( $update_plugins->response[ $plugin_path ] );
			set_site_transient( 'update_plugins', $update_plugins );
		}

		return $deleted;
	}


	/**
	 * Put dummy contents into the free plugin, it will prevent triggering fatal errors when included. Also, empty the `uninstall.php` file.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @return (bool) True on success. False on failure.
	 */
	protected function make_free_plugin_dummy() {
		if ( defined( 'SECUPRESS_FREE_IS_FINE' ) && SECUPRESS_FREE_IS_FINE ) {
			return;
		}
		$filesystem  = static::get_filesystem();
		$plugin_path = static::get_free_plugin_path();
		$plugin_dir  = dirname( $plugin_path );

		/**
		 * In worst case scenario we can't delete the plugin with `$this->delete_free_plugin()`.
		 * So we try to empty the `uninstall.php` file.
		 * Well, it's a long shot because if we can't delete the plugin, we probably can't edit this file content neither, nor the main file content.
		 */
		$filesystem->put_contents( $plugin_dir . '/uninstall.php', "<?php\n" );

		// Put dummy contents in the main file.
		$this->load_i18n();

		$dummy  = "<?php\n";
		$dummy .= "/**\n";
		/** Translators: Plugin Name of the plugin/theme */
		$dummy .= ' * Plugin Name: ' . __( 'SecuPress — WordPress Security', 'secupress' ) . "\n";
		$dummy .= ' * Description: ' . __( 'PLEASE DELETE ME <strong>VIA FTP</strong>.', 'secupress' ) . "\n";
		$dummy .= " * Version: 100.0\n";
		$dummy .= " * Network: true\n";
		$dummy .= " */\n";

		return $filesystem->put_contents( $plugin_path, $dummy );
	}


	/**
	 * Maybe remove the free plugin from the list of active plugins.
	 * If we're inside `activate_plugin()` we won't be able to remove the free plugin from the list of active plugins, the option is updated after our change.
	 * For this reason, this callback is hooked on `activated_plugin`, it is triggered after the list is updated by WordPress.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @param (string) $plugin Path to the main plugin file from plugins directory.
	 */
	public function maybe_deactivate_free_plugin( $plugin ) {
		$this_plugin = plugin_basename( $this->this_plugin_path );

		if ( $this_plugin === $plugin ) {
			$this->deactivate_free_plugin();
		}
	}


	/**
	 * Remove the free plugin from the list of active plugins.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	protected function deactivate_free_plugin() {
		$plugin  = static::get_free_plugin_basename();
		$plugins = get_option( 'active_plugins', array() );
		$plugins = is_array( $plugins ) ? array_flip( $plugins ) : array();

		if ( isset( $plugins[ $plugin ] ) ) {
			// "Deactivate" the plugin.
			unset( $plugins[ $plugin ] );
			$plugins = array_keys( $plugins );
			update_option( 'active_plugins', $plugins );
		}

		if ( ! is_multisite() ) {
			return;
		}

		$plugins = get_site_option( 'active_sitewide_plugins', array() );
		$plugins = is_array( $plugins ) ? $plugins : array();

		if ( isset( $plugins[ $plugin ] ) ) {
			// "Deactivate" the plugin.
			unset( $plugins[ $plugin ] );
			update_site_option( 'active_sitewide_plugins', $plugins );
		}
	}


	/** Secondary hooks ========================================================================= */

	/**
	 * Prevent any action related to the free plugin.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	public function prevent_free_plugin_action() {
		if ( ! empty( $_POST['checked'] ) ) { // WPCS: CSRF ok.
			$plugin_file = static::get_free_plugin_basename();

			if ( is_array( $_POST['checked'] ) ) { // WPCS: CSRF ok.
				$key = array_search( $plugin_file, $_POST['checked'], true ); // WPCS: CSRF ok.

				if ( false !== $key ) {
					unset( $_POST['checked'][ $key ] );
				}
			} elseif ( trim( $_POST['checked'] ) === $plugin_file ) { // WPCS: CSRF ok.
				$_POST['checked'] = '';
			}
		}

		if ( ! empty( $_GET['plugins'] ) ) {
			$plugin_file = static::get_free_plugin_basename();
			$plugins     = explode( ',', $_GET['plugins'] );
			$key         = array_search( $plugin_file, $plugins, true );

			if ( false !== $key ) {
				$this->load_i18n();

				/** Translators: 1 is a plugin name. */
				wp_die( sprintf( __( 'Performing actions with %s is forbidden, unless you want the sky to fall to your head.', 'secupress' ), '<strong>SecuPress</strong>' ) );
			}
		}

		if ( ! empty( $_GET['plugin'] ) ) {
			$plugin_file = static::get_free_plugin_basename();

			if ( trim( $_GET['plugin'] ) === $plugin_file ) {
				$this->load_i18n();

				/** Translators: 1 is a plugin name. */
				wp_die( sprintf( __( 'Performing actions with %s is forbidden, unless you want the sky to fall to your head.', 'secupress' ), '<strong>SecuPress</strong>' ) );
			}
		}
	}


	/**
	 * Prevent the free plugin update via ajax. We display a custom message instead.
	 *
	 * @since 1.3
	 * @see wp_ajax_update_plugin()
	 * @author Grégory Viguier
	 */
	public function prevent_ajax_plugin_update() {
		if ( ! check_ajax_referer( 'updates', false, false ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( empty( $_POST['plugin'] ) || empty( $_POST['slug'] ) ) {
			return;
		}

		$plugin = plugin_basename( sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) );

		if ( static::get_free_plugin_basename() !== $plugin ) {
			return;
		}

		wp_send_json_error( array(
			'update'       => 'plugin',
			'slug'         => sanitize_key( wp_unslash( $_POST['slug'] ) ),
			'oldVersion'   => '',
			'newVersion'   => '',
			/** Translators: %s is the Pro plugin name. */
			'errorMessage' => sprintf( __( 'There is no need to update this plugin anymore, %s is now working without the free plugin (cool uh?).', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
		) );
	}


	/**
	 * Print styles on the plugins list page.
	 * This will hide the border between the free plugin row and the warning in the nnext row.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	public function plugins_list_styles() {
		if ( ! static::current_user_can() ) {
			return array();
		}

		$plugin_file = esc_attr( static::get_free_plugin_basename() );

		echo '<style type="text/css">#wpbody-content [data-plugin="' . $plugin_file . '"] th, #wpbody-content [data-plugin="' . $plugin_file . '"] td {-webkit-box-shadow: none; box-shadow: none;}</style>' . "\n";
	}


	/**
	 * Print scripts on the plugins list page.
	 * This will hide the border between the free plugin row and the warning in the nnext row.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	public function plugins_list_scripts() {
		global $pagenow;

		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		$plugin_file = esc_js( static::get_free_plugin_basename() );

		echo '<script type="text/javascript">jQuery(document).ready(function($) {$("#wpbody-content").find(\'[data-plugin="' . $plugin_file . '"] > .check-column\').text("");});</script>' . "\n";
	}


	/**
	 * Display an admin notice asking the user to delete the free plugin.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	public function display_free_plugin_notice() {
		if ( ! static::current_user_can() ) {
			return;
		}

		if ( ! function_exists( 'wp_normalize_path' ) ) {
			require_once( dirname( $this->this_plugin_path ) . '/core/functions/compat.php' );
		}

		$abspath = wp_normalize_path( ABSPATH );
		$path    = dirname( static::get_free_plugin_path() );
		$path    = wp_normalize_path( $path );
		$path    = '/' . rtrim( str_replace( $abspath, '', $path ), '/' );

		$this->load_i18n();

		/** Translators: 1 is the plugin name, 2 is a folder path. */
		echo '<div class="error"><p>' . sprintf( __( '%1$s couldn’t delete the free plugin. Please delete the folder %2$s via FTP.', 'secupress' ), '<strong>SecuPress Pro</strong>', "<code>{$path}</code>" ) . '</p></div>' . "\n";
	}


	/**
	 * Remove all action links on the free plugin row.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @param (array) $actions An array of plugin action links.
	 *
	 * @return (array) An array containing only a stroked delete action.
	 */
	public function remove_free_plugin_action_links( $actions ) {
		if ( ! static::current_user_can() ) {
			return array();
		}

		$new_actions = array();

		if ( isset( $actions['activate'] ) && current_user_can( 'activate_plugins' ) ) {
			$new_actions['activate'] = '<del class="edit">' . ( is_network_admin() ? __( 'Network Activate' ) : __( 'Activate' ) ) . '</del>'; // WP i18n.
		}

		if ( isset( $actions['delete'] ) ) {
			$new_actions['delete'] = '<del class="delete">' . __( 'Delete', 'secupress' ) . '</del>';
		}

		return $new_actions;
	}


	/**
	 * Print a warning under the free plugin row.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	public function add_warning_after_free_plugin_row() {
		if ( ! static::current_user_can() ) {
			return;
		}

		if ( ! function_exists( 'wp_normalize_path' ) ) {
			require_once( dirname( $this->this_plugin_path ) . '/core/functions/compat.php' );
		}

		$abspath = wp_normalize_path( ABSPATH );
		$path    = dirname( static::get_free_plugin_path() );
		$path    = wp_normalize_path( $path );
		$path    = '/' . rtrim( str_replace( $abspath, '', $path ), '/' );

		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

		$this->load_i18n();

		echo '<tr class="plugin-to-delete"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange">';
		/** Translators: 1 is a folder path. */
		echo '<div class="error-message notice inline notice-alt notice-error"><p>' . sprintf( __( 'Please delete the folder %s via FTP.', 'secupress' ), "<code>{$path}</code>" ) . '</p></div>';
		echo "</td></tr>\n";
	}


	/** Tools =================================================================================== */

	/**
	 * Get the path of the free version if it is installed.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @return (string) Path of the Free plugin main file. An empty string if it is not installed.
	 */
	public static function get_free_plugin_path() {
		static $free_plugin_path;

		if ( isset( $free_plugin_path ) ) {
			return $free_plugin_path;
		}

		if ( defined( 'SECUPRESS_FILE' ) ) {
			$free_plugin_path = str_replace( '\\', '/', SECUPRESS_FILE );
		} else {
			$free_plugin_path = static::get_plugins_dir() . 'secupress/secupress.php';
		}

		$free_plugin_path = file_exists( $free_plugin_path ) ? $free_plugin_path : '';

		return $free_plugin_path;
	}


	/**
	 * A shorthand to get the free plugin basename.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @return (string) Plugin basename of the Free plugin. An empty string if it is not installed.
	 */
	public static function get_free_plugin_basename() {
		static $plugin_basename;

		if ( isset( $plugin_basename ) ) {
			return $plugin_basename;
		}

		$plugin_basename = static::get_free_plugin_path();
		$plugin_basename = $plugin_basename ? plugin_basename( $plugin_basename ) : '';

		return $plugin_basename;
	}


	/**
	 * Return the path of the plugins dir.
	 *
	 * @since 1.3
	 *
	 * @return (string)
	 */
	public static function get_plugins_dir() {
		static $plugins_folder;

		if ( isset( $plugins_folder ) ) {
			return $plugins_folder;
		}

		$plugins_folder = WP_PLUGIN_DIR;
		$plugins_folder = str_replace( '\\', '/', $plugins_folder );
		$plugins_folder = trailingslashit( $plugins_folder );

		return $plugins_folder;
	}


	/**
	 * Tell if the current user has the capability to manipulate SecuPress.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @return (bool)
	 */
	public static function current_user_can() {
		static $user_can;

		if ( isset( $user_can ) ) {
			return $user_can;
		}

		if ( is_multisite() ) {
			$role = 'manage_network_options';
		} else {
			$role = 'administrator';
			/** This filter is documented in core/functions/common.php. */
			$role = apply_filters( 'secupress.user_capability', $role );
		}

		$user_can = current_user_can( $role ) && current_user_can( 'delete_plugins' );

		return $user_can;
	}


	/**
	 * Get WP Direct filesystem object. Also define chmod constants if not done yet.
	 *
	 * @since 1.3
	 *
	 * @return `$wp_filesystem` object.
	 */
	public static function get_filesystem() {
		static $filesystem;

		if ( $filesystem ) {
			return $filesystem;
		}

		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );

		$filesystem = new WP_Filesystem_Direct( new StdClass() ); // WPCS: override ok.

		// Set the permission constants if not already set.
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
		}

		return $filesystem;
	}


	/**
	 * Display an admin notice asking the user to delete the free plugin.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	protected function load_i18n() {
		static $done = false;

		if ( $done ) {
			return;
		}
		$done = true;

		load_plugin_textdomain( 'secupress-pro', false, dirname( plugin_basename( $this->this_plugin_path ) ) . '/languages' );
	}


	/** For the singleton ======================================================================= */

	/**
	 * Private clone method to prevent cloning of the instance of the *Singleton* instance.
	 *
	 * @since 1.0
	 */
	private function __clone() {}


	/**
	 * Private unserialize method to prevent unserializing of the *Singleton* instance.
	 *
	 * @since 1.0
	 */
	public function __wakeup() {}
}
