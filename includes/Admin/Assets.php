<?php
/**
 * Admin asset enqueuer.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Admin;

use KatsarovDesign\ConsentBanner\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {
	public static function enqueue( string $hook_suffix ): void {
		if ( ! Menu::is_plugin_page() ) {
			return;
		}

		wp_enqueue_style( 'kdconsent-admin', KDCONSENT_PLUGIN_URL . 'assets/css/admin.css', array(), KDCONSENT_PLUGIN_VERSION );
		wp_enqueue_script( 'kdconsent-admin', KDCONSENT_PLUGIN_URL . 'assets/js/admin.js', array(), KDCONSENT_PLUGIN_VERSION, true );
		wp_localize_script(
			'kdconsent-admin',
			'kdconsentAdmin',
			array(
				'addCategoryLabel' => __( 'Add category', 'consent-banner' ),
			)
		);
		wp_set_script_translations( 'kdconsent-admin', Plugin::TEXT_DOMAIN, KDCONSENT_PLUGIN_DIR . 'languages' );
	}
}
