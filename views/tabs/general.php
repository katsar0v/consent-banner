<?php
/**
 * General settings tab view.
 *
 * @var array<string,mixed> $settings
 * @var int                 $consent_version
 *
 * @package KatsarovDesign\ConsentBanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$categories = is_array( $settings['categories'] ?? null ) ? $settings['categories'] : array();
?>
<h2><?php echo esc_html__( 'Categories', 'consent-banner' ); ?></h2>
<p><?php echo esc_html__( 'Essential cookies are always enabled and cannot be disabled.', 'consent-banner' ); ?></p>

<table class="widefat fixed striped" id="kdconsent-categories-table">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'ID', 'consent-banner' ); ?></th>
			<th><?php echo esc_html__( 'Label', 'consent-banner' ); ?></th>
			<th><?php echo esc_html__( 'Description', 'consent-banner' ); ?></th>
			<th><?php echo esc_html__( 'Required', 'consent-banner' ); ?></th>
			<th><?php echo esc_html__( 'Default enabled', 'consent-banner' ); ?></th>
			<th><?php echo esc_html__( 'Action', 'consent-banner' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $categories as $index => $category ) : ?>
			<?php
			$category_id = sanitize_key( (string) ( $category['id'] ?? '' ) );
			$is_required = ! empty( $category['required'] ) || 'essential' === $category_id;
			$is_enabled  = ! empty( $category['enabledByDefault'] ) || 'essential' === $category_id;
			?>
			<tr>
				<td>
					<input type="text" name="categories[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $category_id ); ?>" <?php echo 'essential' === $category_id ? 'readonly' : ''; ?>>
				</td>
				<td>
					<input type="text" name="categories[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) ( $category['label'] ?? '' ) ); ?>">
				</td>
				<td>
					<input type="text" name="categories[<?php echo esc_attr( (string) $index ); ?>][description]" value="<?php echo esc_attr( (string) ( $category['description'] ?? '' ) ); ?>">
				</td>
				<td>
					<input type="checkbox" name="categories[<?php echo esc_attr( (string) $index ); ?>][required]" value="1" <?php checked( $is_required ); ?> <?php disabled( 'essential' === $category_id ); ?>>
					<?php if ( 'essential' === $category_id ) : ?>
						<input type="hidden" name="categories[<?php echo esc_attr( (string) $index ); ?>][required]" value="1">
					<?php endif; ?>
				</td>
				<td>
					<input type="checkbox" name="categories[<?php echo esc_attr( (string) $index ); ?>][enabledByDefault]" value="1" <?php checked( $is_enabled ); ?> <?php disabled( 'essential' === $category_id ); ?>>
					<?php if ( 'essential' === $category_id ) : ?>
						<input type="hidden" name="categories[<?php echo esc_attr( (string) $index ); ?>][enabledByDefault]" value="1">
					<?php endif; ?>
				</td>
				<td>
					<button type="button" class="button kdconsent-remove-row" <?php disabled( 'essential' === $category_id ); ?>><?php echo esc_html__( 'Remove', 'consent-banner' ); ?></button>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p>
	<button type="button" class="button" id="kdconsent-add-category"><?php echo esc_html__( 'Add category', 'consent-banner' ); ?></button>
</p>

<script type="text/template" id="kdconsent-category-row-template">
	<tr>
		<td><input type="text" name="categories[__INDEX__][id]" value=""></td>
		<td><input type="text" name="categories[__INDEX__][label]" value=""></td>
		<td><input type="text" name="categories[__INDEX__][description]" value=""></td>
		<td><input type="checkbox" name="categories[__INDEX__][required]" value="1"></td>
		<td><input type="checkbox" name="categories[__INDEX__][enabledByDefault]" value="1"></td>
		<td><button type="button" class="button kdconsent-remove-row"><?php echo esc_html__( 'Remove', 'consent-banner' ); ?></button></td>
	</tr>
</script>

<h2><?php echo esc_html__( 'Behavior', 'consent-banner' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="kdconsent-consent-lifetime"><?php echo esc_html__( 'Consent lifetime (days)', 'consent-banner' ); ?></label></th>
			<td><input id="kdconsent-consent-lifetime" type="number" min="30" max="730" name="consentLifetimeDays" value="<?php echo esc_attr( (string) ( $settings['consentLifetimeDays'] ?? 180 ) ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Options', 'consent-banner' ); ?></th>
			<td>
				<label><input type="checkbox" name="showRejectButton" value="1" <?php checked( ! empty( $settings['showRejectButton'] ) ); ?>> <?php echo esc_html__( 'Show "Reject all" button', 'consent-banner' ); ?></label><br>
				<label><input type="checkbox" name="enableConsentLog" value="1" <?php checked( ! empty( $settings['enableConsentLog'] ) ); ?>> <?php echo esc_html__( 'Enable consent proof logging (hashed IP/UA)', 'consent-banner' ); ?></label><br>
				<label><input type="checkbox" name="removeOnUninstall" value="1" <?php checked( ! empty( $settings['removeOnUninstall'] ) ); ?>> <?php echo esc_html__( 'Remove plugin data on uninstall', 'consent-banner' ); ?></label><br>
				<label><input type="checkbox" name="bumpConsentVersion" value="1"> <?php echo esc_html__( 'Bump consent version after save and ask everyone again', 'consent-banner' ); ?></label>
			</td>
		</tr>
	</tbody>
</table>

<p>
	<strong><?php echo esc_html__( 'Current consent version:', 'consent-banner' ); ?></strong>
	<?php echo esc_html( (string) $consent_version ); ?>
</p>
