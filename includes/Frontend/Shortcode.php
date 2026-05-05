<?php
/**
 * Frontend shortcode registration.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	public static function register(): void {
		add_shortcode( 'kdcb_preferences', array( self::class, 'render' ) );
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public static function render( array $attributes = array(), string $content = '' ): string {
		$label = '' !== trim( $content )
			? $content
			: __( 'Cookie settings', 'cookie-banner' );

		return sprintf(
			'<button type="button" class="kdcb-open-preferences">%s</button>',
			esc_html( $label )
		);
	}
}
