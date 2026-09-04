<?php
/**
 * WooCommerce server-commerce integration tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Commerce\OrderDispatcher;
use KatsarovDesign\ConsentBanner\Commerce\OrderEventFactory;

final class CommerceIntegrationTest extends WP_UnitTestCase {
	protected function setUp(): void {
		parent::setUp();
		add_filter( 'kdconsent_commerce_enabled', '__return_true' );
		as_unschedule_all_actions( '', array(), OrderDispatcher::GROUP );
	}

	protected function tearDown(): void {
		remove_all_filters( 'kdconsent_commerce_enabled' );
		remove_all_filters( 'kdconsent_runtime_mode' );
		remove_all_filters( 'kdconsent_commerce_order_qualifies' );
		remove_all_filters( 'kdconsent_commerce_deliver_purchase' );
		remove_all_filters( 'kdconsent_commerce_deliver_refund' );
		parent::tearDown();
	}

	public function test_real_woocommerce_order_preserves_exact_line_math_and_paid_time(): void {
		$order = $this->create_paid_order();

		$event = OrderEventFactory::purchase( $order );

		self::assertSame( 'purchase:' . $order->get_id(), $event['event_id'] );
		self::assertSame( '2024-02-03T04:05:06+00:00', $event['occurred_at'] );
		self::assertSame( 10.0, $event['ecommerce']['value'] );
		self::assertEqualsWithDelta( 10 / 3, $event['ecommerce']['items'][0]['price'], PHP_FLOAT_EPSILON );
		self::assertSame( 3, $event['ecommerce']['items'][0]['quantity'] );
		self::assertSame( 14.99, $event['ecommerce']['paid_value'] );
		self::assertStringNotContainsString( 'buyer@example.test', (string) wp_json_encode( $event ) );
		self::assertStringNotContainsString( '+49123456', (string) wp_json_encode( $event ) );
	}

	public function test_real_partial_and_shipping_only_refunds_keep_merchandise_value_exact(): void {
		$order    = $this->create_paid_order();
		$items    = $order->get_items( 'line_item' );
		$item_id  = (int) array_key_first( $items );
		$refund   = wc_create_refund(
			array(
				'order_id'     => $order->get_id(),
				'amount'       => 4.5,
				'reason'       => 'Partial test refund',
				'restock_items' => false,
				'line_items'   => array(
					$item_id => array(
						'qty'          => 1,
						'refund_total' => 3.5,
						'refund_tax'   => array(),
					),
				),
			)
		);
		self::assertInstanceOf( WC_Order_Refund::class, $refund );
		$refund->set_date_created( '2024-03-04 05:06:07' );
		$refund->save();

		$event = OrderEventFactory::refund( $order, $refund );
		self::assertSame( 3.5, $event['ecommerce']['value'] );
		self::assertSame( 4.5, $event['ecommerce']['paid_value'] );
		self::assertCount( 1, $event['ecommerce']['items'] );
		self::assertSame( '2024-03-04T05:06:07+00:00', $event['occurred_at'] );

		$shipping_only = wc_create_refund(
			array(
				'order_id'     => $order->get_id(),
				'amount'       => 2.0,
				'reason'       => 'Shipping-only test refund',
				'restock_items' => false,
			)
		);
		self::assertInstanceOf( WC_Order_Refund::class, $shipping_only );
		$shipping_event = OrderEventFactory::refund( $order, $shipping_only );
		self::assertSame( 0.0, $shipping_event['ecommerce']['value'] );
		self::assertSame( 2.0, $shipping_event['ecommerce']['paid_value'] );
		self::assertSame( array(), $shipping_event['ecommerce']['items'] );
	}

	public function test_real_full_refund_keeps_paid_parent_eligible_and_uses_only_merchandise_lines(): void {
		$order   = $this->create_paid_order();
		$items   = $order->get_items( 'line_item' );
		$item_id = (int) array_key_first( $items );
		$refund  = wc_create_refund(
			array(
				'order_id'     => $order->get_id(),
				'amount'       => 14.99,
				'reason'       => 'Full test refund',
				'restock_items' => false,
				'line_items'   => array(
					$item_id => array(
						'qty'          => 3,
						'refund_total' => 10.0,
						'refund_tax'   => array(),
					),
				),
			)
		);
		self::assertInstanceOf( WC_Order_Refund::class, $refund );
		$order = wc_get_order( $order->get_id() );
		self::assertInstanceOf( WC_Order::class, $order );
		self::assertSame( 'refunded', $order->get_status() );
		self::assertTrue( OrderDispatcher::qualifies( $order ) );
		OrderDispatcher::maybe_schedule_refund( $order->get_id(), $refund->get_id() );
		$refund = wc_get_order( $refund->get_id() );
		self::assertInstanceOf( WC_Order_Refund::class, $refund );
		self::assertSame( 'scheduled', $refund->get_meta( OrderDispatcher::REFUND_STATE, true ) );
		self::assertTrue(
			as_has_scheduled_action(
				'kdconsent_commerce_process_refund',
				array( $order->get_id(), $refund->get_id(), 0 ),
				OrderDispatcher::GROUP
			)
		);

		$event = OrderEventFactory::refund( $order, $refund );
		self::assertSame( 10.0, $event['ecommerce']['value'] );
		self::assertSame( 14.99, $event['ecommerce']['paid_value'] );
		self::assertSame( 3, $event['ecommerce']['items'][0]['quantity'] );
	}

	public function test_real_amount_only_product_refund_preserves_affected_item_and_value(): void {
		$order   = $this->create_paid_order();
		$items   = $order->get_items( 'line_item' );
		$item_id = (int) array_key_first( $items );
		$refund  = wc_create_refund(
			array(
				'order_id'     => $order->get_id(),
				'amount'       => 3.5,
				'reason'       => 'Amount-only product refund',
				'restock_items' => false,
				'line_items'   => array(
					$item_id => array(
						'qty'          => 0,
						'refund_total' => 3.5,
						'refund_tax'   => array(),
					),
				),
			)
		);
		self::assertInstanceOf( WC_Order_Refund::class, $refund );

		$event = OrderEventFactory::refund( $order, $refund );
		self::assertSame( 3.5, $event['ecommerce']['value'] );
		self::assertSame( 3.5, $event['ecommerce']['paid_value'] );
		self::assertCount( 1, $event['ecommerce']['items'] );
		self::assertSame( 1, $event['ecommerce']['items'][0]['quantity'] );
		self::assertSame( 3.5, $event['ecommerce']['items'][0]['price'] );
	}

	public function test_action_scheduler_deduplicates_paid_hooks_and_debug_delivery_is_terminal(): void {
		$order = $this->create_paid_order();
		add_filter( 'kdconsent_runtime_mode', static fn(): string => 'debug' );

		OrderDispatcher::maybe_schedule_purchase( $order->get_id() );
		OrderDispatcher::maybe_schedule_purchase_for_status( $order->get_id(), 'pending', 'processing', $order );
		$order = wc_get_order( $order->get_id() );
		self::assertInstanceOf( WC_Order::class, $order );
		self::assertSame( 'scheduled', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertTrue(
			as_has_scheduled_action(
				'kdconsent_commerce_process_purchase',
				array( $order->get_id(), 0 ),
				OrderDispatcher::GROUP
			)
		);

		OrderDispatcher::deliver_purchase( $order->get_id(), 0 );
		$order = wc_get_order( $order->get_id() );
		self::assertInstanceOf( WC_Order::class, $order );
		self::assertSame( 'debug-delivered', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		OrderDispatcher::deliver_purchase( $order->get_id(), 0 );
		$order = wc_get_order( $order->get_id() );
		self::assertInstanceOf( WC_Order::class, $order );
		self::assertSame( 'debug-delivered', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
	}

	public function test_non_web_order_and_site_filter_never_schedule_purchase(): void {
		$order = $this->create_paid_order( 'admin' );
		OrderDispatcher::maybe_schedule_purchase( $order->get_id() );
		self::assertSame( '', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );

		add_filter( 'kdconsent_commerce_order_qualifies', static fn(): bool => false );
		$web_order = $this->create_paid_order();
		OrderDispatcher::maybe_schedule_purchase( $web_order->get_id() );
		self::assertSame( '', $web_order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
	}

	private function create_paid_order( string $created_via = 'checkout' ): WC_Order {
		$product = new WC_Product_Simple();
		$product->set_name( 'Integration widget' );
		$product->set_regular_price( '5.00' );
		$product->set_price( '5.00' );
		$product->set_sku( 'INTEGRATION-' . wp_generate_uuid4() );
		$product->save();

		$order = wc_create_order( array( 'created_via' => $created_via ) );
		self::assertInstanceOf( WC_Order::class, $order );
		$item = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 3 );
		$item->set_subtotal( 15.0 );
		$item->set_total( 10.0 );
		$order->add_item( $item );
		$order->set_billing_email( 'buyer@example.test' );
		$order->set_billing_phone( '+49123456' );
		$order->set_total( 14.99 );
		$order->set_status( 'processing' );
		$order->set_date_paid( '2024-02-03 04:05:06' );
		$order->update_meta_data(
			OrderDispatcher::CONSENT_META,
			wp_json_encode(
				array(
					'essential' => true,
					'marketing' => false,
				)
			)
		);
		$order->save();

		return $order;
	}
}
