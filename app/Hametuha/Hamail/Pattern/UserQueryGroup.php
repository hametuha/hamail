<?php

namespace Hametuha\Hamail\Pattern;

/**
 * User group which can simplified to WP_User_Query
 *
 * @package hamail
 */
abstract class UserQueryGroup extends UserGroup {

	/**
	 * Should return user count.
	 *
	 * @return int
	 */
	protected function get_count() {
		$query = array_merge( [
			'count_total' => true,
			'number'      => 1,
		], $this->query() );
		$user_query = new \WP_User_Query( $query );
		return $user_query->get_total();
	}

	/**
	 * Should return WP_Users.
	 *
	 * @return \WP_User[]
	 */
	public function get_users() {
		$query = new \WP_User_Query( array_merge( $this->query(), [
			'count_total' => false,
		] ) );
		return $query->get_results();
	}

	/**
	 * Arguments for WP_User query.
	 *
	 * @return array
	 */
	abstract protected function query();
}
