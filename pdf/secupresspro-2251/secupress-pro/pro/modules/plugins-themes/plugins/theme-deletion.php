<?php
/**
 * Module Name: No Theme Deletion
 * Description: Disabled the theme deletion
 * Main Module: plugins_themes
 * Author: SecuPress
 * Version: 1.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

if ( is_admin() ) {
	add_action( 'check_admin_referer', 'secupress_avoid_delete_theme' );
	/**
	 * Prevent theme deletion.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 *
	 * @param (string) $action The action.
	 */
	function secupress_avoid_delete_theme( $action ) {
		if ( strpos( $action, 'delete-theme_' ) === 0 ) {
			secupress_die( __( 'You do not have sufficient permissions to delete plugins on this site.', 'secupress' ), '', array( 'force_die' => true ) );
		}
	}


	add_action( 'admin_footer-themes.php', 'secupress_add_css_to_delete_button', 100 );
	/**
	 * Print some CSS that will hide Deletion buttons.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 */
	function secupress_add_css_to_delete_button() {
		?>
		<style>a.delete-theme{display:none!important;}</style>
		<?php
	}
}
