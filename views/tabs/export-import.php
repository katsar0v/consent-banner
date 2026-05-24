<?php
/**
 * Export / Import settings tab view.
 *
 * @var int $consent_version
 *
 * @package KatsarovDesign\ConsentBanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php echo esc_html__( 'Export settings', 'consent-banner' ); ?></h2>
<p><?php echo esc_html__( 'Download the current plugin configuration as a JSON file.', 'consent-banner' ); ?></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdconsent-export-form">
	<?php wp_nonce_field( 'kdconsent_export_settings', 'kdconsent_export_nonce' ); ?>
	<input type="hidden" name="action" value="kdconsent_export_settings">
	<?php submit_button( __( 'Export JSON', 'consent-banner' ), 'secondary', 'submit', false ); ?>
</form>

<hr>

<h2><?php echo esc_html__( 'Import settings', 'consent-banner' ); ?></h2>
<p><?php echo esc_html__( 'Upload a JSON export to merge or replace this plugin configuration.', 'consent-banner' ); ?></p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="kdconsent-import-form">
	<?php wp_nonce_field( 'kdconsent_import_settings', 'kdconsent_import_nonce' ); ?>
	<input type="hidden" name="action" value="kdconsent_import_settings">

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="kdconsent-import-file"><?php echo esc_html__( 'JSON file', 'consent-banner' ); ?></label>
				</th>
				<td>
					<input
						type="file"
						id="kdconsent-import-file"
						name="kdconsent_import_file"
						accept="application/json,.json"
						required
					>
					<p class="description"><?php echo esc_html__( 'Maximum file size: 1 MB.', 'consent-banner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Import mode', 'consent-banner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="replaceAllSettings" value="1">
						<?php echo esc_html__( 'Replace all settings instead of merging', 'consent-banner' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'By default, imported values merge into the current settings.', 'consent-banner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Consent version', 'consent-banner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bumpConsentVersion" value="1" <?php checked( true ); ?>>
						<?php echo esc_html__( 'Bump consent version after import', 'consent-banner' ); ?>
					</label>
					<p class="description"><?php echo esc_html__( 'Current consent version:', 'consent-banner' ); ?> <?php echo esc_html( (string) $consent_version ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button( __( 'Import JSON', 'consent-banner' ), 'primary', 'submit', false ); ?>
</form>
