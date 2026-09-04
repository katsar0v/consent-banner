<?php
/**
 * Canonical paid-order and refund event construction.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OrderEventFactory {
	/** @return array<string,mixed> */
	public static function purchase( \WC_Order $order ): array {
		$line_data = self::items( $order );
		$consent   = ConsentSnapshot::from_order( $order );
		$paid_date = $order->get_date_paid();
		$raw       = self::base(
			'purchase',
			'purchase:' . $order->get_id(),
			self::occurred_at( $paid_date ? $paid_date : $order->get_date_created() ),
			$consent,
			array(
				'currency'       => $order->get_currency(),
				'transaction_id' => (string) $order->get_id(),
				'value'          => $line_data['value'],
				'paid_value'     => self::money( abs( (float) $order->get_total() ) ),
				'shipping'       => self::money( abs( (float) $order->get_shipping_total() ) ),
				'tax'            => self::money( abs( (float) $order->get_total_tax() ) ),
				'has_email'      => '' !== trim( (string) $order->get_billing_email() ),
				'has_phone'      => '' !== trim( (string) $order->get_billing_phone() ),
				'has_click_id'   => self::has_click_id( $order ),
				'items'          => $line_data['items'],
			)
		);

		return self::filter( $raw );
	}

	/** @return array<string,mixed> */
	public static function refund( \WC_Order $order, \WC_Order_Refund $refund ): array {
		$line_data = self::items( $refund );
		$consent   = ConsentSnapshot::from_order( $order );
		$raw       = self::base(
			'refund',
			'refund:' . $order->get_id() . ':' . $refund->get_id(),
			self::occurred_at( $refund->get_date_created() ),
			$consent,
			array(
				'currency'       => $order->get_currency(),
				'transaction_id' => (string) $order->get_id(),
				'refund_id'      => (string) $refund->get_id(),
				'value'          => $line_data['value'],
				'paid_value'     => self::money( abs( (float) $refund->get_amount() ) ),
				'items'          => $line_data['items'],
			)
		);

		return self::filter( $raw );
	}

	/**
	 * @param array<string,bool>  $consent Consent snapshot.
	 * @param array<string,mixed> $ecommerce Ecommerce data.
	 * @return array<string,mixed>
	 */
	private static function base( string $name, string $event_id, string $occurred_at, array $consent, array $ecommerce ): array {
		return array(
			'schema_version'       => 1,
			'event_name'           => $name,
			'event_id'             => $event_id,
			'occurred_at'          => $occurred_at,
			'source'               => 'server',
			'consent'              => $consent,
			'ecommerce'            => $ecommerce,
			'planned_destinations' => DestinationResolver::resolve( $consent ),
		);
	}

	/**
	 * Redact before exposing the event to filters and redact the filter result again.
	 *
	 * @param array<string,mixed> $raw Raw event.
	 * @return array<string,mixed>
	 */
	private static function filter( array $raw ): array {
		$redacted = EventRedactor::event( $raw );
		$filtered = apply_filters( 'kdconsent_commerce_event', $redacted, $redacted['event_name'] ?? '' );
		$event    = EventRedactor::event( is_array( $filtered ) ? $filtered : $redacted );
		$event['schema_version'] = $redacted['schema_version'];
		$event['event_name']     = $redacted['event_name'];
		$event['event_id']       = $redacted['event_id'];
		$event['occurred_at']    = $redacted['occurred_at'];
		$event['source']         = $redacted['source'];
		$event['consent']        = $redacted['consent'];
		$event['ecommerce']      = $redacted['ecommerce'];
		$allowed  = DestinationResolver::resolve( $redacted['consent'] );
		$planned  = is_array( $event['planned_destinations'] ?? null ) ? $event['planned_destinations'] : array();
		$event['planned_destinations'] = array_values( array_intersect( $planned, $allowed ) );

		return $event;
	}

	/**
	 * @return array{items:list<array<string,mixed>>,value:float}
	 */
	private static function items( \WC_Abstract_Order $order ): array {
		$items = array();
		$value = 0.0;
		foreach ( $order->get_items( 'line_item' ) as $line ) {
			$quantity = abs( (int) $line->get_quantity() );
			$line_total = abs( (float) $line->get_total() );
			if ( 0 === $quantity && $order instanceof \WC_Order_Refund && $line_total > 0.0 ) {
				$quantity = 1;
			}
			if ( 0 === $quantity ) {
				continue;
			}

			$variation_id = $line->get_variation_id();
			$product_id   = absint( $variation_id ? $variation_id : $line->get_product_id() );
			if ( 0 === $product_id ) {
				continue;
			}

			$product    = $line->get_product();
			$value     += $line_total;
			$items[]    = array(
				'item_id'   => (string) $product_id,
				'sku'       => $product instanceof \WC_Product ? (string) $product->get_sku() : '',
				'item_name' => (string) $line->get_name(),
				'price'     => $line_total / $quantity,
				'quantity'  => $quantity,
			);
		}

		return array(
			'items' => $items,
			'value' => self::money( $value ),
		);
	}

	private static function money( float $value ): float {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		return (float) ( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $value, $decimals ) : round( $value, $decimals ) );
	}

	private static function occurred_at( mixed $date ): string {
		return is_object( $date ) && method_exists( $date, 'getTimestamp' )
			? gmdate( 'c', (int) $date->getTimestamp() )
			: gmdate( 'c', 0 );
	}

	private static function has_click_id( \WC_Order $order ): bool {
		$keys = array(
			'_gclid',
			'gclid',
			'_wbraid',
			'wbraid',
			'_gbraid',
			'gbraid',
			'_wc_order_attribution_gclid',
			'_wc_order_attribution_wbraid',
			'_wc_order_attribution_gbraid',
		);
		foreach ( $keys as $key ) {
			if ( '' !== trim( (string) $order->get_meta( $key, true ) ) ) {
				return true;
			}
		}

		return false;
	}
}
