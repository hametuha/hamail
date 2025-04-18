<?php
/**
 * Marketing related functions
 *
 * @package hamail
 */

/**
 * Get available list via API
 *
 * @param string|null $no_list Label for no list is selected.
 * @return array
 */
function hamail_available_lists( $no_list = '' ) {
	$return = [];
	if ( ! is_null( $no_list ) ) {
		if ( ! $no_list ) {
			$no_list = __( 'No sync', 'hamail' );
		}
		$return[''] = $no_list;
	}
	try {
		$sg       = hamail_client();
		$response = $sg->client->contactdb()->lists()->get();
		if ( 200 === $response->statusCode() ) {
			$lists = json_decode( $response->body() )->lists;
			foreach ( $lists as $list ) {
				$return[ $list->id ] = sprintf( '%s(%d)', $list->name, $list->recipient_count );
			}
		}
	} catch ( \Exception $e ) {
		error_log( sprintf( "[HAMAIL_LOG %d]\t%s", $e->getCode(), $e->getMessage() ) );
	}
	return $return;
}

/**
 * Get all segments.
 *
 * @return array[]
 */
function hamail_available_segments() {
	foreach ( hamail_available_lists( null ) as $id => $label ) {
		$segments[] = [
			'type'  => 'list',
			'id'    => $id,
			'label' => $label,
		];
	}
	$segments = [];
	try {
		$sg = hamail_client();
		// Get lists.
		$response = $sg->client->contactdb()->lists()->get();
		if ( 200 === $response->statusCode() ) {
			$lists = json_decode( $response->body() )->lists;
			foreach ( $lists as $list ) {
				$segments[] = [
					'type'    => 'list',
					'id'      => $list->id,
					'label'   => $list->name,
					'count'   => $list->recipient_count,
					'list_id' => $list->id,
				];
			}
		}
		// Get segments.
		$response = $sg->client->contactdb()->segments()->get();
		if ( 200 === $response->statusCode() ) {
			$lists = json_decode( $response->body() )->segments;
			foreach ( $lists as $list ) {
				$segments[] = [
					'type'    => 'segment',
					'id'      => $list->id,
					'label'   => $list->name,
					'count'   => $list->recipient_count,
					'list_id' => $list->list_id,
				];
			}
		}
	} catch ( \Exception $e ) {
		error_log( sprintf( "[HAMAIL_LOG %d]\t%s", $e->getCode(), $e->getMessage() ) );
	}
	return $segments;
}

/**
 * Get sender ID list.
 *
 * @param string|null $no_list
 * @return array
 */
function hamail_available_senders( $no_list = '' ) {
	$return = [];
	if ( ! is_null( $no_list ) ) {
		if ( ! $no_list ) {
			$no_list = __( 'No Sender ID', 'hamail' );
		}
		$return[''] = $no_list;
	}
	try {
		$sg       = hamail_client();
		$response = $sg->client->senders()->get();
		if ( 200 === $response->statusCode() ) {
			$lists = json_decode( $response->body() );
			if ( $lists ) {
				foreach ( $lists as $sender ) {
					$return[ $sender->id ] = sprintf( '%s <%s>', $sender->nickname, $sender->from->email );
				}
			}
		}
	} catch ( \Exception $e ) {
		error_log( sprintf( "[HAMAIL_LOG %d]\t%s", $e->getCode(), $e->getMessage() ) );
	}
	return $return;
}

/**
 * Get list to sync
 *
 * @return string
 */
function hamail_active_list() {
	return get_option( 'hamail_list_to_sync', '' );
}

/**
 * Get custom fields list.
 *
 * @return array
 */
function hamail_get_custom_fields() {
	$fields = [
		'email'      => false,
		'first_name' => false,
		'last_name'  => false,
	];
	if ( ! hamail_enabled() ) {
		return $fields;
	}
	$sg = hamail_client();
	try {
		$response      = $sg->client->contactdb()->custom_fields()->get();
		$custom_fields = json_decode( $response->body() );
		if ( isset( $custom_fields->custom_fields ) ) {
			foreach ( $custom_fields->custom_fields as $field ) {
				$fields[ $field->name ] = $field->id;
			}
		}
	} catch ( \Exception $e ) {
		// Do nothing.
	} finally {
		return $fields;
	}
}

/**
 * Get fields format.
 *
 * @return WP_Error|array
 */
function hamail_fields_array() {
	try {
		$format = get_option( 'hamail_fields_to_sync' );
		if ( ! trim( $format ) ) {
			throw new \Exception( __( 'Fields mapping is empty.' ), 200 );
		}
		$rows = array_map( function ( $row ) {
			return array_filter( array_map( 'trim', explode( ',', trim( $row ) ) ) );
		}, preg_split( '#\r\n#u', $format ) );
		if ( 2 !== count( $rows ) ) {
			throw new Exception( __( 'Fields mapping is mal format.', 'hamail' ), 400 );
		}
		list( $sendgrid, $wordpress ) = $rows;
		if ( count( $sendgrid ) < 1 ) {
			throw new Exception( __( 'No field mapping record exists.', 'hamail' ), 400 );
		}
		if ( count( $sendgrid ) !== count( $wordpress ) ) {
			throw new Exception( __( 'Each field mapping row should be same length.', 'hamail' ), 400 );
		}
		$result = [];
		for ( $i = 0, $l = count( $sendgrid ); $i < $l; $i++ ) {
			$result[ $sendgrid[ $i ] ] = $wordpress[ $i ];
		}
		return $result;
	} catch ( Exception $e ) {
		return new WP_Error( 'invalid_option', $e->getMessage(), [
			'status' => $e->getCode(),
		] );
	}
}

/**
 * Get available role list.
 *
 * @return array
 */
function hamail_available_roles() {
	$roles = [
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
		'customer', // WooCommerce.
		'seller', // Makibishi.
	];
	/**
	 * hamail_available_roles
	 *
	 * Get user role array
	 *
	 * @package hamail
	 * @param array $roles Array of roles to sync.
	 * @return array
	 */
	return apply_filters( 'hamail_available_roles', $roles );
}

/**
 * Get fields data to save.
 *
 * @param WP_User $user
 * @return array|WP_Error
 */
function hamail_fields_to_save( WP_User $user ) {
	$fields = hamail_fields_array();
	if ( is_wp_error( $fields ) ) {
		return $fields;
	}
	$applied_fields = [];
	foreach ( $fields as $sendgrid => $wordpress ) {
		if ( 'role' === $wordpress ) {
			// Get user role.
			$roles      = hamail_available_roles();
			$fixed_role = '';
			foreach ( $user->roles as $role ) {
				if ( in_array( $role, $roles, true ) ) {
					$fixed_role = $role;
				}
			}
			$applied_fields[ $sendgrid ] = $fixed_role;
		} elseif ( isset( $user->{$wordpress} ) ) {
			$applied_fields[ $sendgrid ] = $user->{$wordpress};
		} else {
			$applied_fields[ $sendgrid ] = get_user_meta( $user->ID, $sendgrid, true );
		}
	}
	$key = get_option( 'hamail_site_key' );
	if ( $key ) {
		$applied_fields[ $key ] = home_url();
	}
	if ( $applied_fields ) {
		/**
		 * hamail_user_field
		 *
		 * @param array   $applied_fields
		 * @param WP_User $user
		 */
		return apply_filters( 'hamail_user_field', $applied_fields, $user );
	} else {
		return new WP_Error( 'no_field_data', __( 'Fields data to sync is empty.', 'hamail' ), [
			'status' => 400,
		] );
	}
}

/**
 * Sync account
 *
 * @deprecated
 * @param int $paged
 * @param int $per_page
 */
function hamail_sync_account( $paged = 1, $per_page = 1000 ) {
	$list = (int) get_option( 'hamail_list_to_sync' );
	$key  = get_option( 'hamail_site_key' );
	if ( ! $list || ! $key ) {
		return new WP_Error( 'bad_option', __( 'List or site key is not set.', 'hamail' ), [
			'status' => 500,
		] );
	}
	// Get all contacts and push them to list.
	$sg = hamail_client();
	try {
		$ids        = [];
		$response   = $sg->client->contactdb()->recipients()->get(null, [
			'page'      => $paged,
			'page_size' => $per_page,
		] );
		$recipients = json_decode( $response->body() )->recipients;
		if ( ! $recipients ) {
			return false;
		}
		foreach ( $recipients as $recipient ) {
			foreach ( $recipient->custom_fields as $field ) {
				if ( ( $key === $field->name ) && $field->value ) {
					$ids[] = $recipient->id;
				}
			}
		}
		if ( ! $ids ) {
			return false;
		}
		// Push to list.
		$response = $sg->client->contactdb()->lists()->_( $list )->recipients()->post( $ids );
		return 201 === $response->statusCode();
	} catch ( \Exception $e ) {
		return new WP_Error( 'response_failed', $e->getMessage(), [
			'status' => $e->getCode(),
		] );
	}
}

/**
 * Get recipient ID on send grid.
 *
 * @param int $user_id
 * @return string
 */
function hamail_get_recipient_id( $user_id ) {
	$fields = hamail_fields_array();
	if ( is_wp_error( $fields ) ) {
		return '';
	}
	$user_id_key = array_search( 'ID', $fields, true );
	$sg          = hamail_client();
	$response    = $sg->client->contactdb()->recipients()->search()->get(null, [
		$user_id_key => $user_id,
	] );
	if ( 200 !== $response->statusCode() ) {
		return '';
	}
	$result = json_decode( $response->body() );
	if ( ! $result->recipient_count ) {
		return '';
	}
	foreach ( $result->recipients as $recipient ) {
		return $recipient->id;
	}
}

/**
 * Marketing taxonomy.
 *
 * @return string
 */
function hamail_marketing_category_taxonomy() {
	return 'marketing-category';
}
