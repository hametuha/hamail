<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\API\Helper\RecipientsList;
use Hametuha\Hamail\API\Helper\UserFilter;
use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Service\TemplateSelector;
use Hametuha\Hamail\Ui\SettingsScreen;
use Hametuha\Hamail\Utility\ApiUtility;

/**
 * Transaction mail API
 */
class TransactionMails extends Singleton {

	use ApiUtility;

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		// Initialize template selector.
		TemplateSelector::get_instance();
		UserFilter::get_instance();
		RecipientsList::get_instance();
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'init', [ $this, 'register_mail_post_type' ] );
		// Save post meta.
		add_action( 'save_post_hamail', [ $this, 'save_post_id' ], 10, 2 );
		// Save post and send mail.
		add_action( 'save_post_hamail', [ $this, 'save_post_and_send_mail' ], 11, 2 );
		// Scheduled post.
		add_action( 'transition_post_status', [ $this, 'send_email_for_scheduled_post' ], 20, 3 );
	}

	/**
	 * Register post type.
	 *
	 * @return void
	 */
	public function register_mail_post_type() {
		if ( ! hamail_enabled() ) {
			return;
		}
		$args = [
			'label'           => __( 'Transaction Mail', 'hamail' ),
			'public'          => false,
			'show_ui'         => true,
			'menu_icon'       => 'dashicons-buddicons-pm',
			'supports'        => [ 'title', 'editor', 'author', 'excerpt' ],
			'capability_type' => 'page',
			'show_in_rest'    => true,
			'show_in_menu'    => SettingsScreen::get_instance()->slug,
			'menu_position'   => 20,
		];
		/**
		 * Arguments for hamail custom post type.
		 *
		 * @filter hamail_post_type_arg
		 *
		 * @param array $args
		 *
		 * @return array
		 */
		$args = apply_filters( 'hamail_post_type_arg', $args );
		register_post_type( 'hamail', $args );
	}

	/**
	 * Save data before sending.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post_id( $post_id, $post ) {
		if ( hamail_is_sent( $post ) ) {
			// If sent, nothing will be updated.
			return;
		}
		// Send as admin.
		if ( wp_verify_nonce( filter_input( INPUT_POST, '_hamailadminnonce' ), 'hamail_as_admin' ) ) {
			update_post_meta( $post_id, '_hamail_as_admin', filter_input( INPUT_POST, 'hamail_as_admin' ) );
			update_post_meta( $post_id, '_unsubscribe_group', filter_input( INPUT_POST, 'unsubscribe_group' ) );
		}
		// Save meta data.
		if ( wp_verify_nonce( filter_input( INPUT_POST, '_hamail_recipients' ), 'hamail_recipients' ) ) {
			// Save roles.
			$roles = implode( ',', array_filter( filter_input( INPUT_POST, 'hamail_roles', FILTER_DEFAULT, FILTER_FORCE_ARRAY ) ?? [] ) );
			update_post_meta( $post->ID, '_hamail_roles', $roles );
			// Save filters.
			update_post_meta( $post->ID, '_hamail_user_filter', $_POST['hamail_user_filters'] ?? [] );
			// Save groups.
			$groups = implode( ',', array_filter( filter_input( INPUT_POST, 'hamail_user_groups', FILTER_DEFAULT, FILTER_FORCE_ARRAY ) ?? [] ) );
			update_post_meta( $post->ID, '_hamail_user_groups', $groups );
			// Save users.
			$users_ids = implode( ',', array_filter( array_map( function ( $id ) {
				$id = trim( $id );
				return is_numeric( $id ) ? $id : false;
			}, explode( ',', filter_input( INPUT_POST, 'hamail_recipients_id' ) ?? '' ) ) ) );
			update_post_meta( $post->ID, '_hamail_recipients_id', $users_ids );
			// Save each address.
			update_post_meta( $post->ID, '_hamail_raw_address', filter_input( INPUT_POST, 'hamail_raw_address' ) ?? '' );
		}
	}

	/**
	 * Send email if this post is published.
	 *
	 * @param int     $post_id
	 * @param \WP_Post $post
	 */
	public function save_post_and_send_mail( $post_id, $post ) {
		// Try sending mail.
		if ( 'publish' === $post->post_status && ! hamail_is_sent( $post ) ) {
			hamail_send_message( $post );
		}
	}

	/**
	 * Send email for scheduled post.
	 *
	 * @see wp_publish_post()
	 * @param string $new_status
	 * @param string $old_status
	 * @param \WP_Post $post
	 */
	public function send_email_for_scheduled_post( $new_status, $old_status, $post ) {
		if ( 'hamail' !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status || 'future' !== $old_status ) {
			// This is not scheduled post publication.
			return;
		}
		if ( hamail_is_sent( $post ) ) {
			// Don't know why, but this post is already sent.
			return;
		}
		// Send mail.
		hamail_send_message( $post );
	}

	/**
	 * Change "Publish" button's label.
	 *
	 * @param string $post_type
	 */
	public function add_meta_boxes( $post_type ) {
		// Register meta boxes.
		if ( 'hamail' === $post_type ) {
			add_filter( 'gettext', function ( $translation, $text, $domain ) {
				if ( 'default' !== $domain ) {
					return $translation;
				}
				switch ( $text ) {
					case 'Publish':
						return __( 'Submit' );
					case 'Schedule':
						return _x( 'Schedule', 'submit-label', 'hamail' );
					default:
						return $translation;
				}
			}, 10, 3 );
			// Enqueue scripts.
			wp_enqueue_style( 'hamail-sender' );
			wp_enqueue_script( 'hamail-sender' );
			add_meta_box( 'hamail-recipients', __( 'Recipients', 'hamail' ), [ $this, 'recipients_meta_box' ], $post_type, 'normal', 'high', [] );
			// Sending status.
			add_meta_box( 'hamail-status', __( 'Sending Status', 'hamail' ), [ $this, 'status_meta_box' ], $post_type, 'side', 'low', [] );
		}
		// Recipients.
		$should_show_stats = apply_filters( 'hamail_should_show_placeholder_meta_box', ( 'hamail' === $post_type ), $post_type );
		if ( $should_show_stats ) {
			wp_enqueue_style( 'hamail-sender' );
			// Placeholders.
			$place_holders = hamail_placeholders();
			if ( ! empty( $place_holders ) ) {
				add_meta_box( 'hamail-placeholders', __( 'Available Placeholders', 'hamail' ), [ $this, 'placeholders_meta_box' ], $post_type, 'normal', 'low', [
					'placeholders' => $place_holders,
				] );
			}
		}
	}

	/**
	 * Show placeholders
	 *
	 * @param \WP_Post $post
	 */
	public function placeholders_meta_box( $post, $args ) {
		$place_holders = $args['args']['placeholders'];
		?>
		<p class="description">
			<?php esc_html_e( 'You can use placeholders below in mail body and subject.', 'hamail' ); ?>
		</p>
		<div class="hamail-instruction">
			<table class="hamail-instruction-table">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Placeholder', 'hamail' ); ?></th>
					<th><?php esc_html_e( 'Result Value(Example)', 'hamail' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $place_holders as $key => $value ) : ?>
					<tr>
						<th><?php echo esc_html( $key ); ?></th>
						<td><?php echo esc_html( $value ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * User search selector.
	 *
	 * @param \WP_Post $post
	 */
	public function recipients_meta_box( $post ) {
		$users = [];
		// Get Saved user ids.
		$user_ids = array_values( array_filter( explode( ',', get_post_meta( $post->ID, '_hamail_recipients_id', true ) ), function ( $id ) {
			return ! empty( $id ) && is_numeric( $id );
		} ) );
		if ( $user_ids ) {
			$user_query = new \WP_User_Query( [
				'include' => $user_ids,
			] );
			$users      = array_map( function ( $user ) {
				return [
					'user_id'      => $user->ID,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
				];
			}, $user_query->get_results() );
		}
		if ( ! hamail_is_sent( $post ) ) {
			wp_nonce_field( 'hamail_recipients', '_hamail_recipients', false );
		}
		?>
		<?php if ( hamail_is_sent( $post ) ) : ?>
			<p class="hamail-address-notice-sent">
				<?php esc_html_e( 'This mail has been sent already. Any change won\'t be saved.', 'hamail' ); ?>
			</p>
		<?php endif; ?>


		<p style="text-align: right;">
			<a href="<?php echo esc_url( wp_nonce_url( rest_url( 'hamail/v1/recipients/' . $post->ID ), 'wp_rest' ) ); ?>"
				class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Check Recipients in CSV', 'hamail' ); ?>
			</a>
		</p>

		<div class="hamail-address">
			<div class="hamail-address-group">
				<h4 class="hamail-address-title"><?php esc_html_e( 'Filter Users', 'hamail' ); ?></h4>
				<p class="hamail-filtered-users" style="text-align: right;">
					<?php esc_html_e( 'Matching Recipients: ', 'hamail' ); ?>
					<code id="hamail-user-filter-count"></code>
				</p>
				<h5><?php esc_html_e( 'Roles', 'hamail' ); ?></h5>
				<?php foreach ( get_editable_roles() as $key => $role ) : ?>
					<label class="inline-block">
						<input type="checkbox" name="hamail_roles[]"
								value="<?php echo esc_attr( $key ); ?>" <?php checked( hamail_has_role( $key, $post ) ); ?> />
						<?php echo translate_user_role( $role['name'] ); ?>
					</label>
				<?php endforeach; ?>
				<?php
				// Display filters.
				$filters = UserFilter::get_instance()->filters();
				$current = UserFilter::get_instance()->get_filter( $post );
				if ( ! empty( $filters ) ) {
					foreach ( $filters as $filter ) {
						printf( '<hr /><div class="hamail-user-filter" data-filter-id="%s"><h5>%s</h5>', esc_attr( $filter['id'] ), esc_html( $filter['label'] ) );
						do_action( 'hamail_user_filter_rendering', $filter, $current[ $filter['id'] ] ?? [] );
						echo '</div>';
					}
				}
				?>

			</div>

			<div class="hamail-address-group">
				<h4><?php esc_html_e( 'Specify Users', 'hamail' ); ?></h4>

				<div class="hamail-address-user-group">
					<h5 class="hamail-address-title"><?php esc_html_e( 'User Group', 'hamail' ); ?></h5>
					<?php
					$groups = hamail_user_groups();
					if ( $groups ) {
						$post_groups = array_filter( explode( ',', get_post_meta( $post->ID, '_hamail_user_groups', true ) ) );
						foreach ( $groups as $group ) {
							printf(
								'<label class="inline-block" title="%4$s"><input type="checkbox" name="hamail_user_groups[]" value="%1$s" %5$s /> %2$s <small>(%3$d)</small></label>',
								esc_attr( $group->name ),
								esc_html( $group->label ),
								esc_html( $group->count ),
								esc_attr( $group->description ),
								checked( in_array( $group->name, $post_groups, true ), true, false )
							);
						}
					} else {
						printf( '<p class="description">%s</p>', esc_html__( 'User group is not available.', 'hamail' ) );
					}
					?>
				</div>

				<hr/>

				<div class="hamail-address-users">
					<h5 class="hamail-address-title"><?php esc_html_e( 'User', 'hamail' ); ?></h5>
					<div class="hamail-search-wrapper" id="hamail-search-users">
						<div class="hamail-search-type">
							<span
								class="hamail-search-type-label"><?php echo esc_html_x( 'Search Target:', 'hamail-user-search', 'hamail' ); ?></span>
							<?php
							$checked = true;
							foreach ( hamail_recipients_group() as $search ) :
								?>
								<label class="inline-block">
									<input type="radio" name="hamail_search_action"
											id="<?php echo esc_attr( $search['id'] ); ?>"
											value="<?php echo esc_attr( $search['endpoint'] ); ?>" <?php checked( $checked ); ?> />
									<?php echo esc_html( $search['label'] ); ?>
								</label>
								<?php
								$checked = false;
							endforeach;
							?>
						</div>
						<input class="hamail-search-value" type="hidden" name="hamail_recipients_id"
								value="<?php echo esc_attr( get_post_meta( $post->ID, '_hamail_recipients_id', true ) ); ?>"/>
						<input type="text" class="regular-text hamail-search-field" value=""
								placeholder="<?php esc_attr_e( 'Type and search users...', 'hamail' ); ?>"/>
						<ul class="hamail-search-list"></ul>
					</div>
				</div>

				<hr/>


				<div class="hamail-address-raw">
					<h5 class="hamail-address-title"><?php _e( 'Specified Address', 'hamail' ); ?></h5>
					<label for="hamail_raw_address" class="block">
						<?php _e( 'Enter comma separated mail address', 'hamail' ); ?>
					</label>
					<textarea class="hamail-address-textarea" name="hamail_raw_address"
								placeholder="foo@example.com,var@example.com" rows="3"
								id="hamail_raw_address"><?php echo esc_textarea( get_post_meta( $post->ID, '_hamail_raw_address', true ) ); ?></textarea>
					<p><?php esc_html_e( 'Recipients: ', 'hamail' ); ?><span id="hamail-address-counter">0</span></p>
				</div>

			</div>
		</div><!-- //.hamail-address -->
		<?php
	}

	/**
	 * Display status meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function status_meta_box( $post ) {
		if ( hamail_is_sent() ) :
			?>
			<p class="hamail-success">
				<span class="dashicons dashicons-yes"></span>
				<?php
				echo esc_html( sprintf(
					__( 'This message was sent at %1$s as %2$s.', 'hamail' ),
					hamail_sent_at( $post, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
					get_post_meta( $post->ID, '_hamail_as_admin', true ) ? __( 'Site Admin', 'hamail' ) : get_the_author_meta( 'display_name', $post->post_author )
				) )
				?>
			</p>
			<p>
				<label for="hamail-message-ids"><?php esc_html_e( 'Message ID', 'hamail' ); ?></label>
				<?php
				$message_ids = array_filter( (array) get_post_meta( $post->ID, '_hamail_message_ids', true ) );
				printf(
					'<textarea id="hamail-message-ids" name="hamail_message_ids" rows="3" class="widefat" readonly placeholder="%s">%s</textarea>',
					esc_attr__( 'No message ID', 'hamail' ),
					esc_textarea( implode( "\n", $message_ids ) )
				);
				?>
				<span class="description">
					<?php
					esc_html_e( 'Message IDs are set if the message is actually sent via SendGrid. This works for filtering activity logs.', 'hamail' );
					?>
				</span>
			</p>
		<?php else : ?>
			<?php wp_nonce_field( 'hamail_as_admin', '_hamailadminnonce', false ); ?>
			<p class="description">
				<?php esc_html_e( 'This message is not sent yet.', 'hamail' ); ?>
			</p>
			<label class="hamail-block">
				<input type="checkbox" name="hamail_as_admin" value="1"
					<?php checked( get_post_meta( $post->ID, '_hamail_as_admin', true ) ); ?> />
				<?php esc_html_e( 'Send as Site Admin', 'hamail' ); ?>
			</label>
		<?php endif; ?>
		<hr />
		<h4><label for="unsubscribe-group"><?php esc_html_e( 'Unsubscribe', 'hamail' ); ?></label></h4>
		<select name="unsubscribe_group" id="unsubscribe-group" style="box-sizing: border-box;">
			<?php
			$options = [ '' => __( 'Not Set', 'hamail' ) ];
			$group   = $this->get_unsubscribe_group();
			foreach ( $group as $item ) {
				$options[ $item['id'] ] = $item['name'] . ( $item['is_default'] ? __( '(Default)', 'hamail' ) : '' );
			}
			foreach ( $options as $value => $label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $value ),
					selected( get_post_meta( $post->ID, '_unsubscribe_group', true ), $value, false ),
					esc_html( $label )
				);
			}
			?>
		</select>
		<p class="description"><?php esc_html_e( 'This settings is invalid for legacy templates. Available in future update.', 'hamail' ); ?></p>
		<?php
		$logs = get_post_meta( $post->ID, '_hamail_log' );
		if ( $logs ) :
			?>
			<hr />
			<h4><?php esc_html_e( 'Error Logs', 'hamail' ); ?></h4>
			<?php foreach ( $logs as $log ) : ?>
			<pre class="hamail-success-log"><?php echo nl2br( esc_html( $log ) ); ?></pre>
		<?php endforeach; ?>
			<?php
		endif;
	}
}
