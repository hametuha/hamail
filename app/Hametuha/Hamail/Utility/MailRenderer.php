<?php

namespace Hametuha\Hamail\Utility;


/**
 * Mail renderer.
 *
 * @package hamail
 */
trait MailRenderer {

	/**
	 * Replace subject with string.
	 *
	 * @param string $template Mail template to be replaced.
	 * @param string $subject  Mail subject.
	 * @return string
	 */
	public function replace_subject( $template, $subject ) {
		return $this->replace_mail_placeholder( $template, $subject, '{%subject%}' );
	}

	/**
	 * Replace body with string
	 *
	 * @param string $template Mail template to be replaced.
	 * @param string $subject  Mail subject.
	 * @return string
	 */
	public function replace_body( $template, $subject ) {
		return $this->replace_mail_placeholder( $template, $subject, '{%body%}' );
	}

	/**
	 * Replace string.
	 *
	 * @param string $template    Mail template to be replaced.
	 * @param string $subject     Mail subject.
	 * @param string $placeholder Placeholder.
	 * @return string
	 */
	protected function replace_mail_placeholder( $template, $subject, $placeholder ) {
		return str_replace( $placeholder, $subject, $template );
	}

	/**
	 * Apply template.
	 *
	 * @param string $template
	 * @param string $subject
	 * @param string $body
	 * @return string
	 */
	public function replace( $template, $subject, $body ) {
		return $this->replace_subject( $this->replace_body( $template, $body ), $subject );
	}

	/**
	 * Get preheader text from post excerpt.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function get_preheader( $post ) {
		$excerpt = get_the_excerpt( $post );
		$excerpt = strip_tags( $excerpt );
		return apply_filters( 'hamail_marketing_preheader', $excerpt, $post );
	}
}
