<?php
/**
 * Server commerce contract tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Commerce\ConsentSnapshot;
use KatsarovDesign\ConsentBanner\Commerce\DeliveryConfirmation;
use KatsarovDesign\ConsentBanner\Commerce\EventRedactor;
use KatsarovDesign\ConsentBanner\Commerce\OrderDispatcher;
use KatsarovDesign\ConsentBanner\Commerce\OrderEventFactory;
use PHPUnit\Framework\TestCase;

final class ServerCommerceTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters']           = array();
		$GLOBALS['kdconsent_test_actions']           = array();
		$GLOBALS['kdconsent_test_orders']            = array();
		$GLOBALS['kdconsent_test_scheduled_actions'] = array();
		$GLOBALS['kdconsent_test_action_store']       = array();
		$GLOBALS['kdconsent_test_renewal_order_ids']  = array();
		unset(
			$GLOBALS['kdconsent_test_enqueue_callback'],
			$GLOBALS['kdconsent_test_schedule_callback'],
			$GLOBALS['kdconsent_test_has_scheduled_callback']
		);
		add_filter( 'kdconsent_commerce_enabled', '__return_true' );
		OrderDispatcher::register();
	}

	public function test_purchase_event_uses_exact_values_original_timestamp_and_positive_list(): void {
		$order = $this->paid_order();
		$order->update_meta_data(
			OrderDispatcher::CONSENT_META,
			wp_json_encode(
				array(
					'preferences' => false,
					'analytics'   => false,
					'marketing'   => true,
				)
			)
		);
		$order->update_meta_data( '_gclid', 'private-click-id' );
		$order->update_meta_data( '_wc_order_attribution_utm_source', 'not-a-click-id' );
		add_filter(
			'kdconsent_services',
			static fn(): array => array(
				array(
					'id'      => 'google_ads',
					'name'    => 'Google Ads',
					'purpose' => 'marketing',
				),
				array(
					'id'      => 'analytics_vendor',
					'name'    => 'Analytics',
					'purpose' => 'analytics',
				),
			)
		);

		$filter_saw_private_data = null;
		add_filter(
			'kdconsent_commerce_event',
			static function ( array $event ) use ( &$filter_saw_private_data ): array {
				$encoded = wp_json_encode( $event );
				$filter_saw_private_data = str_contains( (string) $encoded, 'buyer@example.test' );
				$event['billing_email'] = 'injected@example.test';
				$event['event_id'] = 'purchase:999';
				$event['consent']['analytics'] = true;
				$event['ecommerce']['value'] = 999.0;
				$event['ecommerce']['items'][0]['item_name'] = 'leak@example.test';
				$event['ecommerce']['address'] = 'Private street';
				$event['planned_destinations'][] = 'unregistered_vendor';
				return $event;
			}
		);

		$event = OrderEventFactory::purchase( $order );

		self::assertFalse( $filter_saw_private_data );
		self::assertSame( 'purchase:101', $event['event_id'] );
		self::assertSame( '2024-02-03T04:05:06+00:00', $event['occurred_at'] );
		self::assertSame( 10.0, $event['ecommerce']['value'] );
		self::assertSame( 14.99, $event['ecommerce']['paid_value'] );
		self::assertSame( 10 / 3, $event['ecommerce']['items'][0]['price'] );
		self::assertSame( 3, $event['ecommerce']['items'][0]['quantity'] );
		self::assertSame( '12', $event['ecommerce']['items'][0]['item_id'] );
		self::assertTrue( $event['ecommerce']['has_email'] );
		self::assertTrue( $event['ecommerce']['has_phone'] );
		self::assertTrue( $event['ecommerce']['has_click_id'] );
		self::assertFalse( $event['consent']['analytics'] );
		self::assertSame( array( 'google_ads' ), $event['planned_destinations'] );
		self::assertStringNotContainsString( 'buyer@example.test', (string) wp_json_encode( $event ) );
		self::assertStringNotContainsString( 'injected@example.test', (string) wp_json_encode( $event ) );
		self::assertStringNotContainsString( 'Private street', (string) wp_json_encode( $event ) );
		self::assertStringNotContainsString( 'leak@example.test', (string) wp_json_encode( $event ) );
		self::assertStringNotContainsString( 'private-click-id', (string) wp_json_encode( $event ) );
	}

	public function test_utm_metadata_alone_is_not_a_click_identifier(): void {
		$order = $this->paid_order();
		$order->update_meta_data( '_wc_order_attribution_utm_source', 'google' );
		$order->update_meta_data( '_wc_order_attribution_source_type', 'utm' );

		self::assertFalse( OrderEventFactory::purchase( $order )['ecommerce']['has_click_id'] );
	}

	public function test_missing_paid_date_uses_order_creation_time_not_mutable_modified_time(): void {
		$order = new WC_Order(
			110,
			'checkout',
			'processing',
			10.0,
			0.0,
			0.0,
			'EUR',
			'',
			'',
			null,
			new DateTimeImmutable( '2024-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2024-02-03T04:05:06+00:00' )
		);

		self::assertSame( '2024-01-01T00:00:00+00:00', OrderEventFactory::purchase( $order )['occurred_at'] );
	}

	public function test_terminal_purchase_and_refund_states_are_monotonic_after_disqualification(): void {
		$order  = $this->paid_order( 'admin' );
		$refund = new WC_Order_Refund( 201, 2.0, 999, new DateTimeImmutable( '2024-03-04T05:06:07+00:00' ) );
		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'delivered' );
		$refund->update_meta_data( OrderDispatcher::REFUND_STATE, 'debug-delivered' );
		$GLOBALS['kdconsent_test_orders'] = array(
			101 => $order,
			201 => $refund,
		);

		OrderDispatcher::deliver_purchase( 101, 0 );
		OrderDispatcher::deliver_refund( 101, 201, 0 );

		self::assertSame( 'delivered', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertSame( 'debug-delivered', $refund->get_meta( OrderDispatcher::REFUND_STATE, true ) );
	}

	public function test_refunds_use_actual_lines_amount_parent_and_refund_timestamp(): void {
		$order = $this->paid_order();
		$refund = new WC_Order_Refund( 201, 4.5, 101, new DateTimeImmutable( '2024-03-04T05:06:07+00:00' ) );
		$refund->set_items(
			array(
				new WC_Order_Item_Product( 11, 12, -1, -3.5, 'Refunded widget', new WC_Product( 12, 'VAR-12', 'Widget', 5.0 ) ),
			)
		);

		$event = OrderEventFactory::refund( $order, $refund );
		self::assertSame( 'refund:101:201', $event['event_id'] );
		self::assertSame( '2024-03-04T05:06:07+00:00', $event['occurred_at'] );
		self::assertSame( 3.5, $event['ecommerce']['value'] );
		self::assertSame( 4.5, $event['ecommerce']['paid_value'] );
		self::assertSame( '12', $event['ecommerce']['items'][0]['item_id'] );

		$shipping_only = new WC_Order_Refund( 202, 2.5, 101, new DateTimeImmutable( '2024-03-05T00:00:00+00:00' ) );
		$shipping_event = OrderEventFactory::refund( $order, $shipping_only );
		self::assertSame( 0.0, $shipping_event['ecommerce']['value'] );
		self::assertSame( 2.5, $shipping_event['ecommerce']['paid_value'] );
		self::assertSame( array(), $shipping_event['ecommerce']['items'] );

		$amount_only = new WC_Order_Refund( 203, 3.5, 101, new DateTimeImmutable( '2024-03-06T00:00:00+00:00' ) );
		$amount_only->set_items(
			array(
				new WC_Order_Item_Product( 11, 12, 0, -3.5, 'Amount-only widget refund', new WC_Product( 12, 'VAR-12', 'Widget', 5.0 ) ),
			)
		);
		$amount_event = OrderEventFactory::refund( $order, $amount_only );
		self::assertSame( 3.5, $amount_event['ecommerce']['value'] );
		self::assertSame( 3.5, $amount_event['ecommerce']['paid_value'] );
		self::assertSame( 1, $amount_event['ecommerce']['items'][0]['quantity'] );
		self::assertSame( 3.5, $amount_event['ecommerce']['items'][0]['price'] );
	}

	public function test_consent_capture_is_strict_and_store_api_persists_metadata(): void {
		self::assertSame(
			array(
				'essential'   => true,
				'preferences' => false,
				'analytics'   => false,
				'marketing'   => true,
			),
			ConsentSnapshot::normalize(
				array(
					'preferences' => 'false',
					'analytics'   => 0,
					'marketing'   => '1',
				)
			)
		);

		$order = $this->paid_order();
		OrderDispatcher::capture_checkout_consent( $order );
		self::assertSame( 0, $order->save_meta_count );
		self::assertNotSame( '', $order->get_meta( OrderDispatcher::CONSENT_META, true ) );

		OrderDispatcher::capture_store_api_consent( $order );
		self::assertSame( 1, $order->save_meta_count );
	}

	public function test_qualification_defaults_and_filter_only_allow_exclusions(): void {
		self::assertTrue( OrderDispatcher::qualifies( $this->paid_order() ) );
		self::assertTrue( OrderDispatcher::qualifies( $this->paid_order( 'store-api' ) ) );
		self::assertFalse( OrderDispatcher::qualifies( $this->paid_order( 'admin' ) ) );
		self::assertFalse( OrderDispatcher::qualifies( $this->paid_order( 'import' ) ) );
		$GLOBALS['kdconsent_test_renewal_order_ids'][] = 101;
		self::assertFalse( OrderDispatcher::qualifies( $this->paid_order() ) );
		$GLOBALS['kdconsent_test_renewal_order_ids'] = array();

		add_filter( 'kdconsent_commerce_order_qualifies', static fn(): bool => false );
		self::assertFalse( OrderDispatcher::qualifies( $this->paid_order() ) );
		self::assertFalse( OrderDispatcher::qualifies( $this->paid_order( 'admin' ) ) );
	}

	public function test_scheduler_is_idempotent_recovers_stale_state_and_reconciles_zero_ids(): void {
		$order = $this->paid_order();
		$GLOBALS['kdconsent_test_orders'][101] = $order;

		OrderDispatcher::maybe_schedule_purchase( 101 );
		self::assertSame( 'scheduled', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertCount( 1, $GLOBALS['kdconsent_test_scheduled_actions'] );
		OrderDispatcher::maybe_schedule_purchase( 101 );
		self::assertCount( 1, $GLOBALS['kdconsent_test_scheduled_actions'] );

		$GLOBALS['kdconsent_test_scheduled_actions'] = array();
		OrderDispatcher::maybe_schedule_purchase( 101 );
		self::assertCount( 1, $GLOBALS['kdconsent_test_scheduled_actions'] );

		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'scheduled' );
		$GLOBALS['kdconsent_test_scheduled_actions'] = array();
		$GLOBALS['kdconsent_test_enqueue_callback'] = static fn(): int => 0;
		$GLOBALS['kdconsent_test_has_scheduled_callback'] = static fn(): bool => false;
		OrderDispatcher::maybe_schedule_purchase( 101 );
		self::assertSame( '', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );

		$GLOBALS['kdconsent_test_has_scheduled_callback'] = static fn(): bool => true;
		OrderDispatcher::maybe_schedule_purchase( 101 );
		self::assertSame( 'scheduled', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
	}

	public function test_synchronous_enqueue_execution_cannot_overwrite_terminal_state(): void {
		$order = $this->paid_order();
		$GLOBALS['kdconsent_test_orders'][101] = $order;
		add_filter( 'kdconsent_runtime_mode', static fn(): string => 'debug' );
		$live_transport_calls = 0;
		add_action(
			'kdconsent_commerce_deliver_purchase',
			static function () use ( &$live_transport_calls ): void {
				++$live_transport_calls;
			}
		);
		$previous_log = ini_get( 'error_log' );
		$debug_log    = tempnam( sys_get_temp_dir(), 'kdconsent-debug-' );
		ini_set( 'error_log', (string) $debug_log );
		$GLOBALS['kdconsent_test_enqueue_callback'] = static function (): int {
			OrderDispatcher::deliver_purchase( 101, 0 );
			return 99;
		};

		OrderDispatcher::maybe_schedule_purchase( 101 );
		self::assertSame( 'debug-delivered', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertSame( 0, $live_transport_calls );
		$save_count = $order->save_count;
		$log_size   = filesize( (string) $debug_log );
		OrderDispatcher::deliver_purchase( 101, 0 );
		clearstatcache( true, (string) $debug_log );
		self::assertSame( $save_count, $order->save_count );
		self::assertSame( $log_size, filesize( (string) $debug_log ) );
		ini_set( 'error_log', (string) $previous_log );
		unlink( (string) $debug_log );
	}

	public function test_stale_concurrent_attempts_cannot_overwrite_or_clear_terminal_state(): void {
		$stale_order = $this->paid_order();
		$stale_order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'scheduled' );
		$stored_order = $this->paid_order();
		$stored_order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'delivered' );
		$GLOBALS['kdconsent_test_orders'][101] = $stored_order;
		$GLOBALS['kdconsent_test_enqueue_callback'] = static fn(): int => 0;
		$GLOBALS['kdconsent_test_has_scheduled_callback'] = static fn(): bool => false;

		OrderDispatcher::maybe_schedule_purchase_for_status( 101, 'pending', 'processing', $stale_order );
		self::assertSame( 'delivered', $stored_order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );

		$active_order = $this->paid_order();
		$GLOBALS['kdconsent_test_orders'][101] = $active_order;
		add_action(
			'kdconsent_commerce_deliver_purchase',
			static function (): void {
				$concurrently_delivered = new WC_Order(
					101,
					'checkout',
					'processing',
					14.99,
					3.0,
					1.99,
					'EUR',
					'buyer@example.test',
					'+49123456',
					new DateTimeImmutable( '2024-02-03T04:05:06+00:00' )
				);
				$concurrently_delivered->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'delivered' );
				$GLOBALS['kdconsent_test_orders'][101] = $concurrently_delivered;
			},
			10,
			2
		);

		OrderDispatcher::deliver_purchase( 101, 0 );
		self::assertSame(
			'delivered',
			$GLOBALS['kdconsent_test_orders'][101]->get_meta( OrderDispatcher::PURCHASE_STATE, true )
		);
	}

	public function test_live_delivery_requires_explicit_confirmation_and_retries_failures(): void {
		$order = $this->paid_order();
		$GLOBALS['kdconsent_test_orders'][101] = $order;

		OrderDispatcher::deliver_purchase( 101, 0 );
		self::assertSame( 'scheduled', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertCount( 1, $GLOBALS['kdconsent_test_scheduled_actions'] );
		OrderDispatcher::maybe_schedule_purchase( 101 );
		self::assertCount( 1, $GLOBALS['kdconsent_test_scheduled_actions'] );

		$GLOBALS['kdconsent_test_scheduled_actions'] = array();
		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'transport-pending' );
		add_action( 'kdconsent_commerce_deliver_purchase', static function ( array $event, DeliveryConfirmation $confirmation ): void {}, 10, 2 );
		OrderDispatcher::deliver_purchase( 101, 1 );
		self::assertSame( 'scheduled', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );

		$GLOBALS['kdconsent_test_actions']['kdconsent_commerce_deliver_purchase'] = array();
		$GLOBALS['kdconsent_test_scheduled_actions'] = array();
		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'transport-pending' );
		add_action(
			'kdconsent_commerce_deliver_purchase',
			static function ( array $event, DeliveryConfirmation $confirmation ): void {
				$confirmation->confirm();
			},
			10,
			2
		);
		OrderDispatcher::deliver_purchase( 101, 2 );
		self::assertSame( 'delivered', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertSame( array(), $GLOBALS['kdconsent_test_scheduled_actions'] );

		$GLOBALS['kdconsent_test_actions']['kdconsent_commerce_deliver_purchase'] = array();
		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'transport-pending' );
		add_action(
			'kdconsent_commerce_deliver_purchase',
			static function (): void {
				throw new RuntimeException( 'Transport failed' );
			},
			10,
			2
		);
		try {
			OrderDispatcher::deliver_purchase( 101, 0 );
			self::fail( 'Expected transport failure.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Transport failed', $exception->getMessage() );
		}
		self::assertSame( 'scheduled', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertNotEmpty( $GLOBALS['kdconsent_test_scheduled_actions'] );
	}

	public function test_retry_cap_keeps_unconfirmed_live_event_pending(): void {
		$order = $this->paid_order();
		$GLOBALS['kdconsent_test_orders'][101] = $order;

		OrderDispatcher::deliver_purchase( 101, 3 );

		self::assertSame( 'transport-pending', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertSame( array(), $GLOBALS['kdconsent_test_scheduled_actions'] );
	}

	public function test_scheduler_exception_never_creates_a_false_scheduled_state(): void {
		$order = $this->paid_order();
		$GLOBALS['kdconsent_test_orders'][101] = $order;
		$GLOBALS['kdconsent_test_enqueue_callback'] = static function (): int {
			throw new RuntimeException( 'Scheduler storage failed' );
		};

		OrderDispatcher::maybe_schedule_purchase( 101 );

		self::assertSame( '', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertSame( array(), $GLOBALS['kdconsent_test_scheduled_actions'] );
	}

	public function test_failed_and_interrupted_actions_follow_the_capped_retry_path(): void {
		$order = $this->paid_order();
		$GLOBALS['kdconsent_test_orders'][101] = $order;
		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'processing' );
		$GLOBALS['kdconsent_test_action_store'][50] = new KDConsent_Test_Action(
			'kdconsent_commerce_process_purchase',
			array( 101, 0 ),
			OrderDispatcher::GROUP
		);

		OrderDispatcher::retry_failed_action( 50 );
		self::assertSame( 'scheduled', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertNotEmpty( $GLOBALS['kdconsent_test_scheduled_actions'] );

		$GLOBALS['kdconsent_test_scheduled_actions'] = array();
		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'processing' );
		$GLOBALS['kdconsent_test_action_store'][51] = new KDConsent_Test_Action(
			'kdconsent_commerce_process_purchase',
			array( 101, 3 ),
			OrderDispatcher::GROUP
		);
		OrderDispatcher::retry_failed_action( 51 );
		self::assertSame( 'transport-pending', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		self::assertSame( array(), $GLOBALS['kdconsent_test_scheduled_actions'] );

		$order->update_meta_data( OrderDispatcher::PURCHASE_STATE, 'processing' );
		$GLOBALS['kdconsent_test_action_store'][52] = new KDConsent_Test_Action(
			'kdconsent_commerce_process_purchase',
			array( 101, 0 ),
			'other-group'
		);
		OrderDispatcher::retry_failed_action( 52 );
		self::assertSame( 'processing', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
	}

	public function test_refund_dispatch_rejects_mismatched_parent(): void {
		$order  = $this->paid_order();
		$refund = new WC_Order_Refund( 201, 2.0, 999, new DateTimeImmutable( '2024-03-04T05:06:07+00:00' ) );
		$GLOBALS['kdconsent_test_orders'] = array(
			101 => $order,
			201 => $refund,
		);

		OrderDispatcher::maybe_schedule_refund( 101, 201 );
		self::assertSame( '', $refund->get_meta( OrderDispatcher::REFUND_STATE, true ) );
		self::assertSame( array(), $GLOBALS['kdconsent_test_scheduled_actions'] );
	}

	public function test_refund_scheduling_is_parent_checked_and_idempotent(): void {
		$order  = $this->paid_order();
		$refund = new WC_Order_Refund( 201, 2.0, 101, new DateTimeImmutable( '2024-03-04T05:06:07+00:00' ) );
		$GLOBALS['kdconsent_test_orders'] = array(
			101 => $order,
			201 => $refund,
		);

		OrderDispatcher::maybe_schedule_refund( 101, 201 );
		OrderDispatcher::maybe_schedule_refund( 101, 201 );

		self::assertSame( 'scheduled', $refund->get_meta( OrderDispatcher::REFUND_STATE, true ) );
		self::assertCount( 1, $GLOBALS['kdconsent_test_scheduled_actions'] );
	}

	public function test_failed_and_cancelled_orders_never_schedule_even_with_stale_paid_dates(): void {
		foreach ( array( 'failed', 'cancelled' ) as $status ) {
			$order = new WC_Order(
				300 + count( $GLOBALS['kdconsent_test_orders'] ),
				'checkout',
				$status,
				10.0,
				0.0,
				0.0,
				'EUR',
				'',
				'',
				new DateTimeImmutable( '2024-02-03T04:05:06+00:00' )
			);
			$GLOBALS['kdconsent_test_orders'][ $order->get_id() ] = $order;
			OrderDispatcher::maybe_schedule_purchase( $order->get_id() );
			self::assertSame( '', $order->get_meta( OrderDispatcher::PURCHASE_STATE, true ) );
		}

		self::assertSame( array(), $GLOBALS['kdconsent_test_scheduled_actions'] );
	}

	public function test_redactor_never_exposes_forbidden_fields(): void {
		$event = EventRedactor::event(
			array(
				'schema_version' => 1,
				'event_name'     => 'purchase',
				'event_id'       => 'purchase:5',
				'occurred_at'    => '2024-01-01T00:00:00+00:00',
				'email'          => 'secret@example.test',
				'user_agent'     => 'private-agent',
				'ip'             => '192.0.2.1',
				'ecommerce'      => array(
					'transaction_id' => 5,
					'gclid'          => 'private-click',
					'_ga'            => 'private-cookie',
					'items'          => array(),
				),
			)
		);
		$encoded = (string) wp_json_encode( $event );

		self::assertStringNotContainsString( 'secret@example.test', $encoded );
		self::assertStringNotContainsString( 'private-agent', $encoded );
		self::assertStringNotContainsString( '192.0.2.1', $encoded );
		self::assertStringNotContainsString( 'private-click', $encoded );
		self::assertStringNotContainsString( 'private-cookie', $encoded );
	}

	private function paid_order( string $created_via = 'checkout' ): WC_Order {
		$order = new WC_Order(
			101,
			$created_via,
			'processing',
			14.99,
			3.0,
			1.99,
			'EUR',
			'buyer@example.test',
			'+49123456',
			new DateTimeImmutable( '2024-02-03T04:05:06+00:00' ),
			new DateTimeImmutable( '2024-02-01T00:00:00+00:00' )
		);
		$order->set_items(
			array(
				new WC_Order_Item_Product( 11, 12, 3, 10.0, 'Widget', new WC_Product( 12, 'VAR-12', 'Widget', 5.0 ) ),
			)
		);

		return $order;
	}
}
