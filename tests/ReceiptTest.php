<?php
/**
 * Data-minimal receipt tests.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

use KatsarovDesign\ConsentBanner\Domain\ConsentState;
use KatsarovDesign\ConsentBanner\Repository\ConsentLogRepository;
use PHPUnit\Framework\TestCase;

final class ReceiptTest extends TestCase {
	public function test_receipt_is_pseudonymous_and_part_of_the_signed_state(): void {
		$repository = new ConsentLogRepository();
		$categories = array( 'essential' => true, 'analytics' => false );
		$receipt_id = $repository->generate_receipt_id( $categories, 4, 1_800_000_000 );
		$state      = new ConsentState( $categories, 4, 1_800_000_000, $receipt_id );

		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $receipt_id );
		self::assertSame( $receipt_id, $state->to_array()['r'] );
	}

	public function test_repository_never_reads_ip_or_user_agent(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/Repository/ConsentLogRepository.php' );

		self::assertIsString( $source );
		self::assertStringNotContainsString( 'REMOTE_ADDR', $source );
		self::assertStringNotContainsString( 'HTTP_USER_AGENT', $source );
		self::assertStringContainsString( 'RETENTION_DAYS = 365', $source );
	}
}
