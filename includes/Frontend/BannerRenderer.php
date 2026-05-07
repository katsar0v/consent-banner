<?php
/**
 * Banner root rendering.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BannerRenderer {
	public static function render_container(): void {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		echo '<div id="kdconsent-banner-root"></div>';
	}
}
