<?php

namespace Hametuha\HamailDev\Groups;


use Hametuha\Hamail\Model\SearchResultItem;
use Hametuha\Hamail\Pattern\RecipientSelector;

/**
 * Test REST API for user search.
 *
 * @package hamail
 */
class TagAuthor extends RecipientSelector {

	protected function route() {
		return 'search/tag-authors';
	}

	protected function get_from_ids( $ids ) {
		// TODO: Implement get_from_ids() method.
	}
	
	/**
	 * Search tags matches query.
	 *
	 * @param string $term
	 * @param int    $paged
	 *
	 * @return SearchResultItem[]|void|\WP_Error
	 */
	protected function search( $term, $paged = 1 ) {
		global $wpdb;
		// TODO: Implement search() method.
	}
}
