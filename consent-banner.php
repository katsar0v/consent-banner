<?php
/**
 * Plugin Name: Consent Banner
 * Description: GDPR/ePrivacy consent banner with configurable categories.
 * Version: 0.3.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Katsarov Design
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: consent-banner
 * Domain Path: /languages
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDCONSENT_PLUGIN_VERSION', '0.3.0' );
define( 'KDCONSENT_DB_VERSION', '0.2.0' );
define( 'KDCONSENT_PLUGIN_FILE', __FILE__ );
define( 'KDCONSENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDCONSENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$kdconsent_autoload = KDCONSENT_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $kdconsent_autoload ) ) {
	require_once $kdconsent_autoload;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'KatsarovDesign\\ConsentBanner\\';
			if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $prefix ) );
			$file           = KDCONSENT_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook(
	KDCONSENT_PLUGIN_FILE,
	static function (): void {
		Plugin::instance()->activate();
	}
);

register_deactivation_hook(
	KDCONSENT_PLUGIN_FILE,
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

if ( ! function_exists( 'kdconsent_has_consent' ) ) {
	function kdconsent_has_consent( string $category ): bool {
		return ( new \KatsarovDesign\ConsentBanner\Service\ConsentService() )->has_consent( $category );
	}
}

if ( ! function_exists( 'kdcb_has_consent' ) ) {
	function kdcb_has_consent( string $category ): bool {
		\KatsarovDesign\ConsentBanner\LegacyCompat::deprecated_function(
			'kdcb_has_consent',
			'kdconsent_has_consent'
		);

		return kdconsent_has_consent( $category );
	}
}
