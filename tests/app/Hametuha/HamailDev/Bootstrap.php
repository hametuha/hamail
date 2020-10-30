<?php

namespace Hametuha\HamailDev;


use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Pattern\UserGroup;

/**
 * Class Bootstrap
 *
 * @package Hametuha\HamailDev
 */
class Bootstrap extends Singleton {

	/**
	 * Initialize test object.
	 */
	protected function init() {
		add_filter( 'hamail_user_groups', [ $this, 'user_groups' ] );
	}

	/**
	 * Add user groups.
	 *
	 * @param array $groups Array of class name.
	 * @return UserGroup[];
	 */
	public function user_groups( $groups ) {
		$dir = __DIR__ . '/Groups';
		if ( ! is_dir( $dir ) ) {
			return $groups;
		}
		foreach ( scandir( $dir ) as $file ) {
			if ( ! preg_match( '/^(.*)\.php$/u', $file, $match ) ) {
				continue;
			}
			$class_name = 'Hametuha\\HamailDev\\Groups\\' . $match[1];
			$groups[]   = $class_name::get_instance();
		}
		return $groups;
	}
}
