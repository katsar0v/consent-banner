<?php
/**
 * Shared REST controller helpers.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Rest;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Controller {
	/**
	 * @return array<string,mixed>
	 */
	protected function request_data( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( is_array( $json ) && ! empty( $json ) ) {
			return $json;
		}

		$params = $request->get_body_params();
		if ( is_array( $params ) && ! empty( $params ) ) {
			return $params;
		}

		$raw = $request->get_params();
		return is_array( $raw ) ? $raw : array();
	}

	protected function response( mixed $data = null, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}
}
