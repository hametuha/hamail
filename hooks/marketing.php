<?php
/**
 * Marketing taxonomy.
 *
 * @package hamail
 */

/**
 * Register taxonomy.
 */
add_action( 'init', function() {
	$post_types = apply_filters( 'hamail_post_types_in_marketing', [ \Hametuha\Hamail\API\MarketingEmail::POST_TYPE ] );
	register_taxonomy( hamail_marketing_category_taxonomy(), $post_types, [
		'label'             => __( 'Marketing Category', 'hamail' ),
		'hierarchical'      => false,
		'public'            => false,
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_nav_menus' => false,
		'description'       => __( 'Used as marketing category.', 'hamail' ),
		'show_in_rest'      => true,
		'show_tagcloud'     => false,
		'show_admin_column' => true,
	] );
}, 9 );

/**
 * Marketing taxonomy.
 *
 * @return string
 */
function hamail_marketing_category_taxonomy() {
	return 'marketing-category';
}
