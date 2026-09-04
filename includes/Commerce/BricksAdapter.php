<?php
/**
 * Optional semantic attributes for Bricks WooCommerce product lists.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BricksAdapter {
	/**
	 * @param array<string,array<string,mixed>> $attributes Render attributes.
	 * @param string                           $key Current attribute key.
	 * @param object                           $element Bricks element.
	 * @return array<string,array<string,mixed>>
	 */
	public static function attributes( array $attributes, string $key, object $element ): array {
		if ( ! Module::is_enabled() || 'woocommerce-products' !== ( $element->name ?? '' ) ) {
			return $attributes;
		}

		$context = self::list_context( $element, $key );
		if ( '_root' === $key || 'wrapper' === $key ) {
			$attributes[ $key ] = is_array( $attributes[ $key ] ?? null ) ? $attributes[ $key ] : array();
			$attributes[ $key ]['data-kdconsent-commerce-list-id']    = $context['id'];
			$attributes[ $key ]['data-kdconsent-commerce-list-name']  = $context['name'];
			$attributes[ $key ]['data-kdconsent-commerce-list-group'] = $context['group'];
			if ( 'wrapper' === $key ) {
				$attributes[ $key ]['role'] = 'list';
			}
			return $attributes;
		}

		if ( 1 !== preg_match( '/^item-(\d+)$/', $key, $matches ) || ! function_exists( 'wc_get_product' ) ) {
			return $attributes;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product instanceof \WC_Product ) {
			return $attributes;
		}

		$item               = FrontendContext::product_item( $product );
		$attributes[ $key ] = is_array( $attributes[ $key ] ?? null ) ? $attributes[ $key ] : array();
		$attributes[ $key ]['role']                                  = 'listitem';
		$attributes[ $key ]['data-kdconsent-commerce-item']           = '1';
		$attributes[ $key ]['data-kdconsent-commerce-list-id']        = $context['id'];
		$attributes[ $key ]['data-kdconsent-commerce-list-name']      = $context['name'];
		$attributes[ $key ]['data-kdconsent-commerce-list-group']     = $context['group'];
		$attributes[ $key ]['data-kdconsent-commerce-index']          = (string) ( (int) $matches[1] + 1 );
		$attributes[ $key ]['data-kdconsent-commerce-item-id']        = $item['item_id'];
		$attributes[ $key ]['data-kdconsent-commerce-item-sku']       = $item['sku'];
		$attributes[ $key ]['data-kdconsent-commerce-item-name']      = $item['item_name'];
		$attributes[ $key ]['data-kdconsent-commerce-item-price']     = (string) $item['price'];
		$attributes[ $key ]['data-kdconsent-commerce-item-currency']  = function_exists( 'get_woocommerce_currency' )
			? sanitize_text_field( get_woocommerce_currency() )
			: '';

		return $attributes;
	}

	/** @return array{id:string,name:string,group:string} */
	private static function list_context( object $element, string $key ): array {
		$settings     = is_array( $element->settings ?? null ) ? $element->settings : array();
		$element_data = is_array( $element->element ?? null ) ? $element->element : array();
		$default      = array(
			'list_id'    => sanitize_key( (string) ( $element->id ?? 'products' ) ),
			'list_name'  => sanitize_text_field( (string) ( $element_data['label'] ?? 'Products' ) ),
			'list_group' => 'products',
		);
		$filtered     = apply_filters( 'kdconsent_commerce_bricks_list_context', $default, $element, $settings, $key );
		$filtered     = is_array( $filtered ) ? $filtered : $default;

		$id    = sanitize_key( (string) ( $filtered['list_id'] ?? $default['list_id'] ) );
		$name  = sanitize_text_field( (string) ( $filtered['list_name'] ?? $default['list_name'] ) );
		$group = sanitize_key( (string) ( $filtered['list_group'] ?? $default['list_group'] ) );

		return array(
			'id'    => '' !== $id ? $id : 'products',
			'name'  => '' !== $name ? $name : 'Products',
			'group' => '' !== $group ? $group : 'products',
		);
	}
}
