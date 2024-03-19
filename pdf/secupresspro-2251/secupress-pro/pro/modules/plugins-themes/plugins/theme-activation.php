<?php
/**
 * Module Name: No Theme Switch
 * Description: Disabled the theme switch
 * Main Module: plugins_themes
 * Author: SecuPress
 * Version: 1.1
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

if ( is_admin() ) {
	add_action( 'check_admin_referer', 'secupress_avoid_install_theme' );
	/**
	 * Prevent theme activation.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 *
	 * @param (string) $action The action.
	 */
	function secupress_avoid_install_theme( $action ) {
		if ( strpos( $action, 'switch-theme_' ) === 0 ) {
			secupress_die( __( 'You do not have sufficient permissions to switch themes on this site.', 'secupress' ), '', array( 'force_die' => true ) );
		}
	}


	add_action( 'admin_footer-themes.php', 'secupress_add_css_to_active_button', 100 );
	/**
	 * Print some CSS that will hide Activation buttons.
	 *
	 * @since 1.0
	 * @author Julio Potier
	 */
	function secupress_add_css_to_active_button() {
		?>
		<style>.inactive-theme .activate, .theme-actions .activate{display:none!important;}</style>
		<?php
	}
}
