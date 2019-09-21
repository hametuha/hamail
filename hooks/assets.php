<?php
/**
 * Assets
 */

/**
 * Load all API's
 */
add_action( 'plugins_loaded', function() {
	$dir = [ 'API' ];
	foreach ( $dir as $d ) {
		$path = dirname( __DIR__ ) . '/app/Hametuha/Hamail/' . $d;
		if ( ! is_dir( $path ) ) {
			continue;
		}
		foreach ( scandir( $path ) as $file ) {
			if ( ! preg_match( '/^([^._].*)\.php$/u', $file, $matches ) ) {
				continue;
			}
			$class_name = "Hametuha\\Hamail\\{$d}\\{$matches[1]}";
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			$class_name::get_instance();
		}
	}
} );

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
	
	// Reply Helper.
	wp_register_script( 'hamail-reply', $dir . '/js/reply.js', [ 'wp-api-request' ], HAMAIL_VERSION, true );
}, 11 );
