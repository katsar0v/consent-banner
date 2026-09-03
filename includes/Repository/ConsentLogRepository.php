<?php
/**
 * Consent log persistence.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Repository;

use KatsarovDesign\ConsentBanner\Domain\ConsentState;
use KatsarovDesign\ConsentBanner\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentLogRepository {
	public const RETENTION_DAYS = 365;

	/**
	 * @param array<string,bool> $categories Consent choices.
	 */
	public function generate_receipt_id( array $categories, int $version, int $timestamp ): string {
		$entropy = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
		$payload = wp_json_encode( array( $categories, $version, $timestamp ) );
		return hash( 'sha256', $entropy . '|' . ( false === $payload ? '' : $payload ) );
	}

	public function insert( ConsentState $state ): void {
		global $wpdb;

		$receipt_id = $state->receipt_id();
		if ( null === $receipt_id ) {
			return;
		}

		$wpdb->insert(
			Installer::consent_log_table_name(),
			array(
				'consent_hash'    => $receipt_id,
				'categories_json' => wp_json_encode( $state->categories() ),
				'consent_version' => $state->version(),
				'created_at'      => current_time( 'mysql', true ),
				'expires_at'      => gmdate( 'Y-m-d H:i:s', $state->timestamp() + self::RETENTION_DAYS * DAY_IN_SECONDS ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
	}

	public static function purge_expired(): void {
		global $wpdb;

		if ( get_transient( 'kdconsent_receipt_cleanup' ) ) {
			return;
		}

		$table_name = Installer::consent_log_table_name();
		$table      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_name !== $table ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $table_name . ' WHERE expires_at < %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				current_time( 'mysql', true )
			)
		);
		set_transient( 'kdconsent_receipt_cleanup', 1, DAY_IN_SECONDS );
	}
}
