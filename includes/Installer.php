<?php
/**
 * Database and option installer.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\CookieBanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {
	public const DB_VERSION                  = '0.1.0';
	public const OPTION_DB_VERSION           = 'kdcb_db_version';
	public const OPTION_SETTINGS             = 'kdcb_settings';
	public const OPTION_CONSENT_VERSION      = 'kdcb_consent_version';
	public const OPTION_REMOVE_ON_UNINSTALL  = 'kdcb_remove_on_uninstall';
	public const TABLE_CONSENT_LOG           = 'kdcb_consent_log';

	public static function install(): void {
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

		return array(
			'categories'          => apply_filters( 'kdcb_default_categories', $default_categories ),
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
			),
			'styles'              => array(
				'backdrop' => array(
					'color'   => '#000000',
					'opacity' => 0.45,
				),
				'buttons'  => array(
					'accept'    => array(
						'background'      => '#2563EB',
						'text'            => '#FFFFFF',
						'border'          => '#2563EB',
						'hoverBackground' => '#1D4ED8',
						'hoverText'       => '#FFFFFF',
						'hoverBorder'     => '#1D4ED8',
					),
					'reject'    => array(
						'background'      => '#F3F4F6',
						'text'            => '#111827',
						'border'          => '#C0C6CC',
						'hoverBackground' => '#E5E7EB',
						'hoverText'       => '#111827',
						'hoverBorder'     => '#9CA3AF',
					),
					'customize' => array(
						'background'      => '#FFFFFF',
						'text'            => '#1F2328',
						'border'          => '#C0C6CC',
						'hoverBackground' => '#F8FAFC',
						'hoverText'       => '#111827',
						'hoverBorder'     => '#94A3B8',
					),
					'save'      => array(
						'background'      => '#2563EB',
						'text'            => '#FFFFFF',
						'border'          => '#2563EB',
						'hoverBackground' => '#1D4ED8',
						'hoverText'       => '#FFFFFF',
						'hoverBorder'     => '#1D4ED8',
					),
					'close'     => array(
						'background'      => '#F3F4F6',
						'text'            => '#111827',
						'border'          => '#C0C6CC',
						'hoverBackground' => '#E5E7EB',
						'hoverText'       => '#111827',
						'hoverBorder'     => '#9CA3AF',
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
	}

	public static function delete_options(): void {
		delete_option( self::OPTION_DB_VERSION );
		delete_option( self::OPTION_SETTINGS );
		delete_option( self::OPTION_CONSENT_VERSION );
		delete_option( self::OPTION_REMOVE_ON_UNINSTALL );
	}
}
