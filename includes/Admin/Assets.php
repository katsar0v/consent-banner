<?php
/**
 * Admin asset enqueuer.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Admin;

use KatsarovDesign\CookieBanner\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	public static function enqueue( string $hook_suffix ): void {
		if ( ! Menu::is_plugin_page() ) {
			return;
		}

		wp_enqueue_style( 'kdcb-admin', KDCB_PLUGIN_URL . 'assets/css/admin.css', array(), KDCB_PLUGIN_VERSION );
		wp_enqueue_script( 'kdcb-admin', KDCB_PLUGIN_URL . 'assets/js/admin.js', array(), KDCB_PLUGIN_VERSION, true );
		wp_localize_script(
			'kdcb-admin',
			'kdcbAdmin',
			array(
				'addCategoryLabel' => __( 'Add category', 'cookie-banner' ),
			)
		);
		wp_set_script_translations( 'kdcb-admin', Plugin::TEXT_DOMAIN, KDCB_PLUGIN_DIR . 'languages' );
	}
}
