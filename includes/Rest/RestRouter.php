<?php
/**
 * REST route registration.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Rest;

use KatsarovDesign\ConsentBanner\LegacyCompat;
use KatsarovDesign\ConsentBanner\Plugin;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestRouter {
	public const NAMESPACE = 'kdconsent/v1';

	public static function register_routes(): void {
		$consent = new ConsentController();
		self::register_namespace( self::NAMESPACE, $consent );
		self::register_namespace( LegacyCompat::REST_NAMESPACE, $consent );
	}

	private static function register_namespace( string $route_namespace, ConsentController $consent ): void {
		register_rest_route(
			$route_namespace,
			'/config',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $consent, 'config' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$route_namespace,
			'/consent',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $consent, 'save_consent' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$route_namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $consent, 'get_settings' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $consent, 'update_settings' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
				),
			)
		);
	}

	public static function admin_permission( WP_REST_Request $request ): true|WP_Error {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'kdconsent_rest_invalid_nonce',
				__( 'A valid REST nonce is required.', 'consent-banner' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return new WP_Error(
				'kdconsent_rest_forbidden',
				__( 'You are not allowed to manage consent banner settings.', 'consent-banner' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
