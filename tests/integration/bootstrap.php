<?php
/**
 * WordPress test-suite bootstrap.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		$woocommerce = dirname( ABSPATH ) . '/wordpress/wp-content/plugins/woocommerce/woocommerce.php';
		if ( ! is_readable( $woocommerce ) ) {
			$woocommerce = dirname( ABSPATH ) . '/wordpress/wp-content/plugins/woocommerce/plugins/woocommerce/woocommerce.php';
		}
		if ( is_readable( $woocommerce ) ) {
			require_once $woocommerce;
			add_action(
				'init',
				static function (): void {
					if ( class_exists( 'WC_Install' ) ) {
						WC_Install::create_tables();
					}
					if ( class_exists( 'ActionScheduler_StoreSchema' ) ) {
						( new ActionScheduler_StoreSchema() )->register_tables( true );
					}
					if ( class_exists( 'ActionScheduler_LoggerSchema' ) ) {
						( new ActionScheduler_LoggerSchema() )->register_tables( true );
					}
				},
				-PHP_INT_MAX
			);
		}

		require dirname( __DIR__, 2 ) . '/consent-banner.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
