<?php
/**
 * Mail post types must not be readable via REST API by the public.
 *
 * @package hamail
 */

use Hametuha\Hamail\API\MarketingEmail;
use Hametuha\Hamail\API\TransactionMails;

/**
 * Test REST API read access to mail post types.
 */
class TestRestPrivacy extends WP_UnitTestCase {

	/**
	 * Published post IDs keyed by post type.
	 *
	 * @var int[]
	 */
	private $posts = [];

	/**
	 * Register post types and create published mails.
	 */
	public function set_up() {
		parent::set_up();
		// Post types are registered only when hamail is enabled.
		update_option( 'hamail_api_key', 'dummy' );
		TransactionMails::get_instance()->register_mail_post_type();
		MarketingEmail::get_instance()->register_post_type();
		delete_option( 'hamail_api_key' );
		// Never send mails while testing. Hooks are restored after each test.
		remove_all_actions( 'save_post_hamail' );
		remove_all_actions( 'save_post_' . MarketingEmail::POST_TYPE );
		remove_all_actions( 'transition_post_status' );
		// Rebuild REST server to register routes for the post types above.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
		foreach ( [ 'hamail', MarketingEmail::POST_TYPE ] as $post_type ) {
			$this->posts[ $post_type ] = self::factory()->post->create( [
				'post_type'    => $post_type,
				'post_status'  => 'publish',
				'post_title'   => 'Private subject',
				'post_content' => 'Private body',
			] );
		}
	}

	/**
	 * Reset REST server.
	 */
	public function tear_down() {
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	/**
	 * Post types and their REST bases.
	 *
	 * @return array
	 */
	public function post_type_provider() {
		return [
			'transaction mail' => [ 'hamail' ],
			'marketing mail'   => [ 'marketing-mail' ],
		];
	}

	/**
	 * Send GET request.
	 *
	 * @param string $route Route.
	 * @return WP_REST_Response
	 */
	private function get( $route ) {
		return rest_do_request( new WP_REST_Request( 'GET', $route ) );
	}

	/**
	 * Anonymous users cannot list or read published mails.
	 *
	 * @dataProvider post_type_provider
	 * @param string $post_type Post type.
	 */
	public function test_anonymous_cannot_read( $post_type ) {
		wp_set_current_user( 0 );
		$list = $this->get( '/wp/v2/' . $post_type );
		$this->assertSame( 401, $list->get_status() );
		$this->assertArrayNotHasKey( 'X-WP-Total', $list->get_headers() );
		$single = $this->get( '/wp/v2/' . $post_type . '/' . $this->posts[ $post_type ] );
		$this->assertSame( 401, $single->get_status() );
	}

	/**
	 * Logged-in users without edit capability cannot read either.
	 *
	 * @dataProvider post_type_provider
	 * @param string $post_type Post type.
	 */
	public function test_subscriber_cannot_read( $post_type ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $this->get( '/wp/v2/' . $post_type )->get_status() );
		$this->assertSame( 403, $this->get( '/wp/v2/' . $post_type . '/' . $this->posts[ $post_type ] )->get_status() );
	}

	/**
	 * Editors keep access so that the block editor works.
	 *
	 * @dataProvider post_type_provider
	 * @param string $post_type Post type.
	 */
	public function test_editor_can_read( $post_type ) {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$list = $this->get( '/wp/v2/' . $post_type );
		$this->assertSame( 200, $list->get_status() );
		$this->assertCount( 1, $list->get_data() );
		$single = $this->get( '/wp/v2/' . $post_type . '/' . $this->posts[ $post_type ] );
		$this->assertSame( 200, $single->get_status() );
		$this->assertSame( 'Private subject', $single->get_data()['title']['rendered'] );
	}
}
