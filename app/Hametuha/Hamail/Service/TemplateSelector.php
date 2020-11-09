<?php

namespace Hametuha\Hamail\Service;


class TemplateSelector {

	const OPTION_KEY = 'hamail_template_id';

	/**
	 * Get default template.
	 *
	 * @return string
	 */
	public static function get_default_template() {
		return get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Get available templates.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_available_templates() {
		try {
			$sg           = hamail_client();
			$query_params = [ 'generations' => 'legacy,dynamic' ];
			$response     = $sg->client->templates()->get( null, $query_params );
			$json         = json_decode( $response->body(), true );
			if ( ! $json ) {
				throw new \Exception( __( 'Failed to retrieve templates list.', 'hamail' ), 500 );
			}
			if ( empty( $json['templates'] ) ) {
				return [];
			}
			return array_map( function( $template ) {
				return [
					'id'    => $template['id'],
					'name'  => $template['name'],
					'type'  => $template['generation'],
					// translators: %1$s is template name, %2$s is generation.
					'label' => sprintf( _x( '%1$s(%2$s)', 'template-label', 'hamail' ), $template['name'], $template['generation'] ),
				];
			}, $json['templates'] );
		} catch ( \Exception $e ) {
			$code = $e->getCode();
			return new \WP_Error( 'hamail_template_error', $e->getMessage(), [
				'status'               => preg_match( '/^[0-9]{3}$/u', $code ) ? $code : 500,
				'original_status_code' => $code,
			] );
		}
	}

	/**
	 * Get pull down for template
	 *
	 * @param int    $post_id If post id is set, get post meta value.
	 * @param string $name    Template pull down.
	 * @return string
	 */
	public static function get_template_pull_down( $post_id = 0, $name = '' ) {
		if ( ! $name ) {
			$name = self::OPTION_KEY;
		}
		$current_value = self::get_default_template();
		$templates     = self::get_available_templates();
		if ( is_wp_error( $templates ) ) {
			return $templates;
		} elseif ( empty( $templates ) ) {
			return new \WP_Error( 'hamail_template_error', __( 'No template found.', 'hamail' ) );
		}
		$select    = sprintf( '<select id="%1$s" name="%1$s">', esc_attr( $name ) );
		$templates = array_merge( [
			[
				'id'    => '',
				'label' => $post_id ? _x( 'Default', 'hamail' ) : __( 'Not Set', 'hamail' ),
			],
		], $templates );
		foreach ( $templates as $template ) {
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
}
