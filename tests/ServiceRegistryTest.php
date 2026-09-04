<?php
/**
 * Service registry tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ServiceRegistryTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
		$GLOBALS['kdconsent_test_options'] = array(
			Installer::OPTION_CONSENT_VERSION => 4,
		);

		$cache = new ReflectionProperty( SettingsRepository::class, 'cached_settings' );
		$cache->setValue( null, null );
	}

	public function test_registry_sanitizes_descriptors_and_rejects_non_allowlisted_scripts(): void {
		add_filter(
			'kdconsent_services',
			static fn(): array => array(
				array(
					'id'            => 'Clarity<script>',
					'name'          => 'Microsoft Clarity',
					'provider'      => 'Microsoft Ireland Operations Ltd.',
					'purpose'       => 'Analytics',
					'purposeDescription' => 'Anonymous usage analysis',
					'data'          => array( 'Clicks', 'Device type' ),
					'duration'      => '13 months',
					'recipients'    => array( 'Microsoft' ),
					'thirdCountryTransfer' => 'United States (SCC)',
					'privacyUrl'    => 'https://privacy.microsoft.com/',
					'allowedUrls'   => array( 'https://cdn.example.test/clarity.js' ),
					'scriptHandles' => array( 'Clarity Loader' ),
					'scripts'       => array(
						array(
							'handle' => 'loader',
							'src'    => 'https://cdn.example.test/clarity.js',
						),
						array(
							'handle' => 'injected',
							'src'    => 'https://attacker.example/script.js',
						),
					),
					'cookies'       => array( '_clck', '<bad>' ),
				)
			)
		);

		$services = ServiceRegistry::services();

		self::assertCount( 1, $services );
		self::assertSame( 'clarityscript', $services[0]['id'] );
		self::assertSame( 'analytics', $services[0]['purpose'] );
		self::assertSame( 'Microsoft Ireland Operations Ltd.', $services[0]['provider'] );
		self::assertSame( array( 'Clicks', 'Device type' ), $services[0]['data'] );
		self::assertSame( 'https://privacy.microsoft.com/', $services[0]['privacyUrl'] );
		self::assertCount( 1, $services[0]['scripts'] );
		self::assertSame( 'https://cdn.example.test/clarity.js', $services[0]['scripts'][0]['src'] );
		self::assertSame( array( '_clck', 'bad' ), $services[0]['cookies'] );
	}

	public function test_legacy_sync_method_uses_the_combined_fingerprint(): void {
		ServiceRegistry::sync_consent_version();
		add_filter(
			'kdconsent_services',
			static fn(): array => array(
				array(
					'id'      => 'analytics',
					'name'    => 'Analytics',
					'purpose' => 'analytics',
				)
			)
		);

		ServiceRegistry::sync_consent_version();
		ServiceRegistry::sync_consent_version();

		self::assertSame( 5, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}
}
