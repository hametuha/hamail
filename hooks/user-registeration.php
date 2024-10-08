<?php
/**
 * User notification for registration.
 *
 * @package hamail
 */

/**
 * Filter registration email.
 *
 * @param array   $wp_new_user_notification_email
 * @param WP_User $user
 * @param string  $blogname
 *
 * @return array
 */
add_filter( 'wp_new_user_notification_email', function ( $wp_new_user_notification_email, $user, $blogname ) {
	$wp_new_user_notification_email['message'] = preg_replace( '#<(https?://[^>]+)>#u', '$1', $wp_new_user_notification_email['message'] );
	return $wp_new_user_notification_email;
}, 10, 3 );

/**
 * Filter reset password mail
 *
 * @param string $message
 * @return string
 */
add_filter( 'retrieve_password_message', function ( $message ) {
	$message = preg_replace( '#<(https?://[^>]+)>#u', '$1', $message );
	return $message;
} );
