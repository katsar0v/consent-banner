<?php
/**
 * Settings transfer and consent-version policy tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ConsentDefinitionFingerprint;
use KatsarovDesign\ConsentBanner\Service\SettingsTransfer;
use PHPUnit\Framework\TestCase;

final class SettingsTransferTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
		$GLOBALS['kdconsent_test_options'] = array(
			Installer::OPTION_CONSENT_VERSION => 7,
		);

		$this->reset_settings_cache();
		ConsentDefinitionFingerprint::sync();
	}

	public function test_no_bump_material_import_matches_preview_and_stays_synchronized(): void {
		$transfer = new SettingsTransfer();
		$json     = (string) wp_json_encode(
			array(
				'settings' => array(
					'texts' => array(
						'de_DE' => array( 'bannerTitle' => 'Datenschutz-Einstellungen' ),
					),
				),
			)
		);

		$preview = $transfer->preview_import( $json, false, false );
		$result  = $transfer->import_json( $json, false, false );
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 7, $preview['consentVersion'] );
		self::assertSame( 7, $result['consentVersion'] );
		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertSame( 'Datenschutz-Einstellungen', $result['settings']['texts']['de_DE']['bannerTitle'] );
	}

	public function test_no_bump_appearance_import_does_not_change_version(): void {
		$transfer = new SettingsTransfer();
		$json     = (string) wp_json_encode(
			array(
				'settings' => array(
					'position' => 'center',
				),
			)
		);

		$result = $transfer->import_json( $json, false, false );
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 7, $result['consentVersion'] );
		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertSame( 'center', $result['settings']['position'] );
	}

	public function test_requested_bump_for_appearance_import_happens_exactly_once(): void {
		$transfer = new SettingsTransfer();
		$json     = (string) wp_json_encode(
			array(
				'settings' => array(
					'position' => 'center',
				),
			)
		);

		$preview = $transfer->preview_import( $json, false, true );
		$result  = $transfer->import_json( $json, false, true );
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 8, $preview['consentVersion'] );
		self::assertSame( 8, $result['consentVersion'] );
		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}

	private function reset_settings_cache(): void {
		$cache = new ReflectionProperty( SettingsRepository::class, 'cached_settings' );
		$cache->setValue( null, null );
	}
}
