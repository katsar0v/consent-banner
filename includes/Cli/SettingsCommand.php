<?php
/**
 * WP-CLI settings import/export command.
 *
 * @package KatsarovDesign\ConsentBanner
 */

declare(strict_types=1);

namespace KatsarovDesign\ConsentBanner\Cli;

use KatsarovDesign\ConsentBanner\Service\SettingsTransfer;
use KatsarovDesign\ConsentBanner\Service\SettingsTransferException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsCommand {
	/**
	 * Export plugin settings to JSON.
	 *
	 * ## OPTIONS
	 *
	 * <destination>
	 * : File path to write, or "-" to print JSON to stdout.
	 *
	 * [--force]
	 * : Overwrite an existing destination file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp consent-banner export backup.json
	 *     wp consent-banner export - > backup.json
	 *
	 * @param list<string>         $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function export( array $args, array $assoc_args ): void {
		$destination = (string) ( $args[0] ?? '' );
		if ( '' === $destination ) {
			\WP_CLI::error( 'A destination file path or "-" is required.' );
		}

		try {
			$json = ( new SettingsTransfer() )->export_json();
		} catch ( SettingsTransferException $exception ) {
			\WP_CLI::error( $exception->getMessage() );
		}

		if ( '-' === $destination ) {
			\WP_CLI::line( $json );
			return;
		}

		if ( file_exists( $destination ) && ! isset( $assoc_args['force'] ) ) {
			\WP_CLI::error( 'Destination file already exists. Use --force to overwrite it.' );
		}

		$directory = dirname( $destination );
		if ( ! is_dir( $directory ) ) {
			\WP_CLI::error( 'Destination directory does not exist.' );
		}

		$bytes = file_put_contents( $destination, $json . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $bytes ) {
			\WP_CLI::error( 'Settings could not be written to the destination file.' );
		}

		\WP_CLI::success( 'Exported consent banner settings to ' . $destination . '.' );
	}

	/**
	 * Import plugin settings from JSON.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : JSON file path to import.
	 *
	 * [--replace]
	 * : Replace all settings instead of merging.
	 *
	 * [--[no-]bump-version]
	 * : Bump the consent version after import. Enabled by default.
	 *
	 * [--dry-run]
	 * : Validate and report the import action without updating options.
	 *
	 * ## EXAMPLES
	 *
	 *     wp consent-banner import backup.json
	 *     wp consent-banner import backup.json --replace
	 *     wp consent-banner import backup.json --no-bump-version
	 *     wp consent-banner import backup.json --dry-run
	 *
	 * @param list<string>         $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 */
	public function import( array $args, array $assoc_args ): void {
		$source = (string) ( $args[0] ?? '' );
		if ( '' === $source ) {
			\WP_CLI::error( 'A source JSON file path is required.' );
		}

		if ( ! is_readable( $source ) ) {
			\WP_CLI::error( 'Source JSON file does not exist or is not readable.' );
		}

		$json = file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $json ) ) {
			\WP_CLI::error( 'Source JSON file could not be read.' );
		}

		$replace_all          = isset( $assoc_args['replace'] );
		$bump_consent_version = ! array_key_exists( 'bump-version', $assoc_args ) || false !== $assoc_args['bump-version'];
		$dry_run              = isset( $assoc_args['dry-run'] );
		$transfer             = new SettingsTransfer();

		try {
			$result = $dry_run
				? $transfer->preview_import( $json, $replace_all, $bump_consent_version )
				: $transfer->import_json( $json, $replace_all, $bump_consent_version );
		} catch ( SettingsTransferException $exception ) {
			\WP_CLI::error( $exception->getMessage() );
		}

		$mode = $replace_all ? 'replace' : 'merge';
		if ( $dry_run ) {
			\WP_CLI::success(
				sprintf(
					'Dry run passed. Import mode: %1$s. Consent version: %2$d -> %3$d.',
					$mode,
					(int) $result['previousConsentVersion'],
					(int) $result['consentVersion']
				)
			);
			return;
		}

		\WP_CLI::success(
			sprintf(
				'Imported consent banner settings. Import mode: %1$s. Consent version: %2$d -> %3$d.',
				$mode,
				(int) $result['previousConsentVersion'],
				(int) $result['consentVersion']
			)
		);
	}
}
