<?php
/**
 * Uninstall logic for Cookie Banner.
 *
 * @package KatsarovDesign\CookieBanner
 */

declare(strict_types=1);

use KatsarovDesign\CookieBanner\Installer;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Installer.php';

Installer::uninstall();
