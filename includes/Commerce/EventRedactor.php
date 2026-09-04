<?php
/**
 * Positive-list redaction for server commerce events.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EventRedactor {
	/**
	 * Remove every field not explicitly present in the public commerce contract.
	 *
	 * @param array<string,mixed> $raw Untrusted event data.
	 * @return array<string,mixed>
	 */
	public static function event( array $raw ): array {
		$name = sanitize_key( (string) ( $raw['event_name'] ?? '' ) );
		if ( ! in_array( $name, array( 'purchase', 'refund' ), true ) ) {
			return array();
		}

		$consent = array( 'essential' => true );
		foreach ( is_array( $raw['consent'] ?? null ) ? $raw['consent'] : array() as $purpose => $granted ) {
			$purpose = sanitize_key( (string) $purpose );
			if ( '' !== $purpose && 'essential' !== $purpose ) {
				$consent[ $purpose ] = true === $granted || 1 === $granted || '1' === $granted;
			}
		}

		$destinations = array();
		foreach ( is_array( $raw['planned_destinations'] ?? null ) ? $raw['planned_destinations'] : array() as $destination ) {
			$destination = sanitize_key( (string) $destination );
			if ( '' !== $destination ) {
				$destinations[] = $destination;
			}
		}

		return array(
			'schema_version'       => max( 1, absint( $raw['schema_version'] ?? 1 ) ),
			'event_name'           => $name,
			'event_id'             => self::identifier( $raw['event_id'] ?? '', true ),
			'occurred_at'          => self::text( $raw['occurred_at'] ?? '', 40 ),
			'source'               => 'server',
			'consent'              => $consent,
			'ecommerce'            => self::ecommerce( is_array( $raw['ecommerce'] ?? null ) ? $raw['ecommerce'] : array() ),
			'planned_destinations' => array_values( array_unique( $destinations ) ),
		);
	}

	/**
	 * @param array<string,mixed> $raw Raw ecommerce data.
	 * @return array<string,mixed>
	 */
	private static function ecommerce( array $raw ): array {
		$ecommerce = array();
		if ( isset( $raw['currency'] ) ) {
			$ecommerce['currency'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $raw['currency'] ) );
		}
		foreach ( array( 'transaction_id', 'refund_id' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$value = self::identifier( $raw[ $key ] );
				if ( '' !== $value ) {
					$ecommerce[ $key ] = $value;
				}
			}
		}
		foreach ( array( 'value', 'paid_value', 'shipping', 'tax' ) as $key ) {
			if ( isset( $raw[ $key ] ) && is_numeric( $raw[ $key ] ) ) {
				$value = (float) $raw[ $key ];
				if ( is_finite( $value ) ) {
					$ecommerce[ $key ] = $value;
				}
			}
		}
		foreach ( array( 'has_email', 'has_phone', 'has_click_id' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$ecommerce[ $key ] = true === $raw[ $key ];
			}
		}

		$items = array();
		foreach ( is_array( $raw['items'] ?? null ) ? $raw['items'] : array() as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$item_id = self::identifier( $raw_item['item_id'] ?? '' );
			$quantity = isset( $raw_item['quantity'] ) && is_numeric( $raw_item['quantity'] )
				? abs( (int) $raw_item['quantity'] )
				: 0;
			if ( '' === $item_id || 0 === $quantity || ! isset( $raw_item['price'] ) || ! is_numeric( $raw_item['price'] ) ) {
				continue;
			}

			$price = abs( (float) $raw_item['price'] );
			if ( ! is_finite( $price ) ) {
				continue;
			}

			$items[] = array(
				'item_id'   => $item_id,
				'sku'       => self::text( $raw_item['sku'] ?? '' ),
				'item_name' => self::text( $raw_item['item_name'] ?? '' ),
				'price'     => $price,
				'quantity'  => $quantity,
			);
		}
		$ecommerce['items'] = $items;

		return $ecommerce;
	}

	private static function identifier( mixed $value, bool $allow_colon = false ): string {
		$pattern = $allow_colon ? '/[^A-Za-z0-9:_-]/' : '/[^A-Za-z0-9_-]/';
		return substr( (string) preg_replace( $pattern, '', self::text( $value, 150 ) ), 0, 150 );
	}

	private static function text( mixed $value, int $length = 500 ): string {
		$text = sanitize_text_field( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $length, 'UTF-8' );
		}

		$truncated = substr( $text, 0, $length );
		return function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( $truncated, true ) : $truncated;
	}
}
