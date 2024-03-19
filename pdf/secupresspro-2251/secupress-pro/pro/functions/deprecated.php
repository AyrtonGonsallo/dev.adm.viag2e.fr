<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Filter the `active_sitewide_plugins` network option to deactivate the Pro plugin when the Free one is deactivated.
 *
 * @since 1.0
 * @since 1.3 Deprecated.
 * @author Grégory Viguier
 *
 * @param (array) $value     New value of the network option.
 * @param (array) $old_value Old value of the network option.
 *
 * @return (array)
 */
function secupress_deactivate_secupress_pro_sitewide( $value, $old_value ) {
	static $free_plugin;
	static $pro_plugin;

	_deprecated_function( __FUNCTION__, '1.3' );

	if ( $value === $old_value || ! defined( 'SECUPRESS_FILE' ) ) {
		return $value;
	}

	if ( ! isset( $free_plugin ) ) {
		$free_plugin = plugin_basename( SECUPRESS_FILE );
	}

	if ( isset( $value[ $free_plugin ] ) ) {
		// The Free plugin is in the new list, so it is not being deactivated.
		return $value;
	}

	// Remove the Pro plugin.
	if ( ! isset( $pro_plugin ) ) {
		$pro_plugin = plugin_basename( SECUPRESS_PRO_FILE );
	}

	if ( isset( $value[ $pro_plugin ] ) ) {
		unset( $value[ $pro_plugin ] );
		secupress_trigger_plugin_deactivation_hooks( $pro_plugin );
	}

	return $value;
}


/**
 * Filter the `active_plugins` option to deactivate the Pro plugin when the Free one is deactivated.
 *
 * @since 1.0
 * @since 1.3 Deprecated.
 * @author Grégory Viguier
 *
 * @param (array) $value     New value of the option.
 * @param (array) $old_value Old value of the option.
 *
 * @return (array)
 */
function secupress_deactivate_secupress_pro( $value, $old_value ) {
	static $free_plugin;
	static $pro_plugin;

	_deprecated_function( __FUNCTION__, '1.3' );

	if ( $value === $old_value || ! defined( 'SECUPRESS_FILE' ) ) {
		return $value;
	}

	if ( ! isset( $free_plugin ) ) {
		$free_plugin = plugin_basename( SECUPRESS_FILE );
	}

	$value = array_flip( $value );

	if ( isset( $value[ $free_plugin ] ) ) {
		// The Free plugin is in the new list, so it is not being deactivated.
		return array_flip( $value );
	}

	// Remove the Pro plugin.
	if ( ! isset( $pro_plugin ) ) {
		$pro_plugin = plugin_basename( SECUPRESS_PRO_FILE );
	}

	if ( ! isset( $value[ $pro_plugin ] ) ) {
		// The Pro plugin is not in the new list, it has already been removed.
		return array_flip( $value );
	}

	if ( isset( $value[ $pro_plugin ] ) ) {
		unset( $value[ $pro_plugin ] );
		secupress_trigger_plugin_deactivation_hooks( $pro_plugin );
	}

	return array_values( array_flip( $value ) );
}


/**
 * Manually trigger deactivation hooks for the given plugin.
 *
 * @since 1.0
 * @since 1.3 Deprecated.
 * @author Grégory Viguier
 *
 * @param (string) $plugin Plugin base name.
 */
function secupress_trigger_plugin_deactivation_hooks( $plugin ) {
	_deprecated_function( __FUNCTION__, '1.3' );

	if ( ! is_plugin_active( $plugin ) ) {
		return;
	}

	$network_deactivating = is_plugin_active_for_network( $plugin );

	/**
	 * Fires before a plugin is deactivated.
	 *
	 * If a plugin is silently deactivated (such as during an update),
	 * this hook does not fire.
	 *
	 * @since 1.0
	 *
	 * @param (string) $plugin               Plugin path to main plugin file with plugin data.
	 * @param (bool)   $network_deactivating Whether the plugin is deactivated for all sites in the network or just the current site. Multisite only. Default is false.
	 */
	do_action( 'deactivate_plugin', $plugin, $network_deactivating );

	/**
	 * Fires as a specific plugin is being deactivated.
	 *
	 * This hook is the "deactivation" hook used internally by register_deactivation_hook().
	 * The dynamic portion of the hook name, `$plugin`, refers to the plugin basename.
	 *
	 * If a plugin is silently deactivated (such as during an update), this hook does not fire.
	 *
	 * @since 1.0
	 *
	 * @param (bool) $network_deactivating Whether the plugin is deactivated for all sites in the network or just the current site. Multisite only. Default is false.
	 */
	do_action( "deactivate_{$plugin}", $network_deactivating );

	/**
	 * Fires after a plugin is deactivated.
	 *
	 * If a plugin is silently deactivated (such as during an update),
	 * this hook does not fire.
	 *
	 * @since 1.0
	 *
	 * @param (string) $plugin               Plugin basename.
	 * @param (bool)   $network_deactivating Whether the plugin is deactivated for all sites in the network or just the current site. Multisite only. Default false.
	 */
	do_action( 'deactivated_plugin', $plugin, $network_deactivating );
}
