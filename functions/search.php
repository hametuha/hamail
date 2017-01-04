<?php
/**
 * Search user functions
 *
 * @package hamail
 */

/**
 * Search user name
 *
 * @param string $string
 *
 * @return array
 */
function hamail_search( $string ) {
	global $wpdb;
	$like = "%{$string}%";
	// Build union query
	$unions = [];
	// Search post
	$query = <<<SQL
		SELECT ID AS id, 'post' AS type, post_title AS label, post_author as data
		FROM {$wpdb->posts}
		WHERE post_type NOT IN ( 'revision', 'auto-draft', 'attachment' )
		  AND post_title LIKE %s
		LIMIT 10
SQL;
	$unions[] = $wpdb->prepare( $query, $like );
	// Search term
	$query = <<<SQL
		SELECT term_id AS id, 'term' AS type, name AS label, '' as data
		FROM {$wpdb->terms}
		WHERE name like %s
		LIMIT 10
SQL;
	$unions[] = $wpdb->prepare( $query, $like );
	// Search user
	$query = <<<SQL
		SELECT ID as id, 'user' AS type, display_name AS label, user_email as data
		FROM {$wpdb->users}
		WHERE display_name LIKE %s
		LIMIT 10
SQL;
	$unions[] = $wpdb->prepare( $query, $like );
	// Build union
	/**
	 * hamail_search_union
	 *
	 * Array of select query. Easch of query should
	 * select columns as `id`, `type`, `label` and `data`.
	 *
	 * @filter hamail_search_union
	 * @param array $unions
	 * @param string $string
	 */
	$unions = apply_filters( 'hamail_search_union', $unions, $string );
	$query  = implode( ' UNION ', array_map( function( $query ) {
		return "( {$query} )";
	}, $unions ) ) . ' LIMIT 10';
	/**
	 * hamail_search_query
	 *
	 * Search query for incremental search
	 * @filter hamail_search_query
	 * @param string $query
	 * @param string $search_term
	 * @return string
	 */
	$query = apply_filters( 'hamail_search_query', $query, $string );
	return array_map( function( $result ) {
		switch ( $result->type ) {
			case 'post':
				$result->label = sprintf( __( 'Author of "%s"', 'hamail' ), $result->label );
				$user = get_userdata( $result->data );
				$result->id = $user->ID;
				$result->display_name = $user->display_name;
				$result->data = $user->user_email;
				break;
			case 'term':
				$result->label = sprintf( __( 'Authors of posts in "%s"', 'hamail' ), $result->label );
				break;
			case 'user':
				$result->display_name = $result->label;
				$result->label = sprintf( __( '%s (User)', 'hamail' ), $result->label );
				break;
			default:
				/**
				 * hamail_search_result
				 *
				 * Filter applied to each result.
				 *
				 * @filter hamail_search_result
				 * @return stdClass
				 */
				$result = apply_filters( 'hamail_search_result', $result );
				break;
		}
		return $result;
	}, $wpdb->get_results( $query ) );
}

/**
 * Get authors of posts
 *
 * @param int $term_taxonomy_id
 *
 * @return array
 */
function hamail_term_authors( $term_taxonomy_id ) {
	global $wpdb;
	$query = <<<SQL
		SELECT u.ID as user_id, u.display_name, u.user_email
		FROM {$wpdb->users} AS u
		WHERE u.ID IN (
			SELECT p.post_author FROM {$wpdb->posts} AS p
			LEFT JOIN {$wpdb->term_relationships} AS tr
			ON p.ID = tr.object_id
			WHERE tr.term_taxonomy_id = %d
			GROUP BY p.post_author
		)
SQL;
	$query = $wpdb->prepare( $query, $term_taxonomy_id );
	/**
	 * hamail_term_user_query
	 *
	 * @filter hamail_term_user_query
	 * @param string $query
	 * @param int $term_taxonomy_id
	 * @return string
	 */
	$query = apply_filters( 'hamail_term_user_query', $query, $term_taxonomy_id );
	return $wpdb->get_results( $query );
}
