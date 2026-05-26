<?php
/**
 * PHPStan bootstrap file.
 *
 * Defines constants that are set in WordPress but not available
 * during static analysis.
 *
 * @package Hamail
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'HAMAIL_INIT' ) ) {
	define( 'HAMAIL_INIT', __DIR__ . '/hamail.php' );
}

if ( ! defined( 'HAMAIL_VERSION' ) ) {
	define( 'HAMAIL_VERSION', 'phpstan' );
}
