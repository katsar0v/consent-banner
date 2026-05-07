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
		if ( 'bg_BG' !== $this->current_locale() ) {
			return array_values(
				array_filter(
					$categories,
					static fn ( mixed $item ): bool => is_array( $item )
				)
			);
		}

		$defaults = \KatsarovDesign\ConsentBanner\Installer::default_settings();
		$default_index = array();
		foreach ( (array) ( $defaults['categories'] ?? array() ) as $default_category ) {
			if ( ! is_array( $default_category ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $default_category['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}

			$default_index[ $id ] = $default_category;
		}

		$bg_defaults = array(
			'essential' => array(
				'label'       => 'Съществени',
				'description' => 'Необходими за базовата функционалност на сайта.',
			),
			'analytics' => array(
				'label'       => 'Аналитични',
				'description' => 'Помагат ни да разберем трафика и използването на сайта.',
			),
			'marketing' => array(
				'label'       => 'Маркетинг',
				'description' => 'Използват се за персонализирани реклами и кампании.',
			),
			'functional' => array(
				'label'       => 'Функционални',
				'description' => 'Запазват предпочитанията ви за по-добро изживяване.',
			),
		);

		$localized = array();
		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $category['id'] ?? '' ) );
			if ( '' === $id || ! isset( $bg_defaults[ $id ] ) ) {
				$localized[] = $category;
				continue;
			}

			$default_label = isset( $default_index[ $id ]['label'] ) ? (string) $default_index[ $id ]['label'] : '';
			$default_desc  = isset( $default_index[ $id ]['description'] ) ? (string) $default_index[ $id ]['description'] : '';
			$current_label = isset( $category['label'] ) ? (string) $category['label'] : '';
			$current_desc  = isset( $category['description'] ) ? (string) $category['description'] : '';

			if ( '' === $current_label || $current_label === $default_label ) {
				$category['label'] = $bg_defaults[ $id ]['label'];
			}

			if ( '' === $current_desc || $current_desc === $default_desc ) {
				$category['description'] = $bg_defaults[ $id ]['description'];
			}

			$localized[] = $category;
		}

		return $localized;
	}
}
