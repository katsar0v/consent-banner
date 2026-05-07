<?php
/**
 * Category value object.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Category {
	public function __construct(
		private string $id,
		private string $label,
		private string $description,
		private bool $required,
		private bool $enabled_by_default
	) {}

	/**
	 * @param array<string,mixed> $data Raw category payload.
	 */
	public static function from_array( array $data ): self {
		$id               = sanitize_key( (string) ( $data['id'] ?? '' ) );
		$label            = sanitize_text_field( (string) ( $data['label'] ?? '' ) );
		$description      = sanitize_text_field( (string) ( $data['description'] ?? '' ) );
		$required         = ! empty( $data['required'] );
		$enabled_by_default = ! empty( $data['enabledByDefault'] );

		if ( '' === $id ) {
			$id = 'custom';
		}

		if ( 'essential' === $id ) {
			$required           = true;
			$enabled_by_default = true;
		}

		return new self( $id, $label, $description, $required, $enabled_by_default );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'label'            => $this->label,
			'description'      => $this->description,
			'required'         => $this->required,
			'enabledByDefault' => $this->enabled_by_default,
		);
	}

	public function id(): string {
		return $this->id;
	}

	public function required(): bool {
		return $this->required;
	}

	public function enabled_by_default(): bool {
		return $this->enabled_by_default;
	}
}
