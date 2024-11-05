<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Ui\MarketingTemplate;
use Hametuha\Hamail\Ui\SettingsScreen;
use Hametuha\Hamail\Utility\ApiUtility;
use Hametuha\Hamail\Utility\Logger;
use Hametuha\Hamail\Utility\RestApiPermission;

/**
 * Marketing feature.
 *
 * @package hamail
 * @property-read MarketingTemplate $template
 */
class MarketingEmail extends Singleton {

	use ApiUtility;
	use Logger;
	use RestApiPermission;

	const POST_TYPE = 'marketing-mail';

	const META_KEY_SENDER = '_hamail_sender';

	const META_KEY_SENT_AT = '_hamail_sent_at';

	const META_KEY_SCHEDULED_AT = '_hamail_scheduled_at';

	const META_KEY_MARKETING_ID = '_hamail_marketing_id';

	const META_KEY_SEGMENT = '_hamail_segments';

	const META_KEY_UNSUBSCRIBE = '_hamail_unsubscribe';

	const META_KEY_UNSUBSCRIBE_URL = '_hamail_unsubscribe_url';

	const META_KEY_HTML_TEMPLATE = '_hamail_html_template';

	const META_KEY_TEXT_TEMPLATE = '_hamail_text_template';

	/**
	 * Constructor.
	 */
	protected function init() {
		if ( ! hamail_enabled() ) {
			return;
		}
		// Enable marketing template.
		MarketingTemplate::get_instance();
		// Register hooks.
		add_action( 'init', [ $this, 'register_post_type' ], 11 );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . static::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
		add_action( 'save_post_' . static::POST_TYPE, [ $this, 'save_post_and_sync' ], 20, 2 );
		add_action( 'save_post_' . static::POST_TYPE, [ $this, 'publish_if_possible' ], 30, 2 );
		add_action( 'rest_api_init', [ $this, 'register_rest_api' ] );
	}

	/**
	 * Save post meta data.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hamailmarketing' ), 'hamail_marketing_target' ) ) {
			return;
		}
		// Sender ID.
		update_post_meta( $post_id, static::META_KEY_SENDER, filter_input( INPUT_POST, 'hamail_marketing_sender' ) );
		// List and group.
		if ( empty( $_POST['hamail_marketing_targets'] ) ) {
			delete_post_meta( $post_id, static::META_KEY_SEGMENT );
		} else {
			update_post_meta( $post_id, static::META_KEY_SEGMENT, implode( ',', array_map( 'trim', filter_input( INPUT_POST, 'hamail_marketing_targets', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ) ) ) );
		}
		// Unsubscribe group.
		update_post_meta( $post_id, static::META_KEY_UNSUBSCRIBE, filter_input( INPUT_POST, 'hamail_unsubscribe' ) );
		// Unsubscribe URL.
		update_post_meta( $post_id, static::META_KEY_UNSUBSCRIBE_URL, filter_input( INPUT_POST, 'hamail_unsubscribe_url' ) );
		// Templates.
		update_post_meta( $post_id, static::META_KEY_HTML_TEMPLATE, filter_input( INPUT_POST, 'hamail_html_template' ) );
		update_post_meta( $post_id, static::META_KEY_TEXT_TEMPLATE, filter_input( INPUT_POST, 'hamail_text_template' ) );
	}

	/**
	 * Test post as valid
	 *
	 * @param \WP_Post $post
	 * @return \WP_Error|true
	 */
	public function is_valid_as_marketing( $post ) {
		$errors = new \WP_Error();
		// Unsubscribe.
		$unsubscribe     = get_post_meta( $post->ID, static::META_KEY_UNSUBSCRIBE, true );
		$unsubscribe_url = get_post_meta( $post->ID, static::META_KEY_UNSUBSCRIBE_URL, true );
		if ( ! ( $unsubscribe_url || $unsubscribe ) ) {
			$errors->add( 'hamail_marketing_error', __( 'Either Unsubscribe group or custom URL is required.', 'hamail' ) );
		} elseif ( $unsubscribe && ! is_numeric( $unsubscribe ) ) {
			$errors->add( 'hamail_marketing_error', __( 'Unsubscribe group should be numerice.', 'hamail' ) );
		}
		// Sender.
		if ( ! $this->get_post_sender( $post ) ) {
			$errors->add( 'hamail_marketing_error', __( 'Sender is required.', 'hamail' ) );
		}
		// Post content.
		if ( empty( $post->post_content ) ) {
			$errors->add( 'hamail_marketing_error', __( 'Post content is empty. This will be mail body.', 'hamail' ) );
		}
		// Title.
		if ( empty( $post->post_title ) ) {
			$errors->add( 'hamail_marketing_error', __( 'Post title is empty. This will be mail subject.', 'hamail' ) );
		}
		// Target.
		$targets = $this->get_post_segment( $post );
		if ( empty( $targets ) ) {
			$errors->add( 'hamail_marketing_error', __( 'Target is required.', 'hamail' ) );
		}
		return $errors->get_error_messages() ? $errors : true;
	}

	/**
	 * Sync post with Sendgrid
	 *
	 * @param \WP_Post $post
	 * @return string|\WP_Error
	 */
	public function sync( $post ) {
		$errors = $this->is_valid_as_marketing( $post );
		if ( is_wp_error( $errors ) ) {
			return $errors;
		}
		$marketing_id = $this->get_marketing_id( $post );
		$json         = $this->post_to_marketing( $post );
		$sg           = hamail_client();
		if ( $marketing_id ) {
			// Update.
			// Before update, check if this is not published.
			$response = $sg->client->campaigns()->_( $marketing_id )->get();
			$result   = $this->convert_response_to_error( $response );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			// Check status and if this is not draft,
			// impossible to edit.
			if ( 'Draft' !== $result['status'] ) {
				return $marketing_id;
			}
			$response = $sg->client->campaigns()->_( (int) $marketing_id )->patch( $json );
		} else {
			// Newly create.
			$response = $sg->client->campaigns()->post( $json );
		}
		$result = $this->convert_response_to_error( $response );
		// If failed, return errors.
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// Save ID.
		if ( ! $marketing_id ) {
			update_post_meta( $post->ID, self::META_KEY_MARKETING_ID, $result['id'] );
		}
		return $result['id'];
	}

	/**
	 * Convert post object to json.
	 *
	 * @param \WP_Post $post
	 * @return array
	 */
	public function post_to_marketing( $post ) {
		$terms             = get_the_terms( $post, hamail_marketing_category_taxonomy() );
		$json              = [
			'title'       => sprintf( '#%d %s', $post->ID, get_the_title( $post ) ),
			'subject'     => get_the_title( $post ),
			'sender_id'   => (int) $this->get_post_sender( $post ),
			'list_ids'    => [],
			'segment_ids' => [],
			'categories'  => ( ! $terms || is_wp_error( $terms ) ) ? [] : array_map( function ( $term ) {
				return $term->name;
			}, $terms ),
		];
		$unsubscribe_group = get_post_meta( $post->ID, static::META_KEY_UNSUBSCRIBE, true );
		$unsubscribe_url   = get_post_meta( $post->ID, static::META_KEY_UNSUBSCRIBE_URL, true );
		if ( $unsubscribe_url ) {
			$json['custom_unsubscribe_url'] = $unsubscribe_url;
			$json['suppression_group_id']   = null;
		} else {
			$json['suppression_group_id']   = (int) $unsubscribe_group;
			$json['custom_unsubscribe_url'] = '';
		}
		foreach ( $this->get_post_segment( $post ) as $segment ) {
			if ( preg_match( '/(list|segment)_(\d+)/u', $segment, $match ) ) {
				$json[ $match[1] . '_ids' ][] = (int) $match[2];
			}
		}
		$json['html_content']  = $this->template->render_marketing( $post, 'html' );
		$json['plain_content'] = $this->template->render_marketing( $post, 'text' );
		return $json;
	}

	/**
	 * Save post and sync with Sendgrid.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post_and_sync( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hamailmarketing' ), 'hamail_marketing_target' ) ) {
			return;
		}
		$result = $this->sync( $post );
		if ( is_wp_error( $result ) ) {
			$this->error_log( $result, $post );
		}
	}

	/**
	 * Update schedule.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function publish_if_possible( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hamailmarketing' ), 'hamail_marketing_target' ) ) {
			return;
		}
		$result = $this->publish( $post );
		if ( is_wp_error( $result ) ) {
			$this->error_log( $result, $post );
		}
	}

	/**
	 * Publish if possible.
	 *
	 * @param \WP_Post $post
	 * @return bool|\WP_Error
	 */
	public function publish( $post ) {
		$marketing_id = get_post_meta( $post->ID, static::META_KEY_MARKETING_ID, true );
		$scheduled    = (int) get_post_meta( $post->ID, static::META_KEY_SCHEDULED_AT, true );
		if ( ! $marketing_id ) {
			return false;
		}
		if ( hamail_is_debug() ) {
			return false;
		}
		if ( $scheduled && ( $scheduled < time() ) ) {
			// Already published.
			return false;
		}
		$publish_now  = time() + 60 * 10;
		$new_schedule = 0;
		$create       = false;
		$cancel       = false;
		switch ( $post->post_status ) {
			case 'future':
				$will_publish_at = (int) get_gmt_from_date( $post->post_date, 'U' );
				// This is future post.
				if ( ! $scheduled ) {
					// This is first requested.
					$new_schedule = $will_publish_at;
					$create       = true;
				} elseif ( ( $scheduled !== $will_publish_at ) && $will_publish_at > $publish_now ) {
					// Update schedule.
					$new_schedule = $will_publish_at;
				}
				break;
			case 'publish':
				$publish_at = (int) get_gmt_from_date( $post->post_date, 'U' );
				if ( $publish_at < time() - 60 ) {
					// This is old post.
					return false;
				}
				if ( $scheduled && ( $publish_now < $scheduled ) ) {
					// Put forward.
					$new_schedule = $publish_now;
				} elseif ( ! $scheduled ) {
					// Schedule immediately.
					$new_schedule = $publish_now;
					$create       = true;
				}
				break;
			default:
				if ( $scheduled ) {
					// This should be cancel.
					$cancel = true;
				}
				break;
		}
		$sg = hamail_client();
		if ( $cancel ) {
			// Cancel schedule.
			$response = $sg->client->campaigns()->_( $marketing_id )->schedules()->delete();
			$result   = $this->convert_response_to_error( $response );
			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				delete_post_meta( $post->ID, self::META_KEY_SCHEDULED_AT );
				return true;
			}
		} elseif ( ! $new_schedule ) {
			// Nothing to do.
			return false;
		} else {
			$request = [
				'send_at' => $new_schedule,
			];
			if ( $create ) {
				// Create schedule.
				$response = $sg->client->campaigns()->_( $marketing_id )->schedules()->post( $request );
			} else {
				// Update schedule.
				$response = $sg->client->campaigns()->_( $marketing_id )->schedules()->patch( $request );
			}
			$result = $this->convert_response_to_error( $response );
			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				update_post_meta( $post->ID, self::META_KEY_SCHEDULED_AT, $new_schedule );
				return true;
			}
		}
	}

	/**
	 * Register Meta box.
	 *
	 * @param string $post_type
	 */
	public function add_meta_boxes( $post_type ) {
		if ( self::POST_TYPE === $post_type ) {
			add_meta_box( 'hamail-marketing-target', __( 'Marketing Setting', 'hamail' ), [ $this, 'meta_box_marketing_list' ], $post_type, 'side', 'high' );
			add_meta_box( 'hamail-marketing-fields', __( 'Available Fields', 'hamail' ), [ $this, 'meta_box_marketing_fields' ], $post_type, 'advanced', 'high' );
			add_meta_box( 'hamail-marketing-logs', __( 'Marketing Logs', 'hamail' ), [ $this, 'meta_box_marketing_logs' ], $post_type, 'advanced', 'low' );
		}
	}

	/**
	 * Render marketing list.
	 *
	 * @param \WP_Post $post
	 */
	public function meta_box_marketing_list( $post ) {
		wp_nonce_field( 'hamail_marketing_target', '_hamailmarketing', false );
		$error = $this->is_valid_as_marketing( $post );
		if ( is_wp_error( $error ) ) {
			printf( '<p class="wp-ui-text-notification">%s</p>', implode( '<br />', array_map( 'esc_html', $error->get_error_messages() ) ) );
		}
		wp_enqueue_script( 'hamail-marketing-email' );
		wp_enqueue_style( 'hamail-marketing-email-editor' );
		?>
		<p class="description">
			<?php esc_html_e( 'Valid email marketing will be sent after you publish or schedule.', 'hamail' ); ?>
		</p>
		<div id="hamail-marketing-info" class="hamail-marketing" data-id="<?php echo $post->ID; ?>"></div>
		<hr />
		<p class="hamail-meta-row">
			<label for="hamail_marketing_sender" class="block">
				<?php esc_html_e( 'Sender ID', 'hamail' ); ?>
			</label>
			<select name="hamail_marketing_sender" id="hamail_marketing_sender">
				<?php foreach ( hamail_available_senders() as $id => $label ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $this->get_post_sender( $post ) ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<hr />
		<h4><?php esc_html_e( 'Target Group', 'hamail' ); ?></h4>
		<?php
		$groups  = [];
		$current = $this->get_post_segment( $post );
		foreach ( hamail_available_segments() as $list ) {
			if ( $list['id'] === $list['list_id'] ) {
				// This is list.
				$groups[ $list['id'] ] = [
					// translators: %s is list name.
					'label' => sprintf( _x( 'List: %s', 'list gorup', 'hamail' ), $list['label'] ),
					'lists' => [
						array_merge( $list, [
							'label' => __( 'All Recipients', 'hamail' ),
							'value' => 'list_' . $list['id'],
						] ),
					],
				];
			} else {
				// This is segment.
				$groups[ $list['list_id'] ]['lists'][] = array_merge( $list, [
					'value' => 'segment_' . $list['id'],
				] );
			}
		}
		if ( empty( $groups ) ) {
			printf( '<div class="notice notice-warning">%s</div>', esc_html__( 'You have no list available.', 'hamail' ) );
		} else {
			foreach ( $groups as $list_id => $group ) {
				?>
				<h5 class="hamail-meta-title"><?php echo esc_html( $group['label'] ); ?></h5>
				<div class="hamail-meta-body">
					<?php foreach ( $group['lists'] as $list ) : ?>
					<label class="inline-block">
						<input type="checkbox" name="hamail_marketing_targets[]"
							value="<?php echo esc_attr( $list['value'] ); ?>" <?php checked( in_array( $list['value'], $current, true ) ); ?> />
						<?php printf( '%s(%s)', esc_html( $list['label'] ), number_format( $list['count'] ) ); ?>
					</label>
					<?php endforeach; ?>
				</div>
				<?php
			}
		}
		$unsubscribe_groups  = $this->get_unsubscribe_group();
		$current_unsubscribe = get_post_meta( $post->ID, static::META_KEY_UNSUBSCRIBE, true );
		?>
		<hr />
		<h4><?php esc_html_e( 'Unsubscribe' ); ?></h4>
		<p class="description">
			<?php esc_html_e( 'Specify unsubscribe group for this marketing email. Custom URL priors.', 'hamail' ); ?>
		</p>
		<p class="hamail-meta-row">
			<label for="hamail_unsubscribe" class="block">
				<?php esc_html_e( 'Unsubscribe Group', 'hamail' ); ?>
			</label>
			<?php if ( empty( $unsubscribe_groups ) ) : ?>
				<br />
				<span class="wp-ui-text-notification"><?php esc_html_e( 'Failed to get unsubscribe groups.', 'hamail' ); ?></span>
				<input type="hidden" name="hamail_unsubscribe" value="<?php echo esc_attr( $current_unsubscribe ); ?>" />
			<?php else : ?>
				<select name="hamail_unsubscribe" id="hamail_unsubscribe">
					<option value=""<?php selected( $current_unsubscribe, false ); ?>>
						<?php esc_html_e( 'Please Select', 'hamail' ); ?>
					</option>
					<?php foreach ( $unsubscribe_groups as $group ) : ?>
						<option value="<?php echo esc_attr( $group['id'] ); ?>" <?php selected( $group['id'], $current_unsubscribe ); ?>>
							<?php
							echo esc_html( $group['name'] );
							if ( $group['is_default'] ) {
								esc_html_e( '(Default)', 'hamail' );
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</p>
		<p class="hamail-meta-row">
			<label for="hamail_unsubscribe_url" class="block">
				<?php esc_html_e( 'Custom Unsubscribe URL', 'hamail' ); ?>
			</label>
			<input name="hamail_unsubscribe_url" id="hamail_unsubscribe_url" type="url"
				value="<?php echo esc_attr( get_post_meta( $post->ID, static::META_KEY_UNSUBSCRIBE_URL, true ) ); ?>" />
		</p>
		<hr />
		<h4><?php esc_html_e( 'Template' ); ?></h4>
		<?php
		foreach ( [
			[ 'html', 'HTML', static::META_KEY_HTML_TEMPLATE ],
			[ 'text', __( 'Plain Text', 'hamail' ), static::META_KEY_TEXT_TEMPLATE ],
		] as list( $key, $label, $meta_key ) ) {
			$id = "hamail_{$key}_template";
			?>
			<p class="hamail-meta-row">
				<label for="<?php echo esc_attr( $id ); ?>" class="block">
					<?php echo esc_html( $label ); ?>
				</label>
				<?php $cur_template = get_post_meta( $post->ID, $meta_key, true ); ?>
				<select name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>">
					<option value="" <?php selected( $cur_template, false ); ?>><?php esc_html_e( 'Default', 'hamail' ); ?></option>
					<?php foreach ( $this->template->get_templates( $key ) as $template ) : ?>
						<option value="<?php echo esc_attr( $template->ID ); ?>"<?php selected( $template->ID, $cur_template ); ?>>
							<?php
							echo esc_html( get_the_title( $template ) );
							if ( $template->_is_default ) {
								printf( '(%s)', esc_html__( 'Default', 'hamail' ) );
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<a class="button" href="<?php echo esc_url( $this->template->get_preview_link( $post, $key ) ); ?>" style="margin-top: 10px;" target="wp-preview-<?php echo $post->ID; ?>" rel="noopener no-referer">
					<?php
					// translators: %s is format.
					echo esc_html( sprintf( _x( 'Preview %s', 'Preview link', 'hamail' ), $label ) );
					?>
				</a>
			</p>
			<?php
		}
	}

	/**
	 * Render available fields meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function meta_box_marketing_fields( $post ) {
		$custom_fields = $this->get_custom_fields();
		wp_enqueue_style( 'hamail-sender' );
		?>
		<div class="hamail-instruction">
			<p>
				<?php
				printf(
					esc_html__( 'You can put custom fields in brace format like %s. Default value is fallback if the recipient has no field.', 'hamail' ),
					'<code>{%field_name | Default Value%}</code>'
				);
				?>
			</p>
			<?php if ( empty( $custom_fields ) ) : ?>
				<p class="wp-ui-text-notification">
					<?php esc_html_e( 'Failed to get custom fields. Please check API key is valid.', 'hamail' ); ?>
				</p>
			<?php else : ?>
				<table class="hamail-instruction-table">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Place Holder', 'hamail' ); ?></th>
						<th><?php esc_html_e( 'Type', 'hamail' ); ?></th>
						<th><?php esc_html_e( 'Reserved', 'hamail' ); ?></th>
						<th>&nbsp;</th>
					</tr>
					<tbody>
					<?php foreach ( $custom_fields as $field ) : ?>
						<tr>
							<td><?php echo esc_html( $field['name'] ); ?></td>
							<td><?php echo esc_html( $field['type'] ); ?></td>
							<td><?php echo $field['reserved'] ? '<span class="dashicons dashicons-yes wp-ui-text-primary"></span>' : '<span style="color: lightgrey">--</span>'; ?></td>
							<td>
								<button class="button" onclick="window.prompt( this.dataset.title, '{%' + this.dataset.text + '%}' );"
										data-title="<?php esc_attr_e( 'Please copy this as place holder.', 'hamail' ); ?>"
										data-text="<?php echo esc_attr( $field['reserved'] ? $field['name'] : ( $field['name'] . ' | ' . __( 'Default Value', 'hamail' ) ) ); ?>">
									<?php esc_html_e( 'Copy', 'hamail' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display marketing logs.
	 *
	 * @param \WP_Post $post
	 * @return void
	 */
	public function meta_box_marketing_logs( $post ) {
		$logs = $this->get_logs( $post );
		if ( empty( $logs ) ) {
			printf( '<p class="description">%s</p>', esc_html__( 'No logs found.', 'hamail' ) );
			return;
		}
		printf( '<p class="description">%s</p>', esc_html__( 'Displaying recent 20 error logs.', 'hamail' ) );
		foreach ( $logs as $log ) {
			?>
			<details>
				<summary>
					<?php
					$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
					$name   = get_the_author_meta( 'display_name', $log['author'] );
					printf( '%s (%s)', mysql2date( $format, $log['date'] ), esc_html( $name ) );
					?>
				</summary>
				<?php echo wp_kses_post( $log['content'] ); ?>
			</details>
			<?php
		}
	}

	/**
	 * Get post sender ID.
	 *
	 * @param int|\WP_Post|null $post
	 * @return string
	 */
	public function get_post_sender( $post = null ) {
		$post = get_post( $post );
		return (string) get_post_meta( $post->ID, static::META_KEY_SENDER, true );
	}

	/**
	 * Get post's target.
	 *
	 * @param null|int|\WP_Post $post
	 * @return string[]
	 */
	public function get_post_segment( $post = null ) {
		$post = get_post( $post );
		return array_filter( explode( ',', get_post_meta( $post->ID, static::META_KEY_SEGMENT, true ) ), function ( $id ) {
			return preg_match( '/(segment|list)_\d+/u', $id );
		} );
	}

	/**
	 * Get marketing ID.
	 *
	 * @param int|null|\WP_Post $post
	 * @return string
	 */
	public function get_marketing_id( $post ) {
		$post = get_post( $post );
		return (string) get_post_meta( $post->ID, static::META_KEY_MARKETING_ID, true );
	}

	/**
	 * Register post type for marketing automation.
	 */
	public function register_post_type() {
		// Post type.
		$hamail_post_type = get_post_type_object( 'hamail' );
		$args             = apply_filters( 'hamail_marketing_post_type_arg', [
			'label'             => __( 'Marketing Email', 'hamail' ),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_in_admin_bar' => false,
			'show_in_menu'      => SettingsScreen::get_instance()->slug,
			'menu_position'     => 21,
			'supports'          => [ 'title', 'editor', 'author', 'excerpt' ],
			'capability_type'   => 'page',
			'taxonomies'        => [ hamail_marketing_category_taxonomy() ],
		] );
		register_post_type( self::POST_TYPE, $args );
		// Taxonomy.
		$post_types = apply_filters( 'hamail_post_types_in_marketing', [ self::POST_TYPE ] );
		register_taxonomy( hamail_marketing_category_taxonomy(), $post_types, [
			'label'             => __( 'Marketing Category', 'hamail' ),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menus' => false,
			'description'       => __( 'Used as marketing category.', 'hamail' ),
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'show_admin_column' => true,
			'capabilities'      => [
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_posts',
			],
		] );
	}

	/**
	 * Register REST API.
	 */
	public function register_rest_api() {
		register_rest_route( 'hamail/v1', 'marketing/(?P<post_id>\d+)', [
			[
				'methods'             => [ 'GET', 'DELETE', 'POST' ],
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'description'       => __( 'Marketing post ID.', 'hamail' ),
						'validate_callback' => function ( $var ) {
							if ( ! is_numeric( $var ) ) {
								return false;
							}
							$post = get_post( $var );
							return $post && self::POST_TYPE === $post->post_type;
						},
					],
				],
				'permission_callback' => [ $this, 'preview_permission' ],
				'callback'            => [ $this, 'marketing_template_callback' ],
			],
		] );
	}

	/**
	 * Handle REST API request.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function marketing_template_callback( $request ) {
		$post         = get_post( $request->get_param( 'post_id' ) );
		$method       = strtolower( $request->get_method() );
		$marketing_id = get_post_meta( $post->ID, self::META_KEY_MARKETING_ID, true );
		if ( ! $marketing_id ) {
			if ( 'post' ) {
				$marketing_id = $this->sync( $post );
				if ( is_wp_error( $marketing_id ) ) {
					return $marketing_id;
				}
			} else {
				return new \WP_Error( 'hamail_marketing_error', __( 'Campaign dose not exist.', 'hamail' ), [
					'status' => 404,
				] );
			}
		}
		$sg       = hamail_client();
		$response = $sg->client->campaigns()->_( $marketing_id )->get();
		$result   = $this->convert_response_to_error( $response );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		switch ( $method ) {
			case 'get':
			case 'post':
				return new \WP_REST_Response( $result );
			case 'delete':
				$response = $sg->client->campaigns()->_( $marketing_id )->delete();
				$result   = $this->convert_response_to_error( $response );
				if ( is_wp_error( $result ) ) {
					return $result;
				} else {
					delete_post_meta( $post->ID, self::META_KEY_MARKETING_ID );
					return new \WP_REST_Response( [
						'id'      => $marketing_id,
						'message' => __( 'Campaign is deleted from SendGrid.', 'hamail' ),
					] );
				}
			default:
				return new \WP_Error( 'hamail_marketing_error', __( 'Method not allowd.', 'hamail' ), [
					'status' => 400,
				] );
		}
	}

	/**
	 * Check if this mail is sent.
	 *
	 * @param \WP_Post $post
	 * @return bool
	 */
	public function is_sent( $post ) {
		$sent_at = (int) get_post_meta( $post->ID, self::META_KEY_SENT_AT, true );
		return $sent_at && ( time() >= $sent_at );
	}

	/**
	 * Getter.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'template':
				return MarketingTemplate::get_instance();
			default:
				return null;
		}
	}
}
