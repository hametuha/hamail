<?php

namespace Hametuha\Hamail\Pattern;

/**
 * Skeleton for Transactional Email.
 *
 * @package Hametuha\Hamail\Pattern
 */
abstract class TransactionalEmail extends Singleton {

	protected $template_name = '';

	/**
	 * Returns title.
	 *
	 * @return string
	 */
	abstract protected function get_subject();

	public function render_body() {

	}

	/**
	 *
	 */
	public static function exec( $recipients = [] ) {
		$instance = static::get_instance();
	}

	/**
	 *
	 */
	public static function test() {

	}
}
