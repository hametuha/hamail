<?php

namespace Hametuha\Hamail\Ui\ListTable;


use Hametuha\Hamail\Pattern\ListTablePattern;

/**
 * Render list tables.
 *
 * @package hamail
 */
class RecipientsColumn extends ListTablePattern {

	/**
	 * Post types.
	 *
	 * @return string[]
	 */
	protected function post_types() {
		return [ 'hamail' ];
	}

	/**
	 * Add columns.
	 *
	 * @param string[] $columns
	 * @return string[]
	 */
	public function add_columns( $columns ) {
		wp_enqueue_style( 'hamail-sender' );
		$new_columns = [];
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns['recipients'] = __( 'Recipients', 'hamail' );
			}
		}
		$new_columns['status'] = __( 'Status', 'hamail' );
		$new_columns['parent'] = __( 'Reply To', 'hamail' );
		return $new_columns;
	}

	/**
	 * Render columns.
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	public function render_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'status':
				$at = hamail_sent_at( $post_id );
				if ( $at ) {
					printf( '<span class="dashicons dashicons-yes" title="%s"></span>', esc_attr( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $at ) ) );
				} else {
					echo '<span class="dashicons dashicons-no"></span>';
				}
				break;
			case 'recipients':
				$recipients = [];
				// Get roles.
				$roles            = array_filter( explode( ',', get_post_meta( $post_id, '_hamail_roles', true ) ) );
				$registered_roles = get_editable_roles();
				foreach ( $registered_roles as $role => $wp_role ) {
					if ( false !== array_search( $role, $roles, true ) ) {
						$recipients[] = sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'users.php?role=' . $role ) ),
							esc_html( translate_user_role( $wp_role['name'] ) )
						);
					}
				}
				// Groups.
				$groups = hamail_user_groups();
				if ( $groups ) {
					$post_groups = array_filter( explode( ',', get_post_meta( $post_id, '_hamail_user_groups', true ) ) );
					foreach ( $groups as $group ) {
						if ( ! in_array( $group->name, $post_groups, true ) ) {
							continue;
						}
						$recipients[] = sprintf( '<span>%s</span>', esc_html( $group->label ) );
					}
				}
				// Add users.
				$user_ids = array_filter( explode( ',', get_post_meta( $post_id, '_hamail_recipients_id', true ) ) );
				if ( $user_ids ) {
					$user_query = new \WP_User_Query( [
						'include' => $user_ids,
						'number'  => -1,
					] );
					$users      = $user_query->get_results();
					foreach ( $user_query->get_results() as $user ) {
						$recipients[] = sprintf(
							'<a href="%s">%s</a>',
							admin_url( 'user-edit.php?user_id=' . $user->ID ),
							esc_html( $user->display_name )
						);
					}
				}
				// Mail address.
				$emails = array_filter( explode( ',', get_post_meta( $post_id, '_hamail_raw_address', true ) ) );
				foreach ( $emails as $email ) {
					$recipients[] = sprintf(
						'<a href="mailto:%1$s">%1$s</a>',
						esc_html( $email )
					);
				}
				// Apply filters for recipients.
				$recipients = apply_filters( 'hamail_recipients_in_admin_list', $recipients, $post_id );
				// Render items.
				$others = 0;
				if ( 10 < count( $recipients ) ) {
					$others     = count( $recipients ) - 10;
					$recipients = array_slice( $recipients, 0, 10 );
				}
				echo implode( ', ', $recipients );
				if ( $others ) {
					// translators: %s is number of users.
					echo sprintf( esc_html__( ' and %s others', 'hamail' ), number_format_i18n( $others ) );
				}
				break;
			case 'parent':
				$parent = wp_get_post_parent_id( $post_id );
				if ( $parent ) {
					printf( '<a href="%s">#%d</a>', get_edit_post_link( $parent ), $parent );
				} else {
					echo '<span style="color: grey">----</span>';
				}
				break;
			default:
				// Do nothing.
				break;
		}
	}


}
