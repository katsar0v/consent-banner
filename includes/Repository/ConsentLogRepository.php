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
	public function insert( ConsentState $state ): void {
		global $wpdb;

		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$payload    = wp_json_encode( $state->to_array() );
		$payload    = false === $payload ? '' : $payload;

		$wpdb->insert(
			Installer::consent_log_table_name(),
			array(
				'consent_hash'    => hash( 'sha256', $payload . '|' . (string) microtime( true ) ),
				'ip_hash'         => hash( 'sha256', $ip . '|' . wp_salt( 'nonce' ) ),
				'user_agent_hash' => hash( 'sha256', $user_agent . '|' . wp_salt( 'auth' ) ),
				'categories_json' => wp_json_encode( $state->categories() ),
				'consent_version' => $state->version(),
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}
