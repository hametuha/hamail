<?php

namespace Hametuha\Hamail\Controller;


use Hametuha\Hamail\Pattern\Singleton;

/**
 * Use SMTP for wp_mail.
 */
class SmtpController extends Singleton {

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( ! hamail_use_smtp() ) {
			return;
		}
		add_action( 'phpmailer_init', [ $this, 'phpmailer_init' ] );
		add_action( 'wp_mail_failed', [ $this, 'mail_failure_handler' ] );
	}

	/**
	 * FIx PHPMailer settings.
	 *
	 * @see https://sendgrid.kke.co.jp/docs/API_Reference/SMTP_API/getting_started_smtp.html
	 * @param \PHPMailer\PHPMailer\PHPMailer $mailer
	 * @return void
	 */
	public function phpmailer_init( &$mailer ) {
		$mailer->isSMTP();
		$mailer->Host       = 'smtp.sendgrid.net';
		$mailer->SMTPAuth   = true;
		$mailer->Username   = 'apikey';
		$mailer->Password   = get_option( 'hamail_api_key' );
		$mailer->SMTPSecure = 'tls';
		$mailer->Port       = 587;
	}

	/**
	 * Error log if mail send failed.
	 *
	 * @param \WP_Error $error
	 * @return void
	 */
	public function mail_failure_handler( $error ) {
		error_log( sprintf( '[Hamail] %s', $error->get_error_message() ) );
	}
}
