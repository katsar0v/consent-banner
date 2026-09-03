<?php
/**
 * Consent state and cache-safe settings tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Domain\Category;
use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ConsentService;
use PHPUnit\Framework\TestCase;

final class ConsentStateTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
		$GLOBALS['kdconsent_test_options'] = array(
			Installer::OPTION_CONSENT_VERSION => 7,
		);

		$cache = new ReflectionProperty( SettingsRepository::class, 'cached_settings' );
		$cache->setValue( null, null );
	}

	public function test_only_essential_can_be_required_or_enabled_initially(): void {
		$optional = Category::from_array(
			array(
				'id'               => 'analytics',
				'label'            => 'Analytics',
				'description'      => 'Usage',
				'required'         => true,
				'enabledByDefault' => true,
			)
		)->to_array();

		$essential = Category::from_array( array( 'id' => 'essential' ) )->to_array();

		self::assertFalse( $optional['required'] );
		self::assertFalse( $optional['enabledByDefault'] );
		self::assertTrue( $essential['required'] );
		self::assertTrue( $essential['enabledByDefault'] );
	}

	public function test_expiry_rejects_old_and_future_timestamps(): void {
		$now = 1_800_000_000;

		self::assertFalse( ConsentService::is_expired( $now - DAY_IN_SECONDS, 2, $now ) );
		self::assertTrue( ConsentService::is_expired( $now - 2 * DAY_IN_SECONDS, 2, $now ) );
		self::assertTrue( ConsentService::is_expired( $now + 6 * MINUTE_IN_SECONDS, 2, $now ) );
	}

	public function test_material_changes_bump_version_and_patch_preserves_omitted_settings(): void {
		$repository = new SettingsRepository();
		$settings   = $repository->get();

		$patched = $repository->patch( array( 'showDelayMs' => 250 ) );
		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertSame( 250, $patched['showDelayMs'] );
		self::assertSame( $settings['categories'], $patched['categories'] );
		self::assertSame( $settings['texts'], $patched['texts'] );

		$patched['categories'][1]['label'] = 'Anonymous analytics';
		$repository->update( $patched );
		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}
}
