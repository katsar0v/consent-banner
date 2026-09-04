<?php
/**
 * Effective consent-definition fingerprinting.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentDefinitionFingerprint {
	private const MAX_WRITE_ATTEMPTS = 5;

	/**
	 * Synchronize the stored fingerprint with the effective runtime definitions.
	 */
	public static function sync(): void {
		self::sync_definitions( ( new SettingsRepository() )->get(), ServiceRegistry::services() );
	}

	/**
	 * Synchronize definitions immediately after persisted settings change.
	 *
	 * The previous settings seed installations that do not have a combined
	 * fingerprint yet. This preserves immediate version bumps without causing a
	 * second bump during the next request.
	 *
	 * @param array<string,mixed> $previous_settings Previous effective settings.
	 * @param array<string,mixed> $current_settings Current effective settings.
	 * @param bool                $bump_consent_version Whether a changed definition should bump consent.
	 */
	public static function settings_updated( array $previous_settings, array $current_settings, bool $bump_consent_version = true ): void {
		$services = ServiceRegistry::services();

		self::sync_definitions( $current_settings, $services, $previous_settings, $bump_consent_version );
	}

	/**
	 * @param array<string,mixed> $settings Effective settings.
	 * @param list<array<string,mixed>> $services Effective services.
	 * @param array<string,mixed>|null $previous_settings Previous settings during a settings update.
	 * @param bool                     $bump_consent_version Whether a changed definition should bump consent.
	 */
	private static function sync_definitions(
		array $settings,
		array $services,
		?array $previous_settings = null,
		bool $bump_consent_version = true
	): void {
		$hash             = self::fingerprint( $settings, $services );
		$service_changed  = self::legacy_service_changed( $services );
		$settings_changed = null !== $previous_settings
			&& self::settings_fingerprint( $previous_settings ) !== self::settings_fingerprint( $settings );

		for ( $attempt = 0; $attempt < self::MAX_WRITE_ATTEMPTS; $attempt++ ) {
			$previous = get_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH, false );

			if ( false === $previous ) {
				if ( add_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH, $hash, '', false ) ) {
					if ( $bump_consent_version && ( $settings_changed || $service_changed ) ) {
						self::bump_consent_version();
					}

					break;
				}

				self::invalidate_option_cache( Installer::OPTION_CONSENT_DEFINITIONS_HASH );
				continue;
			}

			if ( is_string( $previous ) && hash_equals( $previous, $hash ) ) {
				break;
			}

			if ( self::compare_and_swap_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH, $previous, $hash ) ) {
				if ( $bump_consent_version ) {
					self::bump_consent_version();
				}

				break;
			}
		}

		self::sync_legacy_service_hash( $services );
	}

	/**
	 * @param list<array<string,mixed>> $services Effective services.
	 */
	private static function legacy_service_changed( array $services ): bool {
		$previous = get_option( Installer::OPTION_SERVICE_REGISTRY_HASH, false );

		return false !== $previous
			&& ( ! is_string( $previous ) || ! hash_equals( $previous, self::service_fingerprint( $services ) ) );
	}

	/**
	 * Keep the pre-0.5 service hash current for compatibility without using it
	 * as a second version-bump source.
	 *
	 * @param list<array<string,mixed>> $services Effective services.
	 */
	private static function sync_legacy_service_hash( array $services ): void {
		$hash = self::service_fingerprint( $services );

		for ( $attempt = 0; $attempt < self::MAX_WRITE_ATTEMPTS; $attempt++ ) {
			$previous = get_option( Installer::OPTION_SERVICE_REGISTRY_HASH, false );

			if ( false === $previous ) {
				if ( add_option( Installer::OPTION_SERVICE_REGISTRY_HASH, $hash, '', false ) ) {
					return;
				}

				self::invalidate_option_cache( Installer::OPTION_SERVICE_REGISTRY_HASH );
				continue;
			}

			if ( is_string( $previous ) && hash_equals( $previous, $hash ) ) {
				return;
			}

			if ( self::compare_and_swap_option( Installer::OPTION_SERVICE_REGISTRY_HASH, $previous, $hash ) ) {
				return;
			}
		}
	}

	/**
	 * @param list<array<string,mixed>> $services Effective services.
	 */
	private static function service_fingerprint( array $services ): string {
		return hash( 'sha256', (string) wp_json_encode( $services ) );
	}

	/**
	 * @param array<string,mixed> $settings Effective settings.
	 * @param list<array<string,mixed>> $services Effective services.
	 */
	private static function fingerprint( array $settings, array $services ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'settings' => self::consent_settings( $settings ),
					'services' => $services,
				)
			)
		);
	}

	/**
	 * @param array<string,mixed> $settings Effective settings.
	 */
	private static function settings_fingerprint( array $settings ): string {
		return hash( 'sha256', (string) wp_json_encode( self::consent_settings( $settings ) ) );
	}

	/**
	 * @param array<string,mixed> $settings Effective settings.
	 * @return array<string,mixed>
	 */
	private static function consent_settings( array $settings ): array {
		return array(
			'categories'          => is_array( $settings['categories'] ?? null ) ? $settings['categories'] : array(),
			'texts'               => is_array( $settings['texts'] ?? null ) ? $settings['texts'] : array(),
			'consentLifetimeDays' => isset( $settings['consentLifetimeDays'] ) ? (int) $settings['consentLifetimeDays'] : null,
		);
	}

	private static function bump_consent_version(): void {
		for ( $attempt = 0; $attempt < self::MAX_WRITE_ATTEMPTS; $attempt++ ) {
			$previous = get_option( Installer::OPTION_CONSENT_VERSION, false );

			if ( false === $previous ) {
				if ( add_option( Installer::OPTION_CONSENT_VERSION, 2, '', false ) ) {
					return;
				}

				self::invalidate_option_cache( Installer::OPTION_CONSENT_VERSION );
				continue;
			}

			$next = max( 1, (int) $previous ) + 1;
			if ( self::compare_and_swap_option( Installer::OPTION_CONSENT_VERSION, $previous, $next ) ) {
				return;
			}
		}
	}

	private static function compare_and_swap_option( string $option_name, mixed $expected, mixed $replacement ): bool {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! isset( $wpdb->options ) || ! is_callable( array( $wpdb, 'update' ) ) ) {
			$current = get_option( $option_name, false );
			if ( self::database_value( $current ) !== self::database_value( $expected ) ) {
				return false;
			}

			return update_option( $option_name, $replacement, false );
		}

		$updated = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => self::database_value( $replacement ) ),
			array(
				'option_name'  => $option_name,
				'option_value' => self::database_value( $expected ),
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		self::invalidate_option_cache( $option_name );

		return 1 === $updated;
	}

	private static function database_value( mixed $value ): string {
		return (string) maybe_serialize( $value );
	}

	private static function invalidate_option_cache( string $option_name ): void {
		if ( ! function_exists( 'wp_cache_delete' ) ) {
			return;
		}

		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
