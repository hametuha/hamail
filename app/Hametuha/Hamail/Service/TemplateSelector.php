<?php

namespace Hametuha\Hamail\Service;


use Hametuha\Hamail\Pattern\Singleton;

/**
 * Template selector.
 *
 * @package hamail
 */
class TemplateSelector extends Singleton {

	const OPTION_KEY = 'hamail_template_id';

	const POST_META_KEY = '_hamail_template_id';

	const POST_TYPES = [ 'hamail' ];

	const NO_TEMPLATE = '__no_template__';

	/**
	 * Constructor
	 */
	protected function init() {
		add_action( 'save_post', [ $this, 'save_post' ], 9, 2 );
		add_action( 'add_meta_boxes', function ( $post_type ) {
			if ( in_array( $post_type, self::post_types(), true ) ) {
				add_meta_box( 'hamail-template-box', __( 'Mail Template', 'hamail' ), [ $this, 'do_meta_box' ], $post_type, 'side', 'low' );
			}
		} );
	}

	/**
	 * Save post template data.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		if ( ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hamailtemplatenonce' ), 'hamail_template' ) ) {
			return;
		}
		update_post_meta( $post_id, self::POST_META_KEY, filter_input( INPUT_POST, self::POST_META_KEY ) );
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function do_meta_box( $post ) {
		wp_nonce_field( 'hamail_template', '_hamailtemplatenonce', false );
		$input = self::get_template_pull_down( $post->ID, self::POST_META_KEY, 'legacy' );
		if ( is_wp_error( $input ) ) {
			printf( '<div class="notice notice-error">%s</div>', esc_html( $input->get_error_message() ) );
		} else {
			echo $input;
		}
		$default = self::get_default_template();
		switch ( $default ) {
			case '':
				$message = __( 'Default template is not set.', 'hamail' );
				break;
			default:
				$label     = '';
				$templates = self::get_available_templates( 'legacy' );
				if ( ! is_wp_error( $templates ) ) {
					foreach ( $templates as $template ) {
						if ( $default === $template['id'] ) {
							$label = $template['label'];
							break 1;
						}
					}
				}
				if ( ! $label ) {
					$message = __( 'Default template is set, but failed to retrieve the data. It might be deleted on SendGrid.', 'hamail' );
				} else {
					// translators: %s is template name.
					$message = sprintf( __( 'Default template is %s', 'hamail' ), $label );
				}
				break;
		}
		printf( '<p class="description">%s</p>', esc_html( $message ) );
		// Display preview link

	}

	/**
	 * Get default template.
	 *
	 * @return string
	 */
	public static function get_default_template() {
		return get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Get post template.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function get_post_template( $post_id ) {
		$saved = get_post_meta( $post_id, self::POST_META_KEY, true );
		switch ( $saved ) {
			case self::NO_TEMPLATE:
				return '';
			case '':
				return self::get_default_template();
			default:
				return $saved;
		}
	}

	/**
	 * Get available templates.
	 *
	 * @param string $generation 'legacy', 'dynamic', or 'leagcy,dynamic'
	 * @return array|\WP_Error
	 */
	public static function get_available_templates( string $generation = 'legacy,dynamic' ) {
		$generation = in_array( $generation, [ 'legacy', 'dynamic' ], true ) ? $generation : 'legacy,dynamic';
		static $templates = null;
		if ( is_null( $templates ) ) {
			try {
				$sg           = hamail_client();
				$query_params = [ 'generations' => $generation ];
				$response     = $sg->client->templates()->get( null, $query_params );
				$json         = json_decode( $response->body(), true );
				if ( ! $json ) {
					throw new \Exception( __( 'Failed to retrieve templates list.', 'hamail' ), 500 );
				}
				if ( empty( $json['templates'] ) ) {
					$templates = [];
				} else {
					$templates = array_map( function ( $template ) {
						return [
							'id'    => $template['id'],
							'name'  => $template['name'],
							'type'  => $template['generation'],
							// translators: %1$s is template name, %2$s is generation.
							'label' => sprintf( _x( '%1$s(%2$s)', 'template-label', 'hamail' ), $template['name'], $template['generation'] ),
						];
					}, $json ['templates'] );
				}
			} catch ( \Exception $e ) {
				$code      = $e->getCode();
				$templates = new \WP_Error( 'hamail_template_error', $e->getMessage(), [
					'status'               => preg_match( '/^[0-9]{3}$/u', $code ) ? $code : 500,
					'original_status_code' => $code,
				] );
			}
		}
		return $templates;
	}

	/**
	 * Get pull down for template
	 *
	 * @param int $post_id If post id is set, get post meta value.
	 * @param string $name Template pull down.
	 * @param string $generation 'legacy', 'dynamic', or 'legacy,dynamic'
	 * @return string
	 */
	public static function get_template_pull_down( $post_id = 0, $name = '', $generation = 'legacy,dynamic' ) {
		if ( ! $name ) {
			$name = self::OPTION_KEY;
		}
		$current_value = $post_id ? get_post_meta( $post_id, self::POST_META_KEY, true ) : self::get_default_template();
		$templates     = self::get_available_templates( $generation );
		if ( is_wp_error( $templates ) ) {
			return $templates;
		} elseif ( empty( $templates ) ) {
			return new \WP_Error( 'hamail_template_error', __( 'No template found.', 'hamail' ) );
		}
		$select     = sprintf( '<select id="%1$s" name="%1$s">', esc_attr( $name ) );
		$pull_downs = [
			[
				'id'    => '',
				'label' => $post_id ? _x( 'Default', 'hamail' ) : __( 'Not Set', 'hamail' ),
			],
		];
		if ( $post_id ) {
			$pull_downs[] = [
				'id'    => self::NO_TEMPLATE,
				'label' => __( 'No Template', 'hamail' ),
			];
		}
		$pull_downs = array_merge( $pull_downs, $templates );
		foreach ( $pull_downs as $template ) {
			$select .= sprintf(
				'<option value="%1$s"%3$s>%2$s</option>',
				esc_attr( $template['id'] ),
				esc_html( $template['label'] ),
				selected( $template['id'], $current_value, false )
			);
		}
		$select .= '</select>';
		return $select;
	}

	/**
	 * Available post types.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return apply_filters( 'hamail_template_selectable_post_type', self::POST_TYPES );
	}
}
