<?php

namespace Hametuha\Hamail\Pattern;

/**
 * Singleton pattern.
 * @package hamail
 */
abstract class Singleton {

	private static $instances = [];

	/**
	 * Constructor.
	 */
	private final function __construct() {
		$this->init();
	}

	/**
	 * Construct.
	 */
	protected function init() {}

	/**
	 * Get transactional email.
	 *
	 * @return static
	 */
	public static function get_instance() {
		$class_name = get_called_class();
		if ( ! isset( self::$instances[ $class_name ] ) ) {
			self::$instances[ $class_name ] = new $class_name();
		}
		return self::$instances[ $class_name ];
	}
}
