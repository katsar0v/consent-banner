<?php
/**
 * Browser-commerce PHP tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Commerce\Assets;
use KatsarovDesign\ConsentBanner\Commerce\BricksAdapter;
use KatsarovDesign\ConsentBanner\Commerce\CartRemoveLink;
use KatsarovDesign\ConsentBanner\Commerce\FrontendContext;
use KatsarovDesign\ConsentBanner\Commerce\Module;
use PHPUnit\Framework\TestCase;

final class CommerceModuleTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters']          = array();
		$GLOBALS['kdconsent_test_actions']          = array();
		$GLOBALS['kdconsent_test_enqueued_scripts'] = array();
		$GLOBALS['kdconsent_test_inline_scripts']   = array();
		$GLOBALS['kdconsent_test_products']         = array();
		$GLOBALS['kdconsent_test_registered_scripts'] = array();
		$GLOBALS['kdconsent_test_page']             = array();
		$GLOBALS['kdconsent_test_wc']               = (object) array( 'cart' => null );
	}

	public function test_module_is_disabled_by_default_and_enablement_is_not_cached(): void {
		self::assertFalse( Module::is_enabled() );
		self::assertTrue( Module::allow_order_attribution( true ) );
		Assets::enqueue();
		self::assertArrayNotHasKey( 'kdconsent-commerce', $GLOBALS['kdconsent_test_enqueued_scripts'] );

		add_filter( 'kdconsent_commerce_enabled', '__return_true' );

		self::assertTrue( Module::is_enabled() );
		self::assertFalse( Module::allow_order_attribution( true ) );
	}

	public function test_register_only_installs_lazy_callbacks(): void {
		Module::register();

		self::assertArrayHasKey( 'wp_enqueue_scripts', $GLOBALS['kdconsent_test_actions'] );
		self::assertArrayHasKey( 'wc_order_attribution_allow_tracking', $GLOBALS['kdconsent_test_filters'] );
		self::assertArrayHasKey( 'bricks/element/render_attributes', $GLOBALS['kdconsent_test_filters'] );
		self::assertArrayHasKey( 'woocommerce_available_variation', $GLOBALS['kdconsent_test_filters'] );
		self::assertArrayHasKey( 'woocommerce_cart_item_remove_link', $GLOBALS['kdconsent_test_filters'] );
	}

	public function test_cart_line_value_uses_variation_and_post_discount_net_unit_price(): void {
		$parent    = new WC_Product( 10, 'PARENT', 'Parent', 20.0 );
		$variation = new WC_Product( 11, 'VAR-11', 'Blue', 18.0 );
		$item      = FrontendContext::product_item(
			$variation,
			2,
			array(
				'line_total'   => '30.00',
				'variation_id' => 11,
			)
		);

		self::assertSame( '11', $item['item_id'] );
		self::assertSame( 'VAR-11', $item['sku'] );
		self::assertSame( 15.0, $item['price'] );
		self::assertSame( 2, $item['quantity'] );
		self::assertSame( 10, $parent->get_id() );

		$repeating = FrontendContext::product_item(
			$variation,
			3,
			array( 'line_total' => '10.00' )
		);
		self::assertEqualsWithDelta( 10 / 3, $repeating['price'], PHP_FLOAT_EPSILON );
		self::assertEqualsWithDelta( 10.0, $repeating['price'] * $repeating['quantity'], PHP_FLOAT_EPSILON );
	}

	public function test_cart_context_prefers_variation_and_safely_handles_missing_woocommerce_instance(): void {
		$parent                                          = new WC_Product( 10, 'PARENT', 'Parent', 20.0 );
		$variation                                       = new WC_Product( 11, 'VAR-11', 'Blue', 18.0 );
		$GLOBALS['kdconsent_test_products'][11]           = $variation;
		$GLOBALS['kdconsent_test_page']['cart']           = true;
		$GLOBALS['kdconsent_test_wc']                     = (object) array(
			'cart' => new class( $parent ) {
				public function __construct( private WC_Product $product ) {}

				/** @return array<string,array<string,mixed>> */
				public function get_cart(): array {
					return array(
						'line' => array(
							'data'         => $this->product,
							'variation_id' => 11,
							'quantity'     => 2,
							'line_total'   => '30.00',
						),
					);
				}
			},
		);

		$page = FrontendContext::page();
		self::assertSame( '11', $page['items'][0]['item_id'] );
		self::assertSame( 15.0, $page['items'][0]['price'] );
		self::assertSame( 'line', $page['cartItems'][0]['cart_key'] );
		self::assertSame( '11', $page['cartItems'][0]['item']['item_id'] );
		self::assertSame( 2, $page['currencyDecimals'] );

		$GLOBALS['kdconsent_test_page']['cart'] = false;
		$content_page = FrontendContext::page();
		self::assertSame( array(), $content_page['items'] );
		self::assertSame( array(), $content_page['cartItems'] );

		$GLOBALS['kdconsent_test_wc'] = null;
		self::assertSame( array(), FrontendContext::page()['items'] );
	}

	public function test_remove_link_fragment_contains_exact_current_cart_line_attributes(): void {
		$variation = new WC_Product( 77, 'VAR-77', 'Blue & large', 9.0 );
		$GLOBALS['kdconsent_test_products'][77] = $variation;
		$GLOBALS['kdconsent_test_wc']           = (object) array(
			'cart' => new class( $variation ) {
				public function __construct( private WC_Product $product ) {}

				/** @return array<string,mixed> */
				public function get_cart_item( string $key ): array {
					if ( 'fresh-cart-key' !== $key ) {
						return array();
					}

					return array(
						'data'         => $this->product,
						'product_id'   => 42,
						'variation_id' => 77,
						'quantity'     => 3,
						'line_total'   => '10.00',
					);
				}
			},
		);
		$html = '<a class="remove_from_cart_button" href="?remove_item=fresh-cart-key">Remove</a>';

		self::assertSame( $html, CartRemoveLink::attributes( $html, 'fresh-cart-key' ) );
		add_filter( 'kdconsent_commerce_enabled', '__return_true' );
		$enriched = CartRemoveLink::attributes( $html, 'fresh-cart-key' );

		self::assertStringContainsString( 'data-kdconsent-commerce-item-id="77"', $enriched );
		self::assertStringContainsString( 'data-kdconsent-commerce-item-sku="VAR-77"', $enriched );
		self::assertStringContainsString( 'data-kdconsent-commerce-item-name="Blue &amp; large"', $enriched );
		self::assertStringContainsString( 'data-kdconsent-commerce-quantity="3"', $enriched );
		self::assertStringContainsString( 'data-kdconsent-commerce-cart-key="fresh-cart-key"', $enriched );
		self::assertMatchesRegularExpression( '/data-kdconsent-commerce-item-price="3\.333333333333[0-9]*"/', $enriched );
	}

	public function test_variation_json_is_only_extended_when_module_is_enabled(): void {
		$variation = new WC_Product( 11, 'VAR-11', 'Blue', 18.0 );
		$data      = array( 'variation_id' => 11 );

		self::assertSame( $data, FrontendContext::variation_data( $data, null, $variation ) );
		add_filter( 'kdconsent_commerce_enabled', '__return_true' );
		$extended = FrontendContext::variation_data( $data, null, $variation );

		self::assertSame( '11', $extended['kdconsent_commerce']['item_id'] );
		self::assertSame( 18.0, $extended['kdconsent_commerce']['price'] );
	}

	public function test_bricks_attributes_use_sanitized_filtered_context_and_actual_product(): void {
		$GLOBALS['kdconsent_test_page']['object_id'] = 42;
		$GLOBALS['kdconsent_test_products'][42]      = new WC_Product( 42, 'SKU-42', 'Test product', 12.5 );
		$element                                    = (object) array(
			'name'     => 'woocommerce-products',
			'id'       => 'featured-grid',
			'settings' => array(),
			'element'  => array( 'label' => 'Featured' ),
		);

		self::assertSame( array(), BricksAdapter::attributes( array(), 'item-0', $element ) );
		add_filter( 'kdconsent_commerce_enabled', '__return_true' );
		add_filter(
			'kdconsent_commerce_bricks_list_context',
			static fn(): array => array(
				'list_id'    => 'Home Featured<script>',
				'list_name'  => 'Home <b>Featured</b>',
				'list_group' => 'Single Products!',
			)
		);

		$wrapper = BricksAdapter::attributes( array(), 'wrapper', $element );
		$item    = BricksAdapter::attributes( array(), 'item-0', $element );

		self::assertSame( 'list', $wrapper['wrapper']['role'] );
		self::assertSame( 'homefeaturedscript', $wrapper['wrapper']['data-kdconsent-commerce-list-id'] );
		self::assertSame( 'Home Featured', $item['item-0']['data-kdconsent-commerce-list-name'] );
		self::assertSame( 'singleproducts', $item['item-0']['data-kdconsent-commerce-list-group'] );
		self::assertSame( 'listitem', $item['item-0']['role'] );
		self::assertSame( '1', $item['item-0']['data-kdconsent-commerce-index'] );
		self::assertSame( '42', $item['item-0']['data-kdconsent-commerce-item-id'] );
		self::assertSame( '12.5', $item['item-0']['data-kdconsent-commerce-item-price'] );
	}

	public function test_search_context_contains_no_query_and_asset_uses_sanitized_service_routes(): void {
		$GLOBALS['kdconsent_test_page']['search'] = true;
		$GLOBALS['kdconsent_test_registered_scripts']['registered']['wc-order-attribution'] = true;
		add_filter( 'kdconsent_commerce_enabled', '__return_true' );
		add_filter( 'kdconsent_runtime_mode', static fn(): string => 'debug' );
		add_filter(
			'kdconsent_services',
			static fn(): array => array(
				array(
					'id'      => 'Analytics Vendor',
					'name'    => 'Analytics Vendor',
					'purpose' => 'Analytics',
				),
			)
		);

		Assets::enqueue();

		self::assertArrayHasKey( 'kdconsent-commerce', $GLOBALS['kdconsent_test_enqueued_scripts'] );
		self::assertContains( 'wc-order-attribution', $GLOBALS['kdconsent_test_enqueued_scripts']['kdconsent-commerce'][1] );
		$inline = $GLOBALS['kdconsent_test_inline_scripts']['kdconsent-commerce'][0][0];
		self::assertStringContainsString( '"type":"search"', $inline );
		self::assertStringContainsString( '"services":[{"id":"analyticsvendor","purpose":"analytics"}]', $inline );
		self::assertStringNotContainsString( 'searchTerm', $inline );
		self::assertStringNotContainsString( 'search_term', $inline );
	}
}
