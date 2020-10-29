<?php
/*
Plugin Name: Hamail
Plugin URI: https://wordpress.org/plugins/hamail/
Description: A WordPress plugin for sending e-mail via Sendgrid.
Author: Hametuha INC.
Version: 2.1.0
PHP Version: 5.6
Author URI: https://hametuha.co.jp/
License: GPL3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: hamail
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die();

/**
 * Initialize hamail
 */
function hamail_plugins_loaded( $plugin ) {
	if ( basename( $plugin ) !== basename( __FILE__ ) ) {
		return;
	}

	// Get version number
	$info = get_file_data( __FILE__, [
		'version'     => 'Version',
		'php_version' => 'PHP Version',
		'domain'      => 'Text Domain',
	] );

	define( 'HAMAIL_VERSION', $info['version'] );

	load_plugin_textdomain( $info['domain'], false, basename( __DIR__ ) . '/languages' );

	try {
		if ( version_compare( phpversion(), $info['php_version'], '<' ) ) {
			// translators: %1$s is required PHP version, %2$s is current PHP version.
			throw new Exception( sprintf( __( '[Hamail] Sorry, this plugin requires PHP %1$s and over, but your PHP is %2$s.', 'hamail' ), $info['php_version'], phpversion() ) );
		}
		// Find auto loader
		$auto_loader = __DIR__ . '/vendor/autoload.php';
		if ( ! file_exists( $auto_loader ) ) {
			// translators: %s is composer path.
			throw new Exception( sprintf( __( '[Hamail] PHP auto loader %s is missing. Did you run <code>composer install</code>?', 'hamail' ), $auto_loader ) );
		}
		require $auto_loader;
		// Load functions.
		foreach ( array( 'functions', 'hooks' ) as $dir_name ) {
			$dir = __DIR__ . '/' . $dir_name . '/';
			foreach ( scandir( $dir ) as $file ) {
				if ( preg_match( '#^[^.](.*)\.php$#u', $file ) ) {
					require $dir . $file;
				}
			}
		}
	} catch ( Exception $e ) {
		$error = sprintf( '<div class="error"><p>%s</p></div>', $e->getMessage() );
		add_action( 'admin_notices', function () use ( $error ) {
			echo wp_kses_post( $error );
		} );
	}
}

add_action( 'plugin_loaded', 'hamail_plugins_loaded' );

