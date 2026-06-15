<?php
/**
 * Locale and text helpers.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Localization {
	public function current_locale(): string {
		$locale = determine_locale();

		if ( 0 === strpos( $locale, 'bg' ) ) {
			return 'bg_BG';
		}

		if ( 0 === strpos( $locale, 'de' ) ) {
			return 'de_DE';
		}

		return 'en_US';
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,string>
	 */
	public function resolve_texts( array $settings ): array {
		$texts      = is_array( $settings['texts'] ?? null ) ? $settings['texts'] : array();
		$defaults   = \KatsarovDesign\ConsentBanner\Installer::default_settings()['texts'];
		$locale     = $this->current_locale();
		$fallback   = is_array( $defaults['en_US'] ?? null ) ? $defaults['en_US'] : array();
		$localized  = is_array( $texts[ $locale ] ?? null ) ? $texts[ $locale ] : array();

		return array_merge( $fallback, $localized );
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return list<array<string,mixed>>
	 */
	public function resolve_categories( array $settings ): array {
		$categories = is_array( $settings['categories'] ?? null ) ? $settings['categories'] : array();
		$locale     = $this->current_locale();
		$defaults   = $this->category_defaults( $locale );

		if ( array() === $defaults ) {
			return array_values(
				array_filter(
					$categories,
					static fn ( mixed $item ): bool => is_array( $item )
				)
			);
		}

		$default_settings = \KatsarovDesign\ConsentBanner\Installer::default_settings();
		$default_index = array();
		foreach ( (array) ( $default_settings['categories'] ?? array() ) as $default_category ) {
			if ( ! is_array( $default_category ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $default_category['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}

			$default_index[ $id ] = $default_category;
		}

		$localized = array();
		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $category['id'] ?? '' ) );
			if ( '' === $id || ! isset( $defaults[ $id ] ) ) {
				$localized[] = $category;
				continue;
			}

			$default_label = isset( $default_index[ $id ]['label'] ) ? (string) $default_index[ $id ]['label'] : '';
			$default_desc  = isset( $default_index[ $id ]['description'] ) ? (string) $default_index[ $id ]['description'] : '';
			$current_label = isset( $category['label'] ) ? (string) $category['label'] : '';
			$current_desc  = isset( $category['description'] ) ? (string) $category['description'] : '';

			if ( '' === $current_label || $current_label === $default_label ) {
				$category['label'] = $defaults[ $id ]['label'];
			}

			if ( '' === $current_desc || $current_desc === $default_desc ) {
				$category['description'] = $defaults[ $id ]['description'];
			}

			$localized[] = $category;
		}

		return $localized;
	}

	/**
	 * @return array<string,array{label:string,description:string}>
	 */
	private function category_defaults( string $locale ): array {
		$localized_defaults = array(
			'bg_BG' => array(
				'essential'  => array(
					'label'       => 'Съществени',
					'description' => 'Необходими за базовата функционалност на сайта.',
				),
				'analytics'  => array(
					'label'       => 'Аналитични',
					'description' => 'Помагат ни да разберем трафика и използването на сайта.',
				),
				'marketing'  => array(
					'label'       => 'Маркетинг',
					'description' => 'Използват се за персонализирани реклами и кампании.',
				),
				'functional' => array(
					'label'       => 'Функционални',
					'description' => 'Запазват предпочитанията ви за по-добро изживяване.',
				),
			),
			'de_DE' => array(
				'essential'  => array(
					'label'       => 'Essenziell',
					'description' => 'Für die grundlegende Funktionalität der Website erforderlich.',
				),
				'analytics'  => array(
					'label'       => 'Analyse',
					'description' => 'Hilft uns, Website-Traffic und Nutzung zu verstehen.',
				),
				'marketing'  => array(
					'label'       => 'Marketing',
					'description' => 'Wird verwendet, um Werbung und Kampagnen zu personalisieren.',
				),
				'functional' => array(
					'label'       => 'Funktional',
					'description' => 'Speichert Ihre Präferenzen für ein besseres Nutzererlebnis.',
				),
			),
		);

		return is_array( $localized_defaults[ $locale ] ?? null ) ? $localized_defaults[ $locale ] : array();
	}
}
