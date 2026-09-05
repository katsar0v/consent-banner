<?php
/**
 * Automatic footer output and display-only settings regression tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Frontend\FooterPreferences;
use KatsarovDesign\ConsentBanner\Frontend\Shortcode;
use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ConsentDefinitionFingerprint;
use KatsarovDesign\ConsentBanner\Service\SettingsTransfer;
use PHPUnit\Framework\TestCase;

final class FooterPreferencesTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
		$GLOBALS['kdconsent_test_options'] = array( Installer::OPTION_CONSENT_VERSION => 7 );
		$this->reset_cache();
	}

	public function test_existing_settings_default_to_one_automatic_control(): void {
		$settings = Installer::default_settings();
		unset( $settings['autoFooterPreferences'] );
		update_option( Installer::OPTION_SETTINGS, $settings );

		self::assertTrue( ( new SettingsRepository() )->get()['autoFooterPreferences'] );
		self::assertSame(
			'<div class="kdconsent-footer-preferences"><button type="button" class="kdconsent-open-preferences kdconsent-preferences-button">Cookie settings</button></div>',
			$this->output()
		);
	}

	public function test_disable_persists_without_output_and_reenable_restores_once(): void {
		$repository = new SettingsRepository();
		ConsentDefinitionFingerprint::sync();
		$hash = get_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH );

		$repository->patch( array( 'autoFooterPreferences' => false ) );
		$this->reset_cache();
		self::assertFalse( ( new SettingsRepository() )->get()['autoFooterPreferences'] );
		self::assertSame( '', $this->output() );
		ConsentDefinitionFingerprint::sync();
		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertSame( $hash, get_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH ) );

		$repository->patch( array( 'position' => 'center' ) );
		self::assertSame( '', $this->output(), 'An unrelated PATCH must preserve the disabled setting.' );
		$repository->patch( array( 'autoFooterPreferences' => true ) );
		$this->reset_cache();
		self::assertSame( 1, substr_count( $this->output(), '<button ' ) );
		ConsentDefinitionFingerprint::sync();
		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}

	public function test_manual_shortcode_is_independent_and_escapes_its_label(): void {
		( new SettingsRepository() )->patch( array( 'autoFooterPreferences' => false ) );
		self::assertSame( '', $this->output() );
		self::assertSame(
			'<button type="button" class="kdconsent-open-preferences kdconsent-preferences-button">My &lt;settings&gt;</button>',
			Shortcode::render( array(), 'My <settings>' )
		);
	}

	public function test_export_import_preserves_disabled_value(): void {
		$repository = new SettingsRepository();
		$repository->patch( array( 'autoFooterPreferences' => false ) );
		$transfer = new SettingsTransfer();
		$json     = $transfer->export_json();
		$repository->patch( array( 'autoFooterPreferences' => true ) );
		$transfer->import_json( $json, false, false );
		$this->reset_cache();
		self::assertFalse( $repository->get()['autoFooterPreferences'] );
		self::assertSame( '', $this->output() );
	}

	private function output(): string {
		ob_start();
		FooterPreferences::render();
		return (string) ob_get_clean();
	}

	private function reset_cache(): void {
		( new ReflectionProperty( SettingsRepository::class, 'cached_settings' ) )->setValue( null, null );
	}
}
