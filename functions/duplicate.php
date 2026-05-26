<?php
/**
 * Post duplication helpers.
 *
 * @package hamail
 */

defined( 'ABSPATH' ) || die();

/**
 * Meta keys that represent send/schedule state and should NOT be carried over
 * when a post is duplicated.
 *
 * Use this list when configuring duplication plugins. For example, in
 * Yoast Duplicate Post: Settings → Permissions → "Do not copy these fields".
 *
 * @return string[]
 */
function hamail_volatile_meta_keys() {
	return apply_filters( 'hamail_volatile_meta_keys', [
		// Transactional mail (hamail).
		'_hamail_sent',
		'_hamail_message_ids',
		'_hamail_log',
		// Marketing mail (hamail-marketing).
		'_hamail_sent_at',
		'_hamail_scheduled_at',
		'_hamail_marketing_id',
	] );
}
