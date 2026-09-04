<?php
/**
 * PHPUnit bootstrap.
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['kdconsent_test_options'] = array();
$GLOBALS['kdconsent_test_filters'] = array();
$GLOBALS['kdconsent_test_cache_deletions'] = array();

function __( string $text, string $domain = 'default' ): string {
	return $text;
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

function add_filter( string $hook, callable $callback ): bool {
	$GLOBALS['kdconsent_test_filters'][ $hook ][] = $callback;
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
