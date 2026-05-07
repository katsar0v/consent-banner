<?php
/**
 * Frontend asset registration.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Frontend;

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Plugin;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Rest\RestRouter;
use KatsarovDesign\ConsentBanner\Service\ConsentService;
use KatsarovDesign\ConsentBanner\Service\Localization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	public static function enqueue(): void {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$settings_repository = new SettingsRepository();
		$consent_service     = new ConsentService();
		$localization        = new Localization();

		$settings = $settings_repository->get();
		$consent  = $consent_service->current_from_request();
		$style_path   = KDCONSENT_PLUGIN_DIR . 'assets/css/banner.css';
		$script_path  = KDCONSENT_PLUGIN_DIR . 'assets/js/banner.js';
		$style_ver    = is_readable( $style_path ) ? KDCONSENT_PLUGIN_VERSION . '.' . (string) filemtime( $style_path ) : KDCONSENT_PLUGIN_VERSION;
		$script_ver   = is_readable( $script_path ) ? KDCONSENT_PLUGIN_VERSION . '.' . (string) filemtime( $script_path ) : KDCONSENT_PLUGIN_VERSION;

		wp_enqueue_style( 'kdconsent-banner', KDCONSENT_PLUGIN_URL . 'assets/css/banner.css', array(), $style_ver );
		wp_enqueue_script( 'kdconsent-banner', KDCONSENT_PLUGIN_URL . 'assets/js/banner.js', array(), $script_ver, true );
		wp_localize_script(
			'kdconsent-banner',
			'kdconsentConfig',
			array(
				'restRoot'       => esc_url_raw( rest_url( RestRouter::NAMESPACE . '/' ) ),
				'cookieName'     => ConsentService::COOKIE_NAME,
				'locale'         => $localization->current_locale(),
				'texts'          => $localization->resolve_texts( $settings ),
				'categories'     => $localization->resolve_categories( $settings ),
				'behavior'       => array(
					'position'         => $settings['position'],
					'showRejectButton' => (bool) $settings['showRejectButton'],
					'animation'        => (string) ( $settings['animation'] ?? 'fade-in' ),
					'showDelayMs'      => (int) ( $settings['showDelayMs'] ?? 0 ),
					'styles'           => is_array( $settings['styles'] ?? null ) ? $settings['styles'] : array(),
				),
				'consentVersion' => (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 ),
				'consent'        => null !== $consent ? $consent->to_array() : null,
			)
		);
		wp_set_script_translations( 'kdconsent-banner', Plugin::TEXT_DOMAIN, KDCONSENT_PLUGIN_DIR . 'languages' );
	}
}
