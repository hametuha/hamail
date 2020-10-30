<?php
/**
 * Functions related to user groups.
 *
 * @package hamail
 */


/**
 * Get available user groups.
 *
 * @since 2.2.0
 * @return \Hametuha\Hamail\Pattern\UserGroup[];
 */
function hamail_user_groups() {
	$user_groups = apply_filters( 'hamail_user_groups', [] );
	return array_filter( $user_groups, function( $group ) {
		return is_subclass_of( $group, \Hametuha\Hamail\Pattern\UserGroup::class );
	} );
}

/**
 * Get user group assigned to post.
 *
 * @param WP_Post|null|int $post
 * @return \Hametuha\Hamail\Pattern\UserGroup[]
 */
function hamail_get_groups( $post = null ) {

}
