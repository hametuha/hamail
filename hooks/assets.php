<?php
/**
 * Assets
 */

/**
 * Register assets
 */
add_action( 'init', function() {
	$dir = plugin_dir_url( __DIR__ ).'assets';
	// Style
	wp_register_style( 'hamail-sender', $dir . '/css/hamail.css', [], HAMAIL_VERSION );

} );
