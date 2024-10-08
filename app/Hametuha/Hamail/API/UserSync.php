<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Utility\ApiUtility;
use SendGrid\Response;

/**
 * User sync patter.
 *
 * @package Hametuha\Hamail\API
 */
class UserSync extends Singleton {

	use ApiUtility;

	/**
	 * Constructor.
	 */
	protected function init() {
		if ( hamail_active_list() ) {
			add_action( 'hamail_user_email_changed', [ $this, 'email_change_handler' ], 10, 2 );
			add_action( 'profile_update', [ $this, 'profile_change_handler' ], 200, 2 );
			add_action( 'user_register', [ $this, 'created_handler' ], 200 );
			add_action( 'delete_user', [ $this, 'delete_handler' ] );
		}
	}

	/**
	 * Push user data.
	 *
	 * @param int|\WP_User|string $user User to push.
	 * @return string|\WP_Error Sendgrid ID on success.
	 */
	public function push( $user ) {
		$user = $this->ensure_wp_user( $user );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$data = hamail_fields_to_save( $user );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		try {
			$sg       = hamail_client();
			$existing = $this->get_recipient( $user->ID );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			} elseif ( $existing ) {
				// User exists.
				$response = $sg->client->contactdb()->recipients()->patch( [
					$data,
				] );
			} else {
				// No user.
				$response = $sg->client->contactdb()->recipients()->post( [
					$data,
				] );
			}
			$result = $this->convert_response_to_error( $response );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			// Add recipients to list.
			if ( $existing ) {
				$recipient_ids = [ $existing['id'] ];
				update_user_meta( $user->ID, 'hamail_last_synced', current_time( 'mysql' ) );
			} else {
				$recipient_ids = $result['persisted_recipients'];
				update_user_meta( $user->ID, 'hamail_first_synced', current_time( 'mysql' ) );
				update_user_meta( $user->ID, 'hamail_last_synced', current_time( 'mysql' ) );
			}
			$list = $this->push_to_list( $recipient_ids );
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			return $recipient_ids[0];
		} catch ( \Exception $e ) {
			return new \WP_Error( 'hamail_user_api_error', $e->getMessage(), [
				'code' => $e->getCode(),
			] );
		}
	}

	/**
	 * Bulk push users.
	 *
	 * @deprecated Not reliable.
	 * @param array $query_params
	 * @return int|\WP_Error Updated count. If failed, return error.
	 */
	public function bulk_push( $query_params ) {
		if ( ! hamail_active_list() ) {
			return new \WP_Error( 'hamail_user_api_error', __( 'No account is set.', 'hamail' ) );
		}
		$params           = array_merge( [
			'number' => 1000,
		], $query_params );
		$params['number'] = min( 1000, $params['number'] );
		$offset           = 0;
		$errors           = new \WP_Error();
		$sg               = hamail_client();
		do {
			$has_next         = false;
			$params['offset'] = $offset;
			$user_query       = new \WP_User_Query( $params );
			$offset          += $user_query->get_total();
			$users_data       = [];
			foreach ( $user_query->get_results() as $user ) {
				$user_data = hamail_fields_to_save( $user );
				if ( is_wp_error( $user_data ) ) {
					$errors->add( $user_data->get_error_code(), $user_data->get_error_message() );
				} else {
					$users_data[] = $user_data;
				}
			}
			if ( $users_data ) {
				// Sendgrid
				$response = $sg->client->contactdb()->recipients()->post( $users_data );
				$result   = $this->convert_response_to_error( $response );
				if ( is_wp_error( $result ) ) {
					$errors->add( $result->get_error_code(), $result->get_error_message() );
				} else {
					// Recipients added.
					$recipients_ids = $result['persisted_recipients'];
					if ( $recipients_ids ) {
						$result = $this->push_to_list( $recipients_ids );
						if ( is_wp_error( $result ) ) {
							$errors->add( $result->get_error_code(), $result->get_error_message() );
						}
					}
					$has_next = true;
					sleep( 1 );
				}
			}
		} while ( $has_next );
		return $errors->get_error_messages() ? $errors : $offset;
	}

	/**
	 * Get single recipient.
	 *
	 * @param int|string User ID or email.
	 * @param array|\WP_Error
	 */
	public function get_recipient( $user_id_or_email ) {
		if ( is_numeric( $user_id_or_email ) ) {
			$user = get_userdata( $user_id_or_email );
			if ( ! $user ) {
				return new \WP_Error( 'hamail_user_api_error', __( 'No user found.', 'hamail' ), [
					'status' => 404,
				] );
			}
			$email = $user->user_email;
		} else {
			$email = $user_id_or_email;
		}
		try {
			$response = $this->search( [
				'email' => $email,
			] );
			if ( is_wp_error( $response ) ) {
				return [];
			} elseif ( empty( $response ) ) {
				return [];
			} else {
				foreach ( $response as $user ) {
					return $user;
				}
				return [];
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'hamail_user_api_error', $e->getMessage(), [
				'status' => $e->getCode(),
			] );
		}
	}

	/**
	 * Ensure value is WP_User.
	 *
	 * @param string|int|\WP_User $value
	 * @return \WP_User|\WP_Error
	 */
	protected function ensure_wp_user( $value ) {
		if ( is_a( $value, 'WP_User' ) ) {
			// Do nothing.
			return $value;
		} else {
			$error = new \WP_Error( 'hamail_user_value', sprintf( __( 'User does not exist: %d', 'hamail' ), $value ) );
			if ( is_numeric( $value ) ) {
				$data = get_userdata( $value );
				return $data ?: $error;
			} else {
				$user_id = email_exists( $value );
				return $user_id ? get_userdata( $user_id ) : $error;
			}
		}
	}

	/**
	 * Push recipients ID to list.
	 *
	 * @see https://sendgrid.kke.co.jp/docs/API_Reference/Web_API_v3/Marketing_Campaigns/contactdb.html#Add-Multiple-Recipients-to-a-List-POST
	 * @param string[] $recipient_ids
	 * @return int|\WP_Error
	 */
	public function push_to_list( $recipient_ids ) {
		$limit  = ceil( count( $recipient_ids ) / 1000 );
		$errors = new \WP_Error();
		$sg     = hamail_client();
		$added  = 0;
		for ( $i = 0; $i < $limit; $i++ ) {
			$offset = $i * 1000;
			$max    = $offset + 1000;
			$ids    = [];
			for ( $index = $offset + 0; $index < $max; $index++ ) {
				if ( isset( $recipient_ids[ $index ] ) ) {
					$ids[] = $recipient_ids[ $index ];
				} else {
					break 1;
				}
			}
			$response = $sg->client->contactdb()->lists()->_( hamail_active_list() )->recipients()->post( $ids );
			$result   = $this->convert_response_to_error( $response );
			if ( is_wp_error( $result ) ) {
				$errors->add( $result->get_error_code(), $result->get_error_message() );
			} else {
				$added += count( $ids );
			}
			sleep( 1 );
		}
		if ( $errors->get_error_messages() ) {
			// Error found.
			return $errors;
		} else {
			return $added;
		}
	}

	/**
	 * Search users in list.
	 *
	 * @see https://sendgrid.kke.co.jp/docs/API_Reference/Web_API_v3/Marketing_Campaigns/contactdb.html#Get-Recipients-Matching-Search-Criteria-GET
	 * @param array[] $conditions List of conditions in assoc array field => value.
	 * @return array[]|\WP_Error List of users or WP_Error on failure.
	 */
	public function search( $conditions ) {
		$sg = hamail_client();
		if ( ! $sg ) {
			return new \WP_Error( 'hamail_user_api_error', __( 'Sendgrid client is not set.', 'hamail' ) );
		}
		$response = $sg->client->contactdb()->recipients()->search()->get( null, $conditions );
		$response = $this->convert_response_to_error( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return $response['recipients'];
	}

	/**
	 * If user email is changed,
	 *
	 * @param string $new_mail
	 * @param string $old_mail
	 */
	public function email_change_handler( $new_mail, $old_mail ) {
		$this->delete_from_list( $old_mail );
	}

	/**
	 * If profile is changed.
	 *
	 * @param int       $user_id
	 * @param \stdClass old_user_data
	 */
	public function profile_change_handler( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( $user->user_email !== $old_user_data->user_email ) {
			// User email is changed.
			$this->email_change_handler( $user->user_email, $old_user_data->user_email );
		}
		$this->push( $user );
	}

	/**
	 * Executed if user is created.
	 *
	 * @param int $user_id
	 */
	public function created_handler( $user_id ) {
		$this->push( $user_id );
	}

	/**
	 * Executed if user is deleted.
	 *
	 * @param int $user_id
	 */
	public function delete_handler( $user_id ) {
		$user = get_userdata( $user_id );
		$this->delete_from_list( $user->user_email );
	}

	/**
	 * Delete email from list.
	 *
	 * @param string $email
	 * @return \WP_Error|bool
	 */
	public function delete_from_list( $email ) {
		$recipient = $this->get_recipient( $email );
		try {
			if ( is_wp_error( $recipient ) ) {
				return $recipient;
			} elseif ( ! $recipient ) {
				throw new \Exception( __( 'User is not registered.', 'hamail' ), 404 );
			} else {
				// Remove recipient id from list.
				$sg = hamail_client();
				// Remove from list.
				$response = $sg->client->contactdb()->lists()->_( hamail_active_list() )->recipients()->_( $recipient['id'] )->delete();
				$result   = $this->convert_response_to_error( $response );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				// If contact is in no list, should remove account.
				$response = $sg->client->contactdb()->recipients()->_( $recipient['id'] )->lists()->get();
				$result   = $this->convert_response_to_error( $response );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( empty( $result['lists'] ) ) {
					// This recipient is on no list.
					// Remove recipient.
					$response = $sg->client->contactdb()->recipients()->delete( [
						$recipient['id'],
					] );
					$result   = $this->convert_response_to_error( $response );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}
				// Successfully deleted.
				return true;
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'hamail_user_api_error', $e->getMessage(), [
				'status' => $e->getCode(),
			] );
		}
	}
}
