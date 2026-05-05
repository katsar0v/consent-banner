<?php
/**
 * Consent state value object.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Domain;

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
		private int $timestamp
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'v' => $this->version,
			't' => $this->timestamp,
			'c' => $this->categories,
		);
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
}
