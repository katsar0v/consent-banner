<?php
/**
 * Generic runtime-mode tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Service\RuntimeMode;
use PHPUnit\Framework\TestCase;

final class RuntimeModeTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['kdconsent_test_filters'] = array();
	}

	public function test_live_is_the_safe_default(): void {
		self::assertSame( RuntimeMode::LIVE, RuntimeMode::current() );
		self::assertFalse( RuntimeMode::is_debug() );
	}

	public function test_debug_can_be_enabled_with_the_generic_filter(): void {
		add_filter( 'kdconsent_runtime_mode', static fn(): string => RuntimeMode::DEBUG );

		self::assertSame( RuntimeMode::DEBUG, RuntimeMode::current() );
		self::assertTrue( RuntimeMode::is_debug() );
	}

	public function test_invalid_filtered_values_fall_back_to_live(): void {
		add_filter( 'kdconsent_runtime_mode', static fn(): string => 'staging' );

		self::assertSame( RuntimeMode::LIVE, RuntimeMode::current() );
		self::assertFalse( RuntimeMode::is_debug() );
	}
}
