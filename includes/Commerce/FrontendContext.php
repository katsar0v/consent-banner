<?php
/**
 * Privacy-minimal WooCommerce browser context.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FrontendContext {
	/** @return array{type:string,currency:string,currencyDecimals:int,items:list<array<string,mixed>>,cartItems:list<array<string,mixed>>} */
	public static function page(): array {
		$page = array(
			'type'             => self::page_type(),
			'currency'         => function_exists( 'get_woocommerce_currency' ) ? sanitize_text_field( get_woocommerce_currency() ) : '',
			'currencyDecimals' => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
			'items'            => array(),
			'cartItems'        => array(),
		);

		if ( function_exists( 'is_singular' ) && is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product instanceof \WC_Product ) {
				$page['items'][] = self::product_item( $product );
			}
		}

		$woocommerce = function_exists( 'WC' ) ? WC() : null;
		$cart        = is_object( $woocommerce ) && isset( $woocommerce->cart ) ? $woocommerce->cart : null;
		if ( in_array( $page['type'], array( 'cart', 'checkout' ), true ) && is_object( $cart ) && method_exists( $cart, 'get_cart' ) ) {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( ! is_array( $cart_item ) ) {
					continue;
				}

				$product      = $cart_item['data'] ?? null;
				$variation_id = absint( $cart_item['variation_id'] ?? 0 );
				if ( $variation_id > 0 && function_exists( 'wc_get_product' ) ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation instanceof \WC_Product ) {
						$product = $variation;
					}
				}

				if ( $product instanceof \WC_Product ) {
					$commerce_item = self::product_item(
						$product,
						max( 1, absint( $cart_item['quantity'] ?? 1 ) ),
						$cart_item
					);
					$page['cartItems'][] = array(
						'cart_key'     => sanitize_text_field( (string) $cart_item_key ),
						'product_id'   => absint( $cart_item['product_id'] ?? $product->get_id() ),
						'variation_id' => $variation_id,
						'item'         => $commerce_item,
					);
					$page['items'][] = $commerce_item;
				}
			}
		}

		return $page;
	}

	/**
	 * Add an explicit net-price item to WooCommerce variation JSON.
	 *
	 * @param array<string,mixed> $data Variation response.
	 * @param mixed               $product Parent product.
	 * @param mixed               $variation Product variation.
	 * @return array<string,mixed>
	 */
	public static function variation_data( array $data, mixed $product, mixed $variation ): array {
		if ( ! Module::is_enabled() || ! $variation instanceof \WC_Product ) {
			return $data;
		}

		$data['kdconsent_commerce'] = self::product_item( $variation );
		return $data;
	}

	/**
	 * @param array<string,mixed> $cart_item Cart line data.
	 * @return array{item_id:string,sku:string,item_name:string,price:float,quantity:int}
	 */
	public static function product_item( \WC_Product $product, int $quantity = 1, array $cart_item = array() ): array {
		$quantity   = max( 1, $quantity );
		$line_total = isset( $cart_item['line_total'] ) && is_numeric( $cart_item['line_total'] )
			? (float) $cart_item['line_total']
			: null;
		$price      = null !== $line_total
			? $line_total / $quantity
			: ( function_exists( 'wc_get_price_excluding_tax' ) ? (float) wc_get_price_excluding_tax( $product ) : (float) $product->get_price() );
		$decimals   = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		$unit_price = null !== $line_total
			? $price
			: (float) ( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $price, $decimals ) : round( $price, $decimals ) );

		return array(
			'item_id'   => (string) $product->get_id(),
			'sku'       => sanitize_text_field( (string) $product->get_sku() ),
			'item_name' => sanitize_text_field( (string) $product->get_name() ),
			'price'     => $unit_price,
			'quantity'  => $quantity,
		);
	}

	private static function page_type(): string {
		if ( function_exists( 'is_front_page' ) && is_front_page() ) {
			return 'home';
		}
		if ( function_exists( 'is_singular' ) && is_singular( 'product' ) ) {
			return 'product';
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return 'cart';
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) ) {
			return 'checkout';
		}
		if ( function_exists( 'is_search' ) && is_search() ) {
			return 'search';
		}
		if ( function_exists( 'is_shop' ) && ( is_shop() || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ) ) {
			return 'product_archive';
		}

		return 'content';
	}
}
