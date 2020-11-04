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

	protected function search( $term, $paged = 1 ) {
		// TODO: Implement search() method.
	}
}
