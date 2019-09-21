<?php

namespace Hametuha\Hamail\Service;


class Extractor {
	
	/**
	 * Avoid "new"
	 */
	private function __construct() {
	}
	
	/**
	 * Extract mail from post object.
	 *
	 * @param int|null|\WP_Error
	 */
	public static function process( $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return new \WP_Error( 'hamail_extract_failure', __( 'Post does not exist.', 'hamail' ), [
				'status' => 400,
			] );
		}
		$method = 'extract_' . str_replace( '-', '_', $post->post_type );
		if ( is_callable( static::class . '::' . $method ) ) {
			$result = call_user_func_array( static::class . '::' . $method, [ $post ] );
			if ( is_wp_error( $result ) ) {
				return $result;
			} elseif ( ! isset( $result['to'] ) || ! is_email( $result['to'] ) ) {
				return new \WP_Error( 'hamail_extract_failure', __( 'Failed to extract email.', 'hamail' ) );
			} else {
				return array_merge( [
					'name'    => __( 'Guest', 'hamail' ),
					'body'    => '',
					'subject' => __( 'Contact', 'hamail' ),
				], $result );
			}
		} else {
			return new \WP_Error( 'hamail_extract_failure', __( 'This post type is not supported.', 'hamail' ), [
				'status' => 400,
			] );
		}
	}
	
	/**
	 * Extract mail body from Jetpack feedback.
	 *
	 * @param \WP_Post $post
	 * @return \WP_Error|array
	 */
	protected static function extract_feedback( $post ) {
		list( $body, $args ) = explode( '<!--more-->', $post->post_content );
		$result = [
			'body' => trim( $body ),
		];
		if ( preg_match( '/AUTHOR EMAIL: (.*)/u', $args, $matches ) ) {
			$result['to'] = trim( $matches[1] );
		}
		if ( preg_match( '/AUTHOR: (.*)/u', $args, $matches ) ) {
			$result['name'] = trim( $matches[1] );
		}
		if ( preg_match( '/SUBJECT: (.*)/u', $args, $matches ) ) {
			$result['subject'] = trim( $matches[1] );
		}
		return $result;
	}
}
