<?php
/**
 * REST route registration.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Rest;

use KatsarovDesign\CookieBanner\Plugin;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestRouter {
	public const NAMESPACE = 'kdcb/v1';

	public static function register_routes(): void {
		$consent = new ConsentController();

		register_rest_route(
			self::NAMESPACE,
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
			self::NAMESPACE,
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
			self::NAMESPACE,
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
				'kdcb_rest_invalid_nonce',
				__( 'A valid REST nonce is required.', 'cookie-banner' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return new WP_Error(
				'kdcb_rest_forbidden',
				__( 'You are not allowed to manage cookie banner settings.', 'cookie-banner' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
