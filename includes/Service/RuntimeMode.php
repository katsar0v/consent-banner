<?php
/**
 * Runtime-mode resolution.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RuntimeMode {
	public const LIVE  = 'live';
	public const DEBUG = 'debug';

	public static function current(): string {
		$mode = apply_filters( 'kdconsent_runtime_mode', self::LIVE );

		return in_array( $mode, array( self::LIVE, self::DEBUG ), true ) ? $mode : self::LIVE;
	}

	public static function is_debug(): bool {
		return self::DEBUG === self::current();
	}
}
