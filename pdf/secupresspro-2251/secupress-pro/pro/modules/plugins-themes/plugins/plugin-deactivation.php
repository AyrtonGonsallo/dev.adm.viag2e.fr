<?php
/**
 * Module Name: No Plugin Deactivation
 * Description: Disabled the plugin deactivation
 * Main Module: plugins_themes
 * Author: SecuPress
 * Version: 1.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

if ( is_admin() ) {

	add_filter( 'network_admin_plugin_action_links', 'secupress_no_plugin_deactivation', PHP_INT_MAX );
	add_filter( 'plugin_action_links',               'secupress_no_plugin_deactivation', PHP_INT_MAX );
	/**
	 * Remove plugin deactivation link.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 *
	 * @param (array) $actions The actions (links).
	 */
	function secupress_no_plugin_deactivation( $actions ) {
		global $current_screen;

		if ( ! isset( $actions['deactivate'] ) || ! isset( $current_screen ) ) {
			return $actions;
		}

		if ( $current_screen->in_admin( 'network' ) ) {
			$actions['deactivate'] = '<del>' . __( 'Network Deactivate' ) . '</del>';
		} else {
			$actions['deactivate'] = '<del>' . __( 'Deactivate' ) . '</del>';
		}

		return $actions;
	}


	add_action( 'check_admin_referer', 'secupress_avoid_deactivate_plugin' );
	/**
	 * Prevent plugin deactivation.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 *
	 * @param (string) $action The action.
	 */
	function secupress_avoid_deactivate_plugin( $action ) {
		global $pagenow;

		if ( ( 'plugins.php' === $pagenow && isset( $_GET['action'] ) && 'deactivate' === $_GET['action'] ) || // Page access.
			( 'bulk-plugins' === $action && // Form validation.
			( isset( $_POST['action'] ) && 'deactivate-selected' === $_POST['action'] ) || // WPCS: CSRF ok.
			( isset( $_POST['action2'] ) && 'deactivate-selected' === $_POST['action2'] ) ) // WPCS: CSRF ok.
		) {
			secupress_die( __( 'You do not have sufficient permissions to deactivate plugins on this site.', 'secupress' ), '', array( 'force_die' => true ) );
		}
	}


	add_action( 'admin_footer-plugins.php', 'secupress_add_js_to_deactivate_bulk_action', 100 );
	/**
	 * Print some JavaScript that will remove bulk actions.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 */
	function secupress_add_js_to_deactivate_bulk_action() {
		?>
		<script>
			jQuery( 'option[value="deactivate-selected"]' ).remove();

			if ( 1 === jQuery( '#bulk-action-selector-top option' ).length ) {
				jQuery( '#bulk-action-selector-top' ).remove();
			}

			if ( 1 === jQuery( '#bulk-action-selector-bottom option' ).length ) {
				jQuery( '#bulk-action-selector-bottom' ).remove();
			}

			jQuery( document ).ready( function() {
				if ( 0 === jQuery( 'div.bulkactions select' ).length ) {
					jQuery( 'div.bulkactions' ).remove();
				}
			} );
		</script>
		<?php
	}
}
