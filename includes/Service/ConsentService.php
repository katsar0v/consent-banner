<?php
/**
 * Consent state service.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Service;

use KatsarovDesign\CookieBanner\Domain\ConsentState;
use KatsarovDesign\CookieBanner\Installer;
use KatsarovDesign\CookieBanner\Repository\ConsentLogRepository;
use KatsarovDesign\CookieBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentService {
	public const COOKIE_NAME = 'kdcb_consent';

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

		do_action( 'kdcb_consent_recorded', $state );

		return $state;
	}

	public function current_from_request(): ?ConsentState {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		$raw = (string) wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
		return $this->parse_cookie( $raw );
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
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	private function signing_key(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return wp_salt( 'auth' );
		}

		return defined( 'AUTH_KEY' ) ? AUTH_KEY : 'kdcb-fallback-key';
	}
}
