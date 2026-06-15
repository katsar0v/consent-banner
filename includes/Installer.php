<?php
/**
 * Database and option installer.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {
	public const DB_VERSION                  = '0.2.0';
	public const OPTION_DB_VERSION           = 'kdconsent_db_version';
	public const OPTION_SETTINGS             = 'kdconsent_settings';
	public const OPTION_CONSENT_VERSION      = 'kdconsent_consent_version';
	public const OPTION_REMOVE_ON_UNINSTALL  = 'kdconsent_remove_on_uninstall';
	public const TABLE_CONSENT_LOG           = 'kdconsent_consent_log';

	public static function install(): void {
		self::migrate_legacy_data();
		self::create_tables();
		self::ensure_options();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		$current_version = (string) get_option( self::OPTION_DB_VERSION, '0' );

		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	public static function migrate_legacy_data(): void {
		self::copy_legacy_option( LegacyCompat::OPTION_SETTINGS, self::OPTION_SETTINGS );
		self::copy_legacy_option( LegacyCompat::OPTION_CONSENT_VERSION, self::OPTION_CONSENT_VERSION );
		self::copy_legacy_option( LegacyCompat::OPTION_DB_VERSION, self::OPTION_DB_VERSION );
		self::copy_legacy_option( LegacyCompat::OPTION_REMOVE_ON_UNINSTALL, self::OPTION_REMOVE_ON_UNINSTALL );
		self::rename_legacy_consent_log_table();
	}

	private static function copy_legacy_option( string $legacy_option, string $new_option ): void {
		if ( false !== get_option( $new_option, false ) ) {
			return;
		}

		$missing      = '__kdconsent_missing_option__';
		$legacy_value = get_option( $legacy_option, $missing );
		if ( $missing === $legacy_value ) {
			return;
		}

		add_option( $new_option, $legacy_value, '', false );
	}

	private static function rename_legacy_consent_log_table(): void {
		global $wpdb;

		$legacy_table = self::legacy_consent_log_table_name();
		$new_table    = self::consent_log_table_name();

		$legacy_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) );
		$new_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );

		if ( $legacy_table !== $legacy_exists || $new_table === $new_exists ) {
			return;
		}

		$wpdb->query( "RENAME TABLE `{$legacy_table}` TO `{$new_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::consent_log_table_name();

		dbDelta(
			"CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				consent_hash char(64) NOT NULL,
				ip_hash char(64) NOT NULL,
				user_agent_hash char(64) NOT NULL,
				categories_json longtext NOT NULL,
				consent_version int(11) unsigned NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY created_at (created_at),
				KEY consent_hash (consent_hash)
			) {$charset_collate};"
		);
	}

	public static function ensure_options(): void {
		if ( false === get_option( self::OPTION_SETTINGS, false ) ) {
			add_option( self::OPTION_SETTINGS, self::default_settings(), '', false );
		}

		if ( false === get_option( self::OPTION_CONSENT_VERSION, false ) ) {
			add_option( self::OPTION_CONSENT_VERSION, 1, '', false );
		}

		if ( false === get_option( self::OPTION_REMOVE_ON_UNINSTALL, false ) ) {
			add_option( self::OPTION_REMOVE_ON_UNINSTALL, false, '', false );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		$default_categories = array(
			array(
				'id'               => 'essential',
				'label'            => 'Essential',
				'description'      => 'Required for basic website functionality.',
				'required'         => true,
				'enabledByDefault' => true,
			),
			array(
				'id'               => 'analytics',
				'label'            => 'Analytics',
				'description'      => 'Helps us understand website traffic and usage.',
				'required'         => false,
				'enabledByDefault' => false,
			),
			array(
				'id'               => 'marketing',
				'label'            => 'Marketing',
				'description'      => 'Used to personalize advertising and campaigns.',
				'required'         => false,
				'enabledByDefault' => false,
			),
		);

		$default_categories = apply_filters( LegacyCompat::DEFAULT_CATEGORIES_FILTER, $default_categories );
		if ( ! is_array( $default_categories ) ) {
			$default_categories = array();
		}

		return array(
			'categories'          => apply_filters( 'kdconsent_default_categories', $default_categories ),
			'texts'               => array(
				'en_US' => array(
					'bannerTitle'      => 'We use cookies',
					'bannerBody'       => 'We use cookies to improve your experience. You can accept all, reject non-essential cookies, or customize your choices.',
					'acceptAllLabel'   => 'Accept all',
					'rejectAllLabel'   => 'Reject all',
					'customizeLabel'   => 'Customize',
					'saveLabel'        => 'Save preferences',
					'closeLabel'       => 'Close',
					'preferencesTitle' => 'Cookie preferences',
				),
				'bg_BG' => array(
					'bannerTitle'      => 'Използваме бисквитки',
					'bannerBody'       => 'Използваме бисквитки, за да подобрим вашето изживяване. Може да приемете всички, да откажете неесенциалните или да персонализирате избора си.',
					'acceptAllLabel'   => 'Приеми всички',
					'rejectAllLabel'   => 'Откажи всички',
					'customizeLabel'   => 'Персонализирай',
					'saveLabel'        => 'Запази предпочитанията',
					'closeLabel'       => 'Затвори',
					'preferencesTitle' => 'Предпочитания за бисквитки',
				),
				'de_DE' => array(
					'bannerTitle'      => 'Wir verwenden Cookies',
					'bannerBody'       => 'Wir verwenden Cookies, um Ihr Nutzererlebnis zu verbessern. Sie können alle akzeptieren, nicht notwendige Cookies ablehnen oder Ihre Auswahl anpassen.',
					'acceptAllLabel'   => 'Alle akzeptieren',
					'rejectAllLabel'   => 'Alle ablehnen',
					'customizeLabel'   => 'Anpassen',
					'saveLabel'        => 'Einstellungen speichern',
					'closeLabel'       => 'Schließen',
					'preferencesTitle' => 'Cookie-Einstellungen',
				),
			),
			'styles'              => array(
				'backdrop' => array(
					'color'   => '#000000',
					'opacity' => 0.45,
				),
				'buttons'  => array(
					'accept'    => array(
						'background'      => '#4BAD27',
						'text'            => '#FFFFFF',
						'border'          => '#4BAD27',
						'hoverBackground' => '#FFFFFF',
						'hoverText'       => '#4BAD27',
						'hoverBorder'     => '#4BAD27',
					),
					'reject'    => array(
						'background'      => '#FFFFFF',
						'text'            => '#363636',
						'border'          => '#A9E2B6',
						'hoverBackground' => '#F0FDF4',
						'hoverText'       => '#4BAD27',
						'hoverBorder'     => '#4BAD27',
					),
					'customize' => array(
						'background'      => '#F0FDF4',
						'text'            => '#509860',
						'border'          => '#D6F3DF',
						'hoverBackground' => '#CEF6D7',
						'hoverText'       => '#363636',
						'hoverBorder'     => '#A9E2B6',
					),
					'save'      => array(
						'background'      => '#4BAD27',
						'text'            => '#FFFFFF',
						'border'          => '#4BAD27',
						'hoverBackground' => '#FFFFFF',
						'hoverText'       => '#4BAD27',
						'hoverBorder'     => '#4BAD27',
					),
					'close'     => array(
						'background'      => '#FFFFFF',
						'text'            => '#363636',
						'border'          => '#A9E2B6',
						'hoverBackground' => '#F0FDF4',
						'hoverText'       => '#4BAD27',
						'hoverBorder'     => '#4BAD27',
					),
				),
			),
			'consentLifetimeDays' => 180,
			'position'            => 'bottom',
			'animation'           => 'fade-in',
			'showDelayMs'         => 0,
			'theme'               => 'light',
			'showRejectButton'    => true,
			'enableConsentLog'    => false,
			'removeOnUninstall'   => false,
		);
	}

	public static function consent_log_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_CONSENT_LOG;
	}

	public static function legacy_consent_log_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . LegacyCompat::TABLE_CONSENT_LOG;
	}

	public static function uninstall(): void {
		if ( ! self::should_remove_on_uninstall() ) {
			return;
		}

		self::drop_tables();
		self::delete_options();
	}

	public static function should_remove_on_uninstall(): bool {
		$settings = get_option( self::OPTION_SETTINGS, array() );
		$setting  = is_array( $settings ) ? (bool) ( $settings['removeOnUninstall'] ?? false ) : false;

		return (bool) get_option( self::OPTION_REMOVE_ON_UNINSTALL, false ) || $setting;
	}

	public static function drop_tables(): void {
		global $wpdb;

		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::consent_log_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::legacy_consent_log_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function delete_options(): void {
		delete_option( self::OPTION_DB_VERSION );
		delete_option( self::OPTION_SETTINGS );
		delete_option( self::OPTION_CONSENT_VERSION );
		delete_option( self::OPTION_REMOVE_ON_UNINSTALL );
		delete_option( LegacyCompat::OPTION_DB_VERSION );
		delete_option( LegacyCompat::OPTION_SETTINGS );
		delete_option( LegacyCompat::OPTION_CONSENT_VERSION );
		delete_option( LegacyCompat::OPTION_REMOVE_ON_UNINSTALL );
	}
}
