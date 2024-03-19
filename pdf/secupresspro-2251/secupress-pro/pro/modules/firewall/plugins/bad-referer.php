<?php
/**
 * Module Name: Block Bad Referers
 * Description: If you don't want that some website can link to yours, use this plugin.
 * Main Module: firewall
 * Author: SecuPress
 * Version: 2.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'init', 'secupress_pro_check_and_block_refs' );
/**
 * Block bad referers
 *
 * @return void
 * @author Julio Potier
 * @since 2.0
 **/
function secupress_pro_check_and_block_refs() {
	if ( isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$referers = array_filter( explode( ',', secupress_get_module_option( 'bbq-headers_bad-referer-list', '', 'firewall' ) ) );
		if ( empty( $referers ) ) {
			return;
		}
		foreach ( $referers as $ref ) {
			if ( 0 === strpos( $_SERVER['HTTP_REFERER'], trim( $ref ) ) ) {
				secupress_block( 'BRU', [ 'code' => 400, 'b64' => [ 'data' => esc_html( $_SERVER['HTTP_REFERER'] ) ] ] );
			}
		}
	}
}
