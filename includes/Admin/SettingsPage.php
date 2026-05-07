<?php
/**
 * Settings page controller.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Admin;

use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\LegacyCompat;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'consent-banner' ) );
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
		$notice              = isset( $_GET['kdconsent_notice'] ) ? sanitize_key( wp_unslash( $_GET['kdconsent_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$view = KDCONSENT_PLUGIN_DIR . 'views/settings.php';
		if ( ! is_readable( $view ) ) {
			wp_die( esc_html__( 'View not found.', 'consent-banner' ) );
		}

		require $view;
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this page.', 'consent-banner' ) );
		}

		$is_legacy_save = isset( $_POST['action'] ) && LegacyCompat::ADMIN_SAVE_ACTION === sanitize_key( wp_unslash( $_POST['action'] ) );
		if ( $is_legacy_save ) {
			check_admin_referer( LegacyCompat::ADMIN_SAVE_ACTION, LegacyCompat::SETTINGS_NONCE );
		} else {
			check_admin_referer( 'kdconsent_save_settings', 'kdconsent_settings_nonce' );
		}

		$current_tab = isset( $_POST['kdconsent_current_tab'] )
			? sanitize_key( wp_unslash( $_POST['kdconsent_current_tab'] ) )
			: Menu::DEFAULT_TAB;
		if ( $is_legacy_save && isset( $_POST[ LegacyCompat::CURRENT_TAB_FIELD ] ) ) {
			$current_tab = sanitize_key( wp_unslash( $_POST[ LegacyCompat::CURRENT_TAB_FIELD ] ) );
		}
		$current_tab         = Menu::normalize_tab( $current_tab );
		$settings_repository = new SettingsRepository();
		$settings            = $settings_repository->get();

		if ( 'general' === $current_tab ) {
			$settings['categories'] = isset( $_POST['categories'] ) && is_array( $_POST['categories'] )
				? (array) map_deep( wp_unslash( $_POST['categories'] ), 'sanitize_text_field' )
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
				? (array) map_deep( wp_unslash( $_POST['texts'] ), 'sanitize_textarea_field' )
				: array();
			$settings['styles'] = isset( $_POST['styles'] ) && is_array( $_POST['styles'] )
				? (array) map_deep( wp_unslash( $_POST['styles'] ), 'sanitize_text_field' )
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
					'kdconsent_notice' => 'saved',
					'tab'         => $current_tab,
				)
			)
		);
		exit;
	}
}
