<?php
/**
 * Consent state service.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

use KatsarovDesign\ConsentBanner\Domain\ConsentState;
use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\LegacyCompat;
use KatsarovDesign\ConsentBanner\Repository\ConsentLogRepository;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentService {
	public const COOKIE_NAME = 'kdconsent_consent';

	public function __construct(
		private ?SettingsRepository $settings_repository = null,
		private ?ConsentLogRepository $consent_log_repository = null
	) {
		$this->settings_repository    = $this->settings_repository ?? new SettingsRepository();
		$this->consent_log_repository = $this->consent_log_repository ?? new ConsentLogRepository();
	}

	/**
	 * @param array<string,mixed> $requested_categories
	 */
	public function record( array $requested_categories ): ConsentState {
		$settings   = $this->settings_repository->get();
		$categories = is_array( $settings['categories'] ?? null ) ? $settings['categories'] : array();
		$normalized = array();

		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $category['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}

			$required = ! empty( $category['required'] ) || 'essential' === $id;
			$value    = ! empty( $requested_categories[ $id ] );

			$normalized[ $id ] = $required ? true : $value;
		}

		if ( ! isset( $normalized['essential'] ) ) {
			$normalized['essential'] = true;
		}

		$state = new ConsentState(
			$normalized,
			$this->consent_version(),
			time()
		);

		$this->set_cookie( $state, (int) ( $settings['consentLifetimeDays'] ?? 180 ) );

		if ( ! empty( $settings['enableConsentLog'] ) ) {
			$this->consent_log_repository->insert( $state );
		}

		do_action( 'kdconsent_consent_recorded', $state );
		do_action( LegacyCompat::CONSENT_RECORDED_ACTION, $state );
		$this->clear_cookie( LegacyCompat::COOKIE_NAME );

		return $state;
	}

	public function current_from_request(): ?ConsentState {
		if ( ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
			$state = $this->parse_cookie( $raw );
			if ( null !== $state ) {
				return $state;
			}
		}

		if ( empty( $_COOKIE[ LegacyCompat::COOKIE_NAME ] ) ) {
			return null;
		}

		$legacy_raw = sanitize_text_field( wp_unslash( $_COOKIE[ LegacyCompat::COOKIE_NAME ] ) );
		$state      = $this->parse_cookie( $legacy_raw );
		if ( null === $state ) {
			return null;
		}

		$settings = $this->settings_repository->get();
		$this->set_cookie( $state, (int) ( $settings['consentLifetimeDays'] ?? 180 ) );
		$this->clear_cookie( LegacyCompat::COOKIE_NAME );

		return $state;
	}

	public function has_consent( string $category ): bool {
		$state = $this->current_from_request();
		if ( null === $state ) {
			return false;
		}

		$category = sanitize_key( $category );
		$choices  = $state->categories();
		return ! empty( $choices[ $category ] );
	}

	public function consent_version(): int {
		return max( 1, (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 ) );
	}

	public function parse_cookie( string $raw_cookie ): ?ConsentState {
		$parts = explode( '.', $raw_cookie, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		$encoded   = $parts[0];
		$signature = $parts[1];
		$expected  = hash_hmac( 'sha256', $encoded, $this->signing_key() );

		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}

		$decoded = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		if ( false === $decoded ) {
			return null;
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$version    = isset( $data['v'] ) ? (int) $data['v'] : 0;
		$timestamp  = isset( $data['t'] ) ? (int) $data['t'] : 0;
		$categories = isset( $data['c'] ) && is_array( $data['c'] ) ? $data['c'] : array();

		if ( $version !== $this->consent_version() || $timestamp <= 0 ) {
			return null;
		}

		$normalized = array();
		foreach ( $categories as $key => $value ) {
			$normalized[ sanitize_key( (string) $key ) ] = (bool) $value;
		}

		if ( ! isset( $normalized['essential'] ) ) {
			$normalized['essential'] = true;
		}

		return new ConsentState( $normalized, $version, $timestamp );
	}

	private function set_cookie( ConsentState $state, int $lifetime_days ): void {
		$payload = wp_json_encode( $state->to_array() );
		if ( false === $payload ) {
			return;
		}

		$encoded   = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
		$signature = hash_hmac( 'sha256', $encoded, $this->signing_key() );
		$value     = $encoded . '.' . $signature;
		$expires   = time() + max( 1, $lifetime_days ) * DAY_IN_SECONDS;

		setcookie(
			self::COOKIE_NAME,
			$value,
			$this->cookie_options( $expires )
		);

		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	private function clear_cookie( string $cookie_name ): void {
		if ( headers_sent() ) {
			unset( $_COOKIE[ $cookie_name ] );
			return;
		}

		setcookie(
			$cookie_name,
			'',
			$this->cookie_options( time() - DAY_IN_SECONDS )
		);

		unset( $_COOKIE[ $cookie_name ] );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function cookie_options( int $expires ): array {
		$options = array(
			'expires'  => $expires,
			'path'     => $this->cookie_path(),
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		);

		$domain = defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '';
		if ( '' !== $domain ) {
			$options['domain'] = $domain;
		}

		return $options;
	}

	private function cookie_path(): string {
		$path = defined( 'SITECOOKIEPATH' ) ? (string) SITECOOKIEPATH : '';
		if ( '' === $path && defined( 'COOKIEPATH' ) ) {
			$path = (string) COOKIEPATH;
		}

		return '' !== $path ? $path : '/';
	}

	private function signing_key(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return wp_salt( 'auth' );
		}

		return defined( 'AUTH_KEY' ) ? AUTH_KEY : 'kdconsent-fallback-key';
	}
}
