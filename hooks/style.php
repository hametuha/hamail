<?php
/**
 * Style related functions.
 *
 * @package hamail
 * @phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
 */

use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Apply css to mail body.
 *
 * @param string $body
 * @param string $context
 *
 * @return string
 */
function hamail_apply_css_to_body( $body, $context ) {
	if ( 'html' !== $context ) {
		return $body;
	}
	$styles = hamail_get_mail_css();
	if ( ! $styles ) {
		return $body;
	}
	$cssToInlineStyles = new CssToInlineStyles();
	foreach ( $styles as $style ) {
		if ( ! file_exists( $style ) ) {
			continue;
		}
		$css  = file_get_contents( $style );
		$css  = apply_filters( 'hamail_css_content', $css, $style );
		$body = $cssToInlineStyles->convert( $body, $css );
	}
	// Remove body tag.
	if ( preg_match( '/<body[^>]*?>(.*)<\/body>/smu', $body, $matches ) ) {
		$body = $matches[1];
	}
	return $body;
}
add_filter( 'hamail_body_before_send', 'hamail_apply_css_to_body', 10, 2 );

/**
 * Limit block types.
 *
 * @param string[]                $allowed_blocks Allowed blocks.
 * @param WP_Block_Editor_Context $editor_context Editor context.
 * @return string[]
 */
function hamail_allowed_block_types( $allowed_blocks, $editor_context ) {
	if ( empty( $editor_context->post ) ) {
		return $allowed_blocks;
	}
	switch ( $editor_context->post->post_type ) {
		case 'hamail':
		case 'marketing-mail':
			// For HTML mail, these blocks are allowed.
			return [
				'core/paragraph',
				'core/list',
				'core/list-item',
				'core/heading',
				'core/image',
				'core/spacer',
				'core/quote',
				'core/separator',
				'core/shortcode',
				'core/html',
				'core/buttons',
				'core/button',
				'core/group',
				'hamail/col',
				'hamail/row',
				'hamail/table',
			];
		default:
			return $allowed_blocks;
	}
}
add_filter( 'allowed_block_types_all', 'hamail_allowed_block_types', 10, 2 );

/**
 * Register block.
 *
 * @return void
 */
function hamail_register_block() {
	register_block_type( 'hamail/table', [
		'editor_script_handles' => [ 'hamail-block-table' ],
		'editor_style_handles'  => [ 'hamail-block-table' ],
		'style_handles'         => [ 'hamail-block-table-view' ],
	] );
}
add_action( 'init', 'hamail_register_block' );
