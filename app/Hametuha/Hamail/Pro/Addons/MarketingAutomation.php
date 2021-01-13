<?php

namespace Hametuha\Hamail\Pro\Addons;


use Hametuha\Hamail\Pattern\Singleton;

/**
 * Marketing feature.
 *
 * @package Hametuha\Hamail\Pro\Addons
 */
class MarketingAutomation extends Singleton {

	const POST_TYPE = 'marketing-mail';

	const META_KEY_SENDER = '_hamail_sender';

	const META_KEY_SEGMENT = '_hamail_segments';

	/**
	 * Constructor.
	 */
	protected function init() {
		// TODO: implement.
		return;
		add_action( 'init', [ $this, 'register_post_type' ], 11 );
		add_filter( 'hamail_template_selectable_post_type', [ $this, 'add_post_type' ] );
		add_filter( 'hamail_should_show_placeholder_meta_box', [ $this, 'hamail_placeholder_meta_box' ], 10, 2 );
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
			add_meta_box( 'hamail-marketing-timing', __( 'Schedule', 'hamail' ), [ $this, 'meta_box_marketing_schedule' ], $post_type, 'side', 'high' );
		}
	}

	/**
	 * Render schedule selector.
	 *
	 * @param \WP_Post $post
	 */
	public function meta_box_marketing_schedule( $post ) {
		wp_nonce_field( 'hamail_marketing_schedule', '_hamailmarketingschedule', false );
		$frequency = get_post_meta( $post->ID, '_frequency', true );
		?>
		<p>
			<label for="send_frequency"><?php esc_html_e( 'Frequency', 'hanmail' ) ?></labelf><br />
			<select id="send_frequency" name="send_frequency">
				<option disabled value=""><?php esc_html_e( 'Select Frequency', 'hamail' ); ?></option>
				<option value="yearly"<?php selected( 'yearly', $frequency ) ?>><?php esc_html_e( 'Yearly', 'hamail' ); ?></option>
				<option value="monthly"<?php selected( 'monthly', $frequency ) ?>><?php esc_html_e( 'Monthly', 'hamail' ); ?></option>
				<optgroup label="<?php esc_attr_e( 'Weekly', 'hamail' ) ?>">
					<?php foreach ( range( 0, 6 ) as $day ) :
						$value = 'weekly_' . $day;
						$date = strtotime( 'last sunday', current_time( 'timestamp' ) ) + $day * 3600 * 24;
						?>
						<option value="<?php echo esc_attr( $value ) ?>">
							<?php echo esc_html( date_i18n( 'D', $date ) ); ?>
						</option>
					<?php endforeach; ?>
				</optgroup>
			</select>
		</p>

		<p>
			<label for="send_time"><?php esc_html_e( 'Send At', 'hamail' ); ?></label><br />
			<input type="time" name="send_time" id="send_time" value="<?php echo esc_attr( get_post_meta( $post->ID, 'send_at', '' ) ); ?>" />
		</p>
		<?php
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
			'label'             => __( 'Automated Marketing', 'hamail' ),
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

	/**
	 * Post type supports.
	 *
	 * @param string[] $post_types
	 * @return string[]
	 */
	public function add_post_type( $post_types ) {
		$post_types[] = self::POST_TYPE;
		return $post_types;
	}

	/**
	 * Add placeholder meta box.
	 *
	 * @param bool   $show
	 * @param string $post_type
	 * @return bool
	 */
	public function hamail_placeholder_meta_box( $show, $post_type ) {
		return self::POST_TYPE === $post_type ? true : $show;
	}
}
