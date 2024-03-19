<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Get available alert types.
 *
 * @since 1.0
 *
 * @return (array) Return an array with identifiers as keys and field labels as values.
 */
function secupress_alert_types_labels() {

	return array(
		/* Active now */
			// 1.0
		'email'   => __( 'Email', 'secupress' ),
			// 2.0
		'slack'   => 'Slack',
		/* Not active yet */
		// 'twitter' => 'Twitter',
		// 'sms'     => 'SMS',
		// 'push'    => __( 'Push notification', 'secupress' ),
	);
}


/**
 * Send a notification validation
 *
 * @since 2.0
 * @author Julio Potier
 *
 * @see https://app.slack.com/block-kit-builder/
 *
 * @param (string) $type emails, sms, push, slack or custom, see secupress.notification.type
 * @param (array) $args Custom args
 * @return
 **/
function secupress_send_notification_validation( $type, $args = false ) {
	$current_user = wp_get_current_user();

	switch( $type ) {
		case 'emails':
			//
		break;
		case 'sms':
			//
		break;
		case 'push':
			//
		break;
		case 'slack':
			// Do not use secupress_maybe_reset_slack_notifs() here because the URL is not valid yet, this is the validation call.
			$url      = isset( $args['url'] ) ? $args['url'] : false;
			if ( ! $url ) {
				secupress_set_option( 'notification-types_slack', 0 );
				break;
			}
			$accepted = secupress_get_option( 'notification-types_slack', false );
			if ( $accepted || $url === $accepted ) {
				break;
			}
			secupress_set_option( 'notification-types_slack', 0 );

			$payload  = (object) array(
			   'attachments' =>
			  array (
			    0 =>
			    (object) array(
			       'color' => '#2BCDC1',
			       'blocks' =>
			      array (
			        0 =>
			        (object) array(
			           'type' => 'header',
			           'text' =>
			          (object) array(
			             'type' => 'plain_text',
			             'text' => sprintf( __( '%s Notifications', 'secupress' ), SECUPRESS_PLUGIN_NAME ),
			             'emoji' => true,
			          ),
			        ),
			        1 =>
			        (object) array(
			           'type' => 'section',
			           'text' =>
			          (object) array(
			             'type' => 'mrkdwn',
			             'text' => sprintf( __( '*%1$s* from %2$s requested to send the _SecuPress Notifications_ on this channel.', 'secupress' ), $current_user->display_name, home_url() )
			          ),
			           'block_id' => 'textInfo',
			        ),
			        2 =>
			        (object) array(
			           'type' => 'section',
			           'text' =>
			          (object) array(
			             'type' => 'mrkdwn',
			             'text' => "—\n" . __( 'Click *Accept*, or *ignore* this message.', 'secupress' ),
			          ),
			           'accessory' =>
			          (object) array(
			             'type' => 'image',
			             'image_url' => 'https://pbs.twimg.com/profile_images/737217340950077440/2Q06P22n_400x400.jpg',
			             'alt_text' => 'SecuPress Logo',
			          ),
			        ),
			        3 =>
			        (object) array(
			           'type' => 'section',
			           'text' =>
			          (object) array(
			             'type' => 'mrkdwn',
			             'text' => sprintf( __( 'Only *%s* can accept this request.', 'secupress' ), $current_user->display_name ),
			          ),
			           'accessory' =>
			          (object) array(
			             'type' => 'button',
						 'style' => 'primary',
			             'text' =>
			            (object) array(
			               'type' => 'plain_text',
			               'text' => '✅ ' . __( 'Accept', 'secupress' ),
			               'emoji' => true,
			            ),
			             'value' => 'acceptNotif',
			             'url' => str_replace( '&amp;', '&', wp_nonce_url( admin_url( 'admin-post.php?action=secupress_accept_notification&type=' . $type ), 'secupress_accept_notification-type-' . $type ) ),
			             'action_id' => 'button-action',
			          ),
		            ),
			      ),
			    ),
			  ),
			);
			secupress_send_slack_notification( $payload, true ); // true to bypass acceptation because it's not already accepted but we need to!

		break;
		case 'twitter':
			//
		break;
		default:
			/**
			* Manage the possible new filtered notification type
			* @see 'secupress.notifications.types'
			* @since 2.0
			* @author Julio Potier
			*/
			do_action( 'secupress.notification.type.' . $type );
		break;
	}
	/**
	* Manage the possible new filtered notification type
	* @since 2.0
	* @author Julio Potier
	*
	* @param (string) $type
	*/
	do_action( 'secupress.notification.type', $type );
}

/**
 * Send a Slack notification if the URL is correct
 *
 * @since 2.0
 * @author Julio Potier
 *
 * @param (string|array) $message Can be a Slack Bloc Kit or a simple string
 * @param (bool|string) $bypass True or url will bypass the accepted flag
 **/
function secupress_send_slack_notification( $message, $bypass = false ) {
	/**
	* Can force notifications to be sent without acceptation
	*
	* @author Julio Potier
	* @since 2.0
	*/
	$bypass = apply_filters( 'secupress.notifications.slack.bypass', $bypass );
	$url    = secupress_get_module_option( 'notification-types_slack', false, 'alerts' );
	if ( ! $url && ! $bypass ) {
		secupress_set_option( 'notification-types_slack', 0 );
		return;
	}
	$accepted = secupress_get_option( 'notification-types_slack', false );
	if ( ! $bypass && ( ! $accepted || $url !== $accepted ) ) {
		return;
	}
	if ( ! $url && is_bool( $bypass ) ) {
		return;
	}
	if ( ! $url && true === filter_var( $bypass, FILTER_VALIDATE_URL) ) {
		$url = $bypass;
	}
	$message = is_string( $message ) ? [ 'text' => $message ] : $message;
	$args    = [
				'headers' =>
				[
					'Content-type' => 'application/json',
					'blocking'     => false,
					'timeout'      => 0.01
				],
				'body' => json_encode( $message )
			];

	wp_remote_post( $url, $args );
}
