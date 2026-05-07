<?php
/**
 * Settings page view.
 *
 * @var array<string,mixed>                                   $settings
 * @var array<string,array{label:string,enabled:bool,url?:string}> $tabs
 * @var string                                                $current_tab
 * @var int                                                   $consent_version
 * @var string                                                $notice
 *
 * @package KatsarovDesign\ConsentBanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tab_items = is_array( $tabs ?? null ) ? $tabs : array();

if ( empty( $tab_items ) ) {
	$tab_items = \KatsarovDesign\ConsentBanner\Admin\Menu::tabs();
}

$active_tab = is_string( $current_tab ?? null ) ? $current_tab : \KatsarovDesign\ConsentBanner\Admin\Menu::DEFAULT_TAB;
$active_tab = \KatsarovDesign\ConsentBanner\Admin\Menu::normalize_tab( $active_tab );

if ( ! isset( $tab_items[ $active_tab ] ) ) {
	$active_tab = \KatsarovDesign\ConsentBanner\Admin\Menu::DEFAULT_TAB;
}

$active_tab_label = (string) ( $tab_items[ $active_tab ]['label'] ?? __( 'General settings', 'consent-banner' ) );
?>
<div class="wrap kdconsent-settings-wrap">
	<h1><?php echo esc_html__( 'Consent Banner Settings', 'consent-banner' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__( 'Settings saved.', 'consent-banner' ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper kdconsent-settings-tabs" aria-label="<?php echo esc_attr__( 'Consent Banner settings sections', 'consent-banner' ); ?>">
		<?php foreach ( $tab_items as $tab_key => $tab_config ) : ?>
			<?php
			$label      = (string) ( $tab_config['label'] ?? $tab_key );
			$is_enabled = ! empty( $tab_config['enabled'] );
			$is_active  = $is_enabled && $active_tab === $tab_key;
			?>

			<?php if ( $is_enabled ) : ?>
				<?php
				$url        = (string) ( $tab_config['url'] ?? \KatsarovDesign\ConsentBanner\Admin\Menu::settings_url( array( 'tab' => $tab_key ) ) );
				$tab_class  = 'nav-tab';
				$tab_class .= $is_active ? ' nav-tab-active' : '';
				?>
				<a class="<?php echo esc_attr( $tab_class ); ?>" href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php else : ?>
				<a class="nav-tab nav-tab-disabled" href="#" aria-disabled="true" tabindex="-1" title="<?php echo esc_attr__( 'Coming soon', 'consent-banner' ); ?>">
					<?php echo esc_html( $label ); ?>
					<span class="kdconsent-tab-badge" aria-hidden="true"><?php echo esc_html__( 'Soon', 'consent-banner' ); ?></span>
					<span class="screen-reader-text"><?php echo esc_html__( 'Coming soon', 'consent-banner' ); ?></span>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="kdconsent-settings-form">
		<?php wp_nonce_field( 'kdconsent_save_settings', 'kdconsent_settings_nonce' ); ?>
		<input type="hidden" name="action" value="kdconsent_save_settings">
		<input type="hidden" name="kdconsent_current_tab" value="<?php echo esc_attr( $active_tab ); ?>">

		<div class="kdconsent-settings-panel" role="region" aria-label="<?php echo esc_attr( $active_tab_label ); ?>">
			<?php if ( 'appearance' === $active_tab ) : ?>
				<?php require KDCONSENT_PLUGIN_DIR . 'views/tabs/appearance.php'; ?>
			<?php else : ?>
				<?php require KDCONSENT_PLUGIN_DIR . 'views/tabs/general.php'; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $tab_items[ $active_tab ]['enabled'] ) ) : ?>
			<?php submit_button( __( 'Save Settings', 'consent-banner' ) ); ?>
		<?php endif; ?>
	</form>
</div>
