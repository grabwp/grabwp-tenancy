<?php
/**
 * Status Page - General Tab
 *
 * Environment checks, plugin info, and system information.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 *
 * Variables available from parent scope:
 * @var array  $grabwp_status_wp_config   wp-config.php loader status
 * @var array  $grabwp_status_mu_plugin   MU-Plugin status
 * @var array  $grabwp_status_htaccess    Root .htaccess status
 * @var array  $grabwp_status_data_htaccess Data dir .htaccess status
 * @var array  $grabwp_status_server      Server environment
 * @var string $grabwp_status_base_path   Base data directory path
 * @var bool   $grabwp_status_base_dir_writable  Whether base dir is writable
 * @var bool   $grabwp_status_index_exists Index protection file exists
 * @var int    $grabwp_status_tenant_count Number of registered tenants
 * @var bool   $grabwp_status_is_pro      Whether Pro plugin is active
 * @var string $grabwp_status_pro_version Pro plugin version
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="grabwp-tenancy-form grabwp-status-general">
	<h3><?php esc_html_e( 'Environment Checks', 'grabwp-tenancy' ); ?></h3>

	<?php include __DIR__ . '/partials/status-env-cards.php'; ?>
</div>

<div class="grabwp-settings-grid-2 grabwp-status-info-grid">
	<div class="grabwp-tenancy-form">
		<h3><?php esc_html_e( 'GrabWP Tenancy Information', 'grabwp-tenancy' ); ?></h3>
		<table class="form-table form-table-compact">
			<tr>
				<th scope="row"><?php esc_html_e( 'Base Plugin Version', 'grabwp-tenancy' ); ?></th>
				<td><?php echo esc_html( $grabwp_status_plugin_version ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Pro Plugin', 'grabwp-tenancy' ); ?></th>
				<td>
					<?php if ( $grabwp_status_is_pro ) : ?>
						<span style="color: #46b450;"><?php esc_html_e( 'Active', 'grabwp-tenancy' ); ?></span>
						<?php if ( $grabwp_status_pro_version ) : ?>
							(<?php echo esc_html( $grabwp_status_pro_version ); ?>)
						<?php endif; ?>
					<?php else : ?>
						<span style="color: #dc3232;"><?php esc_html_e( 'Inactive', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Registered Tenants', 'grabwp-tenancy' ); ?></th>
				<td>
					<?php echo esc_html( $grabwp_status_tenant_count ); ?>
					<?php if ( $grabwp_status_tenant_count > 0 ) : ?>
						(<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>"><?php esc_html_e( 'View all', 'grabwp-tenancy' ); ?></a>)
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<div class="grabwp-tenancy-form">
		<details class="grabwp-status-sysinfo">
			<summary>
				<strong><?php esc_html_e( 'System Information', 'grabwp-tenancy' ); ?></strong>
				<span class="grabwp-status-sysinfo-peek">
					<?php
					printf(
						/* translators: 1: WordPress version, 2: PHP version, 3: server software label */
						esc_html__( 'WP %1$s · PHP %2$s · %3$s', 'grabwp-tenancy' ),
						esc_html( get_bloginfo( 'version' ) ),
						esc_html( PHP_VERSION ),
						esc_html( $grabwp_status_server['server_software'] )
					);
					?>
				</span>
			</summary>
			<table class="form-table form-table-compact">
				<tr>
					<th scope="row"><?php esc_html_e( 'WordPress Version', 'grabwp-tenancy' ); ?></th>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'PHP Version', 'grabwp-tenancy' ); ?></th>
					<td><?php echo esc_html( PHP_VERSION ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Database Engine', 'grabwp-tenancy' ); ?></th>
					<td><code><?php echo esc_html( $grabwp_status_db_engine_label ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Web Server', 'grabwp-tenancy' ); ?></th>
					<td>
						<code><?php echo esc_html( $grabwp_status_server['server_software'] ); ?></code>
						<?php if ( $grabwp_status_server['is_nginx'] ) : ?>
							<br><small><?php esc_html_e( 'ℹ .htaccess files are not used by Nginx. Configure tenant routing in your server block instead.', 'grabwp-tenancy' ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $grabwp_status_server['is_apache'] ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'mod_rewrite', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( true === $grabwp_status_server['mod_rewrite'] ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Loaded', 'grabwp-tenancy' ); ?></span>
						<?php elseif ( false === $grabwp_status_server['mod_rewrite'] ) : ?>
							<span style="color: #dc3232;"><?php esc_html_e( '✗ Not loaded', 'grabwp-tenancy' ); ?></span>
							<br><small><?php esc_html_e( 'Path routing requires mod_rewrite. Query string routing (?site=id) will be used as fallback.', 'grabwp-tenancy' ); ?></small>
						<?php else : ?>
							<span style="color: #999;"><?php esc_html_e( 'Cannot detect (apache_get_modules unavailable)', 'grabwp-tenancy' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'WordPress Multisite', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_status_server['is_multisite'] ) : ?>
							<span style="color: #ff8c00;"><?php esc_html_e( '⚠ Yes. GrabWP Tenancy is not designed for Multisite', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<?php esc_html_e( 'No', 'grabwp-tenancy' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WP Debug Mode', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_status_server['wp_debug'] ) : ?>
							<span style="color: #ff8c00;"><?php esc_html_e( 'Enabled', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<?php esc_html_e( 'Disabled', 'grabwp-tenancy' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Data Dir Writable', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_status_base_dir_writable ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Yes', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #dc3232;"><?php esc_html_e( '✗ No', 'grabwp-tenancy' ); ?></span>
							<br><small><?php esc_html_e( 'Plugin needs write access to create tenant directories and manage configuration files.', 'grabwp-tenancy' ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'MU-Plugins Dir Writable', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( $grabwp_status_mu_plugin['dir_writable'] ) : ?>
							<span style="color: #46b450;"><?php esc_html_e( '✓ Yes', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<span style="color: #ff8c00;"><?php esc_html_e( '⚠ No', 'grabwp-tenancy' ); ?></span>
							<br><small><?php esc_html_e( 'Auto-install of MU-plugin will not work. Manual installation required.', 'grabwp-tenancy' ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</details>
	</div>
</div>
