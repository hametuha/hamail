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
	// Load setting as array.
	$root_dir = dirname( __DIR__ );
	$json     = $root_dir . '/wp-dependencies.json';
	if ( ! file_exists( $json ) ) {
		return;
	}
	$settings = json_decode( file_get_contents( $json ), true );
	// Register each setting.
	$dir_base = trailingslashit( $root_dir );
	$url_base = plugin_dir_url( __DIR__ );
	foreach ( $settings as $setting ) {
		if ( ! $setting ) {
			continue;
		}
		$path   = $dir_base . $setting['path'];
		$url    = $url_base . $setting['path'];
		$handle = preg_replace( '/\.(js|css)$/', '', basename( $setting['path'] ) );
		$deps   = $setting['deps'];
		switch ( $setting['ext'] ) {
			case 'js':
				// Register JavaScript.
				wp_register_script( $handle, $url, $deps, $setting['hash'], $setting['footer'] );
				break;
			case 'css':
				// This is CSS.
				wp_register_style( $handle, $url, $deps, $setting['hash'], $setting['media'] );
				break;
		}
	}
}, 11 );
