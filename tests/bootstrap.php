<?php
/**
 * PHPUnit bootstrap.
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['kdconsent_test_options'] = array();

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function sanitize_key( string $key ): string {
	return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
}

function sanitize_text_field( string $text ): string {
	return trim( strip_tags( $text ) );
}

function sanitize_hex_color( mixed $color ): ?string {
	return is_string( $color ) && preg_match( '/^#[0-9a-f]{6}$/i', $color ) ? $color : null;
}

function apply_filters( string $hook, mixed $value ): mixed {
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
	if ( array_key_exists( $name, $GLOBALS['kdconsent_test_options'] ) ) {
		return false;
	}

	$GLOBALS['kdconsent_test_options'][ $name ] = $value;
	return true;
}

function wp_json_encode( mixed $value, int $flags = 0 ): string|false {
	return json_encode( $value, $flags );
}

require_once dirname( __DIR__ ) . '/includes/LegacyCompat.php';
require_once dirname( __DIR__ ) . '/includes/Installer.php';
require_once dirname( __DIR__ ) . '/includes/Domain/Category.php';
require_once dirname( __DIR__ ) . '/includes/Domain/ConsentState.php';
require_once dirname( __DIR__ ) . '/includes/Repository/SettingsRepository.php';
require_once dirname( __DIR__ ) . '/includes/Service/ConsentService.php';
