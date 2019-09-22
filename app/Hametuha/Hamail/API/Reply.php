<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\AbstractRest;
use Hametuha\Hamail\Service\Extractor;

/**
 * Reply handler
 *
 * @package hamail
 */
class Reply extends AbstractRest {
	
	protected $route = 'reply/(?P<post_id>\d+)/?';
	
	public static $repliable = [ 'feedback' ];
	
	protected function init() {
		parent::init();
		add_filter( 'post_row_actions', [ $this, 'add_row_action' ], 10, 2 );
	}
	
	/**
	 * Get arguments.
	 *
	 * @param string $http_method
	 *
	 * @return array
	 */
	protected function get_args( $http_method ) {
		return [
			'post_id' => [
				'required' => true,
				'type'     => 'integer',
				'description' => 'Post ID to reply.',
				'validate_callback' => function( $var ) {
					$post = get_post( $var );
					return $post && self::can_reply( $post->post_type );
				},
			],
		];
	}
	
	/**
	 * Handle POST request
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|array
	 */
	public function handle_post( $request ) {
		$post = get_post( $request->get_param( 'post_id' ) );
		// Extract body.
		$result = Extractor::process( $post );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$greeting = sprintf( __( 'Dear %s,', '%s' ), $result['name'] );
		if ( get_option( 'hamail_template_id' ) ) {
			$message = "<blockquote>\n{$result['body']}\n</blockquote>";
		} else {
			$message = implode( "\n", array_map( function( $row ) {
				return '> ' . $row;
			}, explode( "\n", $result['body'] ) ) );
		}
		$body = <<<TXT
{$greeting}

{$message}
TXT;
		// Create response.
		$reply = hamail_create_user_contact( $result['subject'], $body, [ $result['to'] ], $post->ID );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		return [
			'url'     => get_edit_post_link( $reply->ID, 'redirect' ),
			'message' => sprintf( __( 'A new reply for #%d is created and saved as draft. Will you edit it now?', 'hamail' ), $post->ID ),
		];
	}
	
	/**
	 * Permission.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_pages' );
	}
	
	/**
	 * Detect if post can be reply.
	 *
	 * @param string $post_type
	 * @return bool
	 */
	public static function can_reply( $post_type ) {
		return in_array( $post_type, self::$repliable );
	}
	
	/**
	 * Add actions to mail list.
	 *
	 * @since 2.1.0
	 * @param string[] $actions
	 * @param \WP_Post  $post
	 * @return string[]
	 */
	public function add_row_action( $actions, $post ) {
		if ( self::can_reply( $post->post_type ) ) {
			wp_enqueue_script( 'hamail-reply' );
			$actions[ 'reply' ] = sprintf( '<a class="hamail-reply-link" href="#" data-post-id="%1$d">%2$s</a>', $post->ID, esc_html__( 'Reply', 'hamail' ) );
		}
		return $actions;
	}
}
