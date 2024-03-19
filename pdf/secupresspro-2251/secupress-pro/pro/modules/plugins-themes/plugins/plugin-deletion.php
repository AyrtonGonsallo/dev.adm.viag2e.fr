<?php
/**
 * Module Name: No Plugin Deletion
 * Description: Disabled the plugin deletion
 * Main Module: plugins_themes
 * Author: SecuPress
 * Version: 1.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

if ( is_admin() ) {
	add_filter( 'network_admin_plugin_action_links', 'secupress_no_plugin_deletion', PHP_INT_MAX );
	add_filter( 'plugin_action_links',               'secupress_no_plugin_deletion', PHP_INT_MAX );
	/**
	 * Remove plugin deletion link.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 *
	 * @param (array) $actions The actions (links).
	 */
	function secupress_no_plugin_deletion( $actions ) {
		if ( isset( $actions['delete'] ) ) {
			$actions['delete'] = '<del>' . __( 'Delete' ) . '</del>';
		}
		return $actions;
	}


	add_action( 'check_admin_referer', 'secupress_avoid_delete_plugin' );
	/**
	 * Prevent plugin deletion.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 *
	 * @param (string) $action The action.
	 */
	function secupress_avoid_delete_plugin( $action ) {
		global $pagenow;

		if ( ( 'plugins.php' === $pagenow && isset( $_GET['action'] ) && 'delete-selected' === $_GET['action'] ) || // Page access.
			( 'bulk-plugins' === $action && // Form validation.
			( isset( $_POST['action'] ) && 'delete-selected' === $_POST['action'] ) || // WPCS: CSRF ok.
			( isset( $_POST['action2'] ) && 'delete-selected' === $_POST['action2'] ) ) // WPCS: CSRF ok.
		) {
			secupress_die( __( 'You do not have sufficient permissions to delete plugins on this site.', 'secupress' ), '', array( 'force_die' => true ) );
		}
	}


	add_action( 'admin_footer-plugins.php', 'secupress_add_js_to_delete_bulk_action', 100 );
	/**
	 * Print some JavaScript that will remove bulk actions.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 */
	function secupress_add_js_to_delete_bulk_action() {
		?>
		<script>
			jQuery( 'option[value="delete-selected"]' ).remove();

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
