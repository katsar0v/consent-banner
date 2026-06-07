<?php
/**
 * Public consent and admin settings REST controller.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Rest;

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ConsentService;
use KatsarovDesign\ConsentBanner\Service\Localization;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentController extends Controller {
	private SettingsRepository $settings_repository;
	private ConsentService $consent_service;
	private Localization $localization;

	public function __construct() {
		$this->settings_repository = new SettingsRepository();
		$this->consent_service     = new ConsentService();
		$this->localization        = new Localization();
	}

	public function config(): \WP_REST_Response {
		$settings      = $this->settings_repository->get();
		$current_state = $this->consent_service->current_from_request();

		$response = $this->response(
			array(
				'locale'         => $this->localization->current_locale(),
				'texts'          => $this->localization->resolve_texts( $settings ),
				'categories'     => $this->localization->resolve_categories( $settings ),
				'behavior'       => array(
					'consentLifetimeDays' => (int) $settings['consentLifetimeDays'],
					'position'            => (string) $settings['position'],
					'showRejectButton'    => (bool) $settings['showRejectButton'],
				),
				'consentVersion' => $this->consent_service->consent_version(),
				'consent'        => null !== $current_state ? $current_state->to_array() : null,
			)
		);

		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	public function save_consent( WP_REST_Request $request ): \WP_REST_Response|WP_Error {
		if ( ! $this->rate_limit_ok() ) {
			return new WP_Error(
				'kdconsent_rate_limited',
				__( 'Too many consent requests. Please try again in a minute.', 'consent-banner' ),
				array( 'status' => 429 )
			);
		}

		$data       = $this->request_data( $request );
		$categories = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
		$state      = $this->consent_service->record( $categories );

		$response = $this->response( $state->to_array() );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	public function get_settings(): \WP_REST_Response {
		$settings = $this->settings_repository->get();

		return $this->response(
			array(
				'settings'       => $settings,
				'consentVersion' => (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 ),
			)
		);
	}

	public function update_settings( WP_REST_Request $request ): \WP_REST_Response {
		$data     = $this->request_data( $request );
		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$updated  = $this->settings_repository->update( $settings );

		if ( ! empty( $data['bumpConsentVersion'] ) ) {
			$current_version = (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 );
			update_option( Installer::OPTION_CONSENT_VERSION, $current_version + 1, false );
		}

		return $this->response(
			array(
				'settings'       => $updated,
				'consentVersion' => (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 ),
			)
		);
	}

	private function rate_limit_ok(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'kdconsent_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 60 ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
