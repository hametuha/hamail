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
				esc_html( $wp_error->get_error_code() ),
				esc_html( implode( "\n", $wp_error->get_error_messages() ) ),
				esc_html( var_export( $wp_error->get_error_data(), true ) )
			),
		] );
	}

	/**
	 * Display error logs.
	 *
	 * @param \WP_Post $post
	 * @param int      $per_page
	 * @param int      $paged
	 * @return array<array{id:int, author:int, content:string, date:string}>
	 */
	public function get_logs( $post, $per_page = 20, $paged = 1 ) {
		$comment_query = new \WP_Comment_Query( [
			'post_id' => $post->ID,
			'type'    => 'hamail-log',
			'orderby' => 'comment_date',
			'order'   => 'DESC',
			'number'  => $per_page,
			'paged'   => $paged,
		] );
		return array_map( function ( \WP_Comment $comment ) {
			return [
				'id'      => $comment->comment_ID,
				'author'  => $comment->comment_author,
				'content' => $comment->comment_content,
				'date'    => $comment->comment_date,
			];
		}, $comment_query->get_comments() );
	}
}
