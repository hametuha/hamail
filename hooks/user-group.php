<?php

/**
 * Register admin screen if possible
 */
add_action( 'admin_menu', function() {
	if ( ! hamail_enabled() ) {
		// Do nothing if hamail is not active.
		return;
	}
	// Add sub menu
	add_submenu_page(
		'hamail-send',
		__( 'Group', 'hamail' ),
		__( 'Group', 'hamail' ),
		hamail_capability(),
		'hamail-group',
		'hamail_admin_group'
	);
} );


// Register sync command.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'hamail', \Hametuha\Hamail\Commands\HamailCommands::class );
}

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

		$sg = hamail_client();
		$response = $sg->client->contactdb()->recipients()->search()->get(null, [
			'email' => $current_user['user_email'],
		]);
		$search_results = json_decode( $response->body() )->recipients;
		if ( ! $search_results ) {
			return $send_mail;
		}
		$update = [];
		foreach ( $search_results as $recipient ) {
			$update[] = [
				'id' => $recipient->id,
				'email' => $new_userdata['user_email'],
			];
		}
		$sg->client->contactdb()->recipients()->patch( $update );
	} catch ( Exception $e ) {
		// Do nothing.
	}
	return $send_mail;
}, 10, 3 );
