<?php
/**
 * JSON settings import/export service.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

use KatsarovDesign\ConsentBanner\Domain\Category;
use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsTransfer {
	public const ERROR_EXPORT_FAILED    = 'export-failed';
	public const ERROR_INVALID_JSON     = 'invalid-json';
	public const ERROR_MISSING_SETTINGS = 'missing-settings';

	private const EXPORT_SCHEMA_VERSION    = 1;
	private const EXPORT_PLUGIN_IDENTIFIER = 'katsarovdesign/consent-banner';
	private const SETTINGS_KEYS            = array(
		'categories',
		'texts',
		'styles',
		'consentLifetimeDays',
		'position',
		'animation',
		'showDelayMs',
		'theme',
		'showRejectButton',
		'enableConsentLog',
		'removeOnUninstall',
	);

	private SettingsRepository $settings_repository;

	public function __construct( ?SettingsRepository $settings_repository = null ) {
		$this->settings_repository = $settings_repository ?? new SettingsRepository();
	}

	/**
	 * @return array<string,mixed>
	 */
	public function export_payload(): array {
		return array(
			'schemaVersion'  => self::EXPORT_SCHEMA_VERSION,
			'plugin'         => self::EXPORT_PLUGIN_IDENTIFIER,
			'pluginVersion'  => defined( 'KDCONSENT_PLUGIN_VERSION' ) ? KDCONSENT_PLUGIN_VERSION : '',
			'exportedAt'     => gmdate( 'c' ),
			'consentVersion' => $this->consent_version(),
			'settings'       => $this->settings_repository->get(),
		);
	}

	public function export_json(): string {
		$json = wp_json_encode( $this->export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $json ) ) {
			throw new SettingsTransferException(
				esc_html__( 'Settings could not be exported.', 'consent-banner' ),
				self::ERROR_EXPORT_FAILED // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal machine-readable error code.
			);
		}

		return $json;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function preview_import( string $json, bool $replace_all = false, bool $bump_consent_version = true ): array {
		return $this->prepare_import_result( $json, $replace_all, $bump_consent_version, true );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function import_json( string $json, bool $replace_all = false, bool $bump_consent_version = true ): array {
		$result             = $this->prepare_import_result( $json, $replace_all, $bump_consent_version, false );
		$result['settings'] = $this->settings_repository->update( $result['settings'] );

		if ( $bump_consent_version ) {
			update_option( Installer::OPTION_CONSENT_VERSION, (int) $result['previousConsentVersion'] + 1, false );
		}

		$result['consentVersion'] = $this->consent_version();

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function prepare_import_result( string $json, bool $replace_all, bool $bump_consent_version, bool $sanitize_settings ): array {
		$decoded = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			throw new SettingsTransferException(
				esc_html__( 'The import file is not valid JSON.', 'consent-banner' ),
				self::ERROR_INVALID_JSON // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal machine-readable error code.
			);
		}

		$imported_settings = $this->extract_import_settings( $decoded );
		if ( null === $imported_settings ) {
			throw new SettingsTransferException(
				esc_html__( 'The import file does not contain plugin settings.', 'consent-banner' ),
				self::ERROR_MISSING_SETTINGS // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal machine-readable error code.
			);
		}

		$current_settings         = $this->settings_repository->get();
		$previous_consent_version = $this->consent_version();
		$settings                 = $replace_all
			? $imported_settings
			: $this->merge_settings( $current_settings, $imported_settings );

		$prepared_settings = $sanitize_settings ? $this->settings_repository->sanitize( $settings ) : $settings;

		return array(
			'settings'               => $prepared_settings,
			'previousConsentVersion' => $previous_consent_version,
			'consentVersion'         => $bump_consent_version ? $previous_consent_version + 1 : $previous_consent_version,
			'replace'                => $replace_all,
			'bumpConsentVersion'     => $bump_consent_version,
		);
	}

	private function consent_version(): int {
		return (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 );
	}

	/**
	 * @param array<string,mixed> $payload Decoded JSON payload.
	 * @return array<string,mixed>|null
	 */
	private function extract_import_settings( array $payload ): ?array {
		if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			return $payload['settings'];
		}

		foreach ( self::SETTINGS_KEYS as $setting_key ) {
			if ( array_key_exists( $setting_key, $payload ) ) {
				return $payload;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $imported
	 * @return array<string,mixed>
	 */
	private function merge_settings( array $current, array $imported ): array {
		$merged = $current;

		foreach ( self::SETTINGS_KEYS as $setting_key ) {
			if ( ! array_key_exists( $setting_key, $imported ) ) {
				continue;
			}

			if ( 'categories' === $setting_key ) {
				$merged['categories'] = $this->merge_categories(
					is_array( $current['categories'] ?? null ) ? $current['categories'] : array(),
					is_array( $imported['categories'] ) ? $imported['categories'] : array()
				);
				continue;
			}

			if ( in_array( $setting_key, array( 'texts', 'styles' ), true ) ) {
				$merged[ $setting_key ] = $this->merge_recursive_settings(
					is_array( $current[ $setting_key ] ?? null ) ? $current[ $setting_key ] : array(),
					is_array( $imported[ $setting_key ] ) ? $imported[ $setting_key ] : array()
				);
				continue;
			}

			$merged[ $setting_key ] = $imported[ $setting_key ];
		}

		return $merged;
	}

	/**
	 * @param array<int|string,mixed> $current
	 * @param array<int|string,mixed> $imported
	 * @return list<array<string,mixed>>
	 */
	private function merge_categories( array $current, array $imported ): array {
		$category_order = array();
		$categories     = array();

		foreach ( $current as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$category = Category::from_array( $item );
			$id       = $category->id();
			if ( isset( $categories[ $id ] ) ) {
				continue;
			}

			$category_order[]  = $id;
			$categories[ $id ] = $category->to_array();
		}

		foreach ( $imported as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$category = Category::from_array( $item );
			$id       = $category->id();
			if ( ! isset( $categories[ $id ] ) ) {
				$category_order[] = $id;
			}

			$categories[ $id ] = $category->to_array();
		}

		$merged = array();
		foreach ( $category_order as $id ) {
			$merged[] = $categories[ $id ];
		}

		return $merged;
	}

	/**
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $imported
	 * @return array<string,mixed>
	 */
	private function merge_recursive_settings( array $current, array $imported ): array {
		foreach ( $imported as $key => $value ) {
			if ( is_array( $value ) && isset( $current[ $key ] ) && is_array( $current[ $key ] ) ) {
				$current[ $key ] = $this->merge_recursive_settings( $current[ $key ], $value );
				continue;
			}

			$current[ $key ] = $value;
		}

		return $current;
	}
}
