<?php

namespace Hametuha\Hamail\Pattern;


use Hametuha\Pattern\RestApi;

/**
 * Abstract rest api pattern.
 *
 * @package hamail
 * @since 2.1.0
 */
abstract class AbstractRest extends Singleton {

	/**
	 * @var bool If deprecated, turn this to true.
	 */
	protected $deprecated = false;

	/**
	 * @var string Namespace.
	 */
	protected $namespace = 'hamail/v1';

	/**
	 * Get route
	 *
	 * @return string
	 */
	abstract protected function route();

	/**
	 * Constructor
	 */
	protected function init() {
		if ( ! $this->deprecated ) {
			add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		}
	}

	/**
	 * Register rest route.
	 */
	public function rest_api_init() {
		$args = [];
		foreach ( [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD' ] as $http_method ) {
			$method_name = 'handle_' . strtolower( $http_method );
			if ( method_exists( $this, $method_name ) ) {
				$arg    = [
					'methods'             => $http_method,
					'callback'            => [ $this, 'callback' ],
					'args'                => $this->get_args( $http_method ),
					'permission_callback' => [ $this, 'permission_callback' ],
				];
				$args[] = $arg;
			}
		}
		if ( $args ) {
			register_rest_route( $this->namespace, $this->route(), $args );
		}
	}

	/**
	 * Request callback.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function callback( $request ) {
		$method_name = 'handle_' . strtolower( $request->get_method() );
		try {
			return $this->{$method_name}( $request );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'rest_api_error', $e->getMessage(), [
				'status' => $e->getCode(),
			] );
		}
	}

	/**
	 * Should return arguments.
	 *
	 * @param string $http_method Method name.
	 * @return array
	 */
	abstract protected function get_args( $http_method );

	/**
	 * Permission callback.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function permission_callback( $request ) {
		return true;
	}
}
