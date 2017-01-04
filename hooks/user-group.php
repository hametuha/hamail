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

/**
 *
 */
add_action( 'rest_api_init', function() {

} );
