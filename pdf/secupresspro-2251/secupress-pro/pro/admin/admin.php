<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** MAIN OPTION ================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

add_filter( 'all_plugins', 'secupress_pro_white_label' );
/**
 * White Label the plugin, if you need to
 *
 * @since 1.0
 *
 * @param (array) $all_plugins An array of plugins to display in the list table.
 *
 * @return (array)
 */
function secupress_pro_white_label( $all_plugins ) {
	if ( ! secupress_is_white_label() ) {
		return $all_plugins;
	}

	// We change the plugin's header.
	$plugin = plugin_basename( SECUPRESS_FILE );

	if ( isset( $all_plugins[ $plugin ] ) && is_array( $all_plugins[ $plugin ] ) ) {
		$all_plugins[ $plugin ] = array_merge( $all_plugins[ $plugin ], array(
			// Escape is done in `_get_plugin_data_markup_translate()`.
			'Name'        => secupress_get_option( 'wl_plugin_name' ),
			'PluginURI'   => secupress_get_option( 'wl_plugin_URI' ),
			'Description' => secupress_get_option( 'wl_description' ),
			'Author'      => secupress_get_option( 'wl_author' ),
			'AuthorURI'   => secupress_get_option( 'wl_author_URI' ),
		) );
	}

	return $all_plugins;
}

// Remove the sidebar+ads when whitelabel is activated.
add_filter( 'secupress.no_sidebar', 'secupress_is_white_label' );


add_action( 'wp_head', 'secupress_pro_seo_management', 1000 );
//
function secupress_pro_seo_management() {
	if ( secupress_is_pro() && 32 !== strlen( secupress_get_consumer_key() ) ) {
		if ( function_exists( 'wp_robots_no_robots' ) ) {
			add_filter( 'wp_robots', 'wp_robots_no_index' );
			wp_robots();
		} else {
			wp_no_robots();
		}
	}
}
