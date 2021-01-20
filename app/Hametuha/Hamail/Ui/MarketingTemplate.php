<?php

namespace Hametuha\Hamail\Ui;


use Hametuha\Hamail\API\MarketingEmail;
use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Utility\MailRenderer;

/**
 * Create marketing template.
 *
 * @package hamail
 */
class MarketingTemplate extends Singleton {

	use MailRenderer;

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
		add_action( 'rest_api_init', [ $this, 'preview_rest' ] );
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
		add_action( 'post_submitbox_minor_actions', [ $this, 'preview_box' ] );
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
				echo wp_kses_post( sprintf( __( '%s will be replaced with mail body.', 'hamail' ), '<code>{%body%}</code>' ) );
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

	/**
	 * Display preview link.
	 *
	 * @param \WP_Post $post
	 */
	public function preview_box( $post ) {
		$preview_link = esc_url( add_query_arg( [
			'_wpnonce' => wp_create_nonce( 'wp_rest' ),
		], rest_url( 'hamail/v1/template/preview/' . $post->ID ) ) );
		?>
		<div>
			<a class="button" href="<?php echo $preview_link; ?>" target="hamail-preview-<?php echo $post->ID; ?>">
				<?php esc_html_e( 'Preview', 'hamail' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Register REST route for preview.
	 */
	public function preview_rest() {
		register_rest_route( 'hamail/v1', 'template/preview/(?P<post_id>\d+)', [
			[
				'methods'             => 'GET',
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'description'       => __( 'Post ID of marketing template to preview.', 'hamail' ),
						'validate_callback' => function( $var ) {
							if ( ! is_numeric( $var ) ) {
								return false;
							}
							$post = get_post( $var );
							return $post && ( self::POST_TYPE === $post->post_type );
						},
					],
					'subject' => [
						'type'        => 'string',
						'description' => __( 'Mail subject.', 'hamail' ),
						'default'     => __( 'Re: Hello from {%first_name | Guest%}', 'hamail' ),
					],
					'body'    => [
						'type'        => 'string',
						'description' => __( 'Mail Body.', 'hamail' ),
						'default'     => __( "Hi, {%first_name | Guest%}!\nYou are now member ob our site. Please click the link below.\n{%url%}", 'hamail' ),
					],
				],
				'callback'            => [ $this, 'preview_callback' ],
				'permission_callback' => [ $this, 'preview_permission' ],
			],
		] );
	}

	/**
	 * Permission callback.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function preview_permission( $request ) {
		return current_user_can( 'edit_post', $request->get_param( 'post_id' ) );
	}

	/**
	 * Preview post item.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function preview_callback( \WP_REST_Request $request ) {
		$post = get_post( $request->get_param( 'post_id' ) );
		$string = $this->apply_template( $post, $request->get_param( 'subject' ), $request->get_param( 'body' ) );
		$type = get_post_meta( $post->ID, self::META_KEY_TYPE, true );
		switch ( $type ) {
			case 'html':
				$type = 'text/html';
				break;
			case 'text':
			default:
				$type = 'text/plain';
				break;
		}
		header( sprintf( 'Content-Type: %s; charset=UTF-8', $type ) );
		echo $string;
		exit;
	}

	/**
	 * Apply template to body and subject.
	 *
	 * @param string $post
	 * @param string $subject
	 * @param string $body
	 * @return string
	 */
	public function apply_template( $post, $subject, $body ) {
		$content = (string) get_post_meta( $post->ID, self::META_KEY_BODY, true );
		return $this->replace( $content, $subject, $body );
	}
}
