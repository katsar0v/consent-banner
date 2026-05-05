<?php
/**
 * Settings persistence layer.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Repository;

use KatsarovDesign\CookieBanner\Domain\Category;
use KatsarovDesign\CookieBanner\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsRepository {
	/**
	 * @return array<string,mixed>
	 */
	public function get(): array {
		$defaults = Installer::default_settings();
		$saved    = get_option( Installer::OPTION_SETTINGS, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$merged   = array_merge( $defaults, $saved );

		return $this->sanitize_settings( $merged );
	}

	/**
	 * @param array<string,mixed> $settings Raw incoming settings payload.
	 * @return array<string,mixed>
	 */
	public function update( array $settings ): array {
		$sanitized = $this->sanitize_settings( $settings );

		update_option( Installer::OPTION_SETTINGS, $sanitized, false );
		update_option( Installer::OPTION_REMOVE_ON_UNINSTALL, (bool) $sanitized['removeOnUninstall'], false );

		return $sanitized;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function sanitize_settings( array $settings ): array {
		$defaults           = Installer::default_settings();
		$allowed_positions  = array( 'bottom', 'center' );
		$allowed_animations = array(
			'fade-in',
			'slide-in-up',
			'slide-in-left',
			'slide-in-right',
			'slide-in-down',
			'blur-in',
		);

		$categories = $this->sanitize_categories( $settings['categories'] ?? $defaults['categories'] );
		$texts      = $this->sanitize_texts( $settings['texts'] ?? $defaults['texts'] );
		$styles     = $this->sanitize_styles( $settings['styles'] ?? ( $defaults['styles'] ?? array() ) );
		$lifetime   = (int) ( $settings['consentLifetimeDays'] ?? $defaults['consentLifetimeDays'] );
		$position   = (string) ( $settings['position'] ?? $defaults['position'] );
		$animation  = (string) ( $settings['animation'] ?? ( $defaults['animation'] ?? 'fade-in' ) );
		$show_delay = (int) ( $settings['showDelayMs'] ?? ( $defaults['showDelayMs'] ?? 0 ) );

		if ( ! in_array( $position, $allowed_positions, true ) ) {
			$position = (string) $defaults['position'];
		}

		if ( ! in_array( $animation, $allowed_animations, true ) ) {
			$animation = (string) ( $defaults['animation'] ?? 'fade-in' );
		}

		$show_delay = max( 0, min( 10000, $show_delay ) );

		return array(
			'categories'          => $categories,
			'texts'               => $texts,
			'styles'              => $styles,
			'consentLifetimeDays' => max( 30, min( 730, $lifetime ) ),
			'position'            => $position,
			'animation'           => $animation,
			'showDelayMs'         => $show_delay,
			'theme'               => (string) $defaults['theme'],
			'showRejectButton'    => ! empty( $settings['showRejectButton'] ),
			'enableConsentLog'    => ! empty( $settings['enableConsentLog'] ),
			'removeOnUninstall'   => ! empty( $settings['removeOnUninstall'] ),
		);
	}

	/**
	 * @param mixed $raw
	 * @return list<array<string,mixed>>
	 */
	private function sanitize_categories( mixed $raw ): array {
		$categories = array();
		$raw_items  = is_array( $raw ) ? $raw : array();

		foreach ( $raw_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$category = Category::from_array( $item );
			$id       = $category->id();
			if ( '' === $id || isset( $categories[ $id ] ) ) {
				continue;
			}

			$categories[ $id ] = $category->to_array();
		}

		if ( ! isset( $categories['essential'] ) ) {
			$categories['essential'] = array(
				'id'               => 'essential',
				'label'            => __( 'Essential', 'cookie-banner' ),
				'description'      => __( 'Required for basic website functionality.', 'cookie-banner' ),
				'required'         => true,
				'enabledByDefault' => true,
			);
		}

		$categories['essential']['required']         = true;
		$categories['essential']['enabledByDefault'] = true;

		$filtered = apply_filters( 'kdcb_categories', array_values( $categories ) );
		if ( ! is_array( $filtered ) ) {
			$filtered = array_values( $categories );
		}

		$normalized = array();
		foreach ( $filtered as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$category = Category::from_array( $item );
			$id       = $category->id();
			if ( '' === $id || isset( $normalized[ $id ] ) ) {
				continue;
			}

			$normalized[ $id ] = $category->to_array();
		}

		if ( ! isset( $normalized['essential'] ) ) {
			$normalized['essential'] = array(
				'id'               => 'essential',
				'label'            => __( 'Essential', 'cookie-banner' ),
				'description'      => __( 'Required for basic website functionality.', 'cookie-banner' ),
				'required'         => true,
				'enabledByDefault' => true,
			);
		}

		$normalized['essential']['required']         = true;
		$normalized['essential']['enabledByDefault'] = true;

		uksort(
			$normalized,
			static function ( string $a, string $b ): int {
				if ( 'essential' === $a ) {
					return -1;
				}

				if ( 'essential' === $b ) {
					return 1;
				}

				return strcmp( $a, $b );
			}
		);

		return array_values( $normalized );
	}

	/**
	 * @param mixed $raw
	 * @return array<string,array<string,string>>
	 */
	private function sanitize_texts( mixed $raw ): array {
		$defaults = Installer::default_settings()['texts'];
		$source   = is_array( $raw ) ? $raw : array();
		$result   = array();

		foreach ( array( 'en_US', 'bg_BG' ) as $locale ) {
			$locale_defaults = is_array( $defaults[ $locale ] ?? null ) ? $defaults[ $locale ] : array();
			$locale_source   = is_array( $source[ $locale ] ?? null ) ? $source[ $locale ] : array();
			$values          = array();

			foreach ( $locale_defaults as $key => $default_value ) {
				$raw_value     = $locale_source[ $key ] ?? $default_value;
				$values[ $key ] = sanitize_text_field( (string) $raw_value );
			}

			$result[ $locale ] = $values;
		}

		return $result;
	}

	/**
	 * @param mixed $raw
	 * @return array<string,mixed>
	 */
	private function sanitize_styles( mixed $raw ): array {
		$defaults        = Installer::default_settings();
		$default_styles  = is_array( $defaults['styles'] ?? null ) ? $defaults['styles'] : array();
		$source          = is_array( $raw ) ? $raw : array();
		$default_backdrop = is_array( $default_styles['backdrop'] ?? null ) ? $default_styles['backdrop'] : array();
		$source_backdrop  = is_array( $source['backdrop'] ?? null ) ? $source['backdrop'] : array();

		$backdrop_color = $this->sanitize_hex_color_or_default(
			$source_backdrop['color'] ?? null,
			(string) ( $default_backdrop['color'] ?? '#000000' )
		);

		$raw_opacity = $source_backdrop['opacity'] ?? ( $default_backdrop['opacity'] ?? 0.45 );
		$opacity     = is_numeric( $raw_opacity ) ? (float) $raw_opacity : (float) ( $default_backdrop['opacity'] ?? 0.45 );
		$opacity     = max( 0.0, min( 1.0, $opacity ) );
		$opacity     = round( $opacity, 2 );

		$default_buttons = is_array( $default_styles['buttons'] ?? null ) ? $default_styles['buttons'] : array();
		$source_buttons  = is_array( $source['buttons'] ?? null ) ? $source['buttons'] : array();
		$buttons         = array();

		foreach ( $default_buttons as $button_key => $button_defaults ) {
			if ( ! is_array( $button_defaults ) ) {
				continue;
			}

			$button_source = is_array( $source_buttons[ $button_key ] ?? null ) ? $source_buttons[ $button_key ] : array();
			$buttons[ $button_key ] = array(
				'background'      => $this->sanitize_hex_color_or_default( $button_source['background'] ?? null, (string) ( $button_defaults['background'] ?? '#FFFFFF' ) ),
				'text'            => $this->sanitize_hex_color_or_default( $button_source['text'] ?? null, (string) ( $button_defaults['text'] ?? '#111827' ) ),
				'border'          => $this->sanitize_hex_color_or_default( $button_source['border'] ?? null, (string) ( $button_defaults['border'] ?? '#C0C6CC' ) ),
				'hoverBackground' => $this->sanitize_hex_color_or_default( $button_source['hoverBackground'] ?? null, (string) ( $button_defaults['hoverBackground'] ?? '#F3F4F6' ) ),
				'hoverText'       => $this->sanitize_hex_color_or_default( $button_source['hoverText'] ?? null, (string) ( $button_defaults['hoverText'] ?? '#111827' ) ),
				'hoverBorder'     => $this->sanitize_hex_color_or_default( $button_source['hoverBorder'] ?? null, (string) ( $button_defaults['hoverBorder'] ?? '#C0C6CC' ) ),
			);
		}

		return array(
			'backdrop' => array(
				'color'   => $backdrop_color,
				'opacity' => $opacity,
			),
			'buttons'  => $buttons,
		);
	}

	private function sanitize_hex_color_or_default( mixed $value, string $fallback ): string {
		$default_color = sanitize_hex_color( $fallback );
		$default_color = null !== $default_color ? strtoupper( $default_color ) : '#000000';

		if ( ! is_scalar( $value ) ) {
			return $default_color;
		}

		$color = sanitize_hex_color( (string) $value );

		return null !== $color ? strtoupper( $color ) : $default_color;
	}
}
