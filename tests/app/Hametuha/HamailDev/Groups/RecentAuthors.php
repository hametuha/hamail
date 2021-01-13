<?php

namespace Hametuha\HamailDev\Groups;


use Hametuha\Hamail\Pattern\UserGroup;

/**
 * Recent authors
 *
 * @package hamail
 */
class RecentAuthors extends UserGroup {

	protected function get_label() {
		return 'Recent Authors';
	}

	protected function get_name() {
		return 'recent_authors_dev';
	}

	protected function get_description() {
		return 'Authors who posts within 30 days.';
	}

	public function get_users() {
		global $wpdb;
		$query = <<<SQL
			SELECT u.*
			FROM {$wpdb->posts} AS p
			LEFT JOIN {$wpdb->users} AS u
			ON p.post_author = u.ID
			WHERE p.post_type = 'post'
			  AND p.post_status = 'publish'
		  	  AND p.post_date >= DATE_SUB( NOW(), INTERVAL 30 DAY )
			GROUP BY p.post_author
SQL;
		return array_map( function( $user ) {
			return new \WP_User( $user );
		}, $wpdb->get_results( $query ) );
	}
}
