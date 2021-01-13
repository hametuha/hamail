<?php

namespace Hametuha\HamailDev\Groups;


use Hametuha\Hamail\Pattern\UserQueryGroup;

/**
 * List of recent users.
 *
 * @package hamail
 */
class RecentUsers extends UserQueryGroup {

	/**
	 * Label
	 *
	 * @return string
	 */
	protected function get_label() {
		return 'Recent users';
	}

	/**
	 * Unique name.
	 *
	 * @return string
	 */
	protected function get_name() {
		return 'recent_users_dev';
	}

	/**
	 * Descriptions
	 *
	 * @return string
	 */
	protected function get_description() {
		return 'Users registered in recent 30 days.';
	}

	/**
	 * Query for WP_User_Query
	 *
	 * @return array
	 */
	protected function query() {
		$daysago_30 = current_time( 'timestamp' ) - 30 * 60 * 60 * 24;
		return [
			'date_query' => [
				[
					'after'     => date_i18n( 'Y-m-d', $daysago_30 ),
					'inclusive' => true,
				],
			],
		];
	}
}
