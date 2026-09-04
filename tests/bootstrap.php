<?php
/**
 * PHPUnit bootstrap.
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'KDCONSENT_PLUGIN_VERSION', 'test' );
define( 'KDCONSENT_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'KDCONSENT_PLUGIN_URL', 'https://localhost/wp-content/plugins/consent-banner/' );

$GLOBALS['kdconsent_test_options'] = array();
$GLOBALS['kdconsent_test_filters'] = array();
$GLOBALS['kdconsent_test_cache_deletions'] = array();
$GLOBALS['kdconsent_test_actions'] = array();
$GLOBALS['kdconsent_test_enqueued_scripts'] = array();
$GLOBALS['kdconsent_test_inline_scripts'] = array();
$GLOBALS['kdconsent_test_products'] = array();
$GLOBALS['kdconsent_test_page'] = array();
$GLOBALS['kdconsent_test_wc'] = (object) array( 'cart' => null );
$GLOBALS['kdconsent_test_orders'] = array();
$GLOBALS['kdconsent_test_scheduled_actions'] = array();
$GLOBALS['kdconsent_test_action_store'] = array();
$GLOBALS['kdconsent_test_renewal_order_ids'] = array();

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function __return_true(): bool {
	return true;
}

function sanitize_key( string $key ): string {
	return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function sanitize_text_field( string $text ): string {
	return trim( strip_tags( $text ) );
}

function sanitize_hex_color( mixed $color ): ?string {
	return is_string( $color ) && preg_match( '/^#[0-9a-f]{6}$/i', $color ) ? $color : null;
}

function esc_url_raw( string $url, ?array $protocols = null ): string {
	$scheme = parse_url( $url, PHP_URL_SCHEME );
	return in_array( $scheme, $protocols ?? array( 'http', 'https' ), true ) ? $url : '';
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['kdconsent_test_filters'][ $hook ][] = $callback;
	return true;
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['kdconsent_test_actions'][ $hook ][] = array( $callback, $priority, $accepted_args );
	return true;
}

function do_action( string $hook, mixed ...$args ): void {
	$callbacks = $GLOBALS['kdconsent_test_actions'][ $hook ] ?? array();
	usort( $callbacks, static fn( array $left, array $right ): int => $left[1] <=> $right[1] );
	foreach ( $callbacks as $definition ) {
		$callback      = $definition[0];
		$accepted_args = $definition[2];
		$callback( ...array_slice( $args, 0, $accepted_args ) );
	}
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
	foreach ( $GLOBALS['kdconsent_test_filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}

	return $value;
}

function get_option( string $name, mixed $default = false ): mixed {
	return $GLOBALS['kdconsent_test_options'][ $name ] ?? $default;
}

function update_option( string $name, mixed $value, bool $autoload = true ): bool {
	$GLOBALS['kdconsent_test_options'][ $name ] = $value;
	return true;
}

function add_option( string $name, mixed $value, string $deprecated = '', bool $autoload = true ): bool {
	$callback = $GLOBALS['kdconsent_test_add_option_callback'] ?? null;
	if ( is_callable( $callback ) ) {
		$result = $callback( $name, $value, $deprecated, $autoload );
		if ( is_bool( $result ) ) {
			return $result;
		}
	}

	if ( array_key_exists( $name, $GLOBALS['kdconsent_test_options'] ) ) {
		return false;
	}

	$GLOBALS['kdconsent_test_options'][ $name ] = $value;
	return true;
}

function wp_cache_delete( string $key, string $group = '' ): bool {
	$GLOBALS['kdconsent_test_cache_deletions'][] = array( $key, $group );
	return true;
}

function maybe_serialize( mixed $value ): mixed {
	return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
}

function wp_json_encode( mixed $value, int $flags = 0 ): string|false {
	return json_encode( $value, $flags );
}

function wp_generate_uuid4(): string {
	return '0198f1dd-ec40-7000-8000-000000000001';
}

function absint( mixed $value ): int {
	return abs( (int) $value );
}

function is_admin(): bool {
	return false;
}

function wp_doing_ajax(): bool {
	return false;
}

function is_feed(): bool {
	return false;
}

function wp_enqueue_script( string $handle, string $source, array $dependencies, string $version, array|bool $args ): void {
	$GLOBALS['kdconsent_test_enqueued_scripts'][ $handle ] = array( $source, $dependencies, $version, $args );
}

function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): bool {
	$GLOBALS['kdconsent_test_inline_scripts'][ $handle ][] = array( $data, $position );
	return true;
}

function wp_script_is( string $handle, string $status = 'enqueued' ): bool {
	return ! empty( $GLOBALS['kdconsent_test_registered_scripts'][ $status ][ $handle ] );
}

function is_front_page(): bool {
	return ! empty( $GLOBALS['kdconsent_test_page']['home'] );
}

function is_singular( string $type = '' ): bool {
	return 'product' === $type && ! empty( $GLOBALS['kdconsent_test_page']['product'] );
}

function is_cart(): bool {
	return ! empty( $GLOBALS['kdconsent_test_page']['cart'] );
}

function is_checkout(): bool {
	return ! empty( $GLOBALS['kdconsent_test_page']['checkout'] );
}

function is_order_received_page(): bool {
	return ! empty( $GLOBALS['kdconsent_test_page']['order_received'] );
}

function is_search(): bool {
	return ! empty( $GLOBALS['kdconsent_test_page']['search'] );
}

function is_shop(): bool {
	return ! empty( $GLOBALS['kdconsent_test_page']['shop'] );
}

function is_product_taxonomy(): bool {
	return ! empty( $GLOBALS['kdconsent_test_page']['product_taxonomy'] );
}

function get_queried_object_id(): int {
	return (int) ( $GLOBALS['kdconsent_test_page']['object_id'] ?? 0 );
}

function get_the_ID(): int {
	return (int) ( $GLOBALS['kdconsent_test_page']['object_id'] ?? 0 );
}

function get_woocommerce_currency(): string {
	return 'EUR';
}

function wc_get_price_decimals(): int {
	return 2;
}

function wc_format_decimal( mixed $value, int $decimals = 2 ): string {
	return number_format( (float) $value, $decimals, '.', '' );
}

function wc_get_price_excluding_tax( WC_Product $product ): float {
	return (float) $product->get_price();
}

function wc_get_product( int $product_id ): ?WC_Product {
	return $GLOBALS['kdconsent_test_products'][ $product_id ] ?? null;
}

function WC(): mixed {
	return $GLOBALS['kdconsent_test_wc'];
}

function wc_get_order( int $order_id ): mixed {
	return $GLOBALS['kdconsent_test_orders'][ $order_id ] ?? false;
}

/** @return list<string> */
function wc_get_is_paid_statuses(): array {
	return array( 'processing', 'completed' );
}

function wcs_order_contains_renewal( WC_Order $order ): bool {
	return in_array( $order->get_id(), $GLOBALS['kdconsent_test_renewal_order_ids'], true );
}

/** @param list<int> $args */
function as_enqueue_async_action( string $hook, array $args = array(), string $group = '', bool $unique = false ): int {
	$callback = $GLOBALS['kdconsent_test_enqueue_callback'] ?? null;
	if ( is_callable( $callback ) ) {
		return (int) $callback( $hook, $args, $group, $unique );
	}

	$id = count( $GLOBALS['kdconsent_test_scheduled_actions'] ) + 1;
	$GLOBALS['kdconsent_test_scheduled_actions'][ $id ] = array( $hook, $args, $group, 'pending' );
	return $id;
}

/** @param list<int> $args */
function as_schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '', bool $unique = false ): int {
	$callback = $GLOBALS['kdconsent_test_schedule_callback'] ?? null;
	if ( is_callable( $callback ) ) {
		return (int) $callback( $timestamp, $hook, $args, $group, $unique );
	}

	return as_enqueue_async_action( $hook, $args, $group, $unique );
}

/** @param list<int> $args */
function as_has_scheduled_action( string $hook, ?array $args = null, string $group = '' ): bool {
	$callback = $GLOBALS['kdconsent_test_has_scheduled_callback'] ?? null;
	if ( is_callable( $callback ) ) {
		return (bool) $callback( $hook, $args, $group );
	}

	foreach ( $GLOBALS['kdconsent_test_scheduled_actions'] as $action ) {
		if ( $hook === $action[0] && $args === $action[1] && $group === $action[2] && in_array( $action[3], array( 'pending', 'running' ), true ) ) {
			return true;
		}
	}

	return false;
}

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public function __construct(
			private int $id,
			private string $sku,
			private string $name,
			private float $price
		) {}

		public function get_id(): int {
			return $this->id;
		}

		public function get_sku(): string {
			return $this->sku;
		}

		public function get_name(): string {
			return $this->name;
		}

		public function get_price(): string {
			return (string) $this->price;
		}
	}
}

if ( ! class_exists( 'WC_Abstract_Order' ) ) {
	class WC_Abstract_Order {
		/** @var array<string,mixed> */
		protected array $meta = array();
		/** @var list<object> */
		protected array $items = array();
		public int $save_count = 0;
		public int $save_meta_count = 0;

		public function get_meta( string $key, bool $single = true ): mixed {
			return $this->meta[ $key ] ?? '';
		}

		public function update_meta_data( string $key, mixed $value ): void {
			$this->meta[ $key ] = $value;
		}

		public function delete_meta_data( string $key ): void {
			unset( $this->meta[ $key ] );
		}

		public function save(): int {
			++$this->save_count;
			return 1;
		}

		public function save_meta_data(): void {
			++$this->save_meta_count;
		}

		/** @return list<object> */
		public function get_items( string $type = '' ): array {
			return $this->items;
		}

		/** @param list<object> $items */
		public function set_items( array $items ): void {
			$this->items = $items;
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order extends WC_Abstract_Order {
		public function __construct(
			private int $id,
			private string $created_via = 'checkout',
			private string $status = 'processing',
			private float $total = 0.0,
			private float $shipping = 0.0,
			private float $tax = 0.0,
			private string $currency = 'EUR',
			private string $email = '',
			private string $phone = '',
			private mixed $date_paid = null,
			private mixed $date_created = null,
			private mixed $date_modified = null
		) {
			$this->date_created = $this->date_created ?? new DateTimeImmutable( '@1704067200' );
			$this->date_modified = $this->date_modified ?? $this->date_created;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_created_via(): string {
			return $this->created_via;
		}

		public function get_status(): string {
			return $this->status;
		}

		public function is_paid(): bool {
			return in_array( $this->status, wc_get_is_paid_statuses(), true );
		}

		public function get_total(): string {
			return (string) $this->total;
		}

		public function get_shipping_total(): string {
			return (string) $this->shipping;
		}

		public function get_total_tax(): string {
			return (string) $this->tax;
		}

		public function get_currency(): string {
			return $this->currency;
		}

		public function get_billing_email(): string {
			return $this->email;
		}

		public function get_billing_phone(): string {
			return $this->phone;
		}

		public function get_date_paid(): mixed {
			return $this->date_paid;
		}

		public function get_date_created(): mixed {
			return $this->date_created;
		}

		public function get_date_modified(): mixed {
			return $this->date_modified;
		}
	}
}

if ( ! class_exists( 'WC_Order_Refund' ) ) {
	class WC_Order_Refund extends WC_Order {
		public function __construct( int $id, private float $amount, private int $parent_id, mixed $date_created = null ) {
			parent::__construct( $id, 'refund', 'completed', 0.0, 0.0, 0.0, 'EUR', '', '', null, $date_created );
		}

		public function get_amount(): string {
			return (string) $this->amount;
		}

		public function get_parent_id(): int {
			return $this->parent_id;
		}
	}
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	class WC_Order_Item_Product {
		public function __construct(
			private int $product_id,
			private int $variation_id,
			private int $quantity,
			private float $total,
			private string $name,
			private ?WC_Product $product = null
		) {}

		public function get_product_id(): int {
			return $this->product_id;
		}

		public function get_variation_id(): int {
			return $this->variation_id;
		}

		public function get_quantity(): int {
			return $this->quantity;
		}

		public function get_total(): string {
			return (string) $this->total;
		}

		public function get_name(): string {
			return $this->name;
		}

		public function get_product(): ?WC_Product {
			return $this->product;
		}
	}
}

if ( ! class_exists( 'KDConsent_Test_Action' ) ) {
	class KDConsent_Test_Action {
		/** @param list<int> $args */
		public function __construct( private string $hook, private array $args, private string $group ) {}

		public function get_hook(): string {
			return $this->hook;
		}

		/** @return list<int> */
		public function get_args(): array {
			return $this->args;
		}

		public function get_group(): string {
			return $this->group;
		}
	}
}

if ( ! class_exists( 'KDConsent_Test_Action_Store' ) ) {
	class KDConsent_Test_Action_Store {
		public function fetch_action( int $action_id ): mixed {
			return $GLOBALS['kdconsent_test_action_store'][ $action_id ] ?? null;
		}
	}
}

if ( ! class_exists( 'ActionScheduler' ) ) {
	class ActionScheduler {
		public static function store(): KDConsent_Test_Action_Store {
			return new KDConsent_Test_Action_Store();
		}
	}
}

if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	class WP_HTML_Tag_Processor {
		/** @var array<string,string> */
		private array $attributes = array();

		public function __construct( private string $html ) {}

		/** @param array<string,string> $query */
		public function next_tag( array $query = array() ): bool {
			return false !== stripos( $this->html, '<a' ) && ( empty( $query['tag_name'] ) || 'A' === $query['tag_name'] );
		}

		public function set_attribute( string $name, string $value ): void {
			$this->attributes[ $name ] = $value;
		}

		public function get_updated_html(): string {
			$attributes = '';
			foreach ( $this->attributes as $name => $value ) {
				$attributes .= ' ' . $name . '="' . htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '"';
			}

			return (string) preg_replace( '/<a\b/i', '<a' . $attributes, $this->html, 1 );
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/LegacyCompat.php';
require_once dirname( __DIR__ ) . '/includes/Installer.php';
require_once dirname( __DIR__ ) . '/includes/Domain/Category.php';
require_once dirname( __DIR__ ) . '/includes/Domain/ConsentState.php';
require_once dirname( __DIR__ ) . '/includes/Repository/SettingsRepository.php';
require_once dirname( __DIR__ ) . '/includes/Service/ConsentService.php';
require_once dirname( __DIR__ ) . '/includes/Service/ServiceRegistry.php';
require_once dirname( __DIR__ ) . '/includes/Service/ConsentDefinitionFingerprint.php';
require_once dirname( __DIR__ ) . '/includes/Service/RuntimeMode.php';
require_once dirname( __DIR__ ) . '/includes/Service/SettingsTransferException.php';
require_once dirname( __DIR__ ) . '/includes/Service/SettingsTransfer.php';
require_once dirname( __DIR__ ) . '/includes/Repository/ConsentLogRepository.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/DeliveryConfirmation.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/DestinationResolver.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/EventRedactor.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/ConsentSnapshot.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/OrderEventFactory.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/DebugTransport.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/OrderDispatcher.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/Module.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/FrontendContext.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/BricksAdapter.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/Assets.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/CartRemoveLink.php';
