<?php
/**
 * Make list page for hamail post type
 *
 * @package hamail
 */

// Mail column
add_filter( 'manage_hamail_posts_columns', function ( $columns ) {
	wp_enqueue_style( 'hamail-sender' );
	$new_columns = [];
	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;
		if ( 'title' == $key ) {
			$new_columns['recipients'] = __( 'Recipients', 'hamail' );
		}
	}
	$new_columns['status'] = __( 'Status', 'hamail' );
	return $new_columns;
}, 10, 2 );

// Column content.
add_action( 'manage_hamail_posts_custom_column', function( $column, $post_id ) {
	switch ( $column ) {
		case 'status':
			if ( $at = hamail_sent_at( $post_id ) ) {
				printf( '<span class="dashicons dashicons-yes" title="%s"></span>', esc_attr( mysql2date( get_option( 'date_format' ).' '.get_option( 'time_format' ), $at ) ) );
			} else {
				echo '<span class="dashicons dashicons-no"></span>';
			}
			break;
		case 'recipients':
			$recipients = [];
			// Get roles
			$roles = array_filter( explode( ',', get_post_meta( $post_id, '_hamail_roles', true ) ) );
			$registered_roles = get_editable_roles();
			foreach ( $registered_roles as $role => $wp_role ) {
				if ( false !== array_search( $role, $roles ) ) {
					$recipients[] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'users.php?role='.$role ) ),
						esc_html( translate_user_role( $wp_role['name'] ) )
					);
				}
			}
			// Add users
			if ( $user_ids = array_filter( explode( ',', get_post_meta( $post_id, '_hamail_recipients_id', true ) ) ) ) {
				$user_query = new WP_User_Query( [
					'include' => $user_ids,
					'number'  => -1,
				] );
				if ( $users = $user_query->get_results() ) {
					$recipients += array_map( function ( $user ) {
						return sprintf(
							'<a href="%s">%s</a>',
							admin_url( 'user-edit.php?user_id=' . $user->ID ),
							esc_html( $user->display_name )
						);
					}, $users );
				}
			}
			// Mail address
			if ( $emails = array_filter( explode( ',', get_post_meta( $post_id, '_hamail_raw_address', true ) ) ) ) {
				$recipients += array_map( function( $email ) {
					return sprintf(
						'<a href="mailto:%1$s">%1$s</a>',
						esc_html( $email )
					);
				}, $emails );
			}
			$others = 0;
			if ( 10 < count( $recipients ) ) {
				$others = count( $recipients ) - 10;
				$recipients = array_slice( $recipients, 0, 10 );
			}
			echo implode( ', ', $recipients );
			if ( $others ) {
				echo sprintf( esc_html__( ' and %s others', 'hamail' ), number_format_i18n( $others ) );
			}
			break;
		default:
			// Do nothing.
			break;
	}
}, 10, 2 );
