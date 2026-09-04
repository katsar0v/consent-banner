<?php
/**
 * Local-only server commerce debug transport.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

use KatsarovDesign\ConsentBanner\Service\RuntimeMode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DebugTransport {
	/** @param array<string,mixed> $event Redacted commerce event. */
	public static function deliver( array $event ): bool {
		if ( ! RuntimeMode::is_debug() ) {
			return false;
		}

		$encoded = wp_json_encode( EventRedactor::event( $event ) );
		if ( false === $encoded ) {
			return false;
		}

		return error_log( '[kdconsent-commerce] ' . $encoded ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Explicit local debug transport.
	}
}
