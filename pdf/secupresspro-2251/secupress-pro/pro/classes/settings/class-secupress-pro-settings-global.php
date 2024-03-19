<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * Global settings class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Settings
 * @since 1.0
 * @version 1.0
 */
class SecuPress_Pro_Settings_Global extends SecuPress_Settings_Global {

	const VERSION = '1.0';

	/**
	 * The list of the pro modules.
	 *
	 * @var (array)
	 */
	protected static $pro_modules = array();


	/** Init ==================================================================================== */

	/**
	 * Init: this method is required by the class `SecuPress_Singleton`.
	 *
	 * @since 1.0
	 */
	protected function _init() {
		parent::_init();

		add_filter( 'secupress.global_settings.modules', array( __CLASS__, 'add_modules' ) );
	}


	/** Manage modules ========================================================================== */

	/**
	 * Filter the modules of the global settings to add pro fields.
	 *
	 * @since 1.0
	 *
	 * @param (array) $setting_modules The modules.
	 *
	 * @return (array)
	 */
	public static function add_modules( $setting_modules ) {
		if ( defined( 'WP_SWL' ) && WP_SWL ) {
			static::$pro_modules[] = 'wl';
		} elseif ( ! static::$pro_modules ) {
			return $setting_modules;
		}

		$index = array_search( 'settings-manager', $setting_modules, true );

		if ( false === $index ) {
			return array_merge( $setting_modules, static::$pro_modules );
		}

		array_splice( $setting_modules, $index, 0, static::$pro_modules );
		return $setting_modules;
	}


	/** Includes ================================================================================ */

	/**
	 * Include a module settings file. Also, automatically set the current module and print the sections.
	 *
	 * @since 1.0
	 *
	 * @param (string) $module The module.
	 *
	 * @return (object) The class instance.
	 */
	final protected function load_module_settings( $module ) {
		$pro_modules = array_flip( static::$pro_modules );

		if ( isset( $pro_modules[ $module ] ) ) {
			$module_file = SECUPRESS_PRO_ADMIN_SETTINGS_MODULES . $module . '.php';
		} else {
			$module_file = SECUPRESS_ADMIN_SETTINGS_MODULES . $module . '.php';
		}

		$this->print_open_form_tag( $module );
		$this->require_settings_file( $module_file, $module );
		$this->print_close_form_tag( $module );

		return $this;
	}
}
