<?php

namespace Hametuha\Hamail\Utility;

/**
 * Logger
 *
 * @package hamail
 */
trait Logger {

	/**
	 * Save error log as comment.
	 *
	 * @param \WP_Error $wp_error
	 * @param \WP_Post  $post
	 */
	public function error_log( $wp_error, $post ) {
		wp_insert_comment( [
			'comment_post_ID' => $post->ID,
			'comment_type'    => 'hamail-log',
			'comment_status'  => 'approved',
			'comment_author'  => $post->post_author,
			'comment_content' => sprintf(
				"[%s] %s\n\n<pre>%s</pre>",
				$wp_error->get_error_code(),
				implode( "\n", $wp_error->get_error_messages() ),
				var_export( $wp_error->get_error_data(), true )
			),
		] );
	}
}
