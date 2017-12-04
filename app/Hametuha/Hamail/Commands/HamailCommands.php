<?php

namespace Hametuha\Hamail\Commands;

/**
 * Command utility for hamail.
 *
 * @package hamail
 */
class HamailCommands extends \WP_CLI_Command {

	/**
	 * Sync user account to SendGrid
	 */
	public function sync() {

		// Sync user while it exists.
		$cur_page = 1;
		while( true ) {
			$query = apply_filters( 'hamail_user_push_query', [
//				'role' => 'administrator',
				'role__not_in' => [ 'pending' ],
				'paged' => $cur_page,
				'number' => 1000,
			] );
			$result = hamail_push_users( $query, true );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
				break;
			} elseif ( ! $result ) {
				\WP_CLI::line( 'All user are synced.' );
				break;
			} else {
				foreach ( $result->errors as $error ) {
					\WP_CLI::warning( $error->message );
				}
				echo '.';
				sleep( 2 );
				$cur_page++;
			}
		}
		// Update all recipients to list.
		$cur_page = 1;
		while ( true ) {
			$updated = hamail_sync_account( $cur_page );
			if ( ! $updated ) {
				\WP_CLI::line( 'All user are move to list.' );
				break;
			} elseif ( is_wp_error( $updated) ) {
				\WP_CLI::error( $updated->get_error_message() );
			} else {
				echo '.';
			}
			$cur_page++;
			sleep( 1 );
		}
		\WP_CLI::success( 'Done.' );
	}
}
