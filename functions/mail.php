<?php
/**
 * Mail functions
 *
 * @package hamail
 */

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
	return$instance;
}

if ( get_option( 'hamail_template_id' ) && ! function_exists( 'wp_mail' ) ) {

	/**
	 * Override wp_mail
	 *
	 * @param string|array $to          Array or comma-separated list of email addresses to send message.
	 * @param string       $subject     Email subject
	 * @param string       $message     Message contents
	 * @param string|array $headers     Optional. Additional headers.
	 * @param string|array $attachments Optional. Files to attach.
	 * @return bool Whether the email contents were sent successfully.
	 */
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
		$attachments = (array) $attachments;
		// Filter vars
		$arguments = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );
		$filtered = [];
		foreach ( [
			'to' => [],
			'subject' => '',
			'message' => '',
			'headers' => [],
			'attachments' => [],
		] as $key => $default ) {
			$filtered[ $key ] = isset( $arguments[ $key ] ) ? $arguments[ $key ] : $default;
		}
		$to = array_filter( array_map( 'trim', explode( ',', $filtered['to'] ) ) );
		$subject = $filtered['subject'];
		$message = $filtered['message'];
		$headers = $filtered['headers'];
		$attachments = $filtered['attachments'];
		if ( ! $to || ! $subject || ! $message ) {
			return false;
		}
		$additional_header = [];
		$headers = (array) $headers;
		foreach ( $headers as $header ) {
			foreach ( array_filter( explode( "\n", str_replace( "\r\n", "\n", $header ) ) ) as $line ) {
				$parts = array_map( 'trim', explode( ':', $line ) );
				$type = strtolower( array_shift( $parts ) );
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
						// Do nothing
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
	if ( $email && ! $user ) {
		return new WP_Error( 'invalid_user_data', __( 'The user dose not exist.', 'hamail' ) );
	}
	if ( $email ) {
		$place_holders =  [
			'-id-'       => 0,
			'-name-'     => hamail_guest_name(),
			'-nicename-' => 'V/A',
			'-email-'    => $email,
			'-login-'    => 'V/A',
		];
	} else {
		$place_holders = [
			'-id-'       => $user->ID,
			'-name-'     => $user->display_name,
			'-nicename-' => $user->user_nicename,
			'-email-'    => $user->user_email,
			'-login-'    => $user->user_login,
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
	 * @param array          $place_holders
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
		'template'  => get_option( 'hamail_template_id' ),
		'format'    => get_option( 'hamail_template_id' ) ? 'text/html' : 'text/plain',
		'from'      => hamail_default_from( $context ),
		'from_name' => get_bloginfo( 'name' ),
	    'post_id'   => 0,
	], $context );
}

/**
 * Guest name.
 *
 * @return string
 */
function hamail_guest_name() {
	/**
	 * hamail_guest_name
	 *
	 * @filter hamail_guest_name
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	return apply_filters( 'hamail_guest_name', __( 'Guest', 'hamail' ) );
}

/**
 * Get recipients data.
 *
 * @param array  $recipients
 * @param string $subject
 * @param string $body
 * @return array Associative array of recipients and data.
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
			// This is email
			if ( $id = email_exists( $id_or_email ) ) {
				$id_or_emails[ $id ] = $extra_data;
			} else {
				$id_or_emails[ $id_or_email ] = $extra_data;
			}
		}
	}

	$recipient_data   = [];
	foreach ( $id_or_emails as $id_or_email => $extra_data ) {
		if ( ! $id_or_email ) {
			continue;
		}
		/**
		 * hamail_user_can_receive_mail
		 *
		 * @filter hamail_user_can_receive_mail
		 *
		 * @param bool       $can_receive
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
			// This is email.
			$data = [
				'id'            => 0,
				'email'         => $id_or_email,
				'name'          => hamail_guest_name(),
				'substitutions' => hamail_placeholders( $id_or_email, $extra_data ),
				'custom_args'   => 0,
			];
		}
		if ( is_wp_error( $data ) ) {
			continue;
		}
		$recipient_data[] = $data;
	}
	return $recipient_data;
}

/**
 * Send single mail
 *
 * @param array|string $recipients
 * @param string $subject
 * @param string $body
 * @param array $additional_headers
 * @param array $attachments
 *
 * @return bool|WP_Error
 */
function hamail_simple_mail( $recipients, $subject, $body, $additional_headers = [], $attachments = [] ) {
	// Parse recipients
	$recipients   = (array) $recipients;
	$recipient_data = hamail_get_recipients_data( $recipients, $subject, $body );

	if ( ! $recipient_data ) {
		return new WP_Error( 'no_recipients', __( 'No recipient set.', 'hamail' ) );
	}
	// Create request body
	// TODO: Extract attachment files.
	$headers = array_merge( hamail_default_headers( 'simple' ), $additional_headers );
	$mail    = new SendGrid\Mail();
	// From
	$from = new SendGrid\Email( get_bloginfo( 'name' ), get_option( 'admin_email' ) );
	$mail->setFrom( $from );
	// Reply To
	$reply_to = new SendGrid\ReplyTo( $headers['from'] );
	$mail->setReplyTo( $reply_to );
	// Subject
	$mail->setSubject( $subject );
	// Check if WooCommerce is not activated.
	$no_woocommerce = ! function_exists( 'WC' );
	// Mail body
	if ( 'text/html' == $headers['format'] ) {
		/**
		 * hamail_should_filter
		 *
		 * Filter if we should apply templates
		 *
		 * @package hamail
		 * @param bool   $no_woocommerce If WooCommerce exists, no filter.
		 * @param array  $headers
		 * @param string $subject
		 * @param string $body
		 * @param array  $recipients
		 */
		$should_filter = apply_filters( 'hamail_should_filter', $no_woocommerce, $headers, $subject, $body, $recipients );
		if ( $should_filter ) {
			hamail_is_sending( true );
			$body = apply_filters( 'the_content', $body );
			hamail_is_sending( false );
		}
		/**
		 * hamail_body_before_send
		 *
		 * @param string $body    Mail body.
		 * @param string $context 'html' or 'plain'
		 * @return string
		 */
		$body = apply_filters( 'hamail_body_before_send', $body, 'html' );
		$content = new SendGrid\Content( 'text/html', $body );
		$mail->addContent( $content );
	} else {
		$body = apply_filters( 'hamail_body_before_send', $body, 'plain' );
		$content = new SendGrid\Content( 'text/plain', strip_tags( $body ) );
		$mail->addContent( $content );
	}
	// Add attachment if exists.
	foreach ( $attachments as $path ) {
		if ( ! file_exists( $path ) ) {
			continue;
		}
		$mime = wp_check_filetype( $path );
		if ( ! $mime[ 'type' ] ) {
			continue;
		}
		$attachment = [
			'content'  => base64_encode( file_get_contents( $path ) ),
			'type'     => $mime[ 'type' ],
			'filename' => basename( $path ),
		];
		$mail->addAttachment( $attachment );
	}
	// Add recipients
	foreach ( $recipient_data as $recipient ) {
		$personalization = new SendGrid\Personalization();
		$email = new SendGrid\Email( $recipient['name'] , $recipient['email'] );
		$personalization->addTo( $email );
		$personalization->setSubject( $subject );
		foreach ( $recipient['substitutions'] as $key => $val ) {
			$personalization->addSubstitution( $key, (string) $val );
		}
		if ( isset( $recipient['custom_arg'] ) ) {
			$personalization->addCustomArg( 'userId', (string) $recipient['custom_arg'] );
		}
		if ( isset( $headers['post_id'] ) ) {
			$personalization->addCustomArg( 'postId', (string) $headers['post_id'] );
		}
		$mail->addPersonalization( $personalization );
	}
	/**
	 * hamail_apply_template
	 *
	 * Filter if we should apply templates
	 *
	 * @package hamail
	 * @param bool   $no_woocommerce If WooCommerce exists, no template default.
	 * @param array  $headers
	 * @param string $subject
	 * @param string $body
	 * @param array  $recipients
	 */
	$should_apply_template = apply_filters( 'hamail_apply_template', $no_woocommerce, $headers, $subject, $body, $recipients );
	if ( $headers['template'] && $should_apply_template ) {
		$mail->setTemplateId( $headers['template'] );
	}
	if ( 1 < count( $recipient_data ) ) {
		$mail->addCategory( 'group' );
	} else {
		$mail->addCategory( 'personal' );
	}
	// Execute
	if ( hamail_is_debug() ) {
		error_log( '[HAMAIL]' . "" . var_export( $mail, true ) );
		return true;
	}
	$sg = hamail_client();
	$response = $sg->client->mail()->send()->post( $mail );
	// Get response
	$code = $response->statusCode();
	if ( preg_match( '#2[\d]{2}#u', $code ) ) {
		return true;
	} else {
		$error = json_decode( $response->body() );
		$error->headers = $response->headers();
		return new WP_Error( $code, json_encode( $error ) );
	}
}

/**
 * Send message
 *
 * @param null|int|WP_Error $post
 *
 * @return bool|WP_Error
 */
function hamail_send_message( $post = null ) {
	$post = get_post( $post );
	if ( 'hamail' != $post->post_type ) {
		return false;
	}
	if ( 'publish' != $post->post_status || hamail_is_sent( $post ) ) {
		return false;
	}
	// O.K. Let's try sending
	$subject = get_the_title( $post );
	$body    = apply_filters( 'the_content', $post->post_content );
	$headers = [ 'post_id' => $post->ID ];
	if ( ! get_post_meta( $post->ID, '_hamail_as_admin', true ) ) {
		$author               = get_userdata( $post->post_author );
		$headers['from']      = $author->user_email;
		$headers['from_name'] = $author->display_name;
	}
	// Create user row
	$to = [];
	// raw email
	if ( $raw = array_filter( array_map( 'trim', explode( ',', get_post_meta( $post->ID, '_hamail_raw_address', true ) ) ) ) ) {
		$to += $raw;
	}
	// roles
	if ( $roles = array_filter( array_map( 'trim', explode( ',', get_post_meta( $post->ID, '_hamail_roles', true ) ) ) ) ) {
		$query = new WP_User_Query( [
			'role__in' => $roles,
			'number'   => - 1,
			'fields'   => 'ID',
		] );
		if ( ! empty( $query->results ) ) {
			$to += $query->results;
		}
	}
	// Users
	if ( $user_ids = get_post_meta( $post->ID, '_hamail_recipients_id', true ) ) {
		$user_ids = explode( ',', $user_ids );

		$query = new WP_User_Query( [
			'include' => $user_ids,
			'number'   => - 1,
			'fields'   => 'ID',
		] );
		if ( ! empty( $query->results ) ) {
			$to += $query->results;
		}
	}
	// Send
	$result = hamail_simple_mail( $to, $subject, $body, $headers );
	if ( is_wp_error( $result ) ) {
		$message = sprintf( '[Error] %s: %s', $result->get_error_code(), current_time( 'mysql' ) )."\n";
		$json = json_decode( $result->get_error_message() );
		foreach ( $json->errors as $error ) {
			$message .= "------\n".$error->message;
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
		// Save log
		add_post_meta( $post->ID, '_hamail_log', $message );

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
	 * @return string[]
	 */
	return apply_filters( 'hamail_css_path', $css_path );
}
