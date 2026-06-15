<?php
/**
 * Public banner configuration builder.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

use KatsarovDesign\ConsentBanner\Domain\ConsentState;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PublicConfig {
	public function __construct(
		private ?SettingsRepository $settings_repository = null,
		private ?ConsentService $consent_service = null,
		private ?Localization $localization = null
	) {
		$this->settings_repository = $this->settings_repository ?? new SettingsRepository();
		$this->consent_service     = $this->consent_service ?? new ConsentService( $this->settings_repository );
		$this->localization        = $this->localization ?? new Localization();
	}

	/**
	 * @return array<string,mixed>
	 */
	public function build( ?ConsentState $current_state = null ): array {
		$settings = $this->settings_repository->get();

		if ( null === $current_state ) {
			$current_state = $this->consent_service->current_from_request();
		}

		return array(
			'locale'         => $this->localization->current_locale(),
			'texts'          => $this->localization->resolve_texts( $settings ),
			'categories'     => $this->localization->resolve_categories( $settings ),
			'behavior'       => array(
				'consentLifetimeDays' => (int) $settings['consentLifetimeDays'],
				'position'            => (string) $settings['position'],
				'showRejectButton'    => (bool) $settings['showRejectButton'],
				'animation'           => (string) ( $settings['animation'] ?? 'fade-in' ),
				'showDelayMs'         => (int) ( $settings['showDelayMs'] ?? 0 ),
				'styles'              => is_array( $settings['styles'] ?? null ) ? $settings['styles'] : array(),
			),
			'consentVersion' => $this->consent_service->consent_version(),
			'consent'        => null !== $current_state ? $current_state->to_array() : null,
		);
	}
}
