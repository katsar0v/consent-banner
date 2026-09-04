<?php
/**
 * Idempotent paid-order and refund scheduling and delivery.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

use KatsarovDesign\ConsentBanner\Service\RuntimeMode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OrderDispatcher {
	public const GROUP          = 'kdconsent-commerce';
	public const CONSENT_META   = '_kdconsent_commerce_consent_snapshot';
	public const PURCHASE_STATE = '_kdconsent_commerce_purchase_state';
	public const REFUND_STATE   = '_kdconsent_commerce_refund_state';

	private const PROCESS_PURCHASE = 'kdconsent_commerce_process_purchase';
	private const PROCESS_REFUND   = 'kdconsent_commerce_process_refund';
	private const MAX_RETRY_ATTEMPTS = 3;

	public static function register(): void {
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'capture_checkout_consent' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( self::class, 'capture_store_api_consent' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( self::class, 'maybe_schedule_purchase' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'maybe_schedule_purchase_for_status' ), 20, 4 );
		add_action( 'woocommerce_order_refunded', array( self::class, 'maybe_schedule_refund' ), 20, 2 );
		add_action( self::PROCESS_PURCHASE, array( self::class, 'deliver_purchase' ), 10, 2 );
		add_action( self::PROCESS_REFUND, array( self::class, 'deliver_refund' ), 10, 3 );
		add_action( 'action_scheduler_failed_execution', array( self::class, 'retry_failed_action' ), 20, 1 );
		add_action( 'action_scheduler_failed_action', array( self::class, 'retry_failed_action' ), 20, 1 );
		add_action( 'action_scheduler_unexpected_shutdown', array( self::class, 'retry_failed_action' ), 20, 1 );
	}

	public static function capture_checkout_consent( mixed $order ): void {
		if ( ! Module::is_enabled() || ! $order instanceof \WC_Order ) {
			return;
		}

		$order->update_meta_data( self::CONSENT_META, wp_json_encode( ConsentSnapshot::current() ) );
	}

	/**
	 * Store API orders may already exist in persistent storage when this hook runs.
	 */
	public static function capture_store_api_consent( mixed $order ): void {
		self::capture_checkout_consent( $order );
		if ( Module::is_enabled() && $order instanceof \WC_Order ) {
			$order->save_meta_data();
		}
	}

	/** @param int|string $order_id Order ID. */
	public static function maybe_schedule_purchase( mixed $order_id ): void {
		if ( ! Module::is_enabled() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );
		if ( $order instanceof \WC_Order ) {
			self::schedule_purchase( $order );
		}
	}

	/**
	 * @param int|string $order_id Order ID.
	 * @param string     $from Previous status.
	 * @param string     $to New status.
	 * @param mixed      $order Order object.
	 */
	public static function maybe_schedule_purchase_for_status( mixed $order_id, string $from, string $to, mixed $order ): void {
		$paid_statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		if ( Module::is_enabled() && $order instanceof \WC_Order && in_array( $to, $paid_statuses, true ) ) {
			self::schedule_purchase( $order );
		}
	}

	/** @param int|string $order_id Parent order ID. @param int|string $refund_id Refund ID. */
	public static function maybe_schedule_refund( mixed $order_id, mixed $refund_id ): void {
		if ( ! Module::is_enabled() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order  = wc_get_order( absint( $order_id ) );
		$refund = wc_get_order( absint( $refund_id ) );
		if ( $order instanceof \WC_Order
			&& $refund instanceof \WC_Order_Refund
			&& $refund->get_parent_id() === $order->get_id()
			&& self::qualifies( $order )
			&& self::was_paid( $order )
		) {
			self::schedule( self::PROCESS_REFUND, array( $order->get_id(), $refund->get_id(), 0 ), $refund, self::REFUND_STATE );
		}
	}

	/** @param int|string $order_id Order ID. */
	public static function deliver_purchase( mixed $order_id, mixed $attempt = 0 ): void {
		if ( ! Module::is_enabled() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );
		if ( $order instanceof \WC_Order && self::is_terminal( $order, self::PURCHASE_STATE ) ) {
			return;
		}
		if ( ! $order instanceof \WC_Order || ! self::qualifies( $order ) || ! self::was_paid( $order ) ) {
			self::clear_state( $order, self::PURCHASE_STATE );
			return;
		}

		self::deliver(
			OrderEventFactory::purchase( $order ),
			$order,
			self::PURCHASE_STATE,
			'kdconsent_commerce_deliver_purchase',
			self::PROCESS_PURCHASE,
			array( $order->get_id() ),
			absint( $attempt )
		);
	}

	/** @param int|string $order_id Parent order ID. @param int|string $refund_id Refund ID. */
	public static function deliver_refund( mixed $order_id, mixed $refund_id, mixed $attempt = 0 ): void {
		if ( ! Module::is_enabled() || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order  = wc_get_order( absint( $order_id ) );
		$refund = wc_get_order( absint( $refund_id ) );
		if ( $refund instanceof \WC_Order_Refund && self::is_terminal( $refund, self::REFUND_STATE ) ) {
			return;
		}
		if ( ! $order instanceof \WC_Order
			|| ! $refund instanceof \WC_Order_Refund
			|| $refund->get_parent_id() !== $order->get_id()
			|| ! self::qualifies( $order )
			|| ! self::was_paid( $order )
		) {
			self::clear_state( $refund, self::REFUND_STATE );
			return;
		}

		self::deliver(
			OrderEventFactory::refund( $order, $refund ),
			$refund,
			self::REFUND_STATE,
			'kdconsent_commerce_deliver_refund',
			self::PROCESS_REFUND,
			array( $order->get_id(), $refund->get_id() ),
			absint( $attempt )
		);
	}

	/**
	 * Requeue our own failed or fatally interrupted Action Scheduler jobs.
	 *
	 * @param int|string $action_id Failed Action Scheduler ID.
	 */
	public static function retry_failed_action( mixed $action_id ): void {
		if ( ! Module::is_enabled() || ! class_exists( 'ActionScheduler' ) || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		try {
			$action = \ActionScheduler::store()->fetch_action( absint( $action_id ) );
		} catch ( \Throwable ) {
			return;
		}
		if ( ! is_object( $action )
			|| ! method_exists( $action, 'get_hook' )
			|| ! method_exists( $action, 'get_args' )
			|| ! method_exists( $action, 'get_group' )
			|| self::GROUP !== $action->get_group()
		) {
			return;
		}

		$hook = (string) $action->get_hook();
		$args = $action->get_args();
		if ( ! is_array( $args ) ) {
			return;
		}

		if ( self::PROCESS_PURCHASE === $hook && isset( $args[0] ) ) {
			$order = wc_get_order( absint( $args[0] ) );
			if ( $order instanceof \WC_Order ) {
				if ( ! self::queue_retry( $hook, array( $order->get_id() ), absint( $args[1] ?? 0 ), $order, self::PURCHASE_STATE ) ) {
					self::keep_pending( $order, self::PURCHASE_STATE );
				}
			}
		}
		if ( self::PROCESS_REFUND === $hook && isset( $args[0], $args[1] ) ) {
			$refund = wc_get_order( absint( $args[1] ) );
			if ( $refund instanceof \WC_Order_Refund && $refund->get_parent_id() === absint( $args[0] ) ) {
				if ( ! self::queue_retry( $hook, array( absint( $args[0] ), $refund->get_id() ), absint( $args[2] ?? 0 ), $refund, self::REFUND_STATE ) ) {
					self::keep_pending( $refund, self::REFUND_STATE );
				}
			}
		}
	}

	public static function qualifies( \WC_Order $order ): bool {
		if ( ! in_array( (string) $order->get_created_via(), array( 'checkout', 'store-api' ), true ) ) {
			return false;
		}
		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
			return false;
		}

		return (bool) apply_filters( 'kdconsent_commerce_order_qualifies', true, $order );
	}

	private static function schedule_purchase( \WC_Order $order ): void {
		if ( self::qualifies( $order ) && self::was_paid( $order ) ) {
			self::schedule( self::PROCESS_PURCHASE, array( $order->get_id(), 0 ), $order, self::PURCHASE_STATE );
		}
	}

	/**
	 * Enqueue only after proving Action Scheduler is available. A zero enqueue ID
	 * is reconciled against the scheduler because it can mean either a unique
	 * duplicate or a storage failure.
	 *
	 * @param list<int>          $args Action arguments.
	 * @param \WC_Abstract_Order $entity State owner.
	 */
	private static function schedule( string $hook, array $args, \WC_Abstract_Order $entity, string $meta_key ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		$state = (string) $entity->get_meta( $meta_key, true );
		if ( in_array( $state, array( 'delivered', 'debug-delivered' ), true ) ) {
			return;
		}
		if ( in_array( $state, array( 'scheduled', 'processing', 'transport-pending' ), true ) && self::has_any_scheduled_attempt( $hook, $args ) ) {
			return;
		}

		try {
			$action_id = (int) as_enqueue_async_action( $hook, $args, self::GROUP, true );
			$scheduled = $action_id > 0 || self::has_scheduled_action( $hook, $args );
		} catch ( \Throwable ) {
			$scheduled = false;
		}

		if ( $scheduled ) {
			$entity       = self::fresh_entity( $entity );
			$latest_state = (string) $entity->get_meta( $meta_key, true );
			if ( ! in_array( $latest_state, array( 'delivered', 'debug-delivered' ), true ) ) {
				self::set_state( $entity, $meta_key, 'scheduled' );
			}
			return;
		}

		if ( in_array( $state, array( 'scheduled', 'processing' ), true ) ) {
			self::clear_state( $entity, $meta_key );
		}
	}

	/**
	 * @param array<string,mixed> $event Redacted event.
	 * @param \WC_Abstract_Order  $entity State owner.
	 * @param list<int>           $base_args Action arguments without retry count.
	 */
	private static function deliver(
		array $event,
		\WC_Abstract_Order $entity,
		string $meta_key,
		string $transport_hook,
		string $process_hook,
		array $base_args,
		int $attempt
	): void {
		$state = (string) $entity->get_meta( $meta_key, true );
		if ( in_array( $state, array( 'delivered', 'debug-delivered' ), true ) ) {
			return;
		}

		if ( ! self::set_state( $entity, $meta_key, 'processing' ) ) {
			return;
		}
		try {
			if ( RuntimeMode::is_debug() ) {
				if ( DebugTransport::deliver( $event ) ) {
					self::set_state( $entity, $meta_key, 'debug-delivered' );
					return;
				}

				self::set_state( $entity, $meta_key, 'transport-pending' );
				self::queue_retry( $process_hook, $base_args, $attempt, $entity, $meta_key );
				return;
			}

			$confirmation = new DeliveryConfirmation();
			do_action( $transport_hook, EventRedactor::event( $event ), $confirmation );
			if ( $confirmation->is_confirmed() ) {
				self::set_state( $entity, $meta_key, 'delivered' );
				return;
			}

			self::set_state( $entity, $meta_key, 'transport-pending' );
			self::queue_retry( $process_hook, $base_args, $attempt, $entity, $meta_key );
		} catch ( \Throwable $throwable ) {
			self::set_state( $entity, $meta_key, 'transport-pending' );
			self::queue_retry( $process_hook, $base_args, $attempt, $entity, $meta_key );
			throw $throwable;
		}
	}

	/**
	 * @param list<int> $base_args Action arguments without retry count.
	 */
	private static function queue_retry(
		string $hook,
		array $base_args,
		int $attempt,
		\WC_Abstract_Order $entity,
		string $meta_key
	): bool {
		$entity = self::fresh_entity( $entity );
		if ( in_array( (string) $entity->get_meta( $meta_key, true ), array( 'delivered', 'debug-delivered' ), true ) ) {
			return false;
		}
		if ( $attempt >= self::MAX_RETRY_ATTEMPTS ) {
			return false;
		}

		$next_attempt = $attempt + 1;
		$args         = array_merge( $base_args, array( $next_attempt ) );
		$delay        = MINUTE_IN_SECONDS * ( 2 ** max( 0, $attempt ) );
		$scheduled    = false;
		try {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				$action_id = (int) as_schedule_single_action( time() + $delay, $hook, $args, self::GROUP, true );
			} elseif ( function_exists( 'as_enqueue_async_action' ) ) {
				$action_id = (int) as_enqueue_async_action( $hook, $args, self::GROUP, true );
			} else {
				return false;
			}
			$scheduled = $action_id > 0 || self::has_scheduled_action( $hook, $args );
		} catch ( \Throwable ) {
			$scheduled = false;
		}

		if ( $scheduled ) {
			$entity = self::fresh_entity( $entity );
			$state  = (string) $entity->get_meta( $meta_key, true );
			if ( ! in_array( $state, array( 'delivered', 'debug-delivered' ), true ) ) {
				self::set_state( $entity, $meta_key, 'scheduled' );
			}
		}

		return $scheduled;
	}

	/** @param list<int> $args Action arguments. */
	private static function has_scheduled_action( string $hook, array $args ): bool {
		try {
			if ( function_exists( 'as_has_scheduled_action' ) ) {
				return (bool) as_has_scheduled_action( $hook, $args, self::GROUP );
			}
			if ( function_exists( 'as_next_scheduled_action' ) ) {
				return false !== as_next_scheduled_action( $hook, $args, self::GROUP );
			}
		} catch ( \Throwable ) {
			return false;
		}

		return false;
	}

	/**
	 * Find any initial or backoff attempt for the same commerce entity.
	 *
	 * @param list<int> $initial_args Initial action arguments ending in attempt zero.
	 */
	private static function has_any_scheduled_attempt( string $hook, array $initial_args ): bool {
		array_pop( $initial_args );
		for ( $attempt = 0; $attempt <= self::MAX_RETRY_ATTEMPTS; ++$attempt ) {
			if ( self::has_scheduled_action( $hook, array_merge( $initial_args, array( $attempt ) ) ) ) {
				return true;
			}
		}

		return false;
	}

	private static function was_paid( \WC_Order $order ): bool {
		if ( in_array( (string) $order->get_status(), array( 'failed', 'cancelled' ), true ) ) {
			return false;
		}

		return (bool) $order->get_date_paid() || ( method_exists( $order, 'is_paid' ) && $order->is_paid() );
	}

	private static function set_state( \WC_Abstract_Order $entity, string $meta_key, string $state ): bool {
		$entity        = self::fresh_entity( $entity );
		$current_state = (string) $entity->get_meta( $meta_key, true );
		if ( in_array( $current_state, array( 'delivered', 'debug-delivered' ), true ) ) {
			return $current_state === $state;
		}

		$entity->update_meta_data( $meta_key, $state );
		$entity->save();

		return true;
	}

	private static function clear_state( mixed $entity, string $meta_key ): void {
		if ( ! $entity instanceof \WC_Abstract_Order ) {
			return;
		}
		$entity = self::fresh_entity( $entity );
		if ( self::is_terminal( $entity, $meta_key ) ) {
			return;
		}

		$entity->delete_meta_data( $meta_key );
		$entity->save();
	}

	private static function keep_pending( \WC_Abstract_Order $entity, string $meta_key ): void {
		$state = (string) $entity->get_meta( $meta_key, true );
		if ( ! in_array( $state, array( 'delivered', 'debug-delivered' ), true ) ) {
			self::set_state( $entity, $meta_key, 'transport-pending' );
		}
	}

	private static function is_terminal( \WC_Abstract_Order $entity, string $meta_key ): bool {
		return in_array( (string) $entity->get_meta( $meta_key, true ), array( 'delivered', 'debug-delivered' ), true );
	}

	private static function fresh_entity( \WC_Abstract_Order $entity ): \WC_Abstract_Order {
		if ( function_exists( 'wc_get_order' ) ) {
			$fresh = wc_get_order( $entity->get_id() );
			if ( $fresh instanceof \WC_Abstract_Order ) {
				return $fresh;
			}
		}

		return $entity;
	}
}
