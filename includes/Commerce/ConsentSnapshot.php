<?php
/**
 * Privacy-minimal consent snapshots for commerce events.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

use KatsarovDesign\ConsentBanner\Service\ConsentService;
use KatsarovDesign\ConsentBanner\Service\ServiceRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentSnapshot {
	/**
	 * Read and normalize the current signed consent decision.
	 *
	 * @return array<string,bool>
	 */
	public static function current(): array {
		$state = ( new ConsentService() )->current_from_request();

		return self::normalize( null !== $state ? $state->categories() : array() );
	}

	/**
	 * Read the consent decision captured with an order.
	 *
	 * @return array<string,bool>
	 */
	public static function from_order( \WC_Order $order ): array {
		$raw = $order->get_meta( OrderDispatcher::CONSENT_META, true );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}

		return self::normalize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * @param array<string,mixed> $raw Raw consent values.
	 * @return array<string,bool>
	 */
	public static function normalize( array $raw ): array {
		$purposes = array( 'preferences', 'analytics', 'marketing' );
		foreach ( ServiceRegistry::services() as $service ) {
			$purpose = sanitize_key( (string) ( $service['purpose'] ?? '' ) );
			if ( '' !== $purpose && 'essential' !== $purpose && ! in_array( $purpose, $purposes, true ) ) {
				$purposes[] = $purpose;
			}
		}

		$snapshot = array( 'essential' => true );
		foreach ( $purposes as $purpose ) {
			$value                = $raw[ $purpose ] ?? false;
			$snapshot[ $purpose ] = true === $value || 1 === $value || '1' === $value;
		}

		return $snapshot;
	}
}
