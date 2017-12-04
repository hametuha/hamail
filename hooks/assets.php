<?php
/**
 * Assets
 */

/**
 * Register assets
 */
add_action( 'init', function () {
	$dir = plugin_dir_url( __DIR__ ) . 'assets';

	// Sender
	wp_register_style( 'hamail-sender', $dir . '/css/hamail.css', [], HAMAIL_VERSION );
	wp_register_script( 'hamail-sender', $dir . '/js/user.js', [ 'backbone', 'jquery-ui-autocomplete' ], HAMAIL_VERSION, true );

	// Setting Helper
	wp_register_style( 'hamail-setting', $dir . '/css/hamail-setting.css', [], HAMAIL_VERSION );
	wp_register_script( 'hamail-setting', $dir . '/js/setting.js', [ 'jquery' ], HAMAIL_VERSION, true );

}, 11 );
