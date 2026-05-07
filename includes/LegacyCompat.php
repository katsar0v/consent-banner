<?php
/**
 * Deprecated legacy compatibility shims.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner;

use KatsarovDesign\ConsentBanner\Admin\Menu;
use KatsarovDesign\ConsentBanner\Admin\SettingsPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LegacyCompat {
	public const VERSION                 = '0.2.0';
	public const REST_NAMESPACE          = 'kdcb/v1';
	public const SHORTCODE               = 'kdcb_preferences';
	public const OPEN_PREFERENCES_CLASS  = 'kdcb-open-preferences';
	public const OPTION_DB_VERSION       = 'kdcb_db_version';
	public const OPTION_SETTINGS         = 'kdcb_settings';
	public const OPTION_CONSENT_VERSION  = 'kdcb_consent_version';
	public const OPTION_REMOVE_ON_UNINSTALL = 'kdcb_remove_on_uninstall';
	public const TABLE_CONSENT_LOG       = 'kdcb_consent_log';
	public const COOKIE_NAME             = 'kdcb_consent';
	public const ADMIN_SAVE_ACTION       = 'kdcb_save_settings';
	public const SETTINGS_NONCE          = 'kdcb_settings_nonce';
	public const CURRENT_TAB_FIELD       = 'kdcb_current_tab';
	public const DEFAULT_CATEGORIES_FILTER = 'kdcb_default_categories';
	public const CATEGORIES_FILTER       = 'kdcb_categories';
	public const CONSENT_RECORDED_ACTION = 'kdcb_consent_recorded';

	private function __construct() {}

	public static function register(): void {
		add_action( 'admin_post_' . self::ADMIN_SAVE_ACTION, array( SettingsPage::class, 'handle_save' ) );
		add_action( 'admin_init', array( self::class, 'redirect_legacy_settings_page' ) );
	}

	public static function redirect_legacy_settings_page(): void {
		if ( ! is_admin() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::settings_page_slug() !== $page ) {
			return;
		}

		$query_args = array();
		if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query_args['tab'] = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		wp_safe_redirect( Menu::settings_url( $query_args ) );
		exit;
	}

	public static function deprecated_function( string $function_name, string $replacement ): void {
		if ( function_exists( '_deprecated_function' ) ) {
			_deprecated_function( esc_html( $function_name ), esc_html( self::VERSION ), esc_html( $replacement ) );
		}
	}

	public static function settings_page_slug(): string {
		return implode( '-', array( 'kdcb', 'cookie', 'banner' ) );
	}

	public static function deprecated_shortcode( string $shortcode, string $replacement ): void {
		if ( function_exists( '_deprecated_argument' ) ) {
			_deprecated_argument(
				'shortcode',
				esc_html( self::VERSION ),
				sprintf(
					/* translators: 1: deprecated shortcode, 2: replacement shortcode. */
					esc_html__( '%1$s is deprecated. Use %2$s instead.', 'consent-banner' ),
					'[' . esc_html( $shortcode ) . ']',
					'[' . esc_html( $replacement ) . ']'
				)
			);
		}
	}
}
