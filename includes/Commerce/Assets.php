<?php
/**
 * Browser-commerce asset registration.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

use KatsarovDesign\ConsentBanner\Service\RuntimeMode;
use KatsarovDesign\ConsentBanner\Service\ServiceRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	public static function enqueue(): void {
		if ( ! Module::is_enabled() || is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$path = KDCONSENT_PLUGIN_DIR . 'assets/js/commerce.js';
		$dependencies = array( 'kdconsent-loader' );
		if ( wp_script_is( 'wc-order-attribution', 'registered' ) ) {
			$dependencies[] = 'wc-order-attribution';
		}

		wp_enqueue_script(
			'kdconsent-commerce',
			KDCONSENT_PLUGIN_URL . 'assets/js/commerce.js',
			$dependencies,
			self::asset_version( $path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = array(
			'schemaVersion' => 1,
			'debug'         => RuntimeMode::is_debug(),
			'page'          => FrontendContext::page(),
			'services'      => self::service_routes(),
		);
		$encoded = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( false === $encoded ) {
			return;
		}

		wp_add_inline_script(
			'kdconsent-commerce',
			'window.kdconsentCommerceConfig=' . $encoded . ';',
			'before'
		);
	}

	/** @return list<array{id:string,purpose:string}> */
	private static function service_routes(): array {
		return array_map(
			static fn( array $service ): array => array(
				'id'      => (string) $service['id'],
				'purpose' => (string) $service['purpose'],
			),
			ServiceRegistry::services()
		);
	}

	private static function asset_version( string $path ): string {
		return is_readable( $path )
			? KDCONSENT_PLUGIN_VERSION . '.' . (string) filemtime( $path )
			: KDCONSENT_PLUGIN_VERSION;
	}
}
