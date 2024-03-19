<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Free downgrade class.
 *
 * @package SecuPress
 * @since 1.3
 */
class SecuPress_Pro_Admin_Free_Downgrade extends SecuPress_Admin_Offer_Migration {

	/**
	 * Class version.
	 *
	 * @var (string)
	 */
	const VERSION = '1.0';

	/**
	 * Name of the post action used to install the Free plugin.
	 *
	 * @var (string)
	 */
	const POST_ACTION = 'secupress_maybe_install_free_version';

	/**
	 * Free plugin zip URL.
	 *
	 * @var (string)
	 */
	const DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/secupress.zip';

	/**
	 * The reference to the "Singleton" instance of this class.
	 *
	 * @var (object)
	 */
	protected static $_instance;


	/** Init ==================================================================================== */

	/**
	 * Init.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	protected function _init() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			parent::_init();
			return;
		}

		add_action( 'current_screen',                  array( $this, 'maybe_warn_no_license' ) );
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'maybe_install_free_version' ) );

		parent::_init();
	}


	/** Public methods ========================================================================== */

	/**
	 * Display a warning when the license is not valid.
	 *
	 * @since 1.3
	 * @see Was previously `secupress_warning_no_license()`.
	 * @author Grégory Viguier
	 */
	public function maybe_warn_no_license() {
		if ( static::is_update_page() || static::is_settings_page() ) {
			return;
		}

		if ( ! static::current_user_can() ) {
			return;
		}

		if ( secupress_is_pro() ) {
			// The license is valid.
			return;
		}

		if ( ! secupress_get_consumer_key() ) {
			$message = sprintf(
				/** Translators: %s is a link to the "plugin settings page". */
				__( 'Your Pro license is not set yet. If you want to activate all the Pro features, premium support and updates, take a look at %s.', 'secupress' ),
				'<a href="' . esc_url( static::get_settings_url() ) . '">' . __( 'the plugin settings page', 'secupress' ) . '</a>'
			);

			static::add_notice( $message, 'updated', false );
			return;
		}

		$message = sprintf(
			/** Translators: %s is a link to the "plugin settings page". */
			__( 'Your Pro license is not valid. If you want to activate all the Pro features, premium support and updates, take a look at %s.', 'secupress' ),
			'<a href="' . esc_url( static::get_settings_url() ) . '">' . __( 'the plugin settings page', 'secupress' ) . '</a>'
		);

		$license_error = secupress_get_option( 'license_error' );

		if ( ! $license_error ) {
			static::add_notice( $message, 'updated', false );
			return;
		}

		// See if that error is a EDD error that may require the plugin to be uninstalled.
		$license_error = $this->get_error_message( $license_error );

		if ( ! $license_error ) {
			static::add_notice( $message, 'updated', false );
			return;
		}

		static::add_notice( $license_error, 'error', false );
	}


	/**
	 * Maybe install the Free plugin.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 */
	public function maybe_install_free_version() {
		if ( ! static::current_user_can() ) {
			return;
		}

		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], self::POST_ACTION ) ) {
			secupress_admin_die();
		}

		static::set_transient( $this->get_remote_information() );

		/**
		 * OK, we have all we need, `static::add_migration_data()` will do the rest.
		 */
		wp_safe_redirect( esc_url_raw( static::get_install_url() ) );
		die();
	}


	/** Private methods ========================================================================= */

	/**
	 * Get an error message matching an error code.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @param (string) $code An error code.
	 *
	 * @return (string) An error message. An empty string if the code doesn’t match any message.
	 */
	protected function get_error_message( $code ) {
		/** Translators: we're talking about the license key. */
		$settings_link = '<a href="' . esc_url( static::get_settings_url() ) . '">' . __( 'please verify it', 'secupress' ) . '</a>';
		$install_link  = '<a href="' . esc_url( static::get_post_install_url() ) . '">' . __( 'to install the free version', 'secupress' ) . '</a>';
		$support_link  = '<a href="' . esc_url( static::get_support_url() ) . '" target="_blank" title="' . esc_attr__( 'Open in a new window.', 'secupress' ) . '">' . __( 'our support team', 'secupress' ) . '</a>';
		$account_link  = '<a href="' . esc_url( static::get_account_url() ) . '" target="_blank" title="' . esc_attr__( 'Open in a new window.', 'secupress' ) . '">%s</a>';

		// These are errors returned by EDD and that may (or not) require SecuPress Pro uninstall.
		$edd_errors = array(
			'missing'             => sprintf(
				/** Translators: 1 is a link to the plugin settings page, 2 is a "our support team" link, 3 is a "to install the free version" link. */
				__( 'There is a problem with your license key, %1$s. If you think there is a mistake, you should contact %2$s. Otherwise, you may want %3$s to get future updates.', 'secupress' ),
				$settings_link, $support_link, $install_link
			),
			'key_mismatch'        => sprintf(
				/** Translators: 1 is a link to the plugin settings page, 2 is a "our support team" link, 3 is a "to install the free version" link. */
				__( 'There is a problem with your license key, %1$s. If you think there is a mistake, you should contact %2$s. Otherwise, you may want %3$s to get future updates.', 'secupress' ),
				$settings_link, $support_link, $install_link
			),
			'revoked'             => sprintf(
				/** Translators: 1 is a "our support team" link, 2 is a "to install the free version" link. */
				__( 'This license key has been revoked. If you think there is a mistake, you should contact %1$s. Otherwise, you may want %2$s to get future updates.', 'secupress' ),
				$support_link, $install_link
			),
			'expired'             => sprintf(
				/** Translators: 1 is a "to renew your subscription" link, 2 is a "to install the free version" link. */
				__( 'This license key expired. You may want %1$s or %2$s to get future updates.', 'secupress' ),
				sprintf( $account_link, __( 'to renew your subscription', 'secupress' ) ),
				$install_link
			),
			'no_activations_left' => sprintf(
				/** Translators: 1 is a "to upgrade your license" link, 2 is a "to install the free version" link. */
				__( 'You used as many sites as your license allows. You may want %1$s to add more sites or %2$s to get future updates.', 'secupress' ),
				sprintf( $account_link, __( 'to upgrade your license', 'secupress' ) ),
				$install_link
			),
		);

		return ! empty( $edd_errors[ $code ] ) ? $edd_errors[ $code ] : '';
	}


	/**
	 * Get the Free plugin information. No need to get information with a remote request.
	 *
	 * @since 1.3
	 * @author Grégory Viguier
	 *
	 * @return (object|bool|null) The information object on success, null on failure, false if the data is false.
	 */
	protected function get_remote_information() {
		$information = (object) array(
			// Name, version and homepage are not important.
			'name'                => SECUPRESS_PLUGIN_NAME,
			'version'             => SECUPRESS_VERSION,
			'homepage'            => esc_url_raw( __( 'https://wordpress.org/plugins/secupress/', 'secupress' ) ),
			'download_link'       => self::DOWNLOAD_URL,
			'secupress_data_type' => 'free',
		);

		return static::validate_plugin_information( $information, true );
	}
}
