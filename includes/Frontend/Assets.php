<?php
/**
 * Frontend asset registration.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Frontend;

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\LegacyCompat;
use KatsarovDesign\ConsentBanner\Rest\RestRouter;
use KatsarovDesign\ConsentBanner\Service\ConsentService;
use KatsarovDesign\ConsentBanner\Service\PublicConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	public static function enqueue(): void {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$storage_path  = KDCONSENT_PLUGIN_DIR . 'assets/js/consent-storage.js';
		$loader_path   = KDCONSENT_PLUGIN_DIR . 'assets/js/loader.js';
		$ui_path       = KDCONSENT_PLUGIN_DIR . 'assets/js/banner-ui.js';
		$style_path    = KDCONSENT_PLUGIN_DIR . 'assets/css/banner.css';
		$storage_ver   = self::asset_version( $storage_path );
		$loader_ver    = self::asset_version( $loader_path );
		$public_config = ( new PublicConfig() )->build();
		$public_config['consent'] = null;

		wp_enqueue_script(
			'kdconsent-storage',
			KDCONSENT_PLUGIN_URL . 'assets/js/consent-storage.js',
			array(),
			$storage_ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_enqueue_script(
			'kdconsent-loader',
			KDCONSENT_PLUGIN_URL . 'assets/js/loader.js',
			array( 'kdconsent-storage' ),
			$loader_ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = array(
			'restRoot'         => esc_url_raw( rest_url( RestRouter::NAMESPACE . '/' ) ),
			'cookieName'       => ConsentService::COOKIE_NAME,
			'legacyCookieName' => LegacyCompat::COOKIE_NAME,
			'storageKey'       => 'kdconsent_consent_state',
			'consentVersion'   => max( 1, (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 ) ),
			'config'           => $public_config,
			'assets'           => array(
				'script' => esc_url_raw(
					add_query_arg(
						'ver',
						self::asset_version( $ui_path ),
						KDCONSENT_PLUGIN_URL . 'assets/js/banner-ui.js'
					)
				),
				'style'  => esc_url_raw(
					add_query_arg(
						'ver',
						self::asset_version( $style_path ),
						KDCONSENT_PLUGIN_URL . 'assets/css/banner.css'
					)
				),
			),
		);

		$encoded_config = wp_json_encode( $config );
		if ( false === $encoded_config ) {
			return;
		}

		wp_add_inline_script(
			'kdconsent-loader',
			'window.kdconsentLoaderConfig = ' . $encoded_config . ';',
			'before'
		);
	}

	private static function asset_version( string $path ): string {
		return is_readable( $path )
			? KDCONSENT_PLUGIN_VERSION . '.' . (string) filemtime( $path )
			: KDCONSENT_PLUGIN_VERSION;
	}
}
