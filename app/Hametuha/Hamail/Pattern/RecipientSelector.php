<?php

namespace Hametuha\Hamail\Pattern;

use Hametuha\Hamail\Model\SearchResultItem;

/**
 * Extract recipients
 *
 * @package hamail
 * @since 2.2.0
 */
abstract class RecipientSelector extends AbstractRest {

	/**
	 * @var int Iterms per page.
	 */
	protected $per_page = 10;

	/**
	 * Search target.
	 *
	 * Pagination should be 10. Use `select SQL_CALC_FOUND_ROWS` to get total result.
	 *
	 * @param string $term
	 * @param int    $paged
	 *
	 * @return SearchResultItem[]|\WP_Error
	 */
	abstract protected function search( $term, $paged = 1 );

	/**
	 * Get total count.
	 *
	 * By default, this runs `SELECT FOUND_ROWS()`.
	 *
	 * @param string $term
	 * @param int    $paged
	 * @return int
	 */
	protected function get_search_total( $term, $paged ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
	}

	/**
	 * Search from IDs.
	 *
	 * @param string[] $ids
	 *
	 * @return SearchResultItem[]|\WP_Error
	 */
	abstract protected function get_from_ids( $ids );

	/**
	 * Set arguments.
	 *
	 * @param string $http_method
	 *
	 * @return array|array[]
	 */
	protected function get_args( $http_method ) {
		return [
			'paged' => [
				'type'              => 'int',
				'description'       => __( 'Page number', 'hamail' ),
				'default'           => 1,
				'sanitize_callback' => function( $var ) {
					return max( 1, (int) $var );
				},
			],
			'ids'   => [
				'type'        => 'string',
				'description' => __( 'Uniq IDs of user group.', 'hamail' ),
				'default'     => '',
			],
			'term'  => [
				'type'        => 'string',
				'description' => __( 'Search keywords', 'hamail' ),
				'default'     => '',
			],
		];
	}

	/**
	 * Search API.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	protected function handle_get( $request ) {
		$ids   = $request->get_param( 'ids' );
		$term  = $request->get_param( 'term' );
		$paged = $request->get_param( 'paged' );
		$total = 0;
		if ( $ids ) {
			$result = $this->get_from_ids( array_map( 'trim', explode( ',', $ids ) ) );
			if ( ! is_wp_error( $result ) && $result ) {
				$total = count( $result );
			}
		} elseif ( ! empty( $term ) ) {
			$result = $this->search( $term, $paged );
			if ( ! is_wp_error( $result ) && $result ) {
				$total = $this->get_search_total( $term, $paged );
			}
		} else {
			return new \WP_Error( 'hamail_api_error', __( 'Invalid request. Please specify serch keywords or IDs.', 'hamail' ), [
				'status' => 400,
			] );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = new \WP_REST_Response( array_map( function( $search_result_item ) {
			return $search_result_item->convert();
		}, $result ) );
		$response->set_headers( [
			'X-WP-Total' => $total,
			'X-WP-Next'  => $total > $paged * $this->per_page ? 'more' : 'no',
		] );
		return $response;
	}
}
