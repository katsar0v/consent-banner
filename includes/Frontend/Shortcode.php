<?php
/**
 * Frontend shortcode registration.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Frontend;

use KatsarovDesign\ConsentBanner\LegacyCompat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	public static function register(): void {
		add_shortcode( 'kdconsent_preferences', array( self::class, 'render' ) );
		add_shortcode( LegacyCompat::SHORTCODE, array( self::class, 'render_legacy' ) );
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public static function render( array $attributes = array(), string $content = '' ): string {
		$label = '' !== trim( $content )
			? $content
			: __( 'Cookie settings', 'consent-banner' );

		return sprintf(
			'<button type="button" class="kdconsent-open-preferences">%s</button>',
			esc_html( $label )
		);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public static function render_legacy( array $attributes = array(), string $content = '' ): string {
		LegacyCompat::deprecated_shortcode( LegacyCompat::SHORTCODE, 'kdconsent_preferences' );

		return self::render( $attributes, $content );
	}
}
