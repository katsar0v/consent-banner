<?php
/**
 * Declarative optional-service registry.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ServiceRegistry {
	/**
	 * @return list<array<string,mixed>>
	 */
	public static function services(): array {
		$raw = apply_filters( 'kdconsent_services', array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$services = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$service = self::sanitize( $item );
			if ( '' === $service['id'] || 'essential' === $service['purpose'] || isset( $services[ $service['id'] ] ) ) {
				continue;
			}

			$services[ $service['id'] ] = $service;
		}

		ksort( $services );
		return array_values( $services );
	}

	public static function sync_consent_version(): void {
		ConsentDefinitionFingerprint::sync();
	}

	/**
	 * @param array<string,mixed> $raw Raw descriptor.
	 * @return array<string,mixed>
	 */
	private static function sanitize( array $raw ): array {
		$allowed_urls = self::sanitize_urls( $raw['allowedUrls'] ?? array() );
		$scripts      = array();

		foreach ( is_array( $raw['scripts'] ?? null ) ? $raw['scripts'] : array() as $script ) {
			if ( ! is_array( $script ) ) {
				continue;
			}

			$src    = esc_url_raw( (string) ( $script['src'] ?? '' ), array( 'http', 'https' ) );
			$handle = sanitize_key( (string) ( $script['handle'] ?? '' ) );
			if ( '' === $src || '' === $handle || ! in_array( $src, $allowed_urls, true ) ) {
				continue;
			}

			$scripts[] = array(
				'handle' => $handle,
				'src'    => $src,
				'async'  => ! empty( $script['async'] ),
				'defer'  => ! empty( $script['defer'] ),
			);
		}

		$teardown = is_array( $raw['teardown'] ?? null ) ? $raw['teardown'] : array();

		return array(
			'id'                 => sanitize_key( (string) ( $raw['id'] ?? '' ) ),
			'name'               => sanitize_text_field( (string) ( $raw['name'] ?? $raw['id'] ?? '' ) ),
			'provider'           => sanitize_text_field( (string) ( $raw['provider'] ?? '' ) ),
			'purpose'            => sanitize_key( (string) ( $raw['purpose'] ?? '' ) ),
			'purposeDescription' => sanitize_text_field( (string) ( $raw['purposeDescription'] ?? '' ) ),
			'data'               => self::sanitize_text_list( $raw['data'] ?? array() ),
			'duration'           => sanitize_text_field( (string) ( $raw['duration'] ?? '' ) ),
			'recipients'         => self::sanitize_text_list( $raw['recipients'] ?? array() ),
			'thirdCountryTransfer' => sanitize_text_field( (string) ( $raw['thirdCountryTransfer'] ?? '' ) ),
			'privacyUrl'         => esc_url_raw( (string) ( $raw['privacyUrl'] ?? '' ), array( 'http', 'https' ) ),
			'scriptHandles'      => self::sanitize_keys( $raw['scriptHandles'] ?? array() ),
			'allowedUrls'        => $allowed_urls,
			'scripts'            => $scripts,
			'cookies'            => self::sanitize_storage_names( $raw['cookies'] ?? array() ),
			'localStorageKeys'   => self::sanitize_storage_names( $raw['localStorageKeys'] ?? array() ),
			'sessionStorageKeys' => self::sanitize_storage_names( $raw['sessionStorageKeys'] ?? array() ),
			'teardown'           => array(
				'globalFunction' => preg_replace( '/[^A-Za-z0-9_.$]/', '', (string) ( $teardown['globalFunction'] ?? '' ) ),
				'event'          => sanitize_key( (string) ( $teardown['event'] ?? '' ) ),
			),
		);
	}

	/** @return list<string> */
	private static function sanitize_urls( mixed $raw ): array {
		$urls = array();
		foreach ( is_array( $raw ) ? $raw : array() as $url ) {
			$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/** @return list<string> */
	private static function sanitize_keys( mixed $raw ): array {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $raw ) ? $raw : array() ) ) ) );
	}

	/** @return list<string> */
	private static function sanitize_storage_names( mixed $raw ): array {
		$names = array_map(
			static fn( mixed $name ): string => preg_replace( '/[^A-Za-z0-9_.:\-]/', '', (string) $name ),
			is_array( $raw ) ? $raw : array()
		);

		return array_values( array_unique( array_filter( $names ) ) );
	}

	/** @return list<string> */
	private static function sanitize_text_list( mixed $raw ): array {
		$values = array_map( 'sanitize_text_field', is_array( $raw ) ? $raw : array() );
		return array_values( array_unique( array_filter( $values ) ) );
	}
}
