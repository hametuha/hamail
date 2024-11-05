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
		$body = $cssToInlineStyles->convert( $body, $css );
	}
	// Remove body tag.
	if ( preg_match( '/<body[^>]*?>(.*)<\/body>/smu', $body, $matches ) ) {
		$body = $matches[1];
	}
	return $body;
}
add_filter( 'hamail_body_before_send', 'hamail_apply_css_to_body', 10, 2 );
