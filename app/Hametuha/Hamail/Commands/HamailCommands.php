<?php

namespace Hametuha\Hamail\Commands;


use Hametuha\Hamail\API\UserSync;
use Hametuha\Hamail\Service\Extractor;
use cli\Table;

/**
 * Command utility for hamail.
 *
 * @package hamail
 * @property-read UserSync $user_sync User sync api.
 */
class HamailCommands extends \WP_CLI_Command {

	/**
	 * Add or update user.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : User id to update.
	 *
	 * [--dry-run]
	 * : If set, never update.
	 *
	 * @param array $args  Arguments.
	 * @param array $assoc Options.
	 * @synopsis <user_id> [--dry-run]
	 */
	public function update_user( $args, $assoc ) {
		list( $user_id ) = $args;
		$dry_run         = ! empty( $assoc['dry-run'] );
		$user            = get_userdata( $user_id );
		if ( ! $user ) {
			// translators: %s is user ID.
			\WP_CLI::error( sprintf( __( 'User ID does not exist: %s', 'hamail' ), $user_id ) );
		}
		$recipient = $this->user_sync->get_recipient( $user_id );
		if ( is_wp_error( $recipient ) ) {
			\WP_CLI::error( $recipient->get_error_message() );
		}
		if ( ! $recipient ) {
			// No recipient, add new.
			if ( $dry_run ) {
				// translators: %1$d is user id, %2$d is list id.
				\WP_CLI::success( sprintf( __( 'User %1$d is not found in the contact list %2$d. Will be added.', 'hamail' ), $user_id, hamail_active_list() ) );
				exit;
			}
		} else {
			// Found, update.
			if ( $dry_run ) {
				// translators: %1$d is user id, %2$d is list id.
				\WP_CLI::success( sprintf( __( 'User %1$d will be updated in the contact list %2$d.', 'hamail' ), $user_id, hamail_active_list() ) );
				exit;
			}
		}
		$result = $this->user_sync->push( $user );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		} else {
			// translators: %1$d is user id, %2$s is sendgrid ID.
			\WP_CLI::success( sprintf( __( 'User %1$d is registered as %2$s', 'hamail' ), $user_id, $result ) );
		}
	}

	/**
	 * Remove user from recipients.
	 *
	 * ## OPTIONS
	 *
	 * : <user_id_or_email>
	 *   ID or email to be deleted from Sendgrid.
	 *
	 * @param array $args
	 * @synopsis <user_id_or_email>
	 */
	public function delete_recipient( $args ) {
		list( $user_id_or_email ) = $args;
		if ( is_numeric( $user_id_or_email ) ) {
			$user = get_userdata( $user_id_or_email );
			if ( ! $user ) {
				// translators: %d is user id or email.
				\WP_CLI::error( sprintf( __( 'User %s does not exist.', 'hamail' ), $user_id_or_email ) );
			}
			$email = $user->user_email;
		} else {
			$email = $user_id_or_email;
		}
		$result = $this->user_sync->delete_from_list( $email );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		// translators: %s is email address.
		\WP_CLI::success( sprintf( __( '%s is deleted from contact list.', 'hamail' ), $email ) );
	}

	/**
	 * Sync user account to SendGrid
	 *
	 * @deprecated
	 * @global $wpdb;
	 */
	public function sync() {
		\WP_CLI::line( __( 'Start syncing all users to sendgrid list.', 'hamail' ) );
		global $wpdb;
		$total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users}" );
		// translators: %1$d is user count, %2$d is operation time.
		\WP_CLI::confirm( sprintf( __( 'You have %1$d users. This will take %2$d seconds approximately. Are you ready?', 'hamail' ), $total, $total / 1000 * 5 ) );
		\WP_CLI::line( __( 'Syncing...', 'hamail' ) );
		$result = $this->user_sync->bulk_push( [
			'number' => 1000,
		] );
		if ( is_wp_error( $result ) ) {
			foreach ( $result->get_error_messages() as $message ) {
				\WP_CLI::warning( $message );
			}
			\WP_CLI::error( __( 'Syncing failed.', 'hamail' ) );
		} else {
			\WP_CLI::success( sprintf( __( '%d users synced.', 'hamail' ), $result ) );
		}
	}

	/**
	 * Export users as csv.
	 *
	 * ## OPTIONS
	 *
	 * : [--destination=<destination>]
	 *   Optional. If specified, output as CSV file.
	 *
	 * @param array $args
	 * @param array $assoc
	 * @synopsis [--destination=<destination>]
	 */
	public function export( $args, array $assoc ) {
		$destination = ! empty( $assoc['destination'] ) ? $assoc['destination'] : false;
		$table       = new Table();
		$csv         = null;
		if ( $destination ) {
			if ( file_exists( $destination ) ) {
				// translators: %s id file path.
				\WP_CLI::error( sprintf( __( 'File %s already exists.', 'hamail' ), $destination ) );
			}
			$parent = realpath( dirname( $destination ) );
			if ( ! is_dir( $parent ) || ! is_writeable( $parent ) ) {
				// translators: %s is directory.
				\WP_CLI::error( sprintf( __( 'Parent directory %s is not writable.', 'hamail' ), $destination ) );
			}
			$csv        = new \SplFileObject( $destination, 'w' );
			$set_header = function ( $headers ) use ( &$csv ) {
				$csv->fputcsv( $headers );
			};
			$set_row    = function ( $fields ) use ( &$csv ) {
				$csv->fputcsv( $fields );
			};
		} else {
			$set_header = function ( $headers ) use ( &$table ) {
				$table->setHeaders( $headers );
			};
			$set_row    = function ( $fields ) use ( &$table ) {
				$table->addRow( $fields );
			};
		}
		\WP_CLI::line( __( 'Exporting 1000 users per dot. Please be patient.', 'hamail' ) );
		$has_next = true;
		$paged    = 1;
		$header   = false;
		$count    = 0;
		while ( $has_next ) {
			$user_query = new \WP_User_Query( [
				'number' => 1000,
				'paged'  => $paged,
			] );
			$users      = $user_query->get_results();
			if ( count( $users ) ) {
				++$paged;
				foreach ( $users as $user ) {
					$fields = hamail_fields_to_save( $user );
					if ( is_wp_error( $fields ) ) {
						continue;
					}
					if ( ! $header ) {
						$header = true;
						$set_header( array_keys( $fields ) );
					}
					$set_row( array_values( $fields ) );
					++$count;
				}
				echo '.';
			} else {
				$has_next = false;
			}
		}
		\WP_CLI::line( '' );
		if ( ! $count ) {
			\WP_CLI::error( __( 'No user found. Please check your setting.', 'hamail' ) );
		} else {
			// translators: %d is user count.
			\WP_CLI::line( sprintf( __( '%d users found.', 'hamail' ), $count ) );
		}

		if ( $destination ) {
			// translators: %s is CSV path.
			\WP_CLI::success( sprintf( __( 'CSV is output: %s', 'hamail' ), $destination ) );
		} else {
			$table->display();
		}
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
		$id_or_emails         = explode( ',', $id_or_emails );
		add_filter( 'hamail_placeholders', function ( $data, $user ) {
			$data['-extra-'] = is_a( $user, 'WP_User' ) ? 'WP_User' : 'Email';
			return $data;
		}, 10, 2 );
		$recipient_data = hamail_get_recipients_data( $id_or_emails );
		if ( ! $recipient_data ) {
			\WP_CLI::error( __( 'No data found.', 'hamail' ) );
		}
		print_r( $recipient_data );
		\WP_CLI::line( '' );
		// translators: %d is amount of data.
		\WP_CLI::success( sprintf( __( '%d data converted.', 'hamail' ), count( $recipient_data ) ) );
	}

	/**
	 * Test user data to be sync.
	 *
	 * @param array $args
	 * @synopsis <user_id>
	 */
	public function test_fields( $args ) {
		list( $user_id ) = $args;
		$user            = get_userdata( $user_id );
		if ( ! $user ) {
			// translators: %d is user id.
			\WP_CLI::error( __( 'User %d not found.', 'hamail' ), $user_id );
		}
		$fields = hamail_fields_to_save( $user );
		if ( is_wp_error( $fields ) ) {
			\WP_CLI::error( $fields->get_error_message() );
		}
		$table = new Table();
		$table->setHeaders( [ 'Field', 'Value' ] );
		foreach ( $fields as $field => $value ) {
			$table->addRow( [ $field, $value ] );
		}
		$table->display();
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
			// translators: %s is class name.
			\WP_CLI::error( sprintf( __( 'Class %s does not exist.', 'hamail' ), $class_name ) );
		}
		/** @var \Hametuha\Hamail\Pattern\TransactionalEmail $class_name */
		$data = $class_name::test();
		print_r( $data );
		\WP_CLI::line( '' );
		// translators: %s is class name.
		\WP_CLI::success( sprintf( __( 'Above is the data of %s.', 'hamail' ), $class_name ) );
	}

	/**
	 * Extract mail object.
	 *
	 * ## OPTIONS
	 *
	 * : <post_id>
	 *   Post ID to extract.
	 *
	 * @synopsis <post_id>
	 * @param array $args
	 */
	public function extract( $args ) {
		list( $post_id ) = $args;
		$result          = Extractor::process( $post_id );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		} else {
			print_r( $result );
			\WP_CLI::line( '' );
		}
	}

	/**
	 * Get message recipients.
	 *
	 * ## OPTIONS
	 *
	 * : <post_id>
	 *   Post ID to get recipients.
	 *
	 * @synopsis <post_id>
	 * @param array $args
	 */
	public function get_recipients( $args ) {
		list( $post_id ) = $args;
		$post            = get_post( $post_id );
		if ( ! $post || 'hamail' !== $post->post_type ) {
			\WP_CLI::error( __( 'No message found.', 'hamail' ) );
		}
		// translators: %1$s is post title, %2$d is post ID.
		\WP_CLI::line( sprintf( __( 'Get the recipients of #%2$d %1$s...', 'hamail' ), get_the_title( $post ), $post->ID ) );
		$to = hamail_get_message_recipients( $post );
		if ( empty( $to ) ) {
			\WP_CLI::error( 'Message %s has no recipient.', 'hamail' );
		}
		$table = new \cli\Table();
		$table->setHeaders( [ 'Type', 'Value', 'User' ] );
		foreach ( $to as $id_email ) {
			$row = [];
			if ( is_numeric( $id_email ) ) {
				$row[] = 'ID';
				$user  = true;
			} elseif ( is_email( $id_email ) ) {
				$row[] = 'Email';
				$user  = email_exists( $id_email );
			} else {
				$row[] = 'NAN';
				$user  = false;
			}
			$row[] = $id_email;
			$row[] = $user ? 'Yes' : 'No';
			$table->addRow( $row );
		}
		$table->display();
		\WP_CLI::success( sprintf( '%s has %d recipients.', get_the_title( $post ), count( $to ) ) );
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
		$subject    = isset( $assoc['subject'] ) ? $assoc['subject'] : __( 'This is a test mail from WP-CLI', 'hamail' );
		$body       = isset( $assoc['body'] ) ? $assoc['body'] : __( 'Dear -email-,
we sent you a test mail.

This email validates your setting is correct.

For example, how does a URL below looks like?
https://example.com

Also, you should check tags like <strong>strong</strong>, <em>emphasis</em>, <code>difficult code</code> and so on.

If this is html mail, <a href="https://example.com">link</a> should work properly.', 'hamail' );
		if ( \wp_mail( $to, $subject, $body ) ) {
			\WP_CLI::success( 'Successfully sent a test mail.' );
		} else {
			\WP_CLI::error( 'Failed to sent a test mail.' );
		}
	}

	/**
	 * Test css path
	 *
	 * ## OPTIONS
	 *
	 * : [<file>]
	 * If set, this css wil be used.
	 *
	 * @synopsis [<file>]
	 * @param array $args  Arguments.
	 * @param array $assoc Options.
	 */
	public function css_test( $args, $assoc ) {
		if ( isset( $args[0] ) ) {
			$file = $args[0];
			if ( file_exists( $file ) ) {
				add_filter( 'hamail_css_path', function ( $path ) use ( $file ) {
					$path[] = $file;
					return $path;
				} );
			} else {
				\WP_CLI::error( sprintf( 'No file found: %s', $file ) );
			}
		}
		$styles = hamail_get_mail_css();
		if ( ! $styles ) {
			\WP_CLI::error( 'No stylesheet exists.' );
		}
		\WP_CLI::line( 'These stylesheets will be applied:' );
		foreach ( $styles as $style ) {
			\WP_CLI::line( $style );
		}
		$body     = <<<HTML
This is a test mail.

You can check how <code>stylesheets</code> will be applied.

Is this <strong>O.K.</strong> for you?
How about <a href="https://example.com">links</a>?
HTML;
		$body     = apply_filters( 'hamail_style_test_body', $body );
		$filtered = apply_filters( 'the_content', $body );
		$filtered = apply_filters( 'hamail_body_before_send', $filtered, 'html' );
		\WP_CLI::line( '' );
		\WP_CLI::line( 'Original------------' );
		echo trim( $body );
		\WP_CLI::line( '' );
		\WP_CLI::line( '--------------------' );
		\WP_CLI::line( '' );
		\WP_CLI::line( 'Style Applied-------' );
		echo trim( $filtered );
		\WP_CLI::line( '' );
		\WP_CLI::line( '--------------------' );
		\WP_CLI::line( '' );
		\WP_CLI::success( 'Done!' );
	}

	/**
	 * Try to send message.
	 *
	 * ## OPTIONS
	 *
	 * : <post_id>
	 *   Post ID to get recipients.
	 *
	 * @synopsis <post_id>
	 * @param array $args Arguments.
	 */
	public function test_message( $args ) {
		list( $post_id ) = $args;
		// Force debug mode.
		if ( ! defined( 'HAMAIL_DEBUG' ) ) {
			define( 'HAMAIL_DEBUG', true );
		}
		$result = hamail_send_message( $post_id, true );
		if ( is_wp_error( $result ) ) {
			foreach ( $result->get_error_codes() as $code ) {
				foreach ( $result->get_error_messages( $code ) as $message ) {
					\WP_CLI::warning( sprintf( '%s: %s', $code, $message ) );
				}
			}
			\WP_CLI::error( __( 'Failed to send messages.', 'hamail' ) );
		} elseif ( $result ) {
			\WP_CLI::success( __( 'Post is successfully sent.', 'hamail' ) );
		} else {
			\WP_CLI::error( __( 'Failed to send message. Because of no post, no recipients, nor already sent.', 'hamail' ) );
		}
	}

	/**
	 * Get message activities
	 *
	 * @synopsis <message_id>
	 * @param array $args
	 * @return void
	 */
	public function activities( $args ) {
		list( $message_id ) = $args;
		$client = hamail_client();
		$response = $client->client->messages()->_( $message_id )->get();
		if ( '200' !== $response->statusCode() ) {
			$body = json_decode( $response->body(), true );
			\WP_CLI::error( implode( "\n", array_map( function( $error ) {
				return $error['message'];
			}, $body['errors'] ) ) );
		}
		var_dump( $response );
	}

	/**
	 * Getter.
	 *
	 * @param string $name
	 * @return mixed Mixed object.
	 */
	public function __get( string $name ) {
		switch ( $name ) {
			case 'user_sync':
				return UserSync::get_instance();
			default:
				return null;
		}
	}
}
