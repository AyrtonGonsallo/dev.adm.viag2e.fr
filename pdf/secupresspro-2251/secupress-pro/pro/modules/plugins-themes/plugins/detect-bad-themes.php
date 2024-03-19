<?php
/**
 * Module Name: Detect Bad Themes
 * Description: Detect if a theme you're using is known as vulnerable
 * Main Module: plugins_themes
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_action( 'admin_footer-themes.php', 'secupress_detect_bad_themes_after_theme_row' );
/**
 * Add a red banner on each "bad" theme on themes page
 *
 * @return void
 * @since 1.0
 */
function secupress_detect_bad_themes_after_theme_row() {
	if ( ( is_network_admin() || ! is_multisite() ) && ! current_user_can( 'update_themes' ) && ! current_user_can( 'delete_themes' ) && ! current_user_can( 'switch_themes' ) ) { // ie. Administrator.
		return;
	}

	$themes     = array( 'vulns' => secupress_get_vulnerable_themes() );
	$all_themes = wp_get_themes();

	foreach ( $all_themes as $theme_name => $theme_data ) {
		$is_vuln    = isset( $themes['vulns'][ $theme_name ] );
		$theme_vuln = $is_vuln ? $themes['vulns'][ $theme_name ] : false;

		if ( ! $is_vuln ) {
			return; // `continue;`? ////
		}

		if ( $is_vuln && version_compare( $theme_data['Version'], $theme_vuln->fixed_in ) === 1 && '' !== $theme_vuln->fixed_in ) {
			return; // `continue;`? ////
		}

		$current_theme = wp_get_theme();
		$current       = get_site_transient( 'update_themes' );
		$r             = isset( $current->response[ $theme_name ] ) ? (object) $current->response[ $theme_name ] : null;

		// HTML output.
		if ( $is_vuln ) {
			$theme_vuln->flaws = unserialize( $theme_vuln->flaws );

			echo '<div class="theme-update secupress-bad-theme" data-theme="' . esc_attr( $theme_name ) . '">';

			printf(
				_n(
					'<strong>%1$s %2$s</strong> is known to contain this vulnerability: %3$s.',
					'<strong>%1$s %2$s</strong> is known to contain these vulnerabilities: %3$s.',
					count( $theme_vuln->flaws ),
					'secupress-pro'
				),
				$theme_data['Name'],
				'' !== $theme_vuln->fixed_in ? sprintf( __( 'version %s (or lower)', 'secupress' ), $theme_vuln->fixed_in ) : __( 'all versions', 'secupress' ),
				'<strong>' . wp_sprintf( '%l', $theme_vuln->flaws ) . '</strong>'
			);

			echo '<br><a href="' . esc_url( $theme_vuln->refs ) . '" target="_blank">' . __( 'More information', 'secupress' ) . '</a>';

			if ( $theme_vuln->fixed_in && current_user_can( 'update_themes' ) ) {
				echo '<p>';

				if ( ! empty( $r->package ) ) {
					printf(
						'<span class="dashicons dashicons-update"></span> ' . __( 'We invite you to <a href="%1$s">Update</a> this theme in version %2$s.', 'secupress' ),
						wp_nonce_url( admin_url( 'update.php?action=upgrade-theme&theme=' ) . $theme_name, 'upgrade-theme_' . $theme_name ),
						'<strong>' . esc_html( isset( $r->new_version ) ? $r->new_version : $theme_vuln->fixed_in ) . '</strong>'
					);
				} else {
					'<span class="dashicons dashicons-update"></span> ' . __( 'We invite you to Update this theme <em>(Automatic update is unavailable for this theme.)</em>.', 'secupress' );
				}

				echo '</p>';

				if ( $theme_name === $current_theme->stylesheet || $theme_name === $current_theme->template ) {
					echo '<span class="dashicons dashicons-admin-appearance"></span> ' . __( 'We invite you to switch theme, then delete it.', 'secupress' );
				} else {
					$delete_url = wp_nonce_url( admin_url( 'themes.php?action=delete&stylesheet=' . $theme_name ), 'delete-theme_' . $theme_name );
					printf( '<span class="dashicons dashicons-admin-appearance"></span> ' . __( 'We invite you to <a href="%s">delete it</a>.', 'secupress' ), $delete_url );
				}
			}

			echo '</div>';
		}
		?>
		</td>
	</tr>
	<?php
	}
}


add_action( 'admin_head', 'secupress_detect_bad_themes_add_notices' );
/**
 * Add a notice if a theme is considered as "bad".
 *
 * @return void
 * @since 1.0
 */
function secupress_detect_bad_themes_add_notices() {
	global $pagenow;

	// don't display the notice yet, next reload.
	if ( false === get_site_transient( 'secupress-detect-bad-themes' ) || 'themes.php' === $pagenow ||
	( is_network_admin() || ! is_multisite() ) && ! current_user_can( 'update_plugins' ) && ! current_user_can( 'delete_plugins' ) && ! current_user_can( 'activate_plugins' ) ) { // ie. Administrator.
		return;
	}

	$themes = array( 'vulns' => secupress_get_vulnerable_themes() );

	if ( ! $themes['vulns'] ) {
		return;
	}

	$counter = count( $themes['vulns'] );
	$url     = admin_url( 'themes.php' );
	$message = sprintf(
		_n(
			'Your installation contains %1$s theme considered as <em>bad</em>, check the details in <a href="%2$s">the themes page</a>.',
			'Your installation contains %1$s themes considered as <em>bad</em>, check the details in <a href="%2$s">the themes page</a>.',
			$counter,
			'secupress-pro'
		),
		'<strong>' . $counter . '</strong>',
		$url
	);
	secupress_add_notice( $message, 'error', 'bad-themes' );
}

add_action( 'secupress.pro.plugins.activation',                                     'secupress_bad_themes_activation' );
add_action( 'secupress.modules.activate_submodule_' . basename( __FILE__, '.php' ), 'secupress_bad_themes_activation' );
/**
 * Initiate the cron that will check for vulnerable themes twice-daily.
 *
 * @since 2.1
 * @author Julio Potier
 */
function secupress_bad_themes_activation() {
	if ( ! wp_next_scheduled( 'secupress_bad_themes' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'secupress_bad_themes' );
		wp_schedule_single_event( time()+5, 'secupress_bad_themes' );
	}
}


add_action( 'secupress_bad_themes', 'secupress_detect_bad_themes_async_get_and_store_infos' );
add_action( 'admin_post_secupress_bad_themes_update_data', 'secupress_detect_bad_themes_async_get_and_store_infos' );
/**
 * Once a day, launch an async call to refresh the vulnerable themes.
 * Moved from Pro to Free + renamed. Originally `secupress_detect_bad_themes_async_get_infos()`.
 *
 * @since 2.1 Moved from /core/admin/admin.php, old hook "admin_footer" via admin-post using AJAX
 * @since 1.1.3
 */
function secupress_detect_bad_themes_async_get_and_store_infos() {
	if ( ! defined( 'DOING_CRON' ) && ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], $_GET['action'] ) ) ) {
		wp_nonce_ays( '' );
	}
	if ( ! function_exists ( 'wp_get_theme' ) ) {
		require_once( ABSPATH . '/wp-includes/theme.php' );
	}
	$themes   = wp_get_themes();
	$themes   = wp_list_pluck( $themes, 'Version' );
	$nonce    = md5( serialize( $themes ) );
	$args     = array( 'body' => array( 'items' => $themes, 'type' => 'theme', '_wpnonce' => $nonce ), 'headers' => [ 'X-Secupress-Key' => secupress_get_consumer_key() ] );

	$response = wp_remote_post( SECUPRESS_WEB_MAIN . 'api/plugin/vulns.php', $args );

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$response = wp_remote_retrieve_body( $response );

		// Store the result only if it's not an error (not -1, -2, -3, or -99).
		if ( (int) $response >= 0 ) {
			update_site_option( 'secupress_bad_themes', $response, false );
			$dt = get_date_from_gmt( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), time() ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			secupress_set_option( 'bad_themes_last_update', date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dt ) ) );
		}
	}
	if ( ! defined( 'DOING_CRON' ) ) {
		wp_safe_redirect( wp_get_referer() );
		die();
	}
}

add_action( 'secupress.pro.plugins.deactivation',                                     'secupress_bad_themes_deactivation' );
add_action( 'secupress.modules.deactivate_submodule_' . basename( __FILE__, '.php' ), 'secupress_bad_themes_deactivation' );
/**
 * Remove the crons.
 *
 * @since 2.1
 * @author Julio Potier
 */
function secupress_bad_themes_deactivation() {
	wp_clear_scheduled_hook( 'secupress_bad_themes' );
}