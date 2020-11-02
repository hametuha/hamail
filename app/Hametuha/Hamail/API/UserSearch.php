<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Model\SearchResultItem;
use Hametuha\Hamail\Pattern\RecipientSelector;

/**
 * Search endpoint to search users.
 *
 * @package hamail
 */
class UserSearch extends RecipientSelector {

	/**
	 * @var \WP_User_Query Query result;
	 */
	protected $query_result = null;

	protected function route() {
		return 'search/users';
	}

	/**
	 * Get users from IDs.
	 *
	 * @param string[] $ids
	 *
	 * @return SearchResultItem[]
	 */
	protected function get_from_ids( $ids ) {
		$ids = array_map( 'intval', array_filter( $ids, 'is_numeric' ) );
		if ( empty( $ids ) ) {
			return [];
		}
		return $this->user_to_item( [
			'include' => $ids,
		] );
	}

	/**
	 * Search users.
	 *
	 * @param string $term
	 * @param int    $paged
	 *
	 * @return SearchResultItem[]
	 */
	protected function search( $term, $paged = 1 ) {
		if ( empty( $term ) ) {
			return [];
		}
		return $this->user_to_item( [
			'search'         => '*' . $term . '*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'number'         => $this->per_page,
			'paged'          => $paged,
		] );
	}

	/**
	 * Get total count.
	 *
	 * @param string $term
	 * @param int    $paged
	 *
	 * @return int
	 */
	protected function get_search_total( $term, $paged ) {
		if ( $this->query_result ) {
			return $this->query_result->get_total();
		} else {
			return 0;
		}
	}

	/**
	 * Convert user_query to data.
	 *
	 * @param array $user_query
	 * @return SearchResultItem[]
	 */
	protected function user_to_item( $user_query ) {
		$this->query_result = new \WP_User_Query( $user_query );
		return array_map( function( \WP_User $user ) {
			return new SearchResultItem( $user->ID, sprintf( '#%d %s', $user->ID, $user->display_name ) );
		}, $this->query_result->get_results() );
	}

	/**
	 * Permission callback.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool|\WP_Error
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_posts' );
	}
}
