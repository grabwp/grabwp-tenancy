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

<div class="wrap">
	<h1><?php esc_html_e( 'GrabWP Tenancy Settings', 'grabwp-tenancy' ); ?></h1>
	<p><?php esc_html_e( 'Configure your multi-tenant WordPress setup.', 'grabwp-tenancy' ); ?></p>



	<div class="grabwp-tenancy-content">
		<div class="">
			<form method="post" action="">
			<?php wp_nonce_field( 'grabwp_tenancy_save_settings' ); ?>
			<input type="hidden" name="action" value="save_settings" />

				<!-- Navigation Tabs -->
				<h2 class="nav-tab-wrapper js-wp-tabs">
					<a href="#tab-tenant-capabilities" class="nav-tab nav-tab-active"><?php esc_html_e( 'Tenant Capabilities', 'grabwp-tenancy' ); ?></a>
					<a href="#tab-performance-security" class="nav-tab"><?php esc_html_e( 'Performance & Security', 'grabwp-tenancy' ); ?></a>
				</h2>

				<!-- Tab Panels Container -->
				<div class="tab-panels-wrap">

					<!-- Panel 1: Tenant Capabilities -->
					<div id="tab-tenant-capabilities" class="grabwp-tab-panel">
						<p class="description"><?php esc_html_e( 'Control what tenant site administrators are allowed to do.', 'grabwp-tenancy' ); ?></p>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Install Plugins & Themes', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="disallow_file_mods" value="1" <?php checked( ! empty( $settings['disallow_file_mods'] ) ); ?> />
										<?php esc_html_e( 'Disallow tenant admins to install, update, and delete plugins/themes (DISALLOW_FILE_MODS)', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Edit Plugins & Themes', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="disallow_file_edit" value="1" <?php checked( ! empty( $settings['disallow_file_edit'] ) ); ?> />
										<?php esc_html_e( 'Disallow tenant admins to use the built-in plugin/theme file editor (DISALLOW_FILE_EDIT)', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Hide Plugin Management', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="hide_plugin_management" value="1" <?php checked( ! empty( $settings['hide_plugin_management'] ) ); ?> />
										<?php esc_html_e( 'Hide the Plugins menu entirely from tenant admin dashboards', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Hide Theme Management', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="hide_theme_management" value="1" <?php checked( ! empty( $settings['hide_theme_management'] ) ); ?> />
										<?php esc_html_e( 'Hide the Appearance menu entirely from tenant admin dashboards', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Hide GrabWP Plugins', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="hide_grabwp_plugins" value="1" <?php checked( ! empty( $settings['hide_grabwp_plugins'] ) ); ?> />
										<?php esc_html_e( 'Hide GrabWP plugins from the plugin list on tenant sites', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>

					<!-- Panel 2: Performance & Security -->
					<div id="tab-performance-security" class="grabwp-tab-panel" style="display: none;">
						<p class="description"><?php esc_html_e( 'Configure performance and security constants for tenant sites.', 'grabwp-tenancy' ); ?></p>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Disable WP-Cron', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="disable_wp_cron" value="1" <?php checked( ! empty( $settings['disable_wp_cron'] ) ); ?> />
										<?php esc_html_e( 'Disable WordPress internal cron on tenant sites (DISABLE_WP_CRON). Use system cron instead.', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Disable XML-RPC', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="disable_xmlrpc" value="1" <?php checked( ! empty( $settings['disable_xmlrpc'] ) ); ?> />
										<?php esc_html_e( 'Disable XML-RPC API on tenant sites. Reduces attack surface and prevents brute-force attempts.', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Post Revisions', 'grabwp-tenancy' ); ?></th>
								<td>
									<input type="number" name="wp_post_revisions" value="<?php echo esc_attr( $settings['wp_post_revisions'] ); ?>" min="0" max="100" class="small-text" />
									<p class="description"><?php esc_html_e( 'Maximum post revisions to keep (WP_POST_REVISIONS). Lower values save database space.', 'grabwp-tenancy' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Empty Trash Days', 'grabwp-tenancy' ); ?></th>
								<td>
									<input type="number" name="empty_trash_days" value="<?php echo esc_attr( $settings['empty_trash_days'] ); ?>" min="0" max="365" class="small-text" />
									<p class="description"><?php esc_html_e( 'Days before trashed posts are permanently deleted (EMPTY_TRASH_DAYS). Set to 0 to disable trash.', 'grabwp-tenancy' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Block External HTTP', 'grabwp-tenancy' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wp_http_block_external" value="1" <?php checked( ! empty( $settings['wp_http_block_external'] ) ); ?> />
										<?php esc_html_e( 'Block all external HTTP requests from tenant sites (WP_HTTP_BLOCK_EXTERNAL). Allowlist hosts below.', 'grabwp-tenancy' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Accessible Hosts', 'grabwp-tenancy' ); ?></th>
								<td>
									<input type="text" name="wp_accessible_hosts" value="<?php echo esc_attr( $settings['wp_accessible_hosts'] ); ?>" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Comma-separated hosts allowed when external HTTP is blocked (WP_ACCESSIBLE_HOSTS). Wildcards supported.', 'grabwp-tenancy' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

				</div>
			<style>
				#wpfooter{
					position:static;
				}
			</style>
			<!-- Inline Lightweight Tab Switching Script -->
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Restore active tab from localStorage
				var activeTab = localStorage.getItem('grabwp_tenancy_settings_tab');
				if (activeTab && $(activeTab).length) {
					$('.js-wp-tabs a').removeClass('nav-tab-active');
					$('.js-wp-tabs a[href="' + activeTab + '"]').addClass('nav-tab-active');
					$('.grabwp-tab-panel').hide();
					$(activeTab).show();
				}

				$('.js-wp-tabs a').on('click', function(e) {
					e.preventDefault();
					
					// 1. Handle navigation active state
					$('.js-wp-tabs a').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					
					// 2. Handle panel visibility
					$('.grabwp-tab-panel').hide();
					var activePanel = $(this).attr('href');
					$(activePanel).show();

					// 3. Save to localStorage
					localStorage.setItem('grabwp_tenancy_settings_tab', activePanel);
				});
			});
			</script>

			<?php submit_button( __( 'Save Settings', 'grabwp-tenancy' ) ); ?>
			</form>
		</div>

	</div>
</div>
