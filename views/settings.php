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
$plugin_version   = defined( 'KDCONSENT_PLUGIN_VERSION' ) ? (string) KDCONSENT_PLUGIN_VERSION : '';
$notice_messages  = array(
	'saved'             => array(
		'type'    => 'success',
		'message' => __( 'Settings saved.', 'consent-banner' ),
	),
	'imported'          => array(
		'type'    => 'success',
		'message' => __( 'Settings imported.', 'consent-banner' ),
	),
	'permission-denied' => array(
		'type'    => 'error',
		'message' => __( 'You are not allowed to manage this page.', 'consent-banner' ),
	),
	'invalid-nonce'     => array(
		'type'    => 'error',
		'message' => __( 'Security check failed. Please try again.', 'consent-banner' ),
	),
	'missing-file'      => array(
		'type'    => 'error',
		'message' => __( 'Choose a JSON file to import.', 'consent-banner' ),
	),
	'upload-error'      => array(
		'type'    => 'error',
		'message' => __( 'The import file could not be uploaded.', 'consent-banner' ),
	),
	'file-too-large'    => array(
		'type'    => 'error',
		'message' => __( 'The import file is too large. Use a JSON file up to 1 MB.', 'consent-banner' ),
	),
	'invalid-json'      => array(
		'type'    => 'error',
		'message' => __( 'The import file is not valid JSON.', 'consent-banner' ),
	),
	'missing-settings'  => array(
		'type'    => 'error',
		'message' => __( 'The import file does not contain plugin settings.', 'consent-banner' ),
	),
	'export-failed'     => array(
		'type'    => 'error',
		'message' => __( 'Settings could not be exported.', 'consent-banner' ),
	),
);
?>
<div class="wrap kdconsent-settings-wrap">
	<h1 class="kdconsent-settings-title">
		<?php echo esc_html__( 'Consent Banner Settings', 'consent-banner' ); ?>
		<?php if ( '' !== $plugin_version ) : ?>
			<?php /* translators: %s: Plugin version number. */ ?>
			<span class="kdconsent-version-badge" aria-label="<?php echo esc_attr( sprintf( __( 'Version %s', 'consent-banner' ), $plugin_version ) ); ?>">
				<?php echo esc_html( 'v' . $plugin_version ); ?>
			</span>
		<?php endif; ?>
	</h1>

	<?php if ( isset( $notice_messages[ $notice ] ) ) : ?>
		<?php
		$notice_type    = 'success' === $notice_messages[ $notice ]['type'] ? 'success' : 'error';
		$notice_message = (string) $notice_messages[ $notice ]['message'];
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice_message ); ?></p>
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

	<?php if ( 'export-import' === $active_tab ) : ?>
		<div class="kdconsent-settings-panel" role="region" aria-label="<?php echo esc_attr( $active_tab_label ); ?>">
			<?php require KDCONSENT_PLUGIN_DIR . 'views/tabs/export-import.php'; ?>
		</div>
	<?php else : ?>
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
	<?php endif; ?>
</div>
