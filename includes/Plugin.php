<?php
/**
 * Plugin bootstrap.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner;

use KatsarovDesign\ConsentBanner\Admin\Assets as AdminAssets;
use KatsarovDesign\ConsentBanner\Admin\Menu;
use KatsarovDesign\ConsentBanner\Admin\SettingsPage;
use KatsarovDesign\ConsentBanner\Cli\SettingsCommand;
use KatsarovDesign\ConsentBanner\Frontend\Assets as FrontendAssets;
use KatsarovDesign\ConsentBanner\Frontend\BannerRenderer;
use KatsarovDesign\ConsentBanner\Frontend\Shortcode;
use KatsarovDesign\ConsentBanner\Rest\RestRouter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public const TEXT_DOMAIN = 'consent-banner';
	public const CAPABILITY  = 'manage_options';

	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( Menu::class, 'register' ) );
		add_action( 'admin_post_kdconsent_save_settings', array( SettingsPage::class, 'handle_save' ) );
		add_action( 'admin_post_kdconsent_export_settings', array( SettingsPage::class, 'handle_export' ) );
		add_action( 'admin_post_kdconsent_import_settings', array( SettingsPage::class, 'handle_import' ) );
		add_action( 'admin_enqueue_scripts', array( AdminAssets::class, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( FrontendAssets::class, 'enqueue' ) );
		add_action( 'wp_footer', array( BannerRenderer::class, 'render_container' ) );
		add_action( 'rest_api_init', array( RestRouter::class, 'register_routes' ) );
		add_action( 'init', array( Shortcode::class, 'register' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'consent-banner', SettingsCommand::class );
		}

		LegacyCompat::register();
		Installer::maybe_upgrade();
	}

	public function activate(): void {
		Installer::install();
	}

	public function deactivate(): void {
		// Intentionally left blank. Cookie decisions persist across deactivation.
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( KDCONSENT_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
