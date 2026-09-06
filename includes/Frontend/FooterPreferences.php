<?php
/**
 * Optional automatic footer preferences control.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Frontend;

use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FooterPreferences {
	public static function render(): void {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$settings = ( new SettingsRepository() )->get();
		if ( empty( $settings['autoFooterPreferences'] ) ) {
			return;
		}

		echo '<div class="kdconsent-footer-preferences">';
		echo Shortcode::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode escapes its label.
		echo '</div>';
	}
}
