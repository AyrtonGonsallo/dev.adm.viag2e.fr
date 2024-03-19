<?php
/**
 * Module Name: Detect Bad Plugins
 * Description: Detect if a plugin you're using is known as vulnerable.
 * Main Module: plugins_themes
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'after_plugin_row', 'secupress_detect_bad_plugins_after_plugin_row', 10, 3 );
/**
 * Add a red banner on each "bad" plugin on plugins page
 *
 * @since 1.0
 *
 * @param (string) $plugin_file Path to the plugin file.
 * @param (array)  $plugin_data Plufin data.
 * @param (string) $context     Context.
 */
function secupress_detect_bad_plugins_after_plugin_row( $plugin_file, $plugin_data, $context ) {
	if ( ( is_network_admin() || ! is_multisite() ) && ! current_user_can( 'update_plugins' ) && ! current_user_can( 'delete_plugins' ) && ! current_user_can( 'activate_plugins' ) ) { // Ie. Administrator.
		return;
	}
	$plugins = array(
		'vulns'      => secupress_get_vulnerable_plugins(),
		'removed'    => secupress_get_removed_plugins(),
		'notupdated' => secupress_get_notupdated_plugins(),
	);
	$plugin_name   = dirname( $plugin_file );
	$is_removed    = isset( $plugins['removed'][ $plugin_name ] );
	$is_notupdated = isset( $plugins['notupdated'][ $plugin_name ] );
	$is_vuln       = isset( $plugins['vulns'][ $plugin_name ] );
	$plugin_vuln   = $is_vuln ? $plugins['vulns'][ $plugin_name ] : false;

	if ( ! $is_removed && ! $is_vuln && ! $is_notupdated ) {
		return;
	}
	if ( $is_vuln && $plugin_vuln['fixed_in'] && version_compare( $plugin_data['Version'], $plugin_vuln['fixed_in'] ) >= 0 ) {
		return;
	}

	$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
	$current       = get_site_transient( 'update_plugins' );
	$page          = get_query_var( 'paged' );
	$s             = isset( $_REQUEST['s'] ) ? esc_attr( stripslashes( $_REQUEST['s'] ) ) : '';
	$r             = isset( $current->response[ $plugin_file ] ) ? $current->response[ $plugin_file ] : null;
	// HTML output.
	?>
	<tr class="secupress-bad-plugins">
		<td colspan="<?php echo $wp_list_table->get_column_count(); ?>">
			<div class="error-message notice inline notice-error notice-alt">
			<?php
			printf( '<em>' . sprintf( __( '%s Warning:', 'secupress' ), SECUPRESS_PLUGIN_NAME ) . '</em> ' );

			if ( $is_vuln ) {
				printf(
					_n(
						'<strong>%1$s %2$s</strong> is known to contain this vulnerability: %3$s.',
						'<strong>%1$s %2$s</strong> is known to contain these vulnerabilities: %3$s.',
						1, // To prevent a trad vuln.
						'secupress-pro'
					),
					$plugin_data['Name'],
					$plugin_vuln['fixed_in'] ? sprintf( __( 'version %s (or lower)', 'secupress' ), $plugin_vuln['fixed_in'] ) : __( 'all versions', 'secupress' ),
					'<strong>' . esc_html( $plugin_vuln['flaws'] ) . '</strong>'
				);

				echo ' <a href="' . esc_url( $plugin_vuln['refs'] ) . '" target="_blank">' . __( 'More information', 'secupress' ) . '</a>';

				if ( ! empty( $plugin_vuln['fixed_in'] ) && current_user_can( 'update_plugins' ) ) {
					echo '<p>';

					if ( ! empty( $r->package ) ) {
						echo '<span class="dashicons dashicons-update" aria-hidden="true"></span> ';
						printf(
							__( '%1$s invites you to <a href="%2$s">Update</a> this plugin to version %3$s.', 'secupress' ),
							SECUPRESS_PLUGIN_NAME,
							esc_url( wp_nonce_url( admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin_file, 'upgrade-plugin_' . $plugin_file ) ),
							'<strong>' . esc_html( isset( $r->new_version ) ? $r->new_version : $plugin_vuln['fixed_in'] ) . '</strong>'
						);
					} elseif ( null !== $r ) { // To be tested ////.
						echo '<span class="dashicons dashicons-update" aria-hidden="true"></span> ' . sprintf( __( '%s invites you to Update this plugin <em>(automatic update is unavailable for this plugin.)</em>.', 'secupress' ), SECUPRESS_PLUGIN_NAME );
					} else {
						echo '<p><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . __( 'Update is unavailable for this plugin.', 'secupress' ) . '</p>';
					}

					echo '</p>';
				} else {
					echo '<p>';

					if ( is_plugin_active( $plugin_file ) && current_user_can( 'activate_plugins' ) ) {
						printf(
							'<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span> ' . __( '%s invites you to <a href="%s">deactivate</a> this plugin, then delete it.', 'secupress' ),
							SECUPRESS_PLUGIN_NAME,
							esc_url( wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=' . $plugin_file . '&plugin_status=' . $context . '&paged=' . $page . '&s=' . $s ), 'deactivate-plugin_' . $plugin_file ) )
						);
					}

					if ( ! is_plugin_active( $plugin_file ) && current_user_can( 'delete_plugins' ) ) {
						printf(
							'<span class="dashicons dashicons-trash" aria-hidden="true"></span> ' . __( '%s invites you to <a href="%s">delete</a> this plugin, no patch has been made by its author.', 'secupress' ),
							SECUPRESS_PLUGIN_NAME,
							esc_url( wp_nonce_url( admin_url( 'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s ), 'bulk-plugins' ) )
						);
					}

					echo '</p>';
				}
			} elseif ( $is_notupdated ) {
				// Not updated.
				printf( __( '<strong>%s</strong> has not been updated on official repository for more than 2 years now. It can be dangerous.', 'secupress' ), esc_html( $plugin_data['Name'] ) );
			} else {
				// Removed.
				printf( __( '<strong>%s</strong> has been removed from official repository for one of these reasons: Security Flaw, on Author’s demand, Not GPL compatible, this plugin is under investigation.', 'secupress' ), esc_html( $plugin_data['Name'] ) );
			}

			if ( ! $is_vuln ) {
				echo '<p>';

				if ( is_plugin_active( $plugin_file ) && current_user_can( 'activate_plugins' ) ) {
					printf(
						'<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span> ' . __( '%s invites you to <a href="%s">deactivate</a> this plugin, then delete it.', 'secupress' ),
						SECUPRESS_PLUGIN_NAME,
						esc_url( wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=' . $plugin_file . '&plugin_status=' . $context . '&paged=' . $page . '&s=' . $s ), 'deactivate-plugin_' . $plugin_file ) )
					);
				}

				if ( ! is_plugin_active( $plugin_file ) && current_user_can( 'delete_plugins' ) ) {
					printf(
						'<span class="dashicons dashicons-trash" aria-hidden="true"></span> ' . __( '%s invites you to <a href="%s">delete</a> this plugin, no patch has been made by its author.', 'secupress' ),
						SECUPRESS_PLUGIN_NAME,
						esc_url( wp_nonce_url( admin_url( 'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s ), 'bulk-plugins' ) )
					);
				}

				echo '</p>';
			}
			?>
			</div>
		</td>
	</tr>
	<?php
}


add_action( 'admin_head', 'secupress_detect_bad_plugins_add_notices' );
/**
 * Add a notice if a plugin is considered as "bad"
 *
 * @since 1.0
 */
function secupress_detect_bad_plugins_add_notices() {
	global $pagenow;

	// Don't display the notice yet, next reload.
	if ( false === get_site_transient( 'secupress-detect-bad-plugins' ) || 'plugins.php' === $pagenow || ( is_network_admin() || ! is_multisite() ) && ! current_user_can( secupress_get_capability() ) ) {
		return;
	}

	$plugins = array(
		'vulns'      => secupress_get_vulnerable_plugins(),
		'removed'    => secupress_get_removed_plugins(),
		'notupdated' => secupress_get_notupdated_plugins(),
	);

	if ( $plugins['vulns'] || $plugins['removed'] || $plugins['notupdated'] ) {
		$counter = count( $plugins['vulns'] ) + count( $plugins['removed'] ) + count( $plugins['notupdated'] );
		$url     = esc_url( admin_url( 'plugins.php' ) );
		$message = sprintf(
			_n(
				'Your installation contains %1$s plugin considered as <em>bad</em>, check the details in <a href="%2$s">the plugins page</a>.',
				'Your installation contains %1$s plugins considered as <em>bad</em>, check the details in <a href="%2$s">the plugins page</a>.',
				$counter,
				'secupress-pro'
			),
			'<strong>' . $counter . '</strong>',
			$url
		);
		secupress_add_notice( $message, 'error', 'bad-plugins' );
	}
}

add_action( 'secupress.pro.plugins.activation',                                     'secupress_bad_plugins_activation' );
add_action( 'secupress.modules.activate_submodule_' . basename( __FILE__, '.php' ), 'secupress_bad_plugins_activation' );
/**
 * Initiate the cron that will check for vulnerable plugins twice-daily.
 *
 * @since 2.1
 * @author Julio Potier
 */
function secupress_bad_plugins_activation() {
	if ( ! wp_next_scheduled( 'secupress_bad_plugins' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'secupress_bad_plugins' );
		wp_schedule_single_event( time()+5, 'secupress_bad_plugins' );
	}
}

add_action( 'secupress_bad_plugins', 'secupress_detect_bad_plugins_async_get_and_store_infos' );
add_action( 'admin_post_secupress_bad_plugins_update_data', 'secupress_detect_bad_plugins_async_get_and_store_infos' );
/**
 * Once a day, launch an async call to refresh the vulnerable plugins.
 * Moved from Pro to Free + renamed. Originally `secupress_detect_bad_plugins_async_get_infos()`.
 *
 * @since 2.1 Moved from /core/admin/admin.php, old hook "admin_footer" via admin-post using AJAX
 * @since 1.1.3
 */
function secupress_detect_bad_plugins_async_get_and_store_infos() {
	if ( ! defined( 'DOING_CRON' ) && ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], $_GET['action'] ) ) ) {
		wp_nonce_ays( '' );
	}
	if ( ! function_exists ( 'get_plugins' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}
	$plugins  = get_plugins();
	$plugins  = wp_list_pluck( $plugins, 'Version' );
	$nonce    = md5( serialize( $plugins ) );
	$args     = array( 'body' => array( 'items' => $plugins, 'type' => 'plugin', '_wpnonce' => $nonce ), 'headers' => [ 'X-Secupress-Key' => secupress_get_consumer_key() ] );

	$response = wp_remote_post( SECUPRESS_WEB_MAIN . 'api/plugin/vulns.php', $args );

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$response = wp_remote_retrieve_body( $response );

		// Store the result only if it's not an error (not -1, -2, -3, or -99).
		if ( (int) $response >= 0 ) {
			update_site_option( 'secupress_bad_plugins', $response, false );
			$dt = get_date_from_gmt( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), time() ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			secupress_set_option( 'bad_plugins_last_update', date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dt ) ) );
		}
	}
	if ( ! defined( 'DOING_CRON' ) ) {
		wp_safe_redirect( wp_get_referer() );
		die();
	}
}

add_action( 'secupress.pro.plugins.deactivation',                                     'secupress_bad_plugins_deactivation' );
add_action( 'secupress.modules.deactivate_submodule_' . basename( __FILE__, '.php' ), 'secupress_bad_plugins_deactivation' );
/**
 * Remove the crons.
 *
 * @since 2.1
 * @author Julio Potier
 */
function secupress_bad_plugins_deactivation() {
	wp_clear_scheduled_hook( 'secupress_bad_plugins' );
}

