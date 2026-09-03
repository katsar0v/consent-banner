<?php
/**
 * Uninstall logic for Consent Banner.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/LegacyCompat.php';
require_once __DIR__ . '/includes/Installer.php';

\KatsarovDesign\ConsentBanner\Installer::uninstall();
