<?php
/**
 * Explicit delivery acknowledgement passed to server transport hooks.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliveryConfirmation {
	private bool $confirmed = false;

	public function confirm(): void {
		$this->confirmed = true;
	}

	public function is_confirmed(): bool {
		return $this->confirmed;
	}
}
