<?php
/**
 * User mail to send.
 *
 * @package hamail
 */

/**
 *
 *
 * @param string $subject
 * @param string $body
 * @param array  $to
 * @param int    $parent
 * @param int    $author_id
 * @param string $status
 * @return WP_Post|WP_Error
 */
function hamail_create_user_contact( $subject, $body, $to = [], $parent = 0, $author_id = 0, $status = 'draft' ) {
	if ( empty( $subject ) || empty( $body ) ) {
		return new WP_Error( 'hamail_mal_format', __( 'Mail subject and mail body are required.', 'hamail' ), [
			'status' => 400,
		] );
	}
	$ids    = [];
	$emails = [];
	foreach ( $to as $id_or_mail ) {
		$id = '';
		if ( is_numeric( $id_or_mail ) ) {
			$ids[] = $id_or_mail;
		} elseif ( $exist = email_exists( $id_or_mail ) ) {
			$ids[] = $exist;
		} elseif ( is_email( $id_or_mail ) ) {
			$emails[] = $id_or_mail;
		}
	}
	if ( ! $ids && ! $emails ) {
		return new WP_Error( 'hamail_mal_format', __( 'At least one recipient is required.', 'hamail' ), [
			'status' => 400,
		] );
	}
	$args = [
		'post_type'    => 'hamail',
		'post_title'   => $subject,
		'post_content' => $body,
		'post_status'  => $status,
		'post_author'  => $author_id ?: get_current_user_id(),
		'post_parent'  => $parent,
	];
	$post_id = wp_insert_post( $args, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	if ( $ids ) {
		update_post_meta( $post_id, '_hamail_recipients_id', implode( ',', $ids ) );
	}
	if ( $emails ) {
		update_post_meta( $post_id,'_hamail_raw_address', implode( ',', $emails ) );
	}
	return get_post( $post_id );
}
