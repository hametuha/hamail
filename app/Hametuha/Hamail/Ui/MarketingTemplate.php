<?php

namespace Hametuha\Hamail\Ui;


use Hametuha\Hamail\API\MarketingEmail;
use Hametuha\Hamail\Pattern\Singleton;

/**
 * Create marketing template.
 *
 * @package hamail
 */
class MarketingTemplate extends Singleton {

	const POST_TYPE = 'marketing-template';

	const META_KEY_BODY = '_template_body';

	const META_KEY_TYPE = '_template_type';

	const META_KEY_DEFAULT = '_is_default';

	/**
	 * Constructor.
	 */
	protected function init() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
	}

	/**
	 * Register post type.
	 */
	public function register_post_type() {
		register_post_type( self::POST_TYPE, [
			'label'             => __( 'Marketing Template', 'hamail' ),
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => false,
			'show_in_rest'      => false,
			'show_in_menu'      => 'edit.php?post_type=' . MarketingEmail::POST_TYPE,
			'show_in_admin_bar' => false,
			'supports'          => [ 'title', 'excerpt' ],
			'capability_type'   => 'page',
		] );
	}

	/**
	 * Register meta boxes.
	 *
	 * @param string $post_type
	 */
	public function add_meta_boxes( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}
		add_meta_box( 'hamail-template-content', __( 'Mail Template', 'hamail' ), [ $this, 'template_meta_box' ], $post_type, 'advanced', 'high' );
		add_meta_box( 'hamail-template-type', __( 'Template Type', 'hamail' ), [ $this, 'type_meta_box' ], $post_type, 'side', 'low' );
	}

	/**
	 * Save post data.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hamailtemplatenonce' ), 'hamail_template_editor' ) ) {
			return;
		}
		// Save content.
		update_post_meta( $post_id, self::META_KEY_BODY, filter_input( INPUT_POST, 'template_body' ) );
		// Save type.
		update_post_meta( $post_id, self::META_KEY_TYPE, filter_input( INPUT_POST, 'template_type' ) );
		// Save default.
		if ( filter_input( INPUT_POST, 'template_is_default' ) ) {
			update_post_meta( $post_id, self::META_KEY_DEFAULT, 1 );
		} else {
			delete_post_meta( $post_id, self::META_KEY_DEFAULT );
		}
	}

	/**
	 * Template meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function template_meta_box( $post ) {
		wp_nonce_field( 'hamail_template_editor', '_hamailtemplatenonce', false );
		$setting = wp_enqueue_code_editor( [
			'codemirror' => [
				'mode' => 'text/html',
			],
		] );
		wp_localize_script( 'hamail-template-editor', 'HamailCodeEditor', $setting );
		wp_enqueue_script( 'hamail-template-editor' );
		wp_enqueue_style( 'hamail-template-editor' );
		?>
		<ol class="hamail-editor-desc">
			<li>
				<?php
				// translators: %s is {%subject%}.
				echo wp_kses_post( sprintf( __( '%s will be replaced with mail subject.', 'hamail' ), '<code>{%subject%}</code>' ) );
				?>
			</li>
			<li>
				<?php
				// translators: %s is {%subject%}.
				echo wp_kses_post( sprintf( __( '%s will be replaced with mail body.', 'hamail' ), '<code>{%subject%}</code>' ) );
				?>
				<span class="required"><?php echo esc_html_x( 'Required', 'Required input element', 'hamail' ); ?></span>
			</li>
		</ol>
		<textarea id="hamail-template-body" name="template_body" class="code-input"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_KEY_BODY, true ) ); ?></textarea>
		<?php
	}

	/**
	 * Type meta box.
	 *
	 * @param \WP_Post $post Post object in editor.
	 */
	public function type_meta_box( $post ) {
		?>
		<p class="description">
			<?php esc_html_e( 'Marketing Email requires either HTML or Text.', 'hamail' ); ?>
		</p>
		<?php
		$current = get_post_meta( $post->ID, self::META_KEY_TYPE, true );
		foreach ( [
			'html' => 'HTML',
			'text' => __( 'Plain Text', 'hamail' ),
		] as $value => $label ) {
			printf(
				'<label class="block-label"><input type="radio" name="template_type" value="%s" %s/> %s</label>',
				esc_attr( $value ),
				checked( $value, $current, false ),
				esc_html( $label )
			);
		}
		?>
		<hr />
		<p>
			<label class="block-label">
				<input type="checkbox" name="template_is_default" value="1" <?php checked( 1, get_post_meta( $post->ID, self::META_KEY_DEFAULT, true ) ); ?>/>
				<?php esc_html_e( 'Use as default', 'hamail' ); ?>
			</label>
		</p>
		<?php
	}
}
