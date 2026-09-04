<?php
/**
 * Exact commerce attributes for WooCommerce cart removal fragments.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartRemoveLink {
	public static function attributes( string $html, string $cart_item_key ): string {
		if ( ! Module::is_enabled() || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}

		$cart_item = self::cart_item( $cart_item_key );
		if ( null === $cart_item ) {
			return $html;
		}

		$quantity   = absint( $cart_item['quantity'] ?? 0 );
		$line_total = $cart_item['line_total'] ?? null;
		$product    = $cart_item['data'] ?? null;
		$variation  = absint( $cart_item['variation_id'] ?? 0 );
		if ( $variation > 0 && function_exists( 'wc_get_product' ) ) {
			$variation_product = wc_get_product( $variation );
			if ( $variation_product instanceof \WC_Product ) {
				$product = $variation_product;
			}
		}
		if ( $quantity < 1 || ! is_numeric( $line_total ) || ! $product instanceof \WC_Product ) {
			return $html;
		}

		$item = FrontendContext::product_item( $product, $quantity, $cart_item );
		if ( '' === $item['item_id'] || '' === $item['item_name'] ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
			return $html;
		}

		$processor->set_attribute( 'data-kdconsent-commerce-item-id', $item['item_id'] );
		$processor->set_attribute( 'data-kdconsent-commerce-item-sku', $item['sku'] );
		$processor->set_attribute( 'data-kdconsent-commerce-item-name', $item['item_name'] );
		$processor->set_attribute( 'data-kdconsent-commerce-item-price', (string) $item['price'] );
		$processor->set_attribute( 'data-kdconsent-commerce-quantity', (string) $item['quantity'] );
		$processor->set_attribute( 'data-kdconsent-commerce-cart-key', sanitize_text_field( $cart_item_key ) );

		return $processor->get_updated_html();
	}

	/** @return array<string,mixed>|null */
	private static function cart_item( string $cart_item_key ): ?array {
		$woocommerce = function_exists( 'WC' ) ? WC() : null;
		$cart        = is_object( $woocommerce ) && isset( $woocommerce->cart ) ? $woocommerce->cart : null;
		if ( ! is_object( $cart ) ) {
			return null;
		}
		if ( method_exists( $cart, 'get_cart_item' ) ) {
			$item = $cart->get_cart_item( $cart_item_key );
			return is_array( $item ) && ! empty( $item ) ? $item : null;
		}
		if ( method_exists( $cart, 'get_cart' ) ) {
			$items = $cart->get_cart();
			return is_array( $items[ $cart_item_key ] ?? null ) ? $items[ $cart_item_key ] : null;
		}

		return null;
	}
}
