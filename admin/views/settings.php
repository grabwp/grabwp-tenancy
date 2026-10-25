<?php
/**
 * GrabWP Tenancy - Settings Admin Page Template
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap grabwp-tenancy-settings">
	<h1><?php esc_html_e( 'GrabWP Tenancy Settings', 'grabwp-tenancy' ); ?></h1>

	<div class="grabwp-tenancy-content">
		<form method="post" action="">
			<?php wp_nonce_field( 'grabwp_tenancy_save_settings' ); ?>
			<input type="hidden" name="action" value="save_settings" />

			<!-- Navigation Tabs -->
			<h2 class="nav-tab-wrapper grabwp-tabs" data-grabwp-tabs-store="grabwp_tenancy_settings_tab">
				<a href="#tab-tenant-capabilities" class="nav-tab nav-tab-active"><?php esc_html_e( 'Tenant Capabilities', 'grabwp-tenancy' ); ?></a>
				<a href="#tab-performance-security" class="nav-tab"><?php esc_html_e( 'Performance & Security', 'grabwp-tenancy' ); ?></a>
				<a href="#tab-routing" class="nav-tab"><?php esc_html_e( 'Routing', 'grabwp-tenancy' ); ?></a>
			</h2>

			<!-- Tab Panels Container -->
			<div class="tab-panels-wrap">

				<!-- Panel 1: Tenant Capabilities -->
				<div id="tab-tenant-capabilities" class="grabwp-tab-panel">
					<?php
					$grabwp_settings_group = 'capabilities';
					require __DIR__ . '/partials/settings-field-rows.php';
					?>
				</div>

				<!-- Panel 2: Performance & Security -->
				<div id="tab-performance-security" class="grabwp-tab-panel grabwp-hidden">
					<?php
					$grabwp_settings_group = 'performance';
					require __DIR__ . '/partials/settings-field-rows.php';
					?>
				</div>

				<!-- Panel 3: Routing -->
				<div id="tab-routing" class="grabwp-tab-panel grabwp-hidden">
					<?php require __DIR__ . '/partials/settings-routing.php'; ?>
				</div>

			</div>
			<style>
				#wpfooter{
					position:static;
				}
			</style>

			<?php submit_button( __( 'Save Settings', 'grabwp-tenancy' ) ); ?>
		</form>

		<?php
		GrabWP_Tenancy_Admin::render_upsell_card(
			array(
				'utm_content' => 'placeholder-scheduling',
				'title'       => __( 'Scheduling and automatic backups', 'grabwp-tenancy' ),
				'message'     => __( 'Schedule automatic backups for each tenant site on an interval you control. Pro automatically pushes backup copies to secure offsite storage after each run, so you never lose client data to a server failure.', 'grabwp-tenancy' ),
				'cta_label'   => __( 'Learn about GrabWP Tenancy Pro', 'grabwp-tenancy' ),
				'margin'      => 'grabwp-mt-sm',
			)
		);
		?>
	</div>
</div>
