<?php
/**
 * WordPress REST and schema integration tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;
use KatsarovDesign\ConsentBanner\Service\ConsentDefinitionFingerprint;

final class RestIntegrationTest extends WP_UnitTestCase {
	private static int $administrator_id;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Installer::install();
		if ( 0 === did_action( 'rest_api_init' ) ) {
			do_action( 'rest_api_init' );
		}

		self::$administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$administrator_id );
	}

	public function test_public_config_is_cookie_neutral_and_publicly_cacheable(): void {
		$_COOKIE['kdconsent_consent'] = 'visitor-specific-value';
		$request                       = new WP_REST_Request( 'GET', '/kdconsent/v1/config' );
		$response                      = rest_get_server()->dispatch( $request );
		$data                          = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertNull( $data['consent'] );
		self::assertStringStartsWith( 'public,', $response->get_headers()['Cache-Control'] );
	}

	public function test_definition_fingerprint_runs_after_runtime_integrations_load(): void {
		self::assertSame(
			PHP_INT_MAX,
			has_action( 'wp_loaded', array( ConsentDefinitionFingerprint::class, 'sync' ) )
		);
	}

	public function test_patch_preserves_omitted_settings(): void {
		$before  = ( new SettingsRepository() )->get();
		$request = new WP_REST_Request( 'PATCH', '/kdconsent/v1/settings' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'settings' => array( 'showDelayMs' => 321 ) ) ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 321, $data['settings']['showDelayMs'] );
		self::assertSame( $before['categories'], $data['settings']['categories'] );
		self::assertSame( $before['texts'], $data['settings']['texts'] );
	}

	public function test_receipt_schema_contains_no_ip_or_user_agent_columns(): void {
		global $wpdb;

		$columns = $wpdb->get_col( 'DESCRIBE ' . Installer::consent_log_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		self::assertContains( 'consent_hash', $columns );
		self::assertContains( 'expires_at', $columns );
		self::assertNotContains( 'ip_hash', $columns );
		self::assertNotContains( 'user_agent_hash', $columns );
	}

	public function test_woocommerce_11_is_loaded_by_the_matrix(): void {
		self::assertTrue( class_exists( 'WooCommerce' ) );
		self::assertStringStartsWith( '11.', WC_VERSION );
	}
}
