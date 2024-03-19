<?php
/**
 * Module Name: Anti Hotlink
 * Description: Prevent medias hotlinking.
 * Main Module: sensitive_data
 * Author: SecuPress
 * Version: 1.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

/** --------------------------------------------------------------------------------------------- */
/** ACTIVATION / DEACTIVATION =================================================================== */
/** ----------------------------------------------------------------------------------------------*/

add_action( 'secupress.modules.activate_submodule_' . basename( __FILE__, '.php' ), 'secupress_hotlink_activation' );
/**
 * On module activation, maybe write the rules.
 *
 * @since 1.0
 */
function secupress_hotlink_activation() {
	global $is_apache, $is_nginx, $is_iis7;

	// Hotlink protection won't work over http, it needs SSL to (maybe) get the referer.
	if ( ! secupress_is_site_ssl() ) {
		$message  = sprintf( __( '%s:', 'secupress' ), __( 'Anti Hotlink', 'secupress' ) ) . ' ';
		$message .= __( 'The anti hotlink can work only over SSL (the URL of your website must start with <code>https://</code>).', 'secupress' );

		secupress_add_settings_error( 'general', 'no_ssl', $message, 'error' );

		secupress_deactivate_submodule_silently( $module, $submodule );
		return;
	}

	// Apache.
	if ( $is_apache ) {
		$rules = secupress_hotlink_get_apache_rules();
	}
	// IIS7.
	elseif ( $is_iis7 ) {
		$rules = secupress_hotlink_get_iis7_rules();
	}
	// Nginx.
	elseif ( $is_nginx ) {
		$rules = secupress_hotlink_get_nginx_rules();
	}
	// Not supported.
	else {
		$rules = '';
	}

	secupress_add_module_rules_or_notice( array(
		'rules'  => $rules,
		'marker' => 'hotlink',
		'title'  => __( 'Anti Hotlink', 'secupress' ),
	) );
}


add_action( 'secupress.modules.deactivate_submodule_' . basename( __FILE__, '.php' ), 'secupress_hotlink_deactivate' );
/**
 * On module deactivation, maybe remove rewrite rules from the `.htaccess`/`web.config` file.
 *
 * @since 1.0
 */
function secupress_hotlink_deactivate() {
	secupress_remove_module_rules_or_notice( 'hotlink', __( 'Anti Hotlink', 'secupress' ) );
}


add_filter( 'secupress.pro.plugins.activation.write_rules', 'secupress_hotlink_plugin_activate', 10, 2 );
/**
 * On SecuPress Pro activation, add the rules to the list of the rules to write.
 *
 * @since 1.0
 *
 * @param (array) $rules Other rules to write.
 *
 * @return (array) Rules to write.
 */
function secupress_hotlink_plugin_activate( $rules ) {
	global $is_apache, $is_nginx, $is_iis7;
	$marker = 'hotlink';

	if ( $is_apache ) {
		$rules[ $marker ] = secupress_hotlink_get_apache_rules();
	} elseif ( $is_iis7 ) {
		$rules[ $marker ] = array( 'nodes_string' => secupress_hotlink_get_iis7_rules() );
	} elseif ( $is_nginx ) {
		$rules[ $marker ] = secupress_hotlink_get_nginx_rules();
	}

	return $rules;
}


/** --------------------------------------------------------------------------------------------- */
/** TOOLS ======================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Get a list of autorized referers as regex patterns.
 *
 * @since 1.0
 *
 * @return (array) A list of regex patterns.
 */
function secupress_hotlink_get_referer_regex_patterns_list() {
	$refs = array();
	/**
	 * Add autorized referers. Current domain will ba added later.
	 *
	 * @since 1.0
	 *
	 * @param (array) $refs An array of autorized referers.
	 */
	$refs = apply_filters( 'secupress.plugin.hotlink.additional_autorized_referers', $refs );

	if ( $refs ) {
		foreach ( $refs as $i => $ref ) {
			$ref  = rtrim( $ref, '/' );
			$ref  = preg_quote( $ref );
			$ref  = preg_replace( '/^https?:/', '^https?:', $ref );
			$ref .= '(?:/?|/.+)$';
		}
	}

	// Add the current domain as an autorized referer.
	$home_url = home_url();
	$home_url = rtrim( $home_url, '/' );
	$home_url = str_replace( 'www.', '', $home_url );

	if ( is_multisite() && is_subdomain_install() ) {
		$home_url = preg_replace( '/^https?:\/\//', '', $home_url );
		$home_url = preg_quote( $home_url );
		$home_url = '^https?://([^.]+\.)?' . $home_url;
	} else {
		$home_url = preg_quote( $home_url );
		$home_url = preg_replace( '/^https?:/', '^https?:', $home_url );
	}

	$home_url .= '(?:/?|/.+)$';
	array_unshift( $refs, $home_url );

	return $refs;
}


/**
 * Get a list of protected file extensions as a regex pattern.
 *
 * @since 1.0
 *
 * @return (string) A regex pattern.
 */
function secupress_hotlink_get_protected_extensions_regex_pattern() {
	$ext = array( 'jpg', 'jpeg', 'png', 'gif' );
	/**
	 * Filter the list of protected file extensions.
	 *
	 * @since 1.0
	 *
	 * @param (array) $ext An array of file extensions.
	 */
	$ext = apply_filters( 'secupress.plugin.hotlink.protected_extensions', $ext );

	return '\.(' . implode( '|', $ext ) . ')$';
}


/**
 * Get the URL of the image replacement.
 *
 * @since 1.0
 *
 * @return (string)
 */
function secupress_hotlink_get_replacement_url() {
	$url = 'data:image/gif;base64,R0lGODdhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=';
	/**
	 * Filter the URL of the image used as replacement when a media is hotlinked.
	 *
	 * @since 1.0
	 *
	 * @param (string) $url The replacement image URL.
	 */
	return apply_filters( 'secupress.plugin.hotlink.replacement_url', $url );
}


/**
 * Get the URI of the image replacement as a regex pattern. The aim is to use it in `RewriteCond`.
 *
 * @since 1.0
 *
 * @return (string|bool) A regex pattern that matches the image replacement URI. False if the image is not delivered from the same domain.
 */
function secupress_hotlink_get_replacement_regex_pattern() {
	$url = secupress_hotlink_get_replacement_url();

	if ( false === strpos( $url, $_SERVER['HTTP_HOST'] ) ) {
		return false;
	}

	$url = explode( $_SERVER['HTTP_HOST'], $url );
	$url = end( $url );
	$url = preg_quote( $url );

	return $url;
}


/** --------------------------------------------------------------------------------------------- */
/** RULES ======================================================================================= */
/** ----------------------------------------------------------------------------------------------*/

/**
 * Get the rewrite rules that should be added into the `.htaccess` file (without the SecuPress marker).
 * Will output something like:
 * <IfModule mod_rewrite.c>
 *     RewriteEngine On
 *     RewriteCond %{REQUEST_FILENAME} -f
 *     RewriteCond %{REQUEST_FILENAME} \.(jpg|jpeg|png|gif)$ [NC]
 *     RewriteCond %{HTTP_REFERER} !^$
 *     RewriteCond %{HTTP_REFERER} !^https?://www\.domain\.com(?:/?|/.+)$ [NC]
 *     RewriteCond %{REQUEST_URI} !^/wp-content/plugins/secupress-free/assets/front/images/hotlink\.png$ [NC]
 *     RewriteRule \.(jpg|jpeg|png|gif)$ https://www.domain.com/wp-content/plugins/secupress-free/assets/front/images/hotlink.png [NC,R,L]
 * </IfModule>
 *
 * @since 1.0
 *
 * @return (string) The rewrite rules, ready to be insterted into the `.htaccess` file.
 */
function secupress_hotlink_get_apache_rules() {
	$refs     = secupress_hotlink_get_referer_regex_patterns_list();
	$ext      = secupress_hotlink_get_protected_extensions_regex_pattern();
	$repl     = secupress_hotlink_get_replacement_url();
	$uri_cond = secupress_hotlink_get_replacement_regex_pattern();

	$out  = "<IfModule mod_rewrite.c>\n";
	$out .= "    RewriteEngine On\n";
	// An existing file.
	$out .= "    RewriteCond %{REQUEST_FILENAME} -f\n";
	// A file with one of the protected extensions.
	$out .= "    RewriteCond %{REQUEST_FILENAME} $ext [NC]\n";
	// Allow empty referer and Google.
	$out .= "    RewriteCond %{HTTP_REFERER} !google. [NC]\n";
	$out .= "    RewriteCond %{HTTP_REFERER} !^$\n";
	// Allowed referers.
	foreach ( $refs as $ref ) {
		$out .= "    RewriteCond %{HTTP_REFERER} !$ref [NC]\n";
	}
	// The URI must not match the replacement image (infinite redirections).
	if ( $uri_cond ) {
		$out .= "    RewriteCond %{REQUEST_URI} !^$uri_cond$ [NC]\n";
	}
	// Redirect to the replacement image.
	$out .= "    RewriteRule $repl [R,L]\n";
	$out .= '</IfModule>';

	return $out;
}


/**
 * Get the rewrite rules that should be added into the `web.config` file.
 * Will output something like:
 * <rule name="SecuPress hotlink">
 *     <match url="\.(jpg|jpeg|png|gif)$"/>
 *     <conditions>
 *         <add input="{REQUEST_FILENAME}" matchType="isFile"/>
 *         <add input="{REQUEST_FILENAME}" pattern="\.(jpg|jpeg|png|gif)$" ignoreCase="true"/>
 *         <add input="{HTTP_REFERER}" pattern="^$" negate="true"/>
 *         <add input="{HTTP_REFERER}" pattern="^https?://www\.domain\.com(?:/?|/.+)$" negate="true" ignoreCase="true"/>
 *         <add input="{REQUEST_URI}" pattern="^/wp-content/plugins/secupress-free/assets/front/images/hotlink\.png$" negate="true" ignoreCase="true"/>
 *     </conditions>
 *     <action type="Rewrite" url="https://www.domain.com/wp-content/plugins/secupress-free/assets/front/images/hotlink.png" />
 * </rule>
 *
 * @since 1.0
 * @see https://www.iis.net/learn/extensions/url-rewrite-module/url-rewrite-module-configuration-reference
 * @see http://www.it-notebook.org/iis/article/prevent_hotlinking_url_rewrite.htm
 *
 * @return (string) The rewrite rules, ready to be insterted into the `web.config` file.
 */
function secupress_hotlink_get_iis7_rules() {
	$refs     = secupress_hotlink_get_referer_regex_patterns_list();
	$ext      = secupress_hotlink_get_protected_extensions_regex_pattern();
	$repl     = secupress_hotlink_get_replacement_url();
	$uri_cond = secupress_hotlink_get_replacement_regex_pattern();
	$marker   = 'hotlink';
	$spaces   = str_repeat( ' ', 10 );

	$out  = "<rule name=\"SecuPress $marker\">\n";
	$out .= "$spaces  <conditions>\n";
	$out .= "$spaces    <add input=\"{REQUEST_FILENAME}\" matchType=\"isFile\"/>\n";
	$out .= "$spaces    <add input=\"{REQUEST_FILENAME}\" pattern=\"$ext\" ignoreCase=\"true\"/>\n";
	$out .= "$spaces    <add input=\"{HTTP_REFERER}\" pattern=\"^$\" negate=\"true\"/>\n";
	foreach ( $refs as $ref ) {
		$out .= "$spaces    <add input=\"{HTTP_REFERER}\" pattern=\"$ref\" negate=\"true\" ignoreCase=\"true\"/>\n";
	}
	if ( $uri_cond ) {
		$out .= "$spaces    <add input=\"{REQUEST_URI}\" pattern=\"^$uri_cond$\" negate=\"true\" ignoreCase=\"true\"/>\n";
	}
	$out .= "$spaces  </conditions>\n";
	$out .= "$spaces  <action type=\"Rewrite\" url=\"$repl\" />\n";
	$out .= "$spaces</rule>";

	return $out;
}


/**
 * Get the rewrite rules that should be added into the `nginx.conf` file (without the SecuPress marker).
 * Will output something like:
 * if (-f $request_filename) {
 *     set $cond_hotlink 1$cond_hotlink;
 * }
 * if ($request_filename ~* "\.(jpg|jpeg|png|gif)$") {
 *     set $cond_hotlink 2$cond_hotlink;
 * }
 * if ($http_referer !~ "^$") {
 *     set $cond_hotlink 3$cond_hotlink;
 * }
 * if ($http_referer !~* "^https?:\/\/www\.domain\.com(?:\/?|\/.+)$") {
 *     set $cond_hotlink 4$cond_hotlink;
 * }
 * if ($uri !~* "^\/wp-content\/plugins\/secupress-free\/assets\/front\/images\/hotlink\.png$") {
 *     set $cond_hotlink 5$cond_hotlink;
 * }
 * if ($cond_hotlink = "54321") {
 *     rewrite \.(jpg|jpeg|png|gif)$ http://www.domain.com/wp-content/plugins/secupress-free/assets/front/images/hotlink.png redirect;
 * }
 *
 * @since 1.0
 *
 * @return (string) The rewrite rules, ready to be insterted into the `nginx.conf` file.
 */
function secupress_hotlink_get_nginx_rules() {
	$refs     = secupress_hotlink_get_referer_regex_patterns_list();
	$ext      = secupress_hotlink_get_protected_extensions_regex_pattern();
	$repl     = secupress_hotlink_get_replacement_url();
	$uri_cond = secupress_hotlink_get_replacement_regex_pattern();
	$base     = secupress_get_rewrite_bases();
	$base     = $base['base'];
	$marker   = 'hotlink';
	$i        = 3;
	$rule_val = '321';

	$out  = "location $base {\n";
	$out .= "    # BEGIN SecuPress $marker\n";
	$out .= '    if (-f $request_filename) {' . "\n";
	$out .= '        set $cond_hotlink 1$cond_hotlink;' . "\n";
	$out .= "    }\n";
	$out .= '    if ($request_filename ~* "' . $ext . '") {' . "\n";
	$out .= '        set $cond_hotlink 2$cond_hotlink;' . "\n";
	$out .= "    }\n";
	$out .= '    if ($http_referer !~ "^$") {' . "\n";
	$out .= '        set $cond_hotlink 3$cond_hotlink;' . "\n";
	$out .= "    }\n";
	foreach ( $refs as $ref ) {
		++$i;
		$rule_val = $i . $rule_val;
		$out .= '    if ($http_referer !~ "' . $ref . '") {' . "\n";
		$out .= '        set $cond_hotlink ' . $i . '$cond_hotlink;' . "\n";
		$out .= "    }\n";
	}
	if ( $uri_cond ) {
		++$i;
		$rule_val = $i . $rule_val;
		$out .= '    if ($uri !~* "' . $uri_cond . '") {' . "\n";
		$out .= '        set $cond_hotlink ' . $i . '$cond_hotlink;' . "\n";
		$out .= "    }\n";
	}
	$out .= '    if ($cond_hotlink = "' . $rule_val . '") {' . "\n";
	$out .= "        rewrite ^ $repl redirect;\n";
	$out .= "    }\n";
	$out .= "    # END SecuPress\n";
	$out .= '}';

	return $out;
}
