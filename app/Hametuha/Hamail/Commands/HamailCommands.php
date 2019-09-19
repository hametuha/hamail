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

	/**
	 * Test data to which data will be passed as personalized data.
	 *
	 * ## OPTIONS
	 *
	 * : <recipients>
	 *   CSV value of id or emails.
	 *
	 * @synopsis <recipients>
	 * @param array $args
	 */
	public function test_data( $args ) {
		list( $id_or_emails ) = $args;
		$id_or_emails = explode( ',', $id_or_emails );
		add_filter( 'hamail_placeholders', function( $data, $user ) {
			$data[ '-extra-' ] = is_a( $user, 'WP_User' ) ? 'WP_User' : 'Email';
			return $data;
		}, 10, 2 );
		$recipient_data = hamail_get_recipients_data( $id_or_emails );
		if ( ! $recipient_data ) {
			\WP_CLI::error( __( 'No data found.', 'hamail' ) );
		}
		print_r( $recipient_data );
		\WP_CLI::line( '' );
		\WP_CLI::success( sprintf( __( '%d data converted.', 'hamail' ), count( $recipient_data ) ) );
	}

	/**
	 * Test transactional mail class.
	 *
	 * ## OPTIONS
	 *
	 * : <class_name>
	 *   Mail class name.
	 *
	 * @synopsis <class_name>
	 * @param array $args
	 */
	public function test_mail_class( $args ) {
		list( $class_name ) = $args;
		if ( ! class_exists( $class_name ) ) {
			\WP_CLI::error( sprintf( __( 'Class %s does not exist.', 'hamail' ), $class_name ) );
		}
		/** @var \Hametuha\Hamail\Pattern\TransactionalEmail $class_name */
		$data = $class_name::test();
		print_r( $data );
		\WP_CLI::line( '' );
		\WP_CLI::success( sprintf( __( 'Above is the data of %s.', 'hamail' ), $class_name ) );
	}
	
	/**
	 * Send email via wp_mail
	 *
	 * ## OPTIONS
	 *
	 * : <to>
	 *   Mail address sent to.
	 * : [--subject=<subject>]
	 *   Mail subject.
	 * : [--body=<body>]
	 *   Mail body.
	 *
	 * @synopsis <to> [--subject=<subject>] [--body=<body>]
	 * @param array $args
	 * @param array $assoc
	 */
	public function wp_mail( $args, $assoc ) {
		list( $to ) = $args;
		$subject = isset( $assoc['subject'] ) ? $assoc['subject'] : __( 'This is a test mail from WP-CLI', 'hamail' );
		$body    = isset( $assoc['body'] ) ? $assoc['body'] : __( 'Dear -email-, we sent you a test mail. This email validates your setting is correct.', 'hamail' );
		if ( \wp_mail( $to, $subject, $body ) ) {
			\WP_CLI::success( 'Successfully sent a test mail.' );
		} else {
			\WP_CLI::error( 'Failed to sent a test mail.' );
		}
	}
}
