<?php
/**
 * Module Name: Anti 404 Guessing
 * Description: Prevent WordPress redirection on posts/pages using ?name paramater
 * Main Module: sensitive_data
 * Author: SecuPress
 * Version: 2.0
 */

defined( 'SECUPRESS_VERSION' ) or die( 'Something went wrong.' );

add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
