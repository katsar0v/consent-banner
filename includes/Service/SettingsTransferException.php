<?php
/**
 * Settings transfer failure.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsTransferException extends \RuntimeException {
	public function __construct(
		string $message,
		private string $error_code
	) {
		parent::__construct( $message );
	}

	public function error_code(): string {
		return $this->error_code;
	}
}
