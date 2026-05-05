<?php
/**
 * Consent log persistence.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Repository;

use KatsarovDesign\CookieBanner\Domain\ConsentState;
use KatsarovDesign\CookieBanner\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ConsentLogRepository {
	public function insert( ConsentState $state ): void {
		global $wpdb;

		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
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
