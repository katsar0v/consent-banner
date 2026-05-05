<?php
/**
 * Settings page controller.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner\Admin;

use KatsarovDesign\CookieBanner\Installer;
use KatsarovDesign\CookieBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'cookie-banner' ) );
		}

		$settings_repository = new SettingsRepository();
		$settings            = $settings_repository->get();
		$tabs                = Menu::tabs();
		$current_tab         = Menu::current_tab();

		foreach ( $tabs as $tab_key => $tab_config ) {
			if ( ! empty( $tab_config['enabled'] ) ) {
				$tabs[ $tab_key ]['url'] = Menu::settings_url( array( 'tab' => $tab_key ) );
			}
		}

		$consent_version     = (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 );
		$notice              = isset( $_GET['kdcb_notice'] ) ? sanitize_key( wp_unslash( $_GET['kdcb_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$view = KDCB_PLUGIN_DIR . 'views/settings.php';
		if ( ! is_readable( $view ) ) {
			wp_die( esc_html__( 'View not found.', 'cookie-banner' ) );
		}

		require $view;
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this page.', 'cookie-banner' ) );
		}

		check_admin_referer( 'kdcb_save_settings', 'kdcb_settings_nonce' );

		$current_tab = isset( $_POST['kdcb_current_tab'] )
			? sanitize_key( wp_unslash( $_POST['kdcb_current_tab'] ) )
			: Menu::DEFAULT_TAB;
		$current_tab         = Menu::normalize_tab( $current_tab );
		$settings_repository = new SettingsRepository();
		$settings            = $settings_repository->get();

		if ( 'general' === $current_tab ) {
			$settings['categories'] = isset( $_POST['categories'] ) && is_array( $_POST['categories'] )
				? (array) wp_unslash( $_POST['categories'] )
				: array();
			$settings['consentLifetimeDays'] = isset( $_POST['consentLifetimeDays'] )
				? absint( wp_unslash( $_POST['consentLifetimeDays'] ) )
				: (int) ( $settings['consentLifetimeDays'] ?? 180 );
			$settings['showRejectButton']  = ! empty( $_POST['showRejectButton'] );
			$settings['enableConsentLog']  = ! empty( $_POST['enableConsentLog'] );
			$settings['removeOnUninstall'] = ! empty( $_POST['removeOnUninstall'] );
		}

		if ( 'appearance' === $current_tab ) {
			$settings['texts'] = isset( $_POST['texts'] ) && is_array( $_POST['texts'] )
				? (array) wp_unslash( $_POST['texts'] )
				: array();
			$settings['styles'] = isset( $_POST['styles'] ) && is_array( $_POST['styles'] )
				? (array) wp_unslash( $_POST['styles'] )
				: array();
			$settings['animation'] = isset( $_POST['animation'] )
				? sanitize_key( wp_unslash( $_POST['animation'] ) )
				: (string) ( $settings['animation'] ?? 'fade-in' );
			$settings['showDelayMs'] = isset( $_POST['showDelayMs'] )
				? absint( wp_unslash( $_POST['showDelayMs'] ) )
				: (int) ( $settings['showDelayMs'] ?? 0 );
			$settings['position'] = isset( $_POST['position'] )
				? sanitize_key( wp_unslash( $_POST['position'] ) )
				: (string) ( $settings['position'] ?? 'bottom' );
		}

		$settings_repository->update( $settings );

		if ( ! empty( $_POST['bumpConsentVersion'] ) ) {
			$current_version = (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 );
			update_option( Installer::OPTION_CONSENT_VERSION, $current_version + 1, false );
		}

		wp_safe_redirect(
			Menu::settings_url(
				array(
					'kdcb_notice' => 'saved',
					'tab'         => $current_tab,
				)
			)
		);
		exit;
	}
}
