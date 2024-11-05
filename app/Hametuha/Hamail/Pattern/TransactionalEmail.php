<?php

namespace Hametuha\Hamail\Pattern;

/**
 * Skeleton for Transactional Email.
 *
 * @package hamail
 */
abstract class TransactionalEmail extends Singleton {

	/**
	 * Recipients data.
	 *
	 * @var array
	 */
	protected $recipients = [];

	protected $data = [];

	/**
	 * Set recipients.
	 *
	 * @param array $recipients
	 */
	protected function set_recipients( $recipients = [] ) {
		$this->recipients = $recipients;
	}

	/**
	 * Set data for this mail.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	protected function set_data( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Returns title.
	 *
	 * @return string
	 */
	abstract protected function get_subject();

	/**
	 * Return mail body.
	 *
	 * @return string
	 */
	abstract protected function get_body();

	/**
	 * Headers for this email.
	 *
	 * @return array
	 */
	protected function get_headers() {
		return [];
	}

	/**
	 * Attachments for this email.
	 *
	 * @return array
	 */
	protected function get_attachments() {
		return [];
	}

	/**
	 * Render contents programmatically.
	 *
	 * @return \WP_Error|bool
	 */
	protected function send() {
		if ( ! $this->recipients ) {
			// No recipients.
			// translators: %s is class name.
			return new \WP_Error( 'no_recipients', sprintf( __( 'No recipients set: %s', 'hamail' ), get_called_class() ) );
		}
		$subject = $this->get_subject();
		$body    = $this->get_body();
		if ( ! $subject || ! $body ) {
			// translators: %s is class name.
			return new \WP_Error( 'invalid_mail_parts', sprintf( __( 'Subject or mail body is empty: %s', 'hamail' ), get_called_class() ) );
		}
		return hamail_simple_mail( $this->recipients, $subject, $body, $this->get_headers(), $this->get_attachments() );
	}


	/**
	 * Execute mail send.
	 *
	 * @param array $recipients
	 * @return \WP_Error|bool
	 */
	public static function exec( $recipients = [] ) {
		$instance = static::get_instance();
		$instance->set_recipients( $recipients );
		$result = $instance->send();
		if ( is_wp_error( $result ) ) {
			error_log( '[Hamail] ' . $result->get_error_message() );
		}
		return $result;
	}

	/**
	 * Register hooks here.
	 */
	public static function register() {
		// Do something, for example, register hook.
	}

	/**
	 * Returns body and subject.
	 *
	 * @return array
	 */
	public static function test() {
		$instance = static::get_instance();
		return [
			'subject'     => $instance->get_subject(),
			'body'        => $instance->get_body(),
			'headers'     => $instance->get_headers(),
			'attachments' => $instance->get_attachments(),
		];
	}
}
