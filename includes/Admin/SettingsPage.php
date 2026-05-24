<?php
/**
 * Settings page controller.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Admin;

use KatsarovDesign\ConsentBanner\Domain\Category;
use KatsarovDesign\ConsentBanner\Installer;
use KatsarovDesign\ConsentBanner\LegacyCompat;
use KatsarovDesign\ConsentBanner\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {
	private const EXPORT_NONCE_ACTION      = 'kdconsent_export_settings';
	private const EXPORT_NONCE_FIELD       = 'kdconsent_export_nonce';
	private const IMPORT_NONCE_ACTION      = 'kdconsent_import_settings';
	private const IMPORT_NONCE_FIELD       = 'kdconsent_import_nonce';
	private const MAX_IMPORT_FILE_BYTES    = 1048576;
	private const EXPORT_SCHEMA_VERSION    = 1;
	private const EXPORT_PLUGIN_IDENTIFIER = 'katsarovdesign/consent-banner';
	private const IMPORT_FILE_FIELD        = 'kdconsent_import_file';
	private const SETTINGS_KEYS            = array(
		'categories',
		'texts',
		'styles',
		'consentLifetimeDays',
		'position',
		'animation',
		'showDelayMs',
		'theme',
		'showRejectButton',
		'enableConsentLog',
		'removeOnUninstall',
	);

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

	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::redirect_to_export_import_tab( 'permission-denied' );
		}

		$nonce = isset( $_POST[ self::EXPORT_NONCE_FIELD ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::EXPORT_NONCE_FIELD ] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::EXPORT_NONCE_ACTION ) ) {
			self::redirect_to_export_import_tab( 'invalid-nonce' );
		}

		$settings_repository = new SettingsRepository();
		$payload             = array(
			'schemaVersion'  => self::EXPORT_SCHEMA_VERSION,
			'plugin'         => self::EXPORT_PLUGIN_IDENTIFIER,
			'pluginVersion'  => defined( 'KDCONSENT_PLUGIN_VERSION' ) ? KDCONSENT_PLUGIN_VERSION : '',
			'exportedAt'     => gmdate( 'c' ),
			'consentVersion' => (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 ),
			'settings'       => $settings_repository->get(),
		);
		$json                = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $json ) ) {
			self::redirect_to_export_import_tab( 'export-failed' );
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="consent-banner-settings-' . gmdate( 'Y-m-d-His' ) . '.json"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			self::redirect_to_export_import_tab( 'permission-denied' );
		}

		$nonce = isset( $_POST[ self::IMPORT_NONCE_FIELD ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::IMPORT_NONCE_FIELD ] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::IMPORT_NONCE_ACTION ) ) {
			self::redirect_to_export_import_tab( 'invalid-nonce' );
		}

		$upload_error = isset( $_FILES[ self::IMPORT_FILE_FIELD ]['error'] )
			? absint( wp_unslash( $_FILES[ self::IMPORT_FILE_FIELD ]['error'] ) )
			: UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $upload_error ) {
			self::redirect_to_export_import_tab( 'missing-file' );
		}

		if ( UPLOAD_ERR_OK !== $upload_error ) {
			self::redirect_to_export_import_tab( 'upload-error' );
		}

		$file_size = isset( $_FILES[ self::IMPORT_FILE_FIELD ]['size'] )
			? absint( wp_unslash( $_FILES[ self::IMPORT_FILE_FIELD ]['size'] ) )
			: 0;
		if ( $file_size <= 0 ) {
			self::redirect_to_export_import_tab( 'missing-file' );
		}

		if ( self::MAX_IMPORT_FILE_BYTES < $file_size ) {
			self::redirect_to_export_import_tab( 'file-too-large' );
		}

		$tmp_name = isset( $_FILES[ self::IMPORT_FILE_FIELD ]['tmp_name'] )
			? sanitize_text_field( wp_unslash( $_FILES[ self::IMPORT_FILE_FIELD ]['tmp_name'] ) )
			: '';
		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) || ! is_readable( $tmp_name ) ) {
			self::redirect_to_export_import_tab( 'upload-error' );
		}

		$json = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $json ) ) {
			self::redirect_to_export_import_tab( 'upload-error' );
		}

		$decoded = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			self::redirect_to_export_import_tab( 'invalid-json' );
		}

		$imported_settings = self::extract_import_settings( $decoded );
		if ( null === $imported_settings ) {
			self::redirect_to_export_import_tab( 'missing-settings' );
		}

		$settings_repository = new SettingsRepository();
		$replace_all         = ! empty( $_POST['replaceAllSettings'] );
		$settings            = $replace_all
			? $imported_settings
			: self::merge_settings( $settings_repository->get(), $imported_settings );

		$settings_repository->update( $settings );

		if ( ! empty( $_POST['bumpConsentVersion'] ) ) {
			$current_version = (int) get_option( Installer::OPTION_CONSENT_VERSION, 1 );
			update_option( Installer::OPTION_CONSENT_VERSION, $current_version + 1, false );
		}

		self::redirect_to_export_import_tab( 'imported' );
	}

	/**
	 * @param array<string,mixed> $payload Decoded JSON payload.
	 * @return array<string,mixed>|null
	 */
	private static function extract_import_settings( array $payload ): ?array {
		if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			return $payload['settings'];
		}

		foreach ( self::SETTINGS_KEYS as $setting_key ) {
			if ( array_key_exists( $setting_key, $payload ) ) {
				return $payload;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $imported
	 * @return array<string,mixed>
	 */
	private static function merge_settings( array $current, array $imported ): array {
		$merged = $current;

		foreach ( self::SETTINGS_KEYS as $setting_key ) {
			if ( ! array_key_exists( $setting_key, $imported ) ) {
				continue;
			}

			if ( 'categories' === $setting_key ) {
				$merged['categories'] = self::merge_categories(
					is_array( $current['categories'] ?? null ) ? $current['categories'] : array(),
					is_array( $imported['categories'] ) ? $imported['categories'] : array()
				);
				continue;
			}

			if ( in_array( $setting_key, array( 'texts', 'styles' ), true ) ) {
				$merged[ $setting_key ] = self::merge_recursive_settings(
					is_array( $current[ $setting_key ] ?? null ) ? $current[ $setting_key ] : array(),
					is_array( $imported[ $setting_key ] ) ? $imported[ $setting_key ] : array()
				);
				continue;
			}

			$merged[ $setting_key ] = $imported[ $setting_key ];
		}

		return $merged;
	}

	/**
	 * @param array<int|string,mixed> $current
	 * @param array<int|string,mixed> $imported
	 * @return list<array<string,mixed>>
	 */
	private static function merge_categories( array $current, array $imported ): array {
		$category_order = array();
		$categories     = array();

		foreach ( $current as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$category = Category::from_array( $item );
			$id       = $category->id();
			if ( isset( $categories[ $id ] ) ) {
				continue;
			}

			$category_order[]  = $id;
			$categories[ $id ] = $category->to_array();
		}

		foreach ( $imported as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$category = Category::from_array( $item );
			$id       = $category->id();
			if ( ! isset( $categories[ $id ] ) ) {
				$category_order[] = $id;
			}

			$categories[ $id ] = $category->to_array();
		}

		$merged = array();
		foreach ( $category_order as $id ) {
			$merged[] = $categories[ $id ];
		}

		return $merged;
	}

	/**
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $imported
	 * @return array<string,mixed>
	 */
	private static function merge_recursive_settings( array $current, array $imported ): array {
		foreach ( $imported as $key => $value ) {
			if ( is_array( $value ) && isset( $current[ $key ] ) && is_array( $current[ $key ] ) ) {
				$current[ $key ] = self::merge_recursive_settings( $current[ $key ], $value );
				continue;
			}

			$current[ $key ] = $value;
		}

		return $current;
	}

	private static function redirect_to_export_import_tab( string $notice ): void {
		wp_safe_redirect(
			Menu::settings_url(
				array(
					'kdconsent_notice' => $notice,
					'tab'              => 'export-import',
				)
			)
		);
		exit;
	}
}
