<?php
/**
 * Effective consent-definition fingerprint tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ConsentDefinitionFingerprint;
use PHPUnit\Framework\TestCase;

final class ConsentDefinitionFingerprintTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
		$GLOBALS['kdconsent_test_options'] = array(
			Installer::OPTION_CONSENT_VERSION => 7,
		);
		$GLOBALS['kdconsent_test_cache_deletions'] = array();
		unset( $GLOBALS['kdconsent_test_add_option_callback'], $GLOBALS['wpdb'] );

		$this->reset_settings_cache();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['kdconsent_test_add_option_callback'], $GLOBALS['wpdb'] );
	}

	public function test_initial_sync_establishes_a_baseline_without_bumping(): void {
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertIsString( get_option( Installer::OPTION_CONSENT_DEFINITIONS_HASH ) );
		self::assertIsString( get_option( Installer::OPTION_SERVICE_REGISTRY_HASH ) );
	}

	public function test_category_and_service_changes_together_bump_exactly_once(): void {
		ConsentDefinitionFingerprint::sync();

		add_filter(
			'kdconsent_categories',
			static function ( array $categories ): array {
				$categories[] = array(
					'id'               => 'preferences',
					'label'            => 'Preferences',
					'description'      => 'Remember choices',
					'required'         => false,
					'enabledByDefault' => false,
				);

				return $categories;
			}
		);
		add_filter(
			'kdconsent_services',
			static fn(): array => array(
				array(
					'id'      => 'reviews',
					'name'    => 'Reviews',
					'purpose' => 'preferences',
				)
			)
		);
		$this->reset_settings_cache();

		ConsentDefinitionFingerprint::sync();
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}

	public function test_service_definition_change_bumps_once(): void {
		$service_name = 'Analytics';
		add_filter(
			'kdconsent_services',
			static function () use ( &$service_name ): array {
				return array(
					array(
						'id'      => 'analytics',
						'name'    => $service_name,
						'purpose' => 'analytics',
					)
				);
			}
		);
		ConsentDefinitionFingerprint::sync();

		$service_name = 'Privacy-friendly analytics';
		ConsentDefinitionFingerprint::sync();
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}

	public function test_legacy_service_hash_detects_a_change_during_upgrade(): void {
		$GLOBALS['kdconsent_test_options'][ Installer::OPTION_SERVICE_REGISTRY_HASH ] = hash(
			'sha256',
			(string) wp_json_encode( array() )
		);
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

		ConsentDefinitionFingerprint::sync();
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}

	public function test_appearance_only_changes_do_not_bump(): void {
		ConsentDefinitionFingerprint::sync();
		$repository = new SettingsRepository();
		$settings   = $repository->get();

		$settings['styles']['backdrop']['color'] = '#112233';
		$settings['position']                    = 'center';
		$settings['animation']                   = 'slide-in-up';
		$settings['showDelayMs']                 = 500;
		$repository->update( $settings );
		ConsentDefinitionFingerprint::sync();

		self::assertSame( 7, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}

	public function test_text_and_lifetime_changes_each_bump_once(): void {
		ConsentDefinitionFingerprint::sync();
		$repository = new SettingsRepository();
		$settings   = $repository->get();

		$settings['texts']['de_DE']['bannerTitle'] = 'Datenschutz-Einstellungen';
		$settings                                  = $repository->update( $settings );
		ConsentDefinitionFingerprint::sync();
		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );

		$settings['consentLifetimeDays'] = 365;
		$repository->update( $settings );
		ConsentDefinitionFingerprint::sync();
		self::assertSame( 9, get_option( Installer::OPTION_CONSENT_VERSION ) );
	}

	public function test_lost_initial_add_does_not_bump_a_second_time(): void {
		$previous_settings = Installer::default_settings();
		$current_settings  = $previous_settings;
		$current_settings['categories'][1]['label'] = 'Anonymous analytics';

		$GLOBALS['kdconsent_test_add_option_callback'] = static function ( string $name, mixed $value ): ?bool {
			if ( Installer::OPTION_CONSENT_DEFINITIONS_HASH !== $name ) {
				return null;
			}

			$GLOBALS['kdconsent_test_options'][ $name ] = $value;
			$GLOBALS['kdconsent_test_options'][ Installer::OPTION_CONSENT_VERSION ] = 8;

			return false;
		};

		ConsentDefinitionFingerprint::settings_updated( $previous_settings, $current_settings );

		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertContains(
			array( Installer::OPTION_CONSENT_DEFINITIONS_HASH, 'options' ),
			$GLOBALS['kdconsent_test_cache_deletions']
		);
	}

	public function test_lost_compare_and_swap_does_not_bump_a_second_time(): void {
		ConsentDefinitionFingerprint::sync();
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

		$GLOBALS['wpdb'] = new class() {
			public string $options = 'wp_options';
			public bool $lost_definition_update = false;

			/**
			 * @param array<string,mixed> $data Replacement fields.
			 * @param array<string,mixed> $where Compare fields.
			 * @param list<string>        $data_format Replacement formats.
			 * @param list<string>        $where_format Compare formats.
			 */
			public function update( string $table, array $data, array $where, array $data_format, array $where_format ): int {
				$name = (string) $where['option_name'];

				if ( Installer::OPTION_CONSENT_DEFINITIONS_HASH === $name && ! $this->lost_definition_update ) {
					$this->lost_definition_update = true;
					$GLOBALS['kdconsent_test_options'][ $name ] = $data['option_value'];
					$GLOBALS['kdconsent_test_options'][ Installer::OPTION_CONSENT_VERSION ] = 8;

					return 0;
				}

				$current = $GLOBALS['kdconsent_test_options'][ $name ] ?? false;
				if ( (string) $current !== (string) $where['option_value'] ) {
					return 0;
				}

				$GLOBALS['kdconsent_test_options'][ $name ] = $data['option_value'];

				return 1;
			}
		};

		ConsentDefinitionFingerprint::sync();

		self::assertTrue( $GLOBALS['wpdb']->lost_definition_update );
		self::assertSame( 8, get_option( Installer::OPTION_CONSENT_VERSION ) );
		self::assertContains(
			array( Installer::OPTION_CONSENT_DEFINITIONS_HASH, 'options' ),
			$GLOBALS['kdconsent_test_cache_deletions']
		);
	}

	private function reset_settings_cache(): void {
		$cache = new ReflectionProperty( SettingsRepository::class, 'cached_settings' );
		$cache->setValue( null, null );
	}
}
