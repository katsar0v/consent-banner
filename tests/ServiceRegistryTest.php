<?php
/**
 * Service registry tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Service\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ServiceRegistryTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
	}

	public function test_registry_sanitizes_descriptors_and_rejects_non_allowlisted_scripts(): void {
		add_filter(
			'kdconsent_services',
			static fn(): array => array(
				array(
					'id'            => 'Clarity<script>',
					'purpose'       => 'Analytics',
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
		self::assertCount( 1, $services[0]['scripts'] );
		self::assertSame( 'https://cdn.example.test/clarity.js', $services[0]['scripts'][0]['src'] );
		self::assertSame( array( '_clck', 'bad' ), $services[0]['cookies'] );
	}
}
