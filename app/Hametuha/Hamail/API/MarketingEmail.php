<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Ui\MarketingTemplate;
use Hametuha\Hamail\Utility\ApiUtility;

/**
 * Marketing feature.
 *
 * @package Hametuha\Hamail\Pro\Addons
 */
class MarketingEmail extends Singleton {

	use ApiUtility;

	const POST_TYPE = 'marketing-mail';

	const META_KEY_SENDER = '_hamail_sender';

	const META_KEY_MARKETING_ID = '_hamail_marketing_id';

	const META_KEY_SEGMENT = '_hamail_segments';

	const META_KEY_UNSUBSCRIBE = '_hamail_unsubscribe';

	const META_KEY_UNSUBSCRIBE_URL = '_hamail_unsubscribe_url';

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
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post_and_sync' ], 10, 2 );
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
			update_post_meta( $post_id, static::META_KEY_SEGMENT, implode( ',', array_map( 'trim', $_POST['hamail_marketing_targets'] ) ) );
		}
		// Unsubscribe group.
		update_post_meta( $post_id, static::META_KEY_UNSUBSCRIBE, filter_input( INPUT_POST, 'hamail_unsubscribe' ) );
		// Unsubscribe URL.
		update_post_meta( $post_id, static::META_KEY_UNSUBSCRIBE_URL, filter_input( INPUT_POST, 'hamail_unsubscribe_url' ) );
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
			$errors->add( 'hamail_marketing_error', __( 'Target is requied.', 'hamail' ) );
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
		if ( $marketing_id ) {

		} else {

		}
	}

	/**
	 * Convert post object to json.
	 *
	 * @param \WP_Post $post
	 */
	public function post_to_marketing( $post ) {
		$terms             = get_the_terms( $post, hamail_marketing_category_taxonomy() );
		$json              = [
			'title'       => sprintf( '#%d %s', $post->ID, get_the_title( $post ) ),
			'subject'     => get_the_title( $post ),
			'sender_id'   => (int) $this->get_post_sender( $post ),
			'list_ids'    => [],
			'segment_ids' => [],
			'categories'  => ( ! $terms || is_wp_error( $terms ) ) ? [] : array_map( function( $term ) {
				return $term->name;
			}, $terms ),
		];
		$unsubscribe_group = $this->get_unsubscribe_group( $post );
		$unsubscribe_url   = get_post_meta( $post->ID, static::META_KEY_UNSUBSCRIBE_URL, true );
		if ( $unsubscribe_url ) {
			$json['custom_unsubscribe_url'] = $unsubscribe_url;
		} else {
			$json['suppression_group_id'] = (int) $unsubscribe_group;
		}
		foreach ( $this->get_post_segment( $post ) as $segment ) {
			if ( preg_match( '/(list|segment)_(\d+)/u', $segment, $match ) ) {
				$json[ $match[1] . '_ids' ][] = (int) $match[2];
			}
		}
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
		$this->sync( $post );
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
		?>
		<p class="hamail-meta-row">
			<label for="hamail_marketing_id" class="block">
				<?php esc_html_e( 'Marketing ID', 'hamail' ); ?>
			</label>
			<select name="hamail_marketing_sender" id="hamail_marketing_sender">
				<?php foreach ( hamail_available_senders() as $id => $label ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $this->get_post_sender( $post ) ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
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
		<?php
	}

	/**
	 * Render available fields meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function meta_box_marketing_fields( $post ) {
		$custom_fields = $this->get_custom_fields();
		wp_enqueue_style( 'hamail-sender' );
		echo '<pre>';
		var_dump( $this->post_to_marketing( $post ) );
		echo '</pre>';
		?>
		<div class="hamail-instruction">
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
		return array_filter( explode( ',', get_post_meta( $post->ID, static::META_KEY_SEGMENT, true ) ), function( $id ) {
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
		$hamail_post_type = get_post_type_object( 'hamail' );
		$args             = apply_filters( 'hamail_marketing_post_type_arg', [
			'label'             => __( 'Marketing Email', 'hamail' ),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_in_admin_bar' => false,
			'show_in_menu'      => true,
			'menu_position'     => 51,
			'menu_icon'         => 'dashicons-email-alt2',
			'supports'          => [ 'title', 'editor', 'author' ],
			'capability_type'   => 'page',
			'taxonomies'        => [ hamail_marketing_category_taxonomy() ],
		] );
		register_post_type( self::POST_TYPE, $args );
	}
}
