<?php
/**
 * Module Name: No Plugin Activation
 * Description: Disabled the plugin activation
 * Main Module: plugins_themes
 * Author: SecuPress
 * Version: 1.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

if ( is_admin() ) {
	add_filter( 'network_admin_plugin_action_links', 'secupress_no_plugin_activation', PHP_INT_MAX );
	add_filter( 'plugin_action_links', 'secupress_no_plugin_activation', PHP_INT_MAX );
	/**
	 * Modify the behavior of the 'activate' link action.
	 *
	 * @author Julio Potier
	 * @since 1.0
	 *
	 * @param (array) $actions The actions to be displayed under the plugin title in plugins' page.
	 *
	 * @return (array)
	 */
	function secupress_no_plugin_activation( $actions ) {
		global $current_screen;

		if ( ! isset( $actions['activate'] ) || ! isset( $current_screen ) ) {
			return $actions;
		}

		if ( $current_screen->in_admin( 'network' ) ) {
			$actions['activate'] = '<del>' . __( 'Network Activate' ) . '</del>';
		} else {
			$actions['activate'] = '<del>' . __( 'Activate' ) . '</del>';
		}

		return $actions;
	}


	add_action( 'check_admin_referer', 'secupress_avoid_activate_plugin' );
	/**
	 * Block any tentative to install a plugin.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 *
	 * @param (array) $action The current action from the plugins' page.
	 */
	function secupress_avoid_activate_plugin( $action ) {
		global $pagenow;

		if ( ( 'plugins.php' === $pagenow && isset( $_GET['action'] ) && 'activate' === $_GET['action'] ) || // Page access.
			( 'bulk-plugins' === $action && // Form validation.
			( isset( $_POST['action'] ) && 'activate-selected' === $_POST['action'] ) || // WPCS: CSRF ok.
			( isset( $_POST['action2'] ) && 'activate-selected' === $_POST['action2'] ) ) // WPCS: CSRF ok.
		) {
			secupress_die( __( 'You do not have sufficient permissions to install plugins on this site.', 'secupress' ), '', array( 'force_die' => true ) );
		}
	}


	add_filter( 'install_plugin_complete_actions', 'secupress_no_plugin_activation_after_plugin_install' );
	/**
	 * Modify the behavior of the 'activate' link action.
	 *
	 * @author Julio Potier
	 * @since 1.0
	 *
	 * @param (array) $install_actions The actions to be displayed under the plugin title in plugins' pages.
	 *
	 * @return (array)
	 */
	function secupress_no_plugin_activation_after_plugin_install( $install_actions ) {
		global $current_screen;

		$from = isset( $_GET['from'] ) ? wp_unslash( $_GET['from'] ) : 'plugins';

		if ( $current_screen->in_admin( 'network' ) ) {
			$install_actions['network_activate'] = '<del>' . __( 'Network Activate' ) . '</del>';
			unset( $install_actions['activate_plugin'] );
		} elseif ( 'import' === $from ) {
			$install_actions['activate_plugin'] = '<del>' . __( 'Activate Plugin &amp; Run Importer' ) . '</del>';
		} else {
			$install_actions['activate_plugin'] = '<del>' . __( 'Activate Plugin' ) . '</del>';
		}

		return $install_actions;
	}


	add_action( 'admin_footer-plugins.php', 'secupress_add_js_to_activate_bulk_action', 100 );
	/**
	 * Remove the action "activate" from the bulk selector (no hook to remove, yet).
	 *
	 * @author Julio Potier
	 * @since 1.0
	 */
	function secupress_add_js_to_activate_bulk_action() {
		?>
		<script>jQuery( 'option[value="activate-selected"]' ).remove();</script>
		<?php
	}
}
