<?php
/**
 * Plugin Name: Hamail
 * Plugin URI: https://wordpress.org/plugins/hamail/
 * Description: A WordPress plugin for sending e-mail via SendGrid.
 * Author: Hametuha INC.
 * Version: nightly
 * Requires at least: 5.9
 * Requires PHP: 7.2
 * Author URI: https://hametuha.co.jp/
 * License: GPL3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: hamail
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) or die();

// Hamail should be loaded at once.
if ( defined( 'HAMAIL_INIT' ) ) {
	return;
}
define( 'HAMAIL_INIT', __FILE__ );

/**
 * Initialize hamail
 *
 * @access private
 */
function hamail_plugins_loaded() {
	// Get version number.
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
		// Find auto loader.
		$auto_loader = __DIR__ . '/vendor/autoload.php';
		if ( ! file_exists( $auto_loader ) ) {
			// translators: %s is composer path.
			throw new Exception( sprintf( __( '[Hamail] PHP autoloader %s is missing. Did you run <code>composer install</code>?', 'hamail' ), $auto_loader ) );
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
		// Transaction mail Sender
		Hametuha\Hamail\API\TransactionMails::get_instance();
		// Marketing Email.
		Hametuha\Hamail\API\MarketingEmail::get_instance();
		// Setting Screen
		Hametuha\Hamail\Ui\SettingsScreen::get_instance();
		// Dynamic emails.
		Hametuha\Hamail\API\DynamicEmails::get_instance();
		// SMTP Handlers
		Hametuha\Hamail\Controller\SmtpController::get_instance();
		// Screens.
		Hametuha\Hamail\Ui\ListTable\RecipientsColumn::get_instance();
		// Enable user sync.
		Hametuha\Hamail\API\UserSync::get_instance();
		// Enable CSV user
		Hametuha\Hamail\API\UserDataGenerator::get_instance();
		// Register command for CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'hamail', Hametuha\Hamail\Commands\HamailCommands::class );
		}
		// Load test file if exists.
		if ( class_exists( 'Hametuha\\HamailDev\\Bootstrap' ) && ! defined( 'HAMAIL_NO_TEST_MODULES' ) ) {
			Hametuha\HamailDev\Bootstrap::get_instance();
		}
	} catch ( Exception $e ) {
		$error = sprintf( '<div class="error"><p>%s</p></div>', $e->getMessage() );
		add_action( 'admin_notices', function () use ( $error ) {
			echo wp_kses_post( $error );
		} );
	}
}
add_action( 'plugins_loaded', 'hamail_plugins_loaded' );
