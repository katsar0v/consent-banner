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
require_once dirname( __DIR__ ) . '/includes/Commerce/Module.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/FrontendContext.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/BricksAdapter.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/Assets.php';
require_once dirname( __DIR__ ) . '/includes/Commerce/CartRemoveLink.php';
