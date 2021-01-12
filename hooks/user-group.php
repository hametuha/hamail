<?php
/**
 * User group hooks.
 *
 * @package hamail
 */

// Register sync command.

/**
 * Update email if user updated.
 *
 * @param bool  $send_mail
 * @param array $current_user
 * @param array $new_userdata
 */
add_filter( 'send_email_change_email', function( $send_mail, $current_user, $new_userdata ) {
	// Mail is change.
	try {
		if ( ! hamail_enabled() ) {
			return $send_mail;
		}
		$sg             = hamail_client();
		$response       = $sg->client->contactdb()->recipients()->search()->get( null, [
			'email' => $current_user['user_email'],
		] );
		$search_results = json_decode( $response->body() )->recipients;
		if ( ! $search_results ) {
			return $send_mail;
		}
		$update = [];
		foreach ( $search_results as $recipient ) {
			$update[] = [
				'id'    => $recipient->id,
				'email' => $new_userdata['user_email'],
			];
		}
		$sg->client->contactdb()->recipients()->patch( $update );
	} catch ( Exception $e ) {
		// Do nothing.
	}
	return $send_mail;
}, 10, 3 );
