<?php
/**
 * Consent state value object.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentState {
	/**
	 * @param array<string,bool> $categories Per-category acceptance state.
	 */
	public function __construct(
		private array $categories,
		private int $version,
		private int $timestamp,
		private ?string $receipt_id = null
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$state = array(
			'v' => $this->version,
			't' => $this->timestamp,
			'c' => $this->categories,
		);

		if ( null !== $this->receipt_id ) {
			$state['r'] = $this->receipt_id;
		}

		return $state;
	}

	/**
	 * @return array<string,bool>
	 */
	public function categories(): array {
		return $this->categories;
	}

	public function version(): int {
		return $this->version;
	}

	public function timestamp(): int {
		return $this->timestamp;
	}

	public function receipt_id(): ?string {
		return $this->receipt_id;
	}
}
