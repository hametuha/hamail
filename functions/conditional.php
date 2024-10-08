<?php

/**
 * Detect if hamail is enabled.
 *
 * @return bool
 */
function hamail_enabled() {
	return (bool) get_option( 'hamail_api_key' );
}

/**
 * Get capability for hamail
 *
 * @return mixed
 */
function hamail_capability() {
	/**
	 * hamail_user_cap
	 *
	 * Capability to send email
	 *
	 * @filter hamail_user_cap
	 * @param  string $cap
	 * @return string
	 */
	return apply_filters( 'hamail_user_cap', 'list_users' );
}

/**
 * Check if user can send email
 *
 * @param null|int $user_id If null, current use id will be set.
 *
 * @return bool
 */
function hamail_allowed( $user_id = null ) {
	if ( is_null( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	return user_can( $user_id, hamail_capability() );
}

/**
 * Check if hamail is sent
 *
 * @param null|int|WP_Post $post
 *
 * @return bool
 */
function hamail_is_sent( $post = null ) {
	$post = get_post( $post );
	return (bool) get_post_meta( $post->ID, '_hamail_sent', true );
}

/**
 * Get mail sent date
 *
 * @param null|int|WP_Post $post
 * @param string $format
 *
 * @return string
 */
function hamail_sent_at( $post = null, $format = 'Y-m-d H:i' ) {
	$post = get_post( $post );
	$date = get_post_meta( $post->ID, '_hamail_sent', true );
	if ( $date ) {
		return mysql2date( $format, $date );
	} else {
		return '';
	}
}

/**
 * Detect if mail has role
 *
 * @param string $role
 * @param null|int|WP_Post $post
 *
 * @return bool
 */
function hamail_has_role( $role, $post = null ) {
	$post  = get_post( $post );
	$roles = array_filter( explode( ',', get_post_meta( $post->ID, '_hamail_roles', true ) ) );
	return in_array( $role, $roles, true );
}
