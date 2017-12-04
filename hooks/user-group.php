<?php

/**
 * Register admin screen if possible
 */
add_action( 'admin_menu', function() {
	if ( ! hamail_enabled() ) {
		// Do nothing if
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

// Update email if user updated.
