<?php
/**
 * Sending screen
 */

/**
 * Change "Publish" button's label.
 */
add_action( 'add_meta_boxes', function() {
	add_filter( 'gettext', function( $translation, $text, $domain ) {
		if ( ! is_admin() ) {
			return $translation;
		}
		$screen = get_current_screen();
		if ( 'hamail' !== $screen->post_type ) {
			return $translation;
		}
		if ( 'default' !== $domain ) {
			return $translation;
		}
		switch( $text ) {
			case 'Publish':
				return __( 'Submit' );
			case 'Schedule':
				return _x( 'Schedule', 'submit-label', 'hamail' );
			default:
				return $translation;
		}
		if ( 'Publish' === $text && 'default' === $domain ) {
			$translation = __( 'Submit' );
		}
		return $translation;
	}, 10, 3 );
} );

/**
 * Register post type
 */
add_action( 'init', function () {
	if ( ! hamail_enabled() ) {
		return;
	}
	$args = [
		'label'           => __( 'User Contact', 'hamail' ),
		'public'          => false,
		'show_ui'         => true,
		'menu_icon'       => 'dashicons-email-alt',
		'supports'        => [ 'title', 'editor', 'author' ],
		'capability_type' => 'page',
	];
	/**
	 * hamail_post_type_arg
	 *
	 * @filter hamail_post_type_arg
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	$args = apply_filters( 'hamail_post_type_arg', $args );
	register_post_type( 'hamail', $args );
} );

/**
 * Save data
 */
add_action( 'save_post_hamail', function ( $post_id, $post ) {
	// Save as admin
	if ( isset( $_REQUEST['_hamailadminnonce'] ) && wp_verify_nonce( $_REQUEST['_hamailadminnonce'], 'hamail_as_admin' ) ) {
		update_post_meta( $post_id, '_hamail_as_admin', isset( $_POST['hamail_as_admin'] ) );
	}
	// Try send mail.
	if ( ! hamail_is_sent( $post ) ) {
		// Save data
		if ( isset( $_REQUEST['_hamail_recipients'] ) && wp_verify_nonce( $_REQUEST['_hamail_recipients'], 'hamail_recipients' ) ) {
			// Save roles
			$roles = isset( $_POST['hamail_roles'] ) ? implode( ',', $_POST['hamail_roles'] ) : '';
			update_post_meta( $post->ID, '_hamail_roles', $roles );
			// Save users
            $users_ids = implode( ',', array_filter( array_map( function( $id ) {
                $id = trim($id);
                return is_numeric( $id ) ? $id : false;
            }, explode( ',', $_POST['hamail_recipients_id'] ) ) ));
            update_post_meta( $post_id, '_hamail_recipients_id', $users_ids );

			// Save each address
			update_post_meta( $post->ID, '_hamail_raw_address', $_POST['hamail_raw_address'] );

		}
		// Send
		if ( 'publish' === $post->post_status ) {
			hamail_send_message( $post );
		}
	}
}, 10, 2 );

/**
 * Show place holders
 *
 * @param WP_Post $post
 */
add_action( 'edit_form_after_title', function( $post ) {
	if ( 'hamail' == $post->post_type ) {
		$place_holders = hamail_placeholders();
		if ( ! $place_holders ) {
			return;
		}
		?>
        <div class="hamail-instruction">
            <table class="hamail-instruction-table">
                <caption><?php _e( 'You can use placeholders below.', 'hamail' ) ?></caption>
                <thead>
                <tr>
                    <th><?php _e( 'Placeholder', 'hamail' ) ?></th>
                    <th><?php _e( 'Result Value(Example)', 'hamail' ) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $place_holders as $key => $value ) : ?>
                    <tr>
                        <th><?php echo esc_html( $key ) ?></th>
                        <td><?php echo esc_html( $value ) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
		<?php
	}
} );

/**
 * Search user via Ajax
 */
add_action( 'wp_ajax_hamail_search', function () {
	try {
		if ( ! hamail_allowed() ) {
			throw new Exception( __( 'You have no permission', 'hamail' ), 403 );
		}
		if ( ! isset( $_GET['term'] ) || ! $_GET['term'] ) {
			throw new Exception( __( 'Search query is not set.', 'hamail' ), 400 );
		}
		wp_send_json( hamail_search( $_GET['term'] ) );
	} catch ( \Exception $e ) {
		status_header( $e->getCode() );
		wp_send_json_error( [
			'message' => $e->getMessage(),
		] );
	}
} );

/**
 * Search user with term_id
 */
add_action( 'wp_ajax_hamail_term_authors', function () {
	try {
		if ( ! hamail_allowed() ) {
			throw new Exception( __( 'You have no permission', 'hamail' ), 403 );
		}
		if ( ! isset( $_GET['term_id'] ) || ! $_GET['term_id'] ) {
			throw new Exception( __( 'Term ID is not set.', 'hamail' ), 400 );
		}
		if ( isset( $_GET['type'] ) && 'term' != $_GET['type'] ) {
			/**
			 * hamail_extra_search
			 *
			 * Filter for extra types
			 * @since 1.0.0
			 * @package hamail
			 * @param array  $result Default emmpty
			 * @param string $type   Custom type name
			 * @param string $id     ID
			 */
			$result = apply_filters( 'hamail_extra_search', [], $_GET['type'], $_GET['term_id'] );
		} else {
			$term = get_term( $_GET['term_id'] );
			if ( ! $term || is_wp_error( $term ) ) {
				throw new Exception( __( 'Term not found.', 'hamail' ), 404 );
			}
			$result = hamail_term_authors( $term->term_taxonomy_id );
		}
		wp_send_json( $result );
	} catch ( \Exception $e ) {
		status_header( $e->getCode() );
		wp_send_json_error( [
			'message' => $e->getMessage(),
		] );
	}
} );

/**
 * Register meta box
 */
add_action( 'add_meta_boxes', function ( $post_type ) {
	if ( 'hamail' == $post_type ) {
		// Enqueue scripts
		wp_enqueue_style( 'hamail-sender' );
		wp_enqueue_script( 'hamail-sender' );
		// Recipients
		add_meta_box( 'hamail-recipients', __( 'Recipients', 'hamail' ), function ( $post ) {
		    $users = [];
			$user_ids   = get_post_meta( $post->ID, '_hamail_recipients_id', true );
			$user_query = new WP_User_Query( [
				'include' => explode( ',', $user_ids ),
			] );
			$users      = array_map( function ( $user ) {
				return [
					'user_id'      => $user->ID,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
				];
			}, $user_query->get_results() );
			wp_localize_script( 'hamail-sender', 'HamailRecipients', [
                'search_endpoint' => admin_url( 'admin-ajax.php?action=hamail_search' ),
                'term_endpoint'   => admin_url( 'admin-ajax.php?action=hamail_term_authors' ),
                'users' => $users,
            ] );
			if ( ! hamail_is_sent( $post ) ) {
				wp_nonce_field( 'hamail_recipients', '_hamail_recipients', false );
			}
			?>
            <div class="hamail-address">
				<?php if ( hamail_is_sent( $post ) ) : ?>
                    <p class="description">
						<?php _e( 'This mail has been sent already. Any change won\'t be saved.', 'hamail' ); ?>
                    </p>
				<?php endif; ?>
                <div class="hamail-address-roles">
                    <h4 class="hamail-address-title"><?php _e( 'Roles', 'hamail' ) ?></h4>
					<?php foreach ( get_editable_roles() as $key => $role ) : ?>
                        <label class="inline-block">
                            <input type="checkbox" name="hamail_roles[]"
                                   value="<?php echo esc_attr( $key ) ?>" <?php checked( hamail_has_role( $key, $post ) ) ?> />
							<?php echo translate_user_role( $role['name'] ) ?>
                        </label>
					<?php endforeach; ?>
                </div>
                <hr />
                <div class="hamail-address-users">
                    <h4 class="hamail-address-title"><?php _e( 'Users', 'hamail' ) ?></h4>
                    <div id="hamail-users">

                        <input type="hidden" name="hamail_recipients_id" id="hamail-address-users-id" value="" />
                        <input type="text" class="regular-text" id="hamail-address-search" value="" placeholder="<?php esc_attr_e( 'Type and search user...', 'hamail' ) ?>" />
                        <ul id="hamail-address-list" class="hamail-address-list">
                        </ul>
                        <script type="text/template" id="hamail-user-card">
                            <span class="hamail-address-user-name" title="<%- user_email %>"><%- display_name %></span>
                            <a href="#" class="remove"><i class="dashicons dashicons-no"></i></a>
                        </script>

                    </div>
                </div>
                <hr />
                <div class="hamail-address-raw">
                    <h4 class="hamail-address-title"><?php _e( 'Specified Address', 'hamail' ) ?></h4>
                    <label for="hamail_raw_address" class="block">
						<?php _e( 'Enter comma separated mail address', 'hamail' ) ?>
                    </label>
                    <textarea class="hamail-address-textarea" name="hamail_raw_address"
                              placeholder="foo@example.com,var@example.com" rows="3"
                              id="hamail_raw_address"><?php echo esc_textarea( get_post_meta( $post->ID, '_hamail_raw_address', true ) ) ?></textarea>
                </div>
            </div>
			<?php
		}, 'hamail', 'normal', 'high' );

		// Sending status
		add_meta_box( 'hamail-status', __( 'Sending Status', 'hamail' ), function ( $post ) {
			?>
			<?php if ( hamail_is_sent() ) : ?>
                <p class="hamail-success">
                    <span class="dashicons dashicons-yes"></span>
					<?php printf(
						__( 'This message was sent at %1$s as %2$s.', 'hamail' ),
						hamail_sent_at( $post, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
						get_post_meta( $post->ID, '_hamail_as_admin', true ) ? __( 'Site Admin', 'hamail' ) : get_the_author_meta( 'display_name', $post->post_author )
					) ?>
                </p>
			<?php else : ?>
				<?php wp_nonce_field( 'hamail_as_admin', '_hamailadminnonce', false ) ?>
                <p class="description">
					<?php _e( 'This message is not sent yet.', 'hamail' ) ?>
                </p>
                <label class="hamail-block">
                    <input type="checkbox" name="hamail_as_admin" value="1"
						<?php checked( get_post_meta( $post->ID, '_hamail_as_admin', true ) ) ?> />
					<?php _e( 'Send as Site Admin', 'hamail' ) ?>
                </label>
			<?php endif; ?>
			<?php if ( $logs = get_post_meta( $post->ID, '_hamail_log' ) ) : ?>
                <h4><?php _e( 'Error Logs', 'hamail' ) ?></h4>
				<?php foreach ( $logs as $log ) : ?>
                    <pre class="hamail-success-log"><?php echo nl2br( esc_html( $log ) ) ?></pre>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php
		}, 'hamail', 'side', 'low' );
	}
} );
