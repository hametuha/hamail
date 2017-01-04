<?php
/**
 * Mail functions
 */

/**
 * Get placeholders
 *
 * @param null|WP_User $user
 *
 * @return array
 */
function hamail_placeholders( $user = null ) {
	if ( is_null( $user ) ) {
		$user = get_userdata( get_current_user_id() );
	}
	$place_holders = [
		'-id-'       => $user->ID,
		'-name-'     => $user->display_name,
		'-nicename-' => $user->user_nicename,
		'-email-'    => $user->user_email,
		'-login-'    => $user->user_login,
	];

	/**
	 * hamail_placeholders
	 *
	 * @filter hamail_placeholders
	 *
	 * @param array $place_holders
	 * @param WP_User $user
	 *
	 * @return array
	 */
	return apply_filters( 'hamail_placeholders', $place_holders, $user );
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
 * Send single mail
 *
 * @param array|string $recipients
 * @param string $subject
 * @param string $body
 * @param array $additional_headers
 *
 * @return bool|WP_Error
 */
function hamail_simple_mail( $recipients, $subject, $body, $additional_headers = [] ) {
	// Parse recipients
	$recipients   = (array) $recipients;
	$id_or_emails = [];
	foreach ( $recipients as $id_or_email ) {
		if ( is_numeric( $id_or_email ) ) {
			$id_or_emails[] = $id_or_email;
		} else {
			// This is email
			if ( $id = email_exists( $id_or_email ) ) {
				$id_or_emails[] = $id;
			} else {
				$id_or_emails[] = $id_or_email;
			}
		}
	}
	$id_or_emails     = array_unique( $id_or_emails );
	$recipient_data   = [];
	/**
	 * hamail_guest_name
	 *
	 * @filter hamail_guest_name
	 *
	 * @param  string $guest_name
	 *
	 * @return string
	 */
	$guest_name = apply_filters( 'hamail_guest_name', __( 'Guest', 'hamail' ) );
	foreach ( $id_or_emails as $id_or_email ) {
		if ( ! $id_or_email ) {
			continue;
		}
		if ( is_numeric( $id_or_email ) ) {
			// This is user id.
			$user = get_userdata( $id_or_email );
			if ( ! $user ) {
				continue;
			}
			/**
			 * hamail_user_can_receive_mail
			 *
			 * @filter hamail_user_can_receive_mail
			 *
			 * @param bool $can_receive
			 * @param WP_User $user
			 *
			 * @return bool
			 */
			$ok = apply_filters( 'hamail_user_can_receive_mail', true, $user );
			if ( ! $ok ) {
				continue;
			}
			$recipient_data[] = [
				'id'  => $user->ID,
				'email' => $user->user_email,
				'name'  => $user->display_name,
				'substitutions' => hamail_placeholders( $user ),
				'custom_args' => $user->ID,
			];
		} else {
			// This is email.
			$place_holders = apply_filters( 'hamail_placeholders', [
				'-id-'       => 0,
				'-name-'     => $guest_name,
				'-nicename-' => 'V/A',
				'-email-'    => $id_or_email,
			    '-login-'    => 'V/A',
			], null );
			$recipient_data[] = [
				'id' => 0,
				'email' => $id_or_email,
				'name'  => $guest_name,
				'substitutions' => $place_holders,
				'custom_args' => 0,
			];
		}
	}
	if ( ! $recipient_data ) {
		return new WP_Error( 'no_recipients', __( 'No recipient set.', 'hamail' ) );
	}
	// Create request body
	$headers      = wp_parse_args( $additional_headers, hamail_default_headers( 'simple' ) );
	$mail = new SendGrid\Mail();
	// From
	$from = new SendGrid\Email( get_bloginfo( 'name' ), get_option( 'admin_email' ) );
	$mail->setFrom( $from );
	// Reply To
	$reply_to = new SendGrid\ReplyTo( $headers['from'] );
	$mail->setReplyTo( $reply_to );
	// Subject
	$mail->setSubject( $subject );
	// Mail body
	if ( 'text/html' == $headers['format'] ) {
		$content = new SendGrid\Content( 'text/html', apply_filters( 'the_content', $body ) );
		$mail->addContent( $content );
	} else {
		$content = new SendGrid\Content( 'text/plain', strip_tags( $body ) );
		$mail->addContent( $content );
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
	if ( $headers['template'] ) {
		$mail->setTemplateId( $headers['template'] );
	}
	if ( 1 < count( $recipient_data ) ) {
		$mail->addCategory( 'group' );
	} else {
		$mail->addCategory( 'personal' );
	}
	// Execute
	$sg       = new \SendGrid( get_option( 'hamail_api_key' ) );
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

