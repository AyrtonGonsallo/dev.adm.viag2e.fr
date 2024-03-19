<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


/**
 * Content of the settings field for the non login time slot.
 *
 * @since 1.0
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_non_login_time_slot_field( $args, $instance ) {
	$name_attribute = 'secupress_' . $instance->get_current_module() . '_settings[' . $args['name'] . ']';

	// Value.
	if ( isset( $args['value'] ) ) {
		$value = $args['value'];
	} else {
		$value = secupress_get_module_option( $args['name'] );
	}

	if ( is_null( $value ) ) {
		$value = $args['default'];
	}

	$from_hour   = isset( $value['from_hour'] )   ? (int) $value['from_hour']   : 0;
	$from_minute = isset( $value['from_minute'] ) ? (int) $value['from_minute'] : 0;
	$to_hour     = isset( $value['to_hour'] )     ? (int) $value['to_hour']     : 0;
	$to_minute   = isset( $value['to_minute'] )   ? (int) $value['to_minute']   : 0;

	// Attributes.
	$attributes = ' type="text" class="small-text" size="2" maxlength="2" autocomplete="off"';
	if ( ! empty( $args['attributes'] ) ) {
		foreach ( $args['attributes'] as $attribute => $attribute_value ) {
			$attributes .= ' ' . $attribute . '="' . esc_attr( $attribute_value ) . '"';
		}
	}

	echo $args['label'] ? '<p id="' . $args['name'] . '-time-slot-label">' . $args['label'] . '</p>' : '';
	?>
	<fieldset aria-labelledby="<?php echo $args['name']; ?>-time-slot-label">
		<legend class="screen-reader-text"><?php _e( 'Start hour and minute', 'secupress' ); ?></legend>
		<label>
			<span class="label-before" aria-hidden="true"><?php _ex( 'From', 'starting hour + minute', 'secupress' ); ?></span>
			<span class="screen-reader-text"><?php _e( 'Hour' ); ?></span>
			<input id="<?php echo $args['name']; ?>_from_hour" name="<?php echo $name_attribute; ?>[from_hour]" value="<?php echo str_pad( $from_hour, 2, 0, STR_PAD_LEFT ); ?>" pattern="0?[0-9]|1[0-9]|2[0-3]"<?php echo $attributes; ?>>
			<span aria-hidden="true"><?php _ex( 'h', 'hour', 'secupress' ); ?></span>
		</label>
		<label>
			<span class="screen-reader-text"><?php _e( 'Minute' ); ?></span>
			<input id="<?php echo $args['name']; ?>_from_minute" name="<?php echo $name_attribute; ?>[from_minute]" value="<?php echo str_pad( $from_minute, 2, 0, STR_PAD_LEFT ); ?>" pattern="0?[0-9]|[1-5][0-9]"<?php echo $attributes; ?>>
			<span aria-hidden="true"><?php _ex( 'min', 'minute', 'secupress' ); ?></span>
		</label>
	</fieldset>

	<fieldset aria-labelledby="<?php echo $args['name']; ?>-time-slot-label">
		<legend class="screen-reader-text"><?php _e( 'End hour and minute', 'secupress' ); ?></legend>
		<label>
			<span class="label-before" aria-hidden="true"><?php _ex( 'To', 'ending hour + minute', 'secupress' ) ?></span>
			<span class="screen-reader-text"><?php _e( 'Hour' ); ?></span>
			<input id="<?php echo $args['name']; ?>_to_hour" name="<?php echo $name_attribute; ?>[to_hour]" value="<?php echo str_pad( $to_hour, 2, 0, STR_PAD_LEFT ); ?>" pattern="0?[0-9]|1[0-9]|2[0-3]"<?php echo $attributes; ?>>
			<span aria-hidden="true"><?php _ex( 'h', 'hour', 'secupress' ); ?></span>
		</label>
		<label>
			<span class="screen-reader-text"><?php _e( 'Minute' ); ?></span>
			<input id="<?php echo $args['name']; ?>_to_minute" name="<?php echo $name_attribute; ?>[to_minute]" value="<?php echo str_pad( $to_minute, 2, 0, STR_PAD_LEFT ); ?>" pattern="0?[0-9]|[1-5][0-9]"<?php echo $attributes; ?>>
			<span aria-hidden="true"><?php _ex( 'min', 'minute', 'secupress' ); ?></span>
		</label>
	</fieldset>
	<?php
}


/**
 * Content of the settings field for the countries management.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_countries_field( $args, $instance ) {
	$name_attribute = 'secupress_' . $instance->get_current_module() . '_settings[' . $args['name'] . ']';

	// Value.
	if ( isset( $args['value'] ) ) {
		$value = $args['value'];
	} else {
		$value = secupress_get_module_option( $args['name'] );
	}

	if ( is_null( $value ) ) {
		$value = $args['default'];
	}
	$value = array_flip( array_filter( (array) $value ) );

	// Attributes.
	$attributes = '';
	if ( ! empty( $args['attributes'] ) ) {
		foreach ( $args['attributes'] as $attribute => $attribute_value ) {
			$attributes .= ' ' . $attribute . '="' . esc_attr( $attribute_value ) . '"';
		}
	}
	$disabled_class = ! empty( $args['attributes']['disabled'] ) ? ' disabled' : '';
	$disabled_attr  = $disabled_class ? ' class="disabled"' : '';
	$_countries     = array( 'AF' => array( 0 => 'Africa', 'AO' => 'Angola', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'BJ' => 'Benin', 'BW' => 'Botswana', 'CD' => 'Congo, The Democratic Republic of the', 'CF' => 'Central African Republic', 'CG' => 'Congo', 'CI' => 'Cote D’Ivoire', 'CM' => 'Cameroon', 'CV' => 'Cape Verde', 'DJ' => 'Djibouti', 'DZ' => 'Algeria', 'EG' => 'Egypt', 'EH' => 'Western Sahara', 'ER' => 'Eritrea', 'ET' => 'Ethiopia', 'GA' => 'Gabon', 'GH' => 'Ghana', 'GM' => 'Gambia', 'GN' => 'Guinea', 'GQ' => 'Equatorial Guinea', 'GW' => 'Guinea-Bissau', 'KE' => 'Kenya', 'KM' => 'Comoros', 'LR' => 'Liberia', 'LS' => 'Lesotho', 'LY' => 'Libya', 'MA' => 'Morocco', 'MG' => 'Madagascar', 'ML' => 'Mali', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MW' => 'Malawi', 'MZ' => 'Mozambique', 'NA' => 'Namibia', 'NE' => 'Niger', 'NG' => 'Nigeria', 'RE' => 'Reunion', 'RW' => 'Rwanda', 'SC' => 'Seychelles', 'SD' => 'Sudan', 'SH' => 'Saint Helena', 'SL' => 'Sierra Leone', 'SN' => 'Senegal', 'SO' => 'Somalia', 'ST' => 'Sao Tome and Principe', 'SZ' => 'Swaziland', 'TD' => 'Chad', 'TG' => 'Togo', 'TN' => 'Tunisia', 'TZ' => 'Tanzania, United Republic of', 'UG' => 'Uganda', 'YT' => 'Mayotte', 'ZA' => 'South Africa', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe', 'SS' => 'South Sudan' ), 'AN' => array( 0 => 'Antarctica', 'AQ' => 'Antarctica', 'BV' => 'Bouvet Island', 'GS' => 'South Georgia and the South Sandwich Islands', 'HM' => 'Heard Island and McDonald Islands', 'TF' => 'French Southern Territories' ), 'AS' => array( 0 => 'Asia', 'AP' => 'Asia/Pacific Region', 'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AM' => 'Armenia', 'AZ' => 'Azerbaijan', 'BD' => 'Bangladesh', 'BH' => 'Bahrain', 'BN' => 'Brunei Darussalam', 'BT' => 'Bhutan', 'CC' => 'Cocos (Keeling) Islands', 'CN' => 'China', 'CX' => 'Christmas Island', 'CY' => 'Cyprus', 'GE' => 'Georgia', 'HK' => 'Hong Kong', 'ID' => 'Indonesia', 'IL' => 'Israel', 'IN' => 'India', 'IO' => 'British Indian Ocean Territory', 'IQ' => 'Iraq', 'IR' => 'Iran, Islamic Republic of', 'JO' => 'Jordan', 'JP' => 'Japan', 'KG' => 'Kyrgyzstan', 'KH' => 'Cambodia', 'KP' => 'Korea, Democratic People’s Republic of', 'KR' => 'Korea, Republic of', 'KW' => 'Kuwait', 'KZ' => 'Kazakhstan', 'LA' => 'Lao People’s Democratic Republic', 'LB' => 'Lebanon', 'LK' => 'Sri Lanka', 'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MO' => 'Macau', 'MV' => 'Maldives', 'MY' => 'Malaysia', 'NP' => 'Nepal', 'OM' => 'Oman', 'PH' => 'Philippines', 'PK' => 'Pakistan', 'PS' => 'Palestinian Territory', 'QA' => 'Qatar', 'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'SY' => 'Syrian Arab Republic', 'TH' => 'Thailand', 'TJ' => 'Tajikistan', 'TM' => 'Turkmenistan', 'TL' => 'Timor-Leste', 'TW' => 'Taiwan', 'UZ' => 'Uzbekistan', 'VN' => 'Vietnam', 'YE' => 'Yemen' ), 'EU' => array( 0 => 'Europe', 'AD' => 'Andorra', 'AL' => 'Albania', 'AT' => 'Austria', 'BA' => 'Bosnia and Herzegovina', 'BE' => 'Belgium', 'BG' => 'Bulgaria', 'BY' => 'Belarus', 'CH' => 'Switzerland', 'CZ' => 'Czech Republic', 'DE' => 'Germany', 'DK' => 'Denmark', 'EE' => 'Estonia', 'ES' => 'Spain', 'FI' => 'Finland', 'FO' => 'Faroe Islands', 'FR' => 'France', 'GB' => 'United Kingdom', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'HR' => 'Croatia', 'HU' => 'Hungary', 'IE' => 'Ireland', 'IS' => 'Iceland', 'IT' => 'Italy', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'MC' => 'Monaco', 'MD' => 'Moldova, Republic of', 'MK' => 'Macedonia', 'MT' => 'Malta', 'NL' => 'Netherlands', 'NO' => 'Norway', 'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'SE' => 'Sweden', 'SI' => 'Slovenia', 'SJ' => 'Svalbard and Jan Mayen', 'SK' => 'Slovakia', 'SM' => 'San Marino', 'TR' => 'Turkey', 'UA' => 'Ukraine', 'VA' => 'Holy See (Vatican City State)', 'RS' => 'Serbia', 'ME' => 'Montenegro', 'AX' => 'Aland Islands', 'GG' => 'Guernsey', 'IM' => 'Isle of Man', 'JE' => 'Jersey' ), 'OC' => array( 0 => 'Oceania', 'AS' => 'American Samoa', 'AU' => 'Australia', 'CK' => 'Cook Islands', 'FJ' => 'Fiji', 'FM' => 'Micronesia, Federated States of', 'GU' => 'Guam', 'KI' => 'Kiribati', 'MH' => 'Marshall Islands', 'MP' => 'Northern Mariana Islands', 'NC' => 'New Caledonia', 'NF' => 'Norfolk Island', 'NR' => 'Nauru', 'NU' => 'Niue', 'NZ' => 'New Zealand', 'PF' => 'French Polynesia', 'PG' => 'Papua New Guinea', 'PN' => 'Pitcairn Islands', 'PW' => 'Palau', 'SB' => 'Solomon Islands', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TV' => 'Tuvalu', 'UM' => 'United States Minor Outlying Islands', 'VU' => 'Vanuatu', 'WF' => 'Wallis and Futuna', 'WS' => 'Samoa' ), 'NA' => array( 0 => 'North America', 'AG' => 'Antigua and Barbuda', 'AI' => 'Anguilla', 'CW' => 'Curacao', 'AW' => 'Aruba', 'BB' => 'Barbados', 'BM' => 'Bermuda', 'BS' => 'Bahamas', 'BZ' => 'Belize', 'CA' => 'Canada', 'CR' => 'Costa Rica', 'CU' => 'Cuba', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'SX' => 'Sint Maarten (Dutch part)', 'GD' => 'Grenada', 'GL' => 'Greenland', 'GP' => 'Guadeloupe', 'GT' => 'Guatemala', 'HN' => 'Honduras', 'HT' => 'Haiti', 'JM' => 'Jamaica', 'KN' => 'Saint Kitts and Nevis', 'KY' => 'Cayman Islands', 'LC' => 'Saint Lucia', 'MQ' => 'Martinique', 'MS' => 'Montserrat', 'MX' => 'Mexico', 'NI' => 'Nicaragua', 'PA' => 'Panama', 'PM' => 'Saint Pierre and Miquelon', 'PR' => 'Puerto Rico', 'SV' => 'El Salvador', 'TC' => 'Turks and Caicos Islands', 'TT' => 'Trinidad and Tobago', 'US' => 'United States', 'VC' => 'Saint Vincent and the Grenadines', 'VG' => 'Virgin Islands, British', 'VI' => 'Virgin Islands, U.S.', 'BL' => 'Saint Barthelemy', 'MF' => 'Saint Martin', 'BQ' => 'Bonaire, Saint Eustatius and Saba' ), 'SA' => array( 0 => 'South America', 'AR' => 'Argentina', 'BO' => 'Bolivia', 'BR' => 'Brazil', 'CL' => 'Chile', 'CO' => 'Colombia', 'EC' => 'Ecuador', 'FK' => 'Falkland Islands (Malvinas)', 'GF' => 'French Guiana', 'GY' => 'Guyana', 'PE' => 'Peru', 'PY' => 'Paraguay', 'SR' => 'Suriname', 'UY' => 'Uruguay', 'VE' => 'Venezuela' ) );

	foreach ( $_countries as $code_country => $countries ) {
		$title   = array_shift( $countries );
		$checked = array_intersect_key( $value, $countries );
		$checked = ! empty( $checked );
		?>
		<label class="continent<?php echo $disabled_class; ?>">
			<input type="checkbox" value="continent-<?php echo $code_country; ?>"<?php checked( $checked ); ?><?php echo $attributes; ?>>
			<?php echo '<span class="label-text">' . $title . '</span>'; ?>
		</label>
		<button type="button" class="hide-if-no-js expand_country"><img src="data:image/gif;base64,R0lGODlhEAAQAMQAAAAAAM/Iu3iYtcK4qPX18bDC09/b0ubm5v///9jTye3t59LMv8a+ruXh2tzYz/j4+PDw7NbRxuTh2f///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAUUABMALAAAAAAQABAAAAVI4CSOZGmeaKqubFkIcCwUp4DcOCLUOHA/O5PgQQQ8II1gSUAAOJ0GJUkAgSgAB4lDOhJoE4DIIsAVCRaMgVpdnrxkMFprjgoBADs=" alt="+" title="<?php esc_attr__( 'Expand', 'secupress' ); ?>" /></button>
		<fieldset class="hide-if-js">
			<legend class="screen-reader-text"><span><?php echo $title; ?></span></legend>
			<?php
			foreach ( $countries as $code => $title ) {
				$args['label_for'] = $args['name'] . '_' . $code;
				?>
				<div>
					<span class="secupress-tree-dash"></span>
					<label<?php echo $disabled_attr; ?>>
						<input type="checkbox" id="<?php echo $args['label_for']; ?>" name="<?php echo $name_attribute; ?>[]" value="<?php echo $code; ?>"<?php checked( isset( $value[ $code ] ) ); ?> data-code-country="<?php echo $code_country; ?>"<?php echo $attributes; ?>>
						<?php echo '<span class="label-text">' . $title . '</span>'; ?>
					</label>
				</div>
				<?php
			}
			?>
		</fieldset>
		<br/>
		<?php
	}
}


/**
 * Content of the settings field for the file scanner.
 *
 * @since 1.0
 * @author Julio Potier
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_file_scanner_field( $args, $instance ) {
	$running = secupress_file_monitoring_get_instance()->is_monitoring_running();

	if ( $running ) {
		$label = __( 'Stop task', 'secupress' );
		$class = ' working';
		$turn  = 'off';
	} else {
		$label = __( 'Search for malwares', 'secupress' );
		$class = '';
		$turn  = 'on';
	}

	$url = esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_toggle_file_scan&turn=' . $turn ), 'secupress_toggle_file_scan' ) );
	?>
	<p>
		<a data-original-i18n="<?php esc_attr_e( 'Search for malwares', 'secupress' ); ?>" data-loading-i18n="<?php esc_attr_e( 'Stop task', 'secupress' ); ?>" id="toggle_file_scanner" href="<?php echo $url; ?>" class="secupress-button<?php echo $class; ?>">
			<?php echo $label; ?>
		</a>
		<span class="secupress-inline-spinner spinner"></span>
		<?php
		$full_filetree = get_site_transient( SECUPRESS_FULL_FILETREE );
		$label = 'off' === $turn ? __( 'Stopping Scanner: <code>Cleaning</code>&hellip;', 'secupress' ) : '';
		if ( $running && is_array( $full_filetree ) ) {
			if ( ! isset( $full_filetree[1] ) && ABSPATH === $full_filetree[0] ) {
				$label = sprintf( __( 'Scanning: %s', 'secupress' ), '<code>' . __( 'Database' ) . '&hellip;</code>' );
			} else {
				$label = sprintf( __( 'Scanning: %s', 'secupress' ), '<code>' . __( 'Loading&hellip;' ) . '</code>' );
			}
		}
		?>
		<span id="secupress-scanner-info" data-nonce="<?php echo wp_create_nonce( 'secupress_malwareScanStatus' ); ?>"><?php echo $label; ?></span>
	</p>
	<?php
	if ( $running ) {
		return;
	}

	$files = secupress_file_scanner_get_result();

	if ( false === $files ) {
		?>
		<p class="description"><?php _e( 'This version of WordPress has not been scanned yet.', 'secupress' ); ?></p>
		<?php
		return;
	}

	$nothing_found = true;

	/**
	 * Files that are not part of the WordPress installation.
	 */
	if ( ! empty( $files['not-wp-files'] ) ) {
		$nothing_found       = false;
		$filelist_select_all = count( $files['not-wp-files'] ) > 1;
		$expert_mode         = isset( $_GET['expertmode'] );
		?>
		<form id="form-not-wp-files" action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_action_on_scanned_files' ), 'secupress_action_on_scanned_files' ) ); ?>" method="post">

			<h4><?php _e( 'The followings are not files from WordPress core', 'secupress' ); ?></h4>

			<p><span class="secupress-inline-alert"><?php _e( 'Possible malware found', 'secupress' ); ?></span></p>

			<fieldset id="secupress-group-diff-files" class="secupress-boxed-group small-boxed-group secupress-check-group">

				<span class="secupress-toggle-sort-all hide-if-no-js"><span class="dashicons dashicons-sort"></span><em><?php _e( 'Toggle all', 'secupress' ); ?></em></span></span>

				<?php if ( $expert_mode && $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-diff-file-1" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-diff-file-1"><em><?php _e( '(Un)Select All', 'secupress-pro' ); ?></em></label></span>
				<?php } ?>

				<ul class="secupress-files-list">
					<?php
					$pattern = $expert_mode ? '<li class="secupress-files-list-item"><input id="diff-file-%1$s" class="secupress-checkbox secupress-row-check" type="checkbox" name="files[]" value="%3$s"> <label for="diff-file-%1$s">%2$s</label>%4$s</li>'
											: '<li class="secupress-files-list-item"><span class="dashicons dashicons-arrow-right secupress-toggle-sort" data-file="%1$s"></span><em>/%2$s</em>%4$s</li>';
					foreach ( $files['not-wp-files'] as $diff_file ) {
						printf( $pattern,
							sanitize_html_class( $diff_file ),
							esc_html( $diff_file ),
							esc_attr( $diff_file ),
							secupress_check_malware( $diff_file ) // All listed files have been spotted as possible malware: display the html directly.
						);
					} ?>
				</ul>

				<?php if ( $expert_mode && $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-diff-file-2" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-diff-file-2"><em><?php _e( '(Un)Select All', 'secupress-pro' ); ?></em></label></span>
				<?php } ?>

			</fieldset>

			<?php if ( $expert_mode ) { ?>
				<p class="submit secupress-clearfix">
					<?php submit_button( __( 'Delete selected files', 'secupress-pro' ), 'secondary alignright', 'submit-delete-files', false ); ?>
				</p>
			<?php } ?>
			<p><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> <em><?php echo '<strong>' . __( 'What to do now?', 'secupress' ) . '</strong> ' . __( 'Check each file content using FTP to determine if it has to be cleaned, deleted or is a false positive.', 'secupress' ); ?></em></p>
			<p><span class="dashicons dashicons-flag" aria-hidden="true"></span> <em><?php echo '<strong>' . __( 'Hacked Website?', 'secupress' ) . '</strong> ' . __( 'Well, this is not a good day for you, we will try to make you smile while we’re working on it!', 'secupress' ); ?></em> <a class="button button-small secupress-button-small" href="<?php echo esc_url( secupress_admin_url( 'get-pro' ) ); ?>#services"><?php _e( 'Ask an Expert', 'secupress' ); ?></a></p>

		</form>
		<?php
	}

	/**
	 * Missing files from WP Core.
	 */
	if ( ! empty( $files['missing-wp-files'] ) ) {
		$nothing_found = false;

		$filelist_select_all = count( $files['missing-wp-files'] ) > 1;
		?>
		<hr>
		<form id="form-recover-missing-files" action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_recover_missing_files' ), 'secupress_recover_missing_files' ) ); ?>" method="post">

			<h4><?php _e( 'The followings are missing from WordPress core files', 'secupress' ); ?></h4>

			<fieldset id="secupress-group-miss-files" class="secupress-boxed-group small-boxed-group secupress-check-group">

				<?php if ( $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-miss-file-1" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-miss-file-1"><em><?php _e( '(Un)Select All', 'secupress' ); ?></em></label></span>
				<?php } ?>

				<ul class="secupress-files-list">
					<?php
					foreach ( $files['missing-wp-files'] as $miss_file ) {
						printf(
							'<li class="secupress-files-list-item"><input id="miss-file-%1$s" class="secupress-checkbox secupress-row-check" type="checkbox" name="files[]" value="%3$s" title="%3$s"> <label for="miss-file-%1$s" title="%3$s">%2$s</label></li>',
							sanitize_html_class( $miss_file ),
							esc_html( $miss_file ),
							esc_attr( $miss_file )
						);
					}
					?>
				</ul>

				<?php if ( $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-miss-file-2" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-miss-file-2"><em><?php _e( '(Un)Select All', 'secupress' ); ?></em></label></span>
				<?php } ?>

			</fieldset>

			<p class="submit secupress-clearfix">
				<?php submit_button( __( 'Recover selected files', 'secupress' ), 'secondary alignright secupress-button secupress-button-mini', 'submit-recover-missing-files', false ); ?>
			</p>

		</form>
		<?php
	}

	/**
	 * Old WP files.
	 */
	if ( ! empty( $files['old-wp-files'] ) ) {

		$nothing_found          = false;
		$filelist_li            = '';
		$filelist_select_all    = count( $files['old-wp-files'] ) > 1;
		$possible_malware_found = false;

		foreach ( $files['old-wp-files'] as $old_file ) {

			$malware = secupress_check_malware( $old_file );
			if ( $malware ) {
				// Display this message if there is at least one malware.
				$possible_malware_found = true;
			}

			$filelist_li .= sprintf(
				'<li class="secupress-files-list-item"><input id="old-file-%1$s" class="secupress-checkbox secupress-row-check" type="checkbox" name="files[]" value="%3$s" title="%3$s"> <label for="old-file-%1$s" title="%3$s">%2$s</label>%4$s</li>',
				sanitize_html_class( $old_file ),
				esc_html( $old_file ),
				esc_attr( $old_file ),
				$malware // Do not escape.
			);
		}
		?>
		<hr>
		<form id="form-old-files" action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_old_files' ), 'secupress_old_files' ) ); ?>" method="post">

			<h4><?php _e( 'The followings files are old WordPress core ones', 'secupress' ); ?></h4>

			<?php if ( $possible_malware_found ) { ?>
				<p><span class="secupress-inline-alert"><?php _e( 'Possible malware found', 'secupress' ); ?></span></p>
			<?php } ?>

			<fieldset id="secupress-group-old-files" class="secupress-boxed-group small-boxed-group secupress-check-group">

				<?php if ( $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-old-file-1" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-old-file-1"><em><?php _e( '(Un)Select All', 'secupress' ); ?></em></label></span>
				<?php } ?>

				<ul class="secupress-files-list">
					<?php echo $filelist_li; ?>
				</ul>

				<?php if ( $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-old-file-2" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-old-file-2"><em><?php _e( '(Un)Select All', 'secupress' ); ?></em></label></span>
				<?php } ?>

			</fieldset>

			<p class="submit secupress-clearfix">
				<?php submit_button( __( 'Delete selected files', 'secupress' ), 'secondary alignright secupress-button secupress-button-mini', 'submit-recover-diff-files', false ); ?>
			</p>

		</form>
		<?php
	}

	/**
	 * Modified WP Core files.
	 */
	if ( ! empty( $files['modified-wp-files'] ) ) {
		$nothing_found          = false;
		$filelist_li            = '';
		$filelist_select_all    = count( $files['modified-wp-files'] ) > 1;
		$possible_malware_found = false;

		foreach ( $files['modified-wp-files'] as $mod_file ) {

			$malware = secupress_check_malware( $mod_file );
			if ( $malware ) {
				// Display this message if there is at least one malware.
				$possible_malware_found = true;
			}

			$filelist_li .= sprintf(
				'<li class="secupress-files-list-item"><input id="mod-file-%1$s" class="secupress-checkbox secupress-row-check" type="checkbox" name="files[]" value="%3$s" title="%3$s"> <label for="mod-file-%1$s" title="%3$s">%2$s</label> <a target="_blank" href="%4$s" class="secupress-button secupress-button-ghost secupress-button-mini secupress-button-primary"><span class="icon"><i class="dashicons dashicons-plus" aria-hidden="true"></i></span><span class="text">' . __( 'See differences', 'secupress' ) . '</span></a>%5$s</li>',
				sanitize_html_class( $mod_file ),
				esc_html( $mod_file ),
				esc_attr( $mod_file ),
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_diff_file&file=' . $mod_file ), 'secupress_diff_file-' . $mod_file ) ),
				$malware // Do not escape.
			);
		}
		?>
		<hr>
		<form id="form-recover-diff-files" action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_action_on_scanned_files' ), 'secupress_action_on_scanned_files' ) ); ?>" method="post">

			<h4><?php _e( 'The followings are modified WordPress core files', 'secupress' ); ?></h4>

			<?php if ( $possible_malware_found ) { ?>
				<p><span class="secupress-inline-alert"><?php _e( 'Possible malware found', 'secupress' ); ?></span></p>
			<?php } ?>

			<fieldset id="secupress-group-mod-files" class="secupress-boxed-group small-boxed-group secupress-check-group">

				<?php if ( $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-mod-file-1" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-mod-file-1"><em><?php _e( '(Un)Select All', 'secupress' ); ?></em></label></span>
				<?php } ?>

				<ul class="secupress-files-list">
					<?php echo $filelist_li; ?>
				</ul>

				<?php if ( $filelist_select_all ) { ?>
					<span class="hide-if-no-js"><input id="secupress-toggle-check-mod-file-2" type="checkbox" class="secupress-checkbox secupress-toggle-check"> <label for="secupress-toggle-check-mod-file-2"><em><?php _e( '(Un)Select All', 'secupress' ); ?></em></label></span>
				<?php } ?>

			</fieldset>

			<p class="submit secupress-clearfix">
				<?php submit_button( __( 'Recover selected files', 'secupress' ), 'secondary alignright secupress-button secupress-button-mini', 'submit-recover-diff-files', false ); ?>
			</p>

		</form>
		<?php
	}
	/**
	 * DATABASE Malwares
	 */
	if ( isset( $files['database-wp'] ) && ! empty( array_filter( array_values( $files['database-wp'] ) ) ) ) {
		$filelist_li   = '';
		$nothing_found = false;
		foreach ( $files['database-wp'] as $type => $_posts ) {
			$filelist_li .= $type . ' ' . var_export( $_posts,1 );
		}
		?>
		<hr>
		<h4><?php _e( 'The followings are Malware Database Injections', 'secupress' ); ?></h4>

		<p><span class="secupress-inline-alert"><?php _e( 'Possible malware found', 'secupress' ); ?></span></p>

		<fieldset id="secupress-group-diff-files" class="secupress-boxed-group small-boxed-group secupress-check-group">

			<span class="secupress-toggle-sort-all hide-if-no-js"><span class="dashicons dashicons-sort"></span><em><?php _e( 'Toggle all', 'secupress' ); ?></em></span></span>

			<ul class="secupress-files-list">
				<?php
				foreach ( $files['database-wp'] as $type => $_posts ) {
					$keywords = secupress_get_database_malware_keywords( $type, 'display' );
					$_posts   = array_flip( array_flip( $_posts ) );
					foreach ( $_posts as $_post_id ) {
						if ( 'publish' !== get_post_status( $_post_id ) ) {
							continue;
						}
						printf(
							'<li class="secupress-files-list-item"><span class="dashicons dashicons-arrow-right secupress-toggle-sort" data-file="%1$s"></span><em>%2$s</em>%3$s%4$s</li>',
							sanitize_html_class( $type ),
							'',
							sprintf( '"%s" (<a href="%s">%s</a>)', get_the_title( $_post_id ), get_edit_post_link( $_post_id, 'href' ), __( 'Edit' ) ),
							sprintf( ' <span class="secupress-inline-alert"><span class="screen-reader-text">%1$s</span></span><div class="secupress-toggle-me %2$s hide-if-js"><strong>%3$s</strong>%4$s<hr></div>',
								__( 'Possible malware found', 'secupress' ),
								sanitize_html_class( $type ),
								_n( 'Found Signature: ', 'Found Signatures: ', count( $keywords['+'] ), 'secupress' ),
								str_replace( '<code></code>', '', '<code>' . implode( '</code>, <code>', $keywords['+'] ) . '</code>' )
							)
						);
					}
				} ?>
			</ul>

		</fieldset>

		<p><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> <em><?php echo '<strong>' . __( 'What to do now?', 'secupress' ) . '</strong> ' . __( 'Check each post content to determine if it has to be cleaned, deleted or is a false positive.', 'secupress' ); ?></em></p>
		<p><span class="dashicons dashicons-flag" aria-hidden="true"></span> <em><?php echo '<strong>' . __( 'Hacked Website?', 'secupress' ) . '</strong> ' . __( 'Well, this is not a good day for you, we will try to make you smile while we’re working on it!', 'secupress' ); ?></em> <a class="button button-small secupress-button-small" href="<?php echo esc_url( secupress_admin_url( 'get-pro' ) ); ?>#services"><?php _e( 'Ask an Expert', 'secupress' ); ?></a></p>
		<?php
	}

	if ( $nothing_found ) {
		?>
		<p class="description"><?php _e( 'Nothing found, well done.', 'secupress' ); ?></p>
		<?php
	}
}


/**
 * Content of the settings field that displays the old backups.
 *
 * @since 1.0
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_backup_history_field( $args, $instance ) {
	$backup_files = secupress_get_backup_file_list();
	?>
	<p id="secupress-no-backups"<?php echo $backup_files ? ' class="hidden"' : ''; ?>><em><?php _e( 'No Backups found.', 'secupress' ); ?></em></p>

	<form id="form-delete-backups"<?php echo ! $backup_files ? ' class="hidden"' : ''; ?> action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_delete_backups' ), 'secupress_delete_backups' ) ); ?>" method="post">

		<strong id="secupress-available-backups"><?php printf( _n( '%s available Backup', '%s available Backups', count( $backup_files ), 'secupress' ), number_format_i18n( count( $backup_files ) ) ); ?></strong>

		<fieldset class="secupress-boxed-group">
			<legend class="screen-reader-text"><span><?php _e( 'Backups', 'secupress' ); ?></span></legend>
			<?php array_map( 'secupress_print_backup_file_formated', array_reverse( $backup_files ) ); ?>
		</fieldset>

		<p class="submit">
			<button class="secupress-button secupress-button-secondary alignright" type="submit" id="submit-delete-backups">
				<span class="icon">
					<i class="secupress-icon-cross" aria-hidden="true"></i>
				</span>
				<span class="text">
					<?php _e( 'Delete all Backups', 'secupress' ); ?>
				</span>
			</button>
		</p>

	</form>
	<?php
}


/**
 * Content of the settings field that displays the DB tables to backup.
 *
 * @since 1.0
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_backup_db_field( $args, $instance ) {
	global $wpdb;

	$wp_tables    = secupress_get_wp_tables();
	$other_tables = secupress_get_non_wp_tables();
	?>
	<form action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_backup_db' ), 'secupress_backup_db' ) ); ?>" id="form-do-db-backup" method="post">

		<fieldset class="secupress-boxed-group">
			<legend class="screen-reader-text"><span><?php _e( 'DataBase Tables', 'secupress' ); ?></span></legend>

			<b><?php _e( 'Unknown tables', 'secupress' ); ?></b>
			<br>
			<?php
			if ( $other_tables ) {
				$chosen_tables = get_site_option( 'secupress_database-backups_settings' );

				if ( is_array( $chosen_tables ) && isset( $chosen_tables['other_tables'] ) && is_array( $chosen_tables['other_tables'] ) ) {
					$chosen_tables = array_intersect( $other_tables, $chosen_tables['other_tables'] );
					$chosen_tables = $chosen_tables ? array_flip( $chosen_tables ) : array();
				} else {
					$chosen_tables = array_flip( $other_tables );
				}

				// Skip our geoip table.
				if ( ! empty( $wpdb->prefix . 'secupress_geoips' ) ) {
					unset( $chosen_tables[ $wpdb->prefix . 'secupress_geoips' ] );
				}

				foreach ( $other_tables as $table ) {
					echo '<label><input name="other_tables[]" value="' . esc_attr( $table ) . '"' . ( isset( $chosen_tables[ $table ] ) ? ' checked="checked"' : '' ) . ' type="checkbox" class="secupress-checkbox secupress-checkbox-mini"> <span class="label-text">' . $table . '</span></label><br>';
				}
			} else {
				_ex( 'None', 'database table', 'secupress' );
			}
			?>
			<hr>
			<b><?php _e( 'WordPress tables (mandatory)', 'secupress' ); ?></b>
			<br>
			<?php
			if ( $wp_tables ) {
				foreach ( $wp_tables as $table ) {
					echo '<label><input disabled="disabled" checked="checked" type="checkbox" class="secupress-checkbox secupress-checkbox-mini"> <span class="label-text">' . $table . '</span></label><br>';
				}
			} else {
				_ex( 'None', 'database table', 'secupress' );
			}
			?>
		</fieldset>

		<p class="submit">
			<button class="secupress-button" type="submit" data-original-i18n="<?php esc_attr_e( 'Backup my Database', 'secupress' ); ?>" data-loading-i18n="<?php esc_attr_e( 'Backuping&hellip;', 'secupress' ); ?>" id="submit-backup-db">
				<span class="icon">
					<i class="secupress-icon-download" aria-hidden="true"></i>
				</span>
				<span class="text">
					<?php _e( 'Backup my Database', 'secupress' ); ?>
				</span>
			</button>
			<span class="spinner secupress-inline-spinner"></span>
		</p>

	</form>
	<?php
}


/**
 * Content of the settings field that displays the files to backup.
 *
 * @since 1.0
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_backup_files_field( $args, $instance ) {
	$ignored_directories = get_site_option( 'secupress_file-backups_settings' );
	$ignored_directories = ! empty( $ignored_directories['ignored_directories'] ) ? $ignored_directories['ignored_directories'] : '';
	?>
	<form action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=secupress_backup_files' ), 'secupress_backup_files' ) ); ?>" id="form-do-files-backup" method="post">

		<fieldset>
			<legend><strong><label for="ignored_directories"><?php _e( 'Do not backup the following files and folders:', 'secupress' ); ?></label></strong></legend>
			<br>
			<textarea id="ignored_directories" name="ignored_directories" cols="50" rows="5"><?php echo esc_textarea( $ignored_directories ); ?></textarea>
			<p class="description">
				<?php _e( 'One file or folder per line.', 'secupress' ); ?>
			</p>
		</fieldset>

		<p class="submit">
			<button class="secupress-button" type="submit" data-original-i18n="<?php esc_attr_e( 'Backup my Files', 'secupress' ); ?>" data-loading-i18n="<?php esc_attr_e( 'Backuping&hellip;', 'secupress' ); ?>" id="submit-backup-files">
				<span class="icon">
					<i class="secupress-icon-download" aria-hidden="true"></i>
				</span>
				<span class="text">
					<?php _e( 'Backup my Files', 'secupress' ); ?>
				</span>
			</button>
			<span class="spinner secupress-inline-spinner"></span>
		</p>

	</form>
	<?php
}


/**
 * Content of the settings field for the scheduled backups.
 *
 * @since 1.0
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_scheduled_backups_field( $args, $instance ) {
	$submodule     = 'schedules-backups';
	$next_schedule = secupress_get_next_scheduled_backup();

	if ( ! $next_schedule ) {
		_e( 'Nothing’s scheduled yet.', 'secupress' );
		return;
	}

	// Date.
	$next_schedule = date_i18n( _x( 'l, F jS, Y \a\t h:ia', 'Schedule date', 'secupress' ), $next_schedule );

	// What to backup.
	$def_types = array( 'db', 'files' );
	$types     = secupress_get_module_option( $submodule . '_type', array(), 'schedules' );
	$types     = is_array( $types ) ? $types : array();
	$types     = array_intersect( $def_types, $types );
	$types     = $types ? $types : $def_types;
	$types     = array_flip( $types );
	$type      = array();

	if ( isset( $types['db'] ) ) {
		$type[] = __( 'the database', 'secupress' );
	}
	if ( isset( $types['files'] ) ) {
		$type[] = __( 'the files', 'secupress' );
	}

	// Periodicity.
	$periodicity = (int) secupress_get_module_option( $submodule . '_periodicity', 1, 'schedules' );

	// Email.
	$email = secupress_get_module_option( $submodule . '_email', '', 'schedules' );
	$email = $email ? is_email( $email ) : false;

	printf(
		/** Translators: 1 is a date, 2 is "the database", "the files", or "the database and the files". */
		__( 'Next backup will occur %1$s and will backup %2$s.', 'secupress' ),
		'<span class="secupress-highlight">' . $next_schedule . '</span>',
		'<span class="secupress-highlight">' . wp_sprintf( '%l', $type ) . '</span>'
	);

	echo "<br/>\n";

	if ( $email ) {
		printf(
			/** Translators: 1 is "repeat every X days", 2 is an email address. */
			__( 'This task will %1$s, and once finished you will be notified at: %2$s.', 'secupress' ),
			'<span class="secupress-highlight">' . sprintf( _n( 'repeat every %d day', 'repeat every %d days', $periodicity, 'secupress' ), $periodicity ) . '</span>',
			'<span class="secupress-highlight">' . $email . '</span>'
		);
	} else {
		printf(
			/** Translators: %s is "repeat every X days". */
			__( 'This task will %s.', 'secupress' ),
			'<span class="secupress-highlight">' . sprintf( _n( 'repeat every %d day', 'repeat every %d days', $periodicity, 'secupress' ), $periodicity ) . '</span>'
		);
	}
}


/**
 * Content of the settings field for the scheduled scan.
 *
 * @since 1.0
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_scheduled_scan_field( $args, $instance ) {
	$submodule     = 'schedules-scan';
	$next_schedule = secupress_get_next_scheduled_scan();

	if ( ! $next_schedule ) {
		_e( 'Nothing’s scheduled yet.', 'secupress' );
		return;
	}

	// Date.
	$next_schedule = date_i18n( _x( 'l, F jS, Y \a\t h:ia', 'Schedule date', 'secupress' ), $next_schedule );

	// Periodicity.
	$periodicity = (int) secupress_get_module_option( $submodule . '_periodicity', 1, 'schedules' );

	// Email.
	$email = secupress_get_module_option( $submodule . '_email', '', 'schedules' );
	$email = $email ? is_email( $email ) : false;

	printf(
		/** Translators: %s is a date. */
		__( 'Next scan will occur %s.', 'secupress' ),
		'<span class="secupress-highlight">' . $next_schedule . '</span>'
	);

	echo "<br/>\n";

	if ( $email ) {
		printf(
			/** Translators: 1 is "repeat every X days", 2 is an email address. */
			__( 'This task will %1$s, and once finished you will be notified at: %2$s.', 'secupress' ),
			'<span class="secupress-highlight">' . sprintf( _n( 'repeat every %d day', 'repeat every %d days', $periodicity, 'secupress' ), $periodicity ) . '</span>',
			'<span class="secupress-highlight">' . $email . '</span>'
		);
	} else {
		printf(
			/** Translators: %s is "repeat every X days". */
			__( 'This task will %s.', 'secupress' ),
			'<span class="secupress-highlight">' . sprintf( _n( 'repeat every %d day', 'repeat every %d days', $periodicity, 'secupress' ), $periodicity ) . '</span>'
		);
	}
}


/**
 * Content of the settings field for the scheduled file monitoring.
 *
 * @since 1.0
 *
 * @param (array)  $args     An array of parameters. See `SecuPress_Settings::field()`.
 * @param (object) $instance SecuPress_Settings object.
 */
function secupress_pro_scheduled_monitoring_field( $args, $instance ) {
	$submodule     = 'schedules-file-monitoring';
	$next_schedule = secupress_get_next_scheduled_file_monitoring();

	if ( ! $next_schedule ) {
		_e( 'Nothing’s scheduled yet.', 'secupress' );
		return;
	}

	// Date.
	$next_schedule = date_i18n( _x( 'l, F jS, Y \a\t h:ia', 'Schedule date', 'secupress' ), $next_schedule );

	// Periodicity.
	$periodicity = (int) secupress_get_module_option( $submodule . '_periodicity', 1, 'schedules' );

	// Email.
	$email = secupress_get_module_option( $submodule . '_email', '', 'schedules' );
	$email = $email ? is_email( $email ) : false;

	printf(
		/** Translators: %s is a date. */
		__( 'Next scan will occur %s.', 'secupress' ),
		'<span class="secupress-highlight">' . $next_schedule . '</span>'
	);

	echo "<br/>\n";

	if ( $email ) {
		printf(
			/** Translators: 1 is "repeat every X days", 2 is an email address. */
			__( 'This task will %1$s, and once finished you will be notified at: %2$s.', 'secupress' ),
			'<span class="secupress-highlight">' . sprintf( _n( 'repeat every %d day', 'repeat every %d days', $periodicity, 'secupress' ), $periodicity ) . '</span>',
			'<span class="secupress-highlight">' . $email . '</span>'
		);
	} else {
		printf(
			/** Translators: %s is "repeat every X days". */
			__( 'This task will %s.', 'secupress' ),
			'<span class="secupress-highlight">' . sprintf( _n( 'repeat every %d day', 'repeat every %d days', $periodicity, 'secupress' ), $periodicity ) . '</span>'
		);
	}
}
