<?php
/**
 * Comment filters.
 *
 * @package hamail
 */

/**
 * Exclude hamail-log comments from admin screens.
 *
 * @param WP_Comment_Query $query Comment query.
 */
add_action( 'pre_get_comments', function ( $query ) {
	if ( ! is_admin() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'edit-comments' ], true ) ) {
		return;
	}
	$type_not_in = $query->query_vars['type__not_in'] ?? [];
	if ( ! is_array( $type_not_in ) ) {
		$type_not_in = [];
	}
	$type_not_in[]                     = 'hamail-log';
	$query->query_vars['type__not_in'] = $type_not_in;
} );
