<?php

namespace Hametuha\Hamail\Ui;


use Hametuha\Hamail\API\MarketingEmail;
use Hametuha\Hamail\Pattern\Singleton;
use Hametuha\Hamail\Utility\MailRenderer;
use Hametuha\Hamail\Utility\RestApiPermission;

/**
 * Create marketing template.
 *
 * @package hamail
 */
class MarketingTemplate extends Singleton {

	use MailRenderer;
	use RestApiPermission;

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
	 * Detect if post type is ready for marketing.
	 *
	 * @param string $post_type
	 * @return bool
	 */
	public function has_template( $post_type ) {
		$marketing_post_type = apply_filters( 'hamail_marketing_post_types', [ MarketingEmail::POST_TYPE ] );
		return in_array( $post_type, $marketing_post_type, true );
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
			'show_in_menu'      => SettingsScreen::get_instance()->slug,
			'menu_position'     => 60,
			'show_in_admin_bar' => false,
			'supports'          => [ 'title', 'excerpt' ],
			'capability_type'   => 'page',
		] );
	}

	/**
	 * Get templates.
	 *
	 * @param string $type    Template type. "text" or "html". If not specified, return all.
	 * @param false  $default Default template.
	 * @param int    $post_id Template post id.
	 * @return \WP_Post[]
	 */
	public function get_templates( $type = '', $default = false, $post_id = 0 ) {
		$args = [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'orderby'        => [ 'date' => 'DESC' ],
			'meta_query'     => [],
			'posts_per_page' => -1,
		];
		if ( $default ) {
			$args['meta_query'][] = [
				'key'   => self::META_KEY_DEFAULT,
				'value' => 1,
			];
		}
		if ( $type ) {
			$args['meta_query'][] = [
				'key'   => self::META_KEY_TYPE,
				'value' => $type,
			];
		}
		if ( $post_id ) {
			$args['p'] = $post_id;
		}
		$query = new \WP_Query( $args );
		return $query->posts;
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
			<li>
				<?php
				// translators: %s is {%subject%}.
				echo wp_kses_post( sprintf( __( '%s will be replaced with unsubscribing URL. Alternatively, you can use <code>&lt;%%asm_group_unsubscribe_url%%&gt;</code>.', 'hamail' ), '<code>[unsubscribe]</code>' ) );
				?>
				<span class="required"><?php echo esc_html_x( 'Required', 'Required input element', 'hamail' ); ?></span>
			</li>
			<li>
				<?php
				// translators: %s is {%excerpt%}.
				echo wp_kses_post( sprintf( __( '%s will be replaced with excerpt. Use one for pre-header text..', 'hamail' ), '<code>{%excerpt%}</code>' ) );
				?>
			</li>
			<?php
			foreach ( [
				'<%asm_preferences_url%>'        => __( 'Replaced with the users of subscribing list page.', 'hamail' ),
				'<%asm_global_unsubscribe_url%>' => __( 'Replaced with global unsubscribe URL. This is optional and not recommended. Use unsubscribe groups.', 'hamail' ),
			] as $tag => $desc ) {
				printf( '<li><code>%s</code>: %s</li>', esc_html( $tag ), wp_kses_post( $desc ) );
			}
			?>
		</ol>
		<textarea id="hamail-template-body" name="template_body" class="code-input"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_KEY_BODY, true ) ); ?></textarea>
		<p class="description">
			<strong><?php esc_html_e( 'Tips:', 'hamail' ); ?></strong>
			<?php esc_html_e( 'Creating HTML mail template is difficult. You can create a designed HTML campaign with WYSIWYG in Sendgrid and copy its HTML.', 'hamail' ); ?>
		</p>
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
		?>
		<div>
			<a class="button" href="<?php echo esc_url( $this->get_preview_link( $post ) ); ?>" target="hamail-preview-<?php echo $post->ID; ?>">
				<?php esc_html_e( 'Preview', 'hamail' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Register REST route for preview.
	 */
	public function preview_rest() {
		// Template Preview.
		register_rest_route( 'hamail/v1', 'template/preview/(?P<post_id>\d+)', [
			[
				'methods'             => 'GET',
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'description'       => __( 'Post ID of marketing template to preview.', 'hamail' ),
						'validate_callback' => function ( $var ) {
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
						'default'     => __( "Hi, {%1\$first_name | Guest%}!\nYou are now member of our site. Please click the link below.\n{%2\$url%}", 'hamail' ),
					],
				],
				'callback'            => [ $this, 'preview_callback' ],
				'permission_callback' => [ $this, 'preview_permission' ],
			],
		] );
		// Marketing Previews.
		register_rest_route( 'hamail/v1', 'marketing/(?P<post_id>\d+)/preview/(?P<format>text|html)', [
			[
				'methods'             => 'GET',
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'description'       => __( 'Post ID of marketing email.', 'hamail' ),
						'validate_callback' => function ( $var ) {
							if ( ! is_numeric( $var ) ) {
								return false;
							}
							$post = get_post( $var );
							return $post && $this->has_template( $post->post_type );
						},
					],
					'format'  => [
						'required'    => true,
						'type'        => 'string',
						'enum'        => [ 'text', 'html' ],
						'description' => __( 'Preview format. "text" or "html"', 'hamail' ),
					],
				],
				'callback'            => [ $this, 'preview_callback' ],
				'permission_callback' => [ $this, 'preview_permission' ],
			],
		] );
	}

	/**
	 * Preview post item.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function preview_callback( \WP_REST_Request $request ) {
		$post = get_post( $request->get_param( 'post_id' ) );
		if ( $this->has_template( $post->post_type ) ) {
			// Search template.
			$type   = $request->get_param( 'format' );
			$string = $this->render_marketing( $post, $type );
		} else {
			// This is marketing template.
			$string = $this->apply_template( $post, $request->get_param( 'subject' ), $request->get_param( 'body' ) );
			$type   = get_post_meta( $post->ID, self::META_KEY_TYPE, true );
		}
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

	/**
	 * Render marketing solution.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param string   $format Format for text.
	 * @return string
	 */
	public function render_marketing( $post, $format = 'text' ) {
		$meta         = get_post_meta( $post->ID, "_hamail_{$format}_template", true );
		$default_html = '<html><head><title>{%subject%}</title></head><body>{%body%}</body></html>';
		$template     = null;
		if ( is_numeric( $meta ) ) {
			$templates = $this->get_templates( $format, false, $meta );
		} else {
			$templates = $this->get_templates( $format, true );
		}
		$string = '';
		if ( $templates ) {
			$template = $templates[0];
			$string   = get_post_meta( $template->ID, self::META_KEY_BODY, true );
		} else {
			// No template.
			if ( 'html' === $format ) {
				$string = $default_html;
			} else {
				$string = '{%body%}';
			}
		}
		// Building pre-header text.
		$preheader = $this->get_preheader( $post );
		// Replace preheader text.
		$string = str_replace( '{%excerpt%}', $preheader, $string );
		// Building subject.
		$subject = apply_filters( 'hamail_marketing_title', get_the_title( $post ), $post, $format );
		// Building body.
		setup_postdata( $post );
		$body = apply_filters( 'the_content', $post->post_content );
		if ( 'html' !== $format ) {
			$body = hamail_html_body_to_plain( $body );
		}
		// Replace placeholder.
		$body = strtr( $body, [
			'{%' => '[%',
			'%}' => '%]',
		] );
		wp_reset_postdata();
		$body = apply_filters( 'hamail_marketing_body', $body, $post, $format );
		$body = hamail_apply_css_to_body( $body, $format );
		// Replace body and subject.
		$replaced_body = $this->replace( $string, $subject, $body );

		// Replace [unsubscribe] to <%asm_group_unsubscribe_url%>
		return str_replace( '[unsubscribe]', '<%asm_group_unsubscribe_url%>', $replaced_body );
	}

	/**
	 * Get preview link.
	 *
	 * @param null|int|\WP_Post $post    Post to preview.
	 * @param string            $format 'text' or 'html'. If set empty, render nothing.
	 * @return string
	 */
	public function get_preview_link( $post = null, $format = 'text' ) {
		$post = get_post( $post );
		if ( self::POST_TYPE === $post->post_type ) {
			$url = rest_url( 'hamail/v1/template/preview/' . $post->ID );
		} else {
			$url = rest_url( sprintf( 'hamail/v1/marketing/%d/preview/%s', $post->ID, $format ) );
		}
		return add_query_arg( [
			'_wpnonce' => wp_create_nonce( 'wp_rest' ),
		], $url );
	}
}
