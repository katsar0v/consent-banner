<?php
/**
 * Appearance settings tab view.
 *
 * @var array<string,mixed> $settings
 *
 * @package KatsarovDesign\CookieBanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$texts    = is_array( $settings['texts'] ?? null ) ? $settings['texts'] : array();
$texts_en = is_array( $texts['en_US'] ?? null ) ? $texts['en_US'] : array();
$texts_bg = is_array( $texts['bg_BG'] ?? null ) ? $texts['bg_BG'] : array();

$default_settings          = \KatsarovDesign\CookieBanner\Installer::default_settings();
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
	'fade-in'       => __( 'Fade in', 'cookie-banner' ),
	'slide-in-up'   => __( 'Slide in up', 'cookie-banner' ),
	'slide-in-left' => __( 'Slide in left', 'cookie-banner' ),
	'slide-in-right'=> __( 'Slide in right', 'cookie-banner' ),
	'slide-in-down' => __( 'Slide in down', 'cookie-banner' ),
	'blur-in'       => __( 'Blur in', 'cookie-banner' ),
);

$fields = array(
	'bannerTitle'      => __( 'Banner title', 'cookie-banner' ),
	'bannerBody'       => __( 'Banner message', 'cookie-banner' ),
	'acceptAllLabel'   => __( 'Accept all label', 'cookie-banner' ),
	'rejectAllLabel'   => __( 'Reject all label', 'cookie-banner' ),
	'customizeLabel'   => __( 'Customize label', 'cookie-banner' ),
	'saveLabel'        => __( 'Save preferences label', 'cookie-banner' ),
	'closeLabel'       => __( 'Close label', 'cookie-banner' ),
	'preferencesTitle' => __( 'Preferences title', 'cookie-banner' ),
);

$button_labels = array(
	'accept'    => __( 'Accept button', 'cookie-banner' ),
	'reject'    => __( 'Reject button', 'cookie-banner' ),
	'customize' => __( 'Customize button', 'cookie-banner' ),
	'save'      => __( 'Save preferences button', 'cookie-banner' ),
	'close'     => __( 'Close button', 'cookie-banner' ),
);

$button_color_fields = array(
	'background'      => __( 'Background', 'cookie-banner' ),
	'text'            => __( 'Text', 'cookie-banner' ),
	'border'          => __( 'Border', 'cookie-banner' ),
	'hoverBackground' => __( 'Hover background', 'cookie-banner' ),
	'hoverText'       => __( 'Hover text', 'cookie-banner' ),
	'hoverBorder'     => __( 'Hover border', 'cookie-banner' ),
);
?>
<h2><?php echo esc_html__( 'Banner layout', 'cookie-banner' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="kdcb-position"><?php echo esc_html__( 'Banner position', 'cookie-banner' ); ?></label></th>
			<td>
				<select id="kdcb-position" name="position">
					<option value="bottom" <?php selected( (string) ( $settings['position'] ?? 'bottom' ), 'bottom' ); ?>><?php echo esc_html__( 'Bottom bar', 'cookie-banner' ); ?></option>
					<option value="center" <?php selected( (string) ( $settings['position'] ?? 'bottom' ), 'center' ); ?>><?php echo esc_html__( 'Centered modal', 'cookie-banner' ); ?></option>
				</select>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php echo esc_html__( 'Display behavior', 'cookie-banner' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="kdcb-animation"><?php echo esc_html__( 'Animation', 'cookie-banner' ); ?></label></th>
			<td>
				<select id="kdcb-animation" name="animation">
					<?php foreach ( $animation_options as $animation_key => $animation_label ) : ?>
						<option value="<?php echo esc_attr( $animation_key ); ?>" <?php selected( $current_animation, $animation_key ); ?>>
							<?php echo esc_html( $animation_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kdcb-show-delay"><?php echo esc_html__( 'Show delay (ms)', 'cookie-banner' ); ?></label></th>
			<td>
				<input
					type="number"
					id="kdcb-show-delay"
					name="showDelayMs"
					value="<?php echo esc_attr( (string) $show_delay_ms ); ?>"
					min="0"
					max="10000"
					step="50"
				>
				<p class="description"><?php echo esc_html__( 'Delay before showing the banner on page load.', 'cookie-banner' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php echo esc_html__( 'Texts (EN / BG)', 'cookie-banner' ); ?></h2>
<table class="widefat fixed striped kdcb-texts-table">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'Field', 'cookie-banner' ); ?></th>
			<th><?php echo esc_html__( 'English (en_US)', 'cookie-banner' ); ?></th>
			<th><?php echo esc_html__( 'Bulgarian (bg_BG)', 'cookie-banner' ); ?></th>
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

<h2><?php echo esc_html__( 'Backdrop', 'cookie-banner' ); ?></h2>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row"><label for="kdcb-backdrop-color"><?php echo esc_html__( 'Backdrop color', 'cookie-banner' ); ?></label></th>
			<td>
				<input
					type="color"
					id="kdcb-backdrop-color"
					class="kdcb-color-input"
					name="styles[backdrop][color]"
					value="<?php echo esc_attr( $backdrop_color ); ?>"
				>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kdcb-backdrop-opacity"><?php echo esc_html__( 'Backdrop opacity', 'cookie-banner' ); ?></label></th>
			<td>
				<input
					type="number"
					id="kdcb-backdrop-opacity"
					name="styles[backdrop][opacity]"
					value="<?php echo esc_attr( $backdrop_opacity ); ?>"
					min="0"
					max="1"
					step="0.01"
				>
				<p class="description"><?php echo esc_html__( 'Use values from 0 to 1.', 'cookie-banner' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>

<h2><?php echo esc_html__( 'Button colors', 'cookie-banner' ); ?></h2>
<table class="widefat fixed striped kdcb-button-colors-table">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'Button', 'cookie-banner' ); ?></th>
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
						<label class="screen-reader-text" for="kdcb-button-<?php echo esc_attr( $button_key . '-' . $field_key ); ?>">
							<?php echo esc_html( $button_label . ' - ' . $field_label ); ?>
						</label>
						<input
							type="color"
							id="kdcb-button-<?php echo esc_attr( $button_key . '-' . $field_key ); ?>"
							class="kdcb-color-input"
							name="styles[buttons][<?php echo esc_attr( $button_key ); ?>][<?php echo esc_attr( $field_key ); ?>]"
							value="<?php echo esc_attr( $field_value ); ?>"
						>
					</td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
