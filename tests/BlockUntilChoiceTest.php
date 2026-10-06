<?php
/**
 * Block-until-choice display setting tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ConsentDefinitionFingerprint;
use KatsarovDesign\ConsentBanner\Service\PublicConfig;
use KatsarovDesign\ConsentBanner\Service\SettingsTransfer;
use PHPUnit\Framework\TestCase;

final class BlockUntilChoiceTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
		$GLOBALS['kdconsent_test_options'] = array( Installer::OPTION_CONSENT_VERSION => 7 );
		unset( $GLOBALS['kdconsent_test_privacy_policy_url'] );
		$this->reset_cache();
	}

	public function test_existing_settings_default_to_not_blocking(): void {
		$settings = Installer::default_settings();
		unset( $settings['blockUntilChoice'] );
		update_option( Installer::OPTION_SETTINGS, $settings );

		self::assertFalse( ( new SettingsRepository() )->get()['blockUntilChoice'] );
	}

	public function test_enabling_persists_and_never_requests_consent_again(): void {
		$repository = new SettingsRepository();
		ConsentDefinitionFingerprint::sync();
		$hash = get_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH );

		$repository->patch( array( 'blockUntilChoice' => true ) );
		$this->reset_cache();
		self::assertTrue( ( new SettingsRepository() )->get()['blockUntilChoice'] );

		$repository->patch( array( 'position' => 'center' ) );
		$this->reset_cache();
		self::assertTrue( ( new SettingsRepository() )->get()['blockUntilChoice'], 'An unrelated PATCH must preserve the setting.' );

		ConsentDefinitionFingerprint::sync();
		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertSame( $hash, get_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH ) );
	}

	public function test_export_import_preserves_enabled_value(): void {
		$repository = new SettingsRepository();
		$repository->patch( array( 'blockUntilChoice' => true ) );
		$transfer = new SettingsTransfer();
		$json     = $transfer->export_json();
		$repository->patch( array( 'blockUntilChoice' => false ) );
		$transfer->import_json( $json, false, false );
		$this->reset_cache();

		self::assertTrue( $repository->get()['blockUntilChoice'] );
	}

	public function test_public_config_exposes_blocking_and_the_privacy_policy_url(): void {
		$GLOBALS['kdconsent_test_privacy_policy_url'] = 'https://example.test/privacy/';
		( new SettingsRepository() )->patch( array( 'blockUntilChoice' => true ) );
		$this->reset_cache();

		$behavior = ( new PublicConfig() )->build()['behavior'];

		self::assertTrue( $behavior['blockUntilChoice'] );
		self::assertSame( 'https://example.test/privacy/', $behavior['privacyPolicyUrl'] );
	}

	private function reset_cache(): void {
		( new ReflectionProperty( SettingsRepository::class, 'cached_settings' ) )->setValue( null, null );
	}
}
