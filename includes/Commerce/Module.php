<?php
/**
 * Optional browser-commerce module composition root.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Module {
	/**
	 * Register lazy integration callbacks.
	 *
	 * The enablement filter is intentionally evaluated inside each callback. This
	 * lets themes register their integration filters after plugins have loaded.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( Assets::class, 'enqueue' ), 30 );
		add_filter( 'wc_order_attribution_allow_tracking', array( self::class, 'allow_order_attribution' ), PHP_INT_MAX );
		add_filter( 'bricks/element/render_attributes', array( BricksAdapter::class, 'attributes' ), 20, 3 );
		add_filter( 'woocommerce_available_variation', array( FrontendContext::class, 'variation_data' ), 20, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( CartRemoveLink::class, 'attributes' ), 20, 2 );
	}

	public static function is_enabled(): bool {
		return (bool) apply_filters( 'kdconsent_commerce_enabled', false );
	}

	public static function allow_order_attribution( mixed $allowed ): mixed {
		return self::is_enabled() ? false : $allowed;
	}
}
