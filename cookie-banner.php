<?php
/**
 * Plugin Name: Cookie Banner
 * Description: GDPR cookie consent banner with configurable categories.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Katsarov Design
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cookie-banner
 * Domain Path: /languages
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

use KatsarovDesign\CookieBanner\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDCB_PLUGIN_VERSION', '0.1.0' );
define( 'KDCB_DB_VERSION', '0.1.0' );
define( 'KDCB_PLUGIN_FILE', __FILE__ );
define( 'KDCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$kdcb_autoload = KDCB_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $kdcb_autoload ) ) {
	require_once $kdcb_autoload;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'KatsarovDesign\\CookieBanner\\';
			if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $prefix ) );
			$file           = KDCB_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook(
	KDCB_PLUGIN_FILE,
	static function (): void {
		Plugin::instance()->activate();
	}
);

register_deactivation_hook(
	KDCB_PLUGIN_FILE,
	static function (): void {
		Plugin::instance()->deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->init();
	}
);

if ( ! function_exists( 'kdcb_has_consent' ) ) {
	function kdcb_has_consent( string $category ): bool {
		return ( new \KatsarovDesign\CookieBanner\Service\ConsentService() )->has_consent( $category );
	}
}
