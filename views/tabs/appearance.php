<?php
/**
 * Appearance settings tab view.
 *
 * @var array<string,mixed> $settings
 *
 * @package KatsarovDesign\ConsentBanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$texts    = is_array( $settings['texts'] ?? null ) ? $settings['texts'] : array();
$texts_en = is_array( $texts['en_US'] ?? null ) ? $texts['en_US'] : array();
$texts_bg = is_array( $texts['bg_BG'] ?? null ) ? $texts['bg_BG'] : array();

$default_settings          = \KatsarovDesign\ConsentBanner\Installer::default_settings();
$default_styles            = is_array( $default_settings['styles'] ?? null ) ? $default_settings['styles'] : array();
$styles                    = is_array( $settings['styles'] ?? null ) ? $settings['styles'] : array();
$styles_backdrop_defaults  = is_array( $default_styles['backdrop'] ?? null ) ? $default_styles['backdrop'] : array();
$styles_backdrop           = is_array( $styles['backdrop'] ?? null ) ? $styles['backdrop'] : array();
$styles_buttons_defaults   = is_array( $default_styles['buttons'] ?? null ) ? $default_styles['buttons'] : array();
$styles_buttons            = is_array( $styles['buttons'] ?? null ) ? $styles['buttons'] : array();

$current_animation = (string) ( $settings['animation'] ?? ( $default_settings['animation'] ?? 'fade-in' ) );
$show_delay_ms     = (int) ( $settings['showDelayMs'] ?? ( $default_settings['showDelayMs'] ?? 0 ) );

$backdrop_color   = (string) ( $styles_backdrop['color'] ?? ( $styles_backdrop_defaults['color'] ?? '#000000' ) );
$backdrop_opacity = (string) ( $styles_backdrop['opacity'] ?? ( $styles_backdrop_defaults['opacity'] ?? '0.45' ) );

$animation_options = array(
	'fade-in'       => __( 'Fade in', 'consent-banner' ),
	'slide-in-up'   => __( 'Slide in up', 'consent-banner' ),
	'slide-in-left' => __( 'Slide in left', 'consent-banner' ),
	'slide-in-right' => __( 'Slide in right', 'consent-banner' ),
	'slide-in-down' => __( 'Slide in down', 'consent-banner' ),
	'blur-in'       => __( 'Blur in', 'consent-banner' ),
);

$fields = array(
	'bannerTitle'      => __( 'Banner title', 'consent-banner' ),
	'bannerBody'       => __( 'Banner message', 'consent-banner' ),
	'acceptAllLabel'   => __( 'Accept all label', 'consent-banner' ),
	'rejectAllLabel'   => __( 'Reject all label', 'consent-banner' ),
	'customizeLabel'   => __( 'Customize label', 'consent-banner' ),
	'saveLabel'        => __( 'Save preferences label', 'consent-banner' ),
	'closeLabel'       => __( 'Close label', 'consent-banner' ),
	'preferencesTitle' => __( 'Preferences title', 'consent-banner' ),
);

$button_labels = array(
	'accept'    => __( 'Accept button', 'consent-banner' ),
	'reject'    => __( 'Reject button', 'consent-banner' ),
	'customize' => __( 'Customize button', 'consent-banner' ),
	'save'      => __( 'Save preferences button', 'consent-banner' ),
	'close'     => __( 'Close button', 'consent-banner' ),
);

$button_color_fields = array(
	'background'      => __( 'Background', 'consent-banner' ),
	'text'            => __( 'Text', 'consent-banner' ),
	'border'          => __( 'Border', 'consent-banner' ),
	'hoverBackground' => __( 'Hover background', 'consent-banner' ),
	'hoverText'       => __( 'Hover text', 'consent-banner' ),
	'hoverBorder'     => __( 'Hover border', 'consent-banner' ),
);
?>
<h2><?php echo esc_html__( 'Banner layout', 'consent-banner' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="kdconsent-position"><?php echo esc_html__( 'Banner position', 'consent-banner' ); ?></label></th>
			<td>
				<select id="kdconsent-position" name="position">
					<option value="bottom" <?php selected( (string) ( $settings['position'] ?? 'bottom' ), 'bottom' ); ?>><?php echo esc_html__( 'Bottom bar', 'consent-banner' ); ?></option>
					<option value="center" <?php selected( (string) ( $settings['position'] ?? 'bottom' ), 'center' ); ?>><?php echo esc_html__( 'Centered modal', 'consent-banner' ); ?></option>
				</select>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php echo esc_html__( 'Display behavior', 'consent-banner' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="kdconsent-animation"><?php echo esc_html__( 'Animation', 'consent-banner' ); ?></label></th>
			<td>
				<select id="kdconsent-animation" name="animation">
					<?php foreach ( $animation_options as $animation_key => $animation_label ) : ?>
						<option value="<?php echo esc_attr( $animation_key ); ?>" <?php selected( $current_animation, $animation_key ); ?>>
							<?php echo esc_html( $animation_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kdconsent-show-delay"><?php echo esc_html__( 'Show delay (ms)', 'consent-banner' ); ?></label></th>
			<td>
				<input
					type="number"
					id="kdconsent-show-delay"
					name="showDelayMs"
					value="<?php echo esc_attr( (string) $show_delay_ms ); ?>"
					min="0"
					max="10000"
					step="50"
				>
				<p class="description"><?php echo esc_html__( 'Delay before showing the banner on page load.', 'consent-banner' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php echo esc_html__( 'Texts (EN / BG)', 'consent-banner' ); ?></h2>
<table class="widefat fixed striped kdconsent-texts-table">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'Field', 'consent-banner' ); ?></th>
			<th><?php echo esc_html__( 'English (en_US)', 'consent-banner' ); ?></th>
			<th><?php echo esc_html__( 'Bulgarian (bg_BG)', 'consent-banner' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $fields as $key => $label ) : ?>
			<tr>
				<td><?php echo esc_html( $label ); ?></td>
				<td>
					<input type="text" name="texts[en_US][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $texts_en[ $key ] ?? '' ) ); ?>">
				</td>
				<td>
					<input type="text" name="texts[bg_BG][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $texts_bg[ $key ] ?? '' ) ); ?>">
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php echo esc_html__( 'Backdrop', 'consent-banner' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="kdconsent-backdrop-color"><?php echo esc_html__( 'Backdrop color', 'consent-banner' ); ?></label></th>
			<td>
				<input
					type="color"
					id="kdconsent-backdrop-color"
					class="kdconsent-color-input"
					name="styles[backdrop][color]"
					value="<?php echo esc_attr( $backdrop_color ); ?>"
				>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kdconsent-backdrop-opacity"><?php echo esc_html__( 'Backdrop opacity', 'consent-banner' ); ?></label></th>
			<td>
				<input
					type="number"
					id="kdconsent-backdrop-opacity"
					name="styles[backdrop][opacity]"
					value="<?php echo esc_attr( $backdrop_opacity ); ?>"
					min="0"
					max="1"
					step="0.01"
				>
				<p class="description"><?php echo esc_html__( 'Use values from 0 to 1.', 'consent-banner' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php echo esc_html__( 'Button colors', 'consent-banner' ); ?></h2>
<table class="widefat fixed striped kdconsent-button-colors-table">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'Button', 'consent-banner' ); ?></th>
			<?php foreach ( $button_color_fields as $field_label ) : ?>
				<th><?php echo esc_html( $field_label ); ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $button_labels as $button_key => $button_label ) : ?>
			<?php
			$button_defaults = is_array( $styles_buttons_defaults[ $button_key ] ?? null ) ? $styles_buttons_defaults[ $button_key ] : array();
			$button_values   = is_array( $styles_buttons[ $button_key ] ?? null ) ? $styles_buttons[ $button_key ] : array();
			?>
			<tr>
				<td><?php echo esc_html( $button_label ); ?></td>
				<?php foreach ( $button_color_fields as $field_key => $field_label ) : ?>
					<?php $field_value = (string) ( $button_values[ $field_key ] ?? ( $button_defaults[ $field_key ] ?? '#000000' ) ); ?>
					<td>
						<label class="screen-reader-text" for="kdconsent-button-<?php echo esc_attr( $button_key . '-' . $field_key ); ?>">
							<?php echo esc_html( $button_label . ' - ' . $field_label ); ?>
						</label>
						<input
							type="color"
							id="kdconsent-button-<?php echo esc_attr( $button_key . '-' . $field_key ); ?>"
							class="kdconsent-color-input"
							name="styles[buttons][<?php echo esc_attr( $button_key ); ?>][<?php echo esc_attr( $field_key ); ?>]"
							value="<?php echo esc_attr( $field_value ); ?>"
						>
					</td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
