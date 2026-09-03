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
	public static function bootstrap(): void {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$settings  = ( new PublicConfig() )->build();
		$transport = apply_filters( 'kdconsent_consent_mode_transport', self::is_local_debug() ? 'debug' : 'dataLayer' );
		$transport = in_array( $transport, array( 'debug', 'dataLayer' ), true ) ? $transport : 'dataLayer';
		$config    = array(
			'cookieName'         => ConsentService::COOKIE_NAME,
			'legacyCookieName'   => LegacyCompat::COOKIE_NAME,
			'storageKey'         => 'kdconsent_consent_state',
			'consentVersion'     => max( 1, (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 ) ),
			'consentLifetimeDays' => (int) ( $settings['behavior']['consentLifetimeDays'] ?? 180 ),
			'transport'          => $transport,
		);

		$encoded_config = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( false === $encoded_config ) {
			return;
		}

		$storage_url = add_query_arg(
			'ver',
			self::asset_version( KDCONSENT_PLUGIN_DIR . 'assets/js/consent-storage.js' ),
			KDCONSENT_PLUGIN_URL . 'assets/js/consent-storage.js'
		);
		$mode_url    = add_query_arg(
			'ver',
			self::asset_version( KDCONSENT_PLUGIN_DIR . 'assets/js/consent-mode-v2.js' ),
			KDCONSENT_PLUGIN_URL . 'assets/js/consent-mode-v2.js'
		);

		echo '<script id="kdconsent-bootstrap-config" data-nowprocket>window.kdconsentBootstrapConfig=' . $encoded_config . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script id="kdconsent-storage-js" data-nowprocket src="' . esc_url( $storage_url ) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		echo '<script id="kdconsent-consent-mode-v2-js" data-nowprocket src="' . esc_url( $mode_url ) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	public static function enqueue(): void {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$loader_path   = KDCONSENT_PLUGIN_DIR . 'assets/js/loader.js';
		$ui_path       = KDCONSENT_PLUGIN_DIR . 'assets/js/banner-ui.js';
		$style_path    = KDCONSENT_PLUGIN_DIR . 'assets/css/banner.css';
		$loader_ver    = self::asset_version( $loader_path );
		$public_config = ( new PublicConfig() )->build();
		$public_config['consent'] = null;

		wp_enqueue_script(
			'kdconsent-loader',
			KDCONSENT_PLUGIN_URL . 'assets/js/loader.js',
			array(),
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

	private static function is_local_debug(): bool {
		return 'local' === wp_get_environment_type()
			&& defined( 'KDCONSENT_TRACKING_MODE' )
			&& 'debug' === KDCONSENT_TRACKING_MODE
			&& ( ! defined( 'KDCONSENT_REMOTE_TRACKING' ) || ! KDCONSENT_REMOTE_TRACKING );
	}
}
