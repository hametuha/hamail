<?php

namespace Hametuha\Hamail\API;


use Hametuha\Hamail\Pattern\Singleton;
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

	const META_KEY_SEGMENT = '_hamail_segments';

	/**
	 * Constructor.
	 */
	protected function init() {
		if ( ! hamail_enabled() ) {
			return;
		}
		add_action( 'init', [ $this, 'register_post_type' ], 11 );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
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
		update_post_meta( $post_id, self::META_KEY_SENDER, filter_input( INPUT_POST, 'hamail_marketing_sender' ) );
		if ( empty( $_POST['hamail_marketing_targets'] ) ) {
			delete_post_meta( $post_id, self::META_KEY_SEGMENT );
		} else {
			update_post_meta( $post_id, self::META_KEY_SEGMENT, implode( ',', array_map( 'trim', $_POST['hamail_marketing_targets'] ) ) );
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
		}
	}

	public function get_marketing_id( $post ) {
		$post = get_post( $post );

	}

	/**
	 * Render marketing list.
	 *
	 * @param \WP_Post $post
	 */
	public function meta_box_marketing_list( $post ) {
		wp_nonce_field( 'hamail_marketing_target', '_hamailmarketing', false );
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
		$current = array_filter( explode( ',', get_post_meta( $post->ID, self::META_KEY_SEGMENT, true ) ), function( $id ) {
			return preg_match( '/(segment|list)_\d+/u', $id );
		} );
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
										data-title="<?php esc_attr_e( 'Please copy this as place holder.', 'hamail' ) ?>"
										data-text="<?php echo esc_attr( $field['reserved'] ? $field['name'] : ( $field['name'] . ' | ' . __( 'Default Value', 'hamail' ) ) ) ?>">
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
		return (string) get_post_meta( $post->ID, self::META_KEY_SENDER, true );
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
			'show_in_menu'      => 'edit.php?post_type=' . $hamail_post_type->name,
			'supports'          => [ 'title', 'editor', 'author' ],
			'capability_type'   => 'page',
		] );
		register_post_type( self::POST_TYPE, $args );
	}
}
