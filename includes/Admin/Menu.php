<?php
/**
 * Admin menu registration.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Admin;

use KatsarovDesign\ConsentBanner\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {
	public const PAGE_SLUG = 'kdconsent-consent-banner';
	public const DEFAULT_TAB = 'general';

	/**
	 * @return array<string,array{label:string,enabled:bool}>
	 */
	public static function tabs(): array {
		return array(
			'general'          => array(
				'label'   => __( 'General settings', 'consent-banner' ),
				'enabled' => true,
			),
			'appearance'       => array(
				'label'   => __( 'Appearance', 'consent-banner' ),
				'enabled' => true,
			),
			'external-scripts' => array(
				'label'   => __( 'External scripts', 'consent-banner' ),
				'enabled' => false,
			),
			'tcf'              => array(
				'label'   => __( 'TCF', 'consent-banner' ),
				'enabled' => false,
			),
		);
	}

	public static function normalize_tab( string $tab ): string {
		$tabs = self::tabs();

		if ( ! isset( $tabs[ $tab ] ) || empty( $tabs[ $tab ]['enabled'] ) ) {
			return self::DEFAULT_TAB;
		}

		return $tab;
	}

	public static function current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : self::DEFAULT_TAB; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return self::normalize_tab( $tab );
	}

	public static function register(): void {
		add_options_page(
			__( 'Consent Banner', 'consent-banner' ),
			__( 'Consent Banner', 'consent-banner' ),
			Plugin::CAPABILITY,
			self::PAGE_SLUG,
			array( SettingsPage::class, 'render' )
		);
	}

	public static function is_plugin_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return self::PAGE_SLUG === $page;
	}

	public static function settings_url( array $query_args = array() ): string {
		$query_args = array_merge(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => self::DEFAULT_TAB,
			),
			$query_args
		);

		if ( isset( $query_args['tab'] ) ) {
			$query_args['tab'] = self::normalize_tab( sanitize_key( (string) $query_args['tab'] ) );
		}

		return add_query_arg( $query_args, admin_url( 'options-general.php' ) );
	}
}
