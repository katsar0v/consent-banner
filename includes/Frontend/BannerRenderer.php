<?php
/**
 * Banner root rendering.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BannerRenderer {
	public static function render_container(): void {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		echo '<div id="kdcb-banner-root"></div>';
	}
}
