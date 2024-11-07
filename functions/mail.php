<?php
/**
 * Mail functions
 *
 * @package hamail
 */

use Hametuha\Hamail\Service\TemplateSelector;

/**
 * Get SendGrid client.
 *
 * @return SendGrid
 */
function hamail_client() {
	static $instance = null;
	if ( is_null( $instance ) ) {
		$instance = new \SendGrid( get_option( 'hamail_api_key' ) );
	}
	return $instance;
}

/**
 * Detect if hamail should override wp_mail function.
 *
 * @return bool
 */
function hamail_override_wp_mail() {
	return '' === get_option( 'hamail_keep_wp_mail', '' );
}

/**
 * Detect if wp_mail() works under SengGrid SMTP API.
 *
 * @return bool
 */
function hamail_use_smtp() {
	return '2' === get_option( 'hamail_keep_wp_mail', '' );
}

if ( hamail_enabled() && ! function_exists( 'wp_mail' ) && hamail_override_wp_mail() ) {

	/**
	 * Override wp_mail
	 *
	 * @param string|array $to Array or comma-separated list of email addresses to send message.
	 * @param string       $subject Email subject.
	 * @param string       $message Message contents.
	 * @param string|array $headers Optional. Additional headers.
	 * @param string|array $attachments Optional. Files to attach.
	 *
	 * @return bool Whether the email contents were sent successfully.
	 */
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
		$attachments = (array) $attachments;
		// Filter vars.
		$arguments = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );
		$filtered  = [];
		foreach (
			[
				'to'          => [],
				'subject'     => '',
				'message'     => '',
				'headers'     => [],
				'attachments' => [],
			] as $key => $default
		) {
			$filtered[ $key ] = isset( $arguments[ $key ] ) ? $arguments[ $key ] : $default;
		}
		$to = $filtered['to'];
		if ( ! is_array( $to ) ) {
			$to = explode( ',', $to );
		}
		$to          = array_filter( array_map( 'trim', $to ) );
		$subject     = $filtered['subject'];
		$message     = $filtered['message'];
		$headers     = $filtered['headers'];
		$attachments = $filtered['attachments'];
		if ( empty( $to ) || ! $subject || ! $message ) {
			return false;
		}
		$additional_header = [];
		$headers           = (array) $headers;
		foreach ( $headers as $header ) {
			foreach ( array_filter( explode( "\n", str_replace( "\r\n", "\n", $header ) ) ) as $line ) {
				$parts = array_map( 'trim', explode( ':', $line ) );
				$type  = strtolower( array_shift( $parts ) );
				$parts = implode( ':', $parts );
				switch ( $type ) {
					case 'reply-to':
						if ( preg_match( '#<(.*@.*)>#', $parts, $match ) ) {
							$additional_header['from'] = $match[1];
						} else {
							$additional_header['from'] = $parts;
						}
						break;
					default:
						// Do nothing.
						break;
				}
			}
		}
		if ( ! is_array( $to ) ) {
			$to = explode( ',', $to );
		}
		$result = hamail_simple_mail( $to, $subject, $message, $additional_header, $attachments );

		return $result && ! is_wp_error( $result );
	}
}

/**
 * Get placeholders
 *
 * @param null|WP_User|string $user
 * @param array               $extra_args
 *
 * @return array|WP_Error
 */
function hamail_placeholders( $user = null, $extra_args = [] ) {
	$email = '';
	if ( is_string( $user ) && is_email( $user ) ) {
		$email = $user;
	} elseif ( is_a( $user, 'WP_User' ) ) {
		// Do nothing.
	} else {
		$user = get_userdata( get_current_user_id() );
	}
	// Check if user is valid.
	if ( ! $email && ! $user ) {
		return new WP_Error( 'invalid_user_data', __( 'The user dose not exist.', 'hamail' ) );
	}
	if ( $email ) {
		$place_holders = [
			'-id-'         => 0,
			'-name-'       => hamail_guest_name( $email ),
			'-nicename-'   => 'V/A',
			'-email-'      => $email,
			'-login-'      => 'V/A',
			'-first_name-' => __( 'First Name' ),
			'-last_name-'  => __( 'Last Name' ),
		];
	} else {
		$place_holders = [
			'-id-'         => $user->ID,
			'-name-'       => $user->display_name,
			'-nicename-'   => $user->user_nicename,
			'-email-'      => $user->user_email,
			'-login-'      => $user->user_login,
			'-first_name-' => $user->first_name,
			'-last_name-'  => $user->last_name,
		];
	}
	if ( $extra_args ) {
		foreach ( $extra_args as $key => $value ) {
			$place_holders[ "-{$key}-" ] = $value;
		}
	}

	/**
	 * hamail_placeholders
	 *
	 * @filter hamail_placeholders
	 *
	 * @param array $place_holders
	 * @param WP_User|string $user
	 *
	 * @return array
	 */
	return apply_filters( 'hamail_placeholders', $place_holders, $user );
}

/**
 * Check if current version is debug mode.
 *
 * @return bool
 */
function hamail_is_debug() {
	return defined( 'HAMAIL_DEBUG' ) && HAMAIL_DEBUG;
}

/**
 * Get default mail from
 *
 * @param string $context
 *
 * @return string
 */
function hamail_default_from( $context = 'simple' ) {
	/**
	 * hamail_default_from
	 *
	 * Default from mail address
	 *
	 * @filter hamail_default_from
	 *
	 * @param string $email
	 * @param string $context
	 *
	 * @return string
	 */
	return apply_filters( 'hamail_default_from', get_option( 'hamail_default_from', '' ) ?: get_option( 'admin_email' ), $context );
}

/**
 * Get default headers
 *
 * @param string $context
 *
 * @return array
 */
function hamail_default_headers( $context = 'simple' ) {
	/**
	 * hamail_default_headers
	 *
	 * @filter hamail_default_headers
	 *
	 * @param array $headers
	 * @param string $context
	 *
	 * @return array
	 */
	return apply_filters( 'hamail_default_headers', [
		'template'  => TemplateSelector::get_default_template(),
		'format'    => get_option( 'hamail_template_id' ) ? 'text/html' : 'text/plain',
		'from'      => hamail_default_from( $context ),
		'from_name' => get_bloginfo( 'name' ),
		'post_id'   => 0,
	], $context );
}

/**
 * Guest name.
 *
 * @param string $email
 *
 * @return string
 */
function hamail_guest_name( $email = '' ) {
	/**
	 * hamail_guest_name
	 *
	 * @filter hamail_guest_name
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	$guest = apply_filters( 'hamail_guest_name', __( 'Guest', 'hamail' ), $email );

	return ( get_option( 'admin_email' ) === $email ) ? __( 'Site Owner', 'hamail' ) : $guest;
}

/**
 * Get recipients data.
 *
 * @param array $recipients
 * @param string $subject
 * @param string $body
 *
 * @return array Array of associative arrays of recipients and data.
 */
function hamail_get_recipients_data( $recipients, $subject = '', $body = '' ) {
	if ( array_keys( $recipients ) === range( 0, count( $recipients ) - 1 ) ) {
		// This is flat array.
		$to_be = [];
		foreach ( $recipients as $id_or_email ) {
			$to_be[ $id_or_email ] = [];
		}
		$recipients = $to_be;
	}
	$id_or_emails = [];
	foreach ( $recipients as $id_or_email => $extra_data ) {
		$extra_data = (array) $extra_data;
		// Normalize user id.
		if ( is_numeric( $id_or_email ) ) {
			$id_or_emails[ $id_or_email ] = $extra_data;
		} else {
			// This is email.
			$id = email_exists( $id_or_email );
			if ( $id ) {
				$id_or_emails[ $id ] = $extra_data;
			} else {
				$id_or_emails[ $id_or_email ] = $extra_data;
			}
		}
	}
	$recipient_data = [];
	foreach ( $id_or_emails as $id_or_email => $extra_data ) {
		if ( ! $id_or_email ) {
			continue;
		}
		/**
		 * hamail_user_can_receive_mail
		 *
		 * @filter hamail_user_can_receive_mail
		 *
		 * @param bool $can_receive
		 * @param int|string $user User ID or email address.
		 *
		 * @return bool
		 */
		$ok = apply_filters( 'hamail_user_can_receive_mail', true, $id_or_email );
		if ( ! $ok ) {
			continue;
		}
		if ( is_numeric( $id_or_email ) ) {
			// This is user id.
			$user = get_userdata( $id_or_email );
			if ( ! $user ) {
				continue;
			}
			$data = [
				'id'            => $user->ID,
				'email'         => $user->user_email,
				'name'          => $user->display_name,
				'substitutions' => hamail_placeholders( $user, $extra_data ),
				'custom_args'   => $user->ID,
			];
		} else {
			$data = [
				'id'            => 0,
				'email'         => $id_or_email,
				'name'          => hamail_guest_name( $id_or_email ),
				'substitutions' => hamail_placeholders( $id_or_email, $extra_data ),
				'custom_args'   => 0,
			];
		}
		if ( is_wp_error( $data ) ) {
			continue;
		}
		if ( is_email( $data['email'] ) ) {
			$recipient_data[] = $data;
		}
	}

	return $recipient_data;
}

/**
 * Send single mail
 *
 * @param string|string[] $recipients
 * @param string          $subject
 * @param string          $body
 * @param array           $additional_headers
 * @param array           $attachments
 *
 * @return bool|WP_Error
 */
function hamail_simple_mail( $recipients, $subject, $body, $additional_headers = [], $attachments = [] ) {
	// Parse recipients.
	$recipients     = (array) $recipients;
	$recipient_data = hamail_get_recipients_data( $recipients, $subject, $body );
	if ( ! $recipient_data ) {
		return new WP_Error( 'no_recipients', __( 'No recipient set.', 'hamail' ) );
	}
	// Create slot because SendGrid has API limit
	// 1,000 mail per 1 request and 10,000 requests per second.
	$limit                 = hamail_bulk_limit();
	$recipients_slot_count = ceil( count( $recipient_data ) / $limit );
	$recipients_slots      = [];
	for ( $i = 0; $i < $recipients_slot_count; $i++ ) {
		$recipients_slots[] = array_slice( $recipient_data, $i * $limit, $limit );
	}
	// Create request body.
	// TODO: Extract attachment files.
	$headers = array_merge( hamail_default_headers( 'simple' ), $additional_headers );
	// From.
	$from = new SendGrid\Mail\From( hamail_default_from(), get_bloginfo( 'name' ) );
	// Reply To.
	$reply_to = new SendGrid\Mail\ReplyTo( $headers['from'] );
	// Subject.
	// template apply args.
	$should_apply = true;
	// Mail body.
	if ( 'text/html' === $headers['format'] ) {
		/**
		 * hamail_should_filter
		 *
		 * Filter if we should apply templates
		 *
		 * @param bool   $should_apply If WooCommerce exists, no filter.
		 * @param array  $headers
		 * @param string $subject
		 * @param string $body
		 * @param array  $recipients
		 *
		 * @package hamail
		 */
		$should_filter = apply_filters( 'hamail_should_filter', $should_apply, $headers, $subject, $body, $recipients );
		if ( $should_filter ) {
			hamail_is_sending( true );
			$body = apply_filters( 'the_content', $body );
			hamail_is_sending( false );
		}
		/**
		 * hamail_body_before_send
		 *
		 * @param string $body     Mail body.
		 * @param string $context 'html' or 'plain'
		 *
		 * @return string
		 */
		$body    = apply_filters( 'hamail_body_before_send', $body, 'html' );
		$content = new SendGrid\Mail\Content( 'text/html', $body );
	} else {
		$body    = apply_filters( 'hamail_body_before_send', $body, 'plain' );
		$content = new SendGrid\Mail\Content( 'text/plain', strip_tags( $body ) );
	}
	// Add attachment if exists.
	$mail_attachments = [];
	foreach ( $attachments as $path ) {
		if ( ! file_exists( $path ) ) {
			continue;
		}
		$mime = wp_check_filetype( $path );
		if ( ! $mime['type'] ) {
			continue;
		}
		$mail_attachments[] = [
			'content'  => base64_encode( file_get_contents( $path ) ),
			'type'     => $mime['type'],
			'filename' => basename( $path ),
		];
	}
	/**
	 * hamail_apply_template
	 *
	 * Filter if we should apply templates
	 *
	 * @param bool $shold_apply This affects force apply template or not.
	 * @param array $headers
	 * @param string $subject
	 * @param string $body
	 * @param array $recipients
	 *
	 * @package hamail
	 */
	$should_apply_template = apply_filters( 'hamail_apply_template', $should_apply, $headers, $subject, $body, $recipients );
	// Category.
	if ( 1 < count( $recipient_data ) ) {
		$category = 'group';
	} else {
		$category = 'personal';
	}
	// Email client.
	$sg = hamail_client();
	// Error object.
	$errors      = new WP_Error();
	$slots_total = 0;
	$sent_total  = 0;
	foreach ( $recipients_slots as $index => $recipients_group ) {
		try {
			// Create mail instance.
			$mail = new SendGrid\Mail\Mail( $from );
			$mail->setSubject( $subject );
			$mail->setReplyTo( $reply_to );
			$mail->addContent( $content );
			// Add attachments.
			if ( ! empty( $mail_attachments ) ) {
				$mail->addAttachments( $mail_attachments );
			}
			// Apply templates.
			if ( $headers['template'] && $should_apply_template ) {
				$mail->setTemplateId( $headers['template'] );
			}
			// Set email category.
			$mail->addCategory( $category );
			// Add recipients.
			foreach ( $recipients_group as $recipient ) {
				try {
					$personalization = new \SendGrid\Mail\Personalization();
					$email           = new \SendGrid\Mail\To( $recipient['email'], $recipient['name'] );
					$personalization->addTo( $email );
					$personalization->setSubject( $subject );
					foreach ( $recipient['substitutions'] as $key => $val ) {
						$personalization->addSubstitution( $key, (string) $val );
						if ( '-id-' === $key ) {
							$arg = new \SendGrid\Mail\CustomArg( 'user_id', (string) $val );
							$personalization->addCustomArg( $arg );
						}
					}
					if ( isset( $recipient['custom_arg'] ) ) {
						$arg = new \SendGrid\Mail\CustomArg( 'custom', (string) $recipient['custom_arg'] );
						$personalization->addCustomArg( $arg );
					}
					if ( isset( $headers['post_id'] ) ) {
						$arg = new \SendGrid\Mail\CustomArg( 'postId', (string) $headers['post_id'] );
						$personalization->addCustomArg( $arg );
					}
					$mail->addPersonalization( $personalization );
					++$sent_total;
				} catch ( \Exception $e ) {
					$errors->add( 'hamail_personlization_exception', sprintf( '[%s] %s', $e->getCode(), $e->getMessage() ) );
				}
			}
			// Set send at.
			$mail->setSendAt( current_time( 'timestamp', true ) + $index * 20 );
			// If debug mode, never send.
			if ( hamail_is_debug() ) {
				++$slots_total;
				error_log( '[HAMAIL] ' . var_export( $mail, true ) );
				continue;
			}
			// Execute Web API.
			$response = $sg->send( $mail );
			// Get response.
			$code = $response->statusCode();
			if ( preg_match( '#2[\d]{2}#u', $code ) ) {
				continue;
			} else {
				$error          = json_decode( $response->body() );
				$error->headers = $response->headers();
				$errors->add( $code, json_encode( $error ) );
			}
		} catch ( \Exception $e ) {
			$errors->add( 'hamail_exception', $e->getMessage() );
		}
	}
	if ( hamail_is_debug() ) {
		error_log( sprintf( '[HAMAIL] %d slots / %d sent / %d recipients', $slots_total, $sent_total, count( $recipients ) ) );
	}
	$errors_messages = $errors->get_error_messages();
	if ( empty( $errors_messages ) ) {
		return true;
	} else {
		return $errors;
	}
}

/**
 * Get recipients.
 *
 * @param null|int|WP_Post $post
 *
 * @return array ID or email.
 */
function hamail_get_message_recipients( $post = null ) {
	$post = get_post( $post );
	// Create user row.
	$to = [];
	// Raw email.
	$emails = array_filter( array_map( 'trim', explode( ',', get_post_meta( $post->ID, '_hamail_raw_address', true ) ) ), function ( $email ) {
		$is_valid = ! empty( $email ) && is_email( $email );
		return apply_filters( 'hamail_is_valid_email', $is_valid, $email );
	} );
	foreach ( $emails as $email ) {
		$to[] = $email;
	}
	// Roles.
	$roles = array_filter( array_map( 'trim', explode( ',', get_post_meta( $post->ID, '_hamail_roles', true ) ) ) );
	if ( $roles ) {
		$query = new WP_User_Query( [
			'role__in' => $roles,
			'number'   => - 1,
			'fields'   => 'ID',
		] );
		foreach ( $query->get_results() as $user_id ) {
			$to[] = $user_id;
		}
	}
	// Groups.
	$groups = array_filter( array_map( 'trim', explode( ',', get_post_meta( $post->ID, '_hamail_user_groups', true ) ) ) );
	if ( $groups ) {
		$user_groups = hamail_user_groups();
		foreach ( $groups as $group ) {
			foreach ( $user_groups as $user_group ) {
				if ( $group !== $user_group->name ) {
					continue;
				}
				foreach ( $user_group->get_users() as $user ) {
					$to[] = $user->ID;
				}
			}
		}
	}
	// Users.
	$user_ids = array_filter( array_map( 'trim', explode( ',', get_post_meta( $post->ID, '_hamail_recipients_id', true ) ) ), function ( $user_id ) {
		return is_numeric( $user_id ) && ( 0 < $user_id );
	} );
	if ( $user_ids ) {
		$query = new WP_User_Query( [
			'include' => $user_ids,
			'number'  => - 1,
			'fields'  => 'ID',
		] );
		foreach ( $query->get_results() as $user_id ) {
			$to[] = $user_id;
		}
	}
	// Unique.
	$to = array_unique( $to );
	$to = apply_filters( 'hamail_message_recipients', $to, $post );
	return $to;
}

/**
 * Send message
 *
 * @param null|int|WP_Post $post
 * @param bool              $force If true, send email if it's already sent or not-published.
 *
 * @return bool|WP_Error
 */
function hamail_send_message( $post = null, $force = false ) {
	$post = get_post( $post );
	if ( 'hamail' !== $post->post_type ) {
		return false;
	}
	if ( ! $force && ( 'publish' !== $post->post_status ) || hamail_is_sent( $post ) ) {
		return false;
	}
	// O.K. Let's try sending.
	$subject = get_the_title( $post );
	$body    = apply_filters( 'hamail_transaction_content', apply_filters( 'the_content', $post->post_content ), $post );
	$headers = [
		'post_id'  => $post->ID,
		'template' => TemplateSelector::get_post_template( $post->ID ),
	];
	if ( ! get_post_meta( $post->ID, '_hamail_as_admin', true ) ) {
		$author               = get_userdata( $post->post_author );
		$headers['from']      = $author->user_email;
		$headers['from_name'] = $author->display_name;
	}
	// Get recipients.
	$to = hamail_get_message_recipients( $post );
	if ( empty( $to ) ) {
		return false;
	}
	// Send.
	$result = hamail_simple_mail( $to, $subject, $body, $headers );
	if ( is_wp_error( $result ) ) {
		$message = sprintf( '[Error] %s: %s', $result->get_error_code(), current_time( 'mysql' ) ) . "\n";
		foreach ( $result->get_error_messages() as $err_message ) {
			$json = json_decode( $err_message );
			if ( ! $json ) {
				// Simple message.
				$message .= "------\n" . $err_message . "\n";
			} else {
				foreach ( $json->errors as $error ) {
					$message .= "------\n" . $error->message;
					if ( $error->field ) {
						$message .= sprintf( "\n[Field]\n%s", $error->field );
					}
					if ( $error->help ) {
						$message .= sprintf( "\n[Help]\n%s", $error->help );
					}
				}
				if ( $json->headers ) {
					$message .= sprintf( "\n-----\n[Headers]\n%s\n", implode( "\n", $json->headers ) );
				}
			}
			// Save log.
			add_post_meta( $post->ID, '_hamail_log', $message );
		}
		return $result;
	} else {
		update_post_meta( $post->ID, '_hamail_sent', current_time( 'mysql' ) );
		return true;
	}
}

/**
 * Set flag while sending email.
 *
 * @param null|bool If true or false is passed, setting will be change.
 *
 * @return bool
 */
function hamail_is_sending( $flag = null ) {
	static $sending = false;
	if ( ! is_null( $flag ) ) {
		$sending = (bool) $sending;
	}

	return $sending;
}

/**
 * Get css file for email.
 *
 * @return string[]
 */
function hamail_get_mail_css() {
	$css_path = [];
	foreach ( [ get_template_directory(), get_stylesheet_directory() ] as $dir ) {
		$css = $dir . '/hamail.css';
		if ( file_exists( $css ) ) {
			$css_path[] = $css;
		}
	}

	/**
	 * CSS path to apply for email.
	 *
	 * @param string[] $css_path
	 *
	 * @return string[]
	 */
	return apply_filters( 'hamail_css_path', $css_path );
}

/**
 * Get bulk limit.
 *
 * @return int
 */
function hamail_bulk_limit() {
	/**
	 * hamail_bulk_limit
	 *
	 * @param int $limit Default 1000
	 * @return int Integer from 2 to 1000.
	 */
	return min( 1000, max( 2, apply_filters( 'hamail_bulk_limit', 1000 ) ) );
}

/**
 * Convert HTML email to plain.
 *
 * @param string $string
 * @return string
 */
function hamail_html_body_to_plain( $string ) {
	$original = $string;
	$string   = strip_shortcodes( $string );
	foreach ( [
		'#<a[^>]*href="([^"]+)"[^>]*>(.*?)</a>#u' => '$2 ($1) ',
		'#<hr[^>]*?>#u'                           => apply_filters( 'hamail_plain_mail_separator', '---------', $string ),
		'#<h([1-6])[^>]*?>(.*?)</h[1-6]>#u'       => function ( $matches ) {
			list( $all, $level, $text ) = $matches;
			$prefix = [ '#### ', '### ', '## ', '# ', '', '' ][ $level - 1 ];
			$prefix = apply_filters( 'hamail_plain_mail_heading_prefix', $prefix, $level );
			return $prefix . $text;
		},
		'#<blockquote[^>]*?>(.*)</blockquote>#u'  => apply_filters( 'hamail_prefix', '> ', 'blockquote' ) . '$1',
		'#<(o|u|d)l>(.*?)</(o|u|d)l>#us'          => function ( $matches ) {
			list( $all, $tag, $content, $tag2 ) = $matches;
			$prefix = '';
			switch ( $tag ) {
				case 'd':
					$prefix  = apply_filters( 'hamail_prefix', _x( '# ', 'prefix-dt', 'hamail' ), 'dt' );
					$content = preg_replace( '#<dt[^>]*?>(.*)</dt>#', "{$prefix}$1\n", $content );
					$content = preg_replace( '#<dd[^>]*?>(.*)</dd>#', "$1\n", $content );
					return $content;
				case 'o':
					// translators: %d is list counter.
					$prefix  = apply_filters( 'hamail_prefix', _x( '%d. ', 'prefix-ol', 'hamail' ), 'ol' );
					break;
				case 'u':
					$prefix  = apply_filters( 'hamail_prefix', _x( '- ', 'prefix-ul', 'hamail' ), 'ul' );
					break;
			}
			$counter = 0;
			return preg_replace_callback( '#<li[^>]*?>(.*?)</li>#', function ( $m ) use ( $prefix, &$counter ) {
				$counter++;
				if ( false !== strpos( $prefix, '%d' ) ) {
					$prefix = sprintf( $prefix, $counter );
				}
				return $prefix . $m[1] . "\n";
			}, $content );
		},
	] as $preg => $callback ) {
		if ( is_callable( $callback ) ) {
			$string = preg_replace_callback( $preg, $callback, $string );
		} else {
			$string = preg_replace( $preg, $callback, $string );
		}
	}
	// Remove tags.
	$string = strip_tags( $string );
	// Convert successive line break to 3.
	$string = preg_replace( '/\n{3,}/u', "\n\n\n", $string );
	// Convert 2 successive line break to 1.
	$string = preg_replace( '/(?<!\n)\n{2}(?!\n)/u', "\n", $string );
	return apply_filters( 'hamail_html_to_plain_text', $string, $original );
}
