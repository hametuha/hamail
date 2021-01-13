<?php

namespace Hametuha\Hamail\Pattern;

/**
 * User group model.
 *
 * @package hamail
 *
 * @property-read string $name
 * @property-read string $label
 * @property-read string $description
 * @property-read int    $count
 */
abstract class UserGroup extends Singleton {

	/**
	 * Should return name.
	 *
	 * Name is a key of this class. should be unique.
	 * e.g. hamail_recent_author
	 *
	 * @return string
	 */
	abstract protected function get_name();

	/**
	 * Should return verbose name.
	 *
	 * Will be displayed to user.
	 *
	 * @return string
	 */
	abstract protected function get_label();

	/**
	 * Description of this group.
	 *
	 * @return string
	 */
	protected function get_description() {
		return '';
	}

	/**
	 * Get total user number.
	 *
	 * @return int
	 */
	protected function get_count() {
		return count( $this->get_users() );
	}

	/**
	 * Should return users.
	 *
	 * @return \WP_User[]
	 */
	abstract public function get_users();

	/**
	 * Getter
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'name':
			case 'label':
			case 'count':
			case 'description':
				$method = 'get_' . $name;
				return $this->{$method}();
			default:
				return null;
		}
	}
}
