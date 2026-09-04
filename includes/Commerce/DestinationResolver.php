<?php
/**
 * Consent-aware server-commerce destination resolution.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Commerce;

use KatsarovDesign\ConsentBanner\Service\ServiceRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DestinationResolver {
	/**
	 * @param array<string,bool> $consent Consent snapshot.
	 * @return list<string>
	 */
	public static function resolve( array $consent ): array {
		$destinations = array();
		foreach ( ServiceRegistry::services() as $service ) {
			$id      = sanitize_key( (string) ( $service['id'] ?? '' ) );
			$purpose = sanitize_key( (string) ( $service['purpose'] ?? '' ) );
			if ( '' !== $id && '' !== $purpose && ! empty( $consent[ $purpose ] ) ) {
				$destinations[] = $id;
			}
		}

		return array_values( array_unique( $destinations ) );
	}
}
