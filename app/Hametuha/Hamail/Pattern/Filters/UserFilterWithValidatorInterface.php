<?php
namespace Hametuha\Hamail\Pattern\Filters;

/**
 *
 */
interface UserFilterWithValidatorInterface {
	/**
	 * Validate filter values.
	 *
	 * @param string[]         $values;
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|bool
	 */
	public function validate_callback( $values, \WP_REST_Request $request );
}
