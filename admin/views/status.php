<?php
/**
 * GrabWP Tenancy - Status Admin Page Template
 *
 * Tab router that gathers data via Status Checker and includes tab partials.
 *
 * @package GrabWP_Tenancy
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';

// Gather shared data.
$grabwp_status_path_status    = GrabWP_Tenancy_Path_Manager::get_path_status();
$grabwp_status_mappings_file  = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
$grabwp_status_base_path      = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
$grabwp_status_settings_inst  = GrabWP_Tenancy_Settings::get_instance();
$grabwp_status_settings_file  = $grabwp_status_settings_inst->get_settings_file_path();

// Count tenants.
$grabwp_status_tenant_count = 0;
if ( file_exists( $grabwp_status_mappings_file ) && is_readable( $grabwp_status_mappings_file ) ) {
	$tenant_mappings = array();
	ob_start();
	include $grabwp_status_mappings_file;
	ob_end_clean();
	if ( is_array( $tenant_mappings ) ) {
		$grabwp_status_tenant_count = count( $tenant_mappings );
	}
}

// Database engine.
if ( defined( 'DB_ENGINE' ) ) {
	$grabwp_status_db_engine_label = ucfirst( DB_ENGINE );
} elseif ( defined( 'DATABASE_TYPE' ) ) {
	$grabwp_status_db_engine_label = ucfirst( DATABASE_TYPE );
} else {
	$grabwp_status_db_engine_label = 'Mysql';
}

// Pro status.
$grabwp_status_is_pro      = defined( 'GRABWP_TENANCY_PRO_ACTIVE' ) && GRABWP_TENANCY_PRO_ACTIVE;
$grabwp_status_pro_version = defined( 'GRABWP_TENANCY_PRO_VERSION' ) ? GRABWP_TENANCY_PRO_VERSION : '';

$grabwp_status_pro_default_config = array();
if ( $grabwp_status_is_pro && class_exists( 'GrabWP_Tenancy_Pro_Config' ) ) {
	$grabwp_pro_config_inst           = GrabWP_Tenancy_Pro_Config::get_instance();
	$grabwp_status_pro_default_config = $grabwp_pro_config_inst->get_default_config();
}

// Environment checks (used by general tab).
$grabwp_status_wp_config      = GrabWP_Tenancy_Status_Checker::get_wp_config_status();
$grabwp_status_mu_plugin      = GrabWP_Tenancy_Status_Checker::get_mu_plugin_status();
$grabwp_status_htaccess       = GrabWP_Tenancy_Status_Checker::get_root_htaccess_status();
$grabwp_status_data_htaccess  = GrabWP_Tenancy_Status_Checker::get_data_htaccess_status( $grabwp_status_base_path );
$grabwp_status_server         = GrabWP_Tenancy_Status_Checker::get_server_environment();
$grabwp_status_base_dir_writable = is_dir( $grabwp_status_base_path ) ? wp_is_writable( $grabwp_status_base_path ) : false;
$grabwp_status_index_exists      = file_exists( $grabwp_status_base_path . '/index.php' );
$grabwp_status_plugin_version    = $this->plugin->version;

// Active tab.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$grabwp_status_active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'GrabWP Tenancy Status', 'grabwp-tenancy' ); ?></h1>

	<nav class="nav-tab-wrapper grabwp-tenancy-tabs">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'general' ) ); ?>"
		   class="nav-tab <?php echo 'general' === $grabwp_status_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Plugin General', 'grabwp-tenancy' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'base' ) ); ?>"
		   class="nav-tab <?php echo 'base' === $grabwp_status_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Base Plugin', 'grabwp-tenancy' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'pro' ) ); ?>"
		   class="nav-tab <?php echo 'pro' === $grabwp_status_active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Pro Plugin', 'grabwp-tenancy' ); ?>
		</a>
	</nav>

	<div class="grabwp-tenancy-content">

		<?php
		// Migration warning on all tabs.
		if ( $grabwp_status_path_status['using_old'] ) :
			$grabwp_new_path     = WP_CONTENT_DIR . '/grabwp-tenancy';
			$grabwp_current_path = $grabwp_status_path_status['current_base'];
			?>
		<div class="notice notice-warning" style="margin: 15px 0;">
			<p><strong><?php esc_html_e( 'GrabWP Tenancy:', 'grabwp-tenancy' ); ?></strong>
			<?php esc_html_e( 'You\'re using a legacy path structure. To comply with WordPress standards:', 'grabwp-tenancy' ); ?></p>
			<p><?php esc_html_e( '1. Deactivate the plugin', 'grabwp-tenancy' ); ?><br>
			<?php
			printf(
				esc_html__( '2. Rename and move the entire %1$s folder to %2$s', 'grabwp-tenancy' ),
				'<code>' . esc_html( basename( $grabwp_current_path ) ) . '</code>',
				'<code>' . esc_html( $grabwp_new_path ) . '</code>'
			);
			?>
			<br>
			<?php esc_html_e( '3. Reactivate the plugin', 'grabwp-tenancy' ); ?></p>
		</div>
		<?php endif; ?>

		<?php
		if ( 'general' === $grabwp_status_active_tab ) {
			include __DIR__ . '/status-general.php';
		} elseif ( 'base' === $grabwp_status_active_tab ) {
			include __DIR__ . '/status-base.php';
		} elseif ( 'pro' === $grabwp_status_active_tab ) {
			include __DIR__ . '/status-pro.php';
		}
		?>

	</div>
</div>
