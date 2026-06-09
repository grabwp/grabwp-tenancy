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

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'Environment Checks', 'grabwp-tenancy' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Quick health-check of all critical plugin components.', 'grabwp-tenancy' ); ?></p>

	<?php
	// 1. wp-config.php Loader
	?>
	<div class="grabwp-env-card" style="margin-top: 16px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
			<strong><?php esc_html_e( '1. wp-config.php Loader', 'grabwp-tenancy' ); ?></strong>
			<?php if ( $grabwp_status_wp_config['loader_active'] ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Active', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ Not loaded', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! $grabwp_status_wp_config['loader_active'] ) : ?>
		<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
			<?php esc_html_e( 'This line loads the tenant detection script before WordPress boots. Without it, domain/path routing cannot identify which tenant is being accessed.', 'grabwp-tenancy' ); ?>
		</p>
		<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
			<?php
			printf(
				esc_html__( 'File: %1$s — place before %2$s', 'grabwp-tenancy' ),
				'<code>' . esc_html( $grabwp_status_wp_config['path'] ) . '</code>',
				'<code>/* That\'s all, stop editing! */</code>'
			);
			?>
		</p>
		<div class="grabwp-manual-code">
			<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"><?php echo esc_html( GrabWP_Tenancy_Installer::get_loader_snippet() ); ?></pre>
			<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
				<button type="button" class="button button-small grabwp-copy-code-btn">
					<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
				</button>
				<?php if ( $grabwp_status_wp_config['writable'] && $grabwp_status_wp_config['stop_editing'] ) : ?>
					<button type="button" class="button button-small button-primary grabwp-fix-btn"
						data-fix-action="grabwp_install_loader"
						data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_install_loader' ) ); ?>">
						<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
					</button>
				<?php elseif ( ! $grabwp_status_wp_config['writable'] ) : ?>
					<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'wp-config.php is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
				<?php elseif ( ! $grabwp_status_wp_config['stop_editing'] ) : ?>
					<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Stop-editing marker not found — manual install required', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php
	// 2. MU-Plugin
	?>
	<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
			<strong><?php esc_html_e( '2. MU-Plugin', 'grabwp-tenancy' ); ?></strong>
			<?php if ( $grabwp_status_mu_plugin['exists'] && $grabwp_status_mu_plugin['valid'] ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Installed', 'grabwp-tenancy' ); ?></span>
			<?php elseif ( $grabwp_status_mu_plugin['exists'] ) : ?>
				<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Outdated', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ Missing', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! ( $grabwp_status_mu_plugin['exists'] && $grabwp_status_mu_plugin['valid'] ) ) : ?>
		<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
			<?php esc_html_e( 'WordPress MU-plugins load on every request, even in tenant context. This file ensures GrabWP Tenancy and Pro are available inside tenant admin dashboards for settings sync, plugin/theme hiding, and management features.', 'grabwp-tenancy' ); ?>
		</p>
		<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
			<?php printf( esc_html__( 'File: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_mu_plugin['path'] ) . '</code>' ); ?>
		</p>
		<div class="grabwp-manual-code">
			<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"><?php echo esc_html( GrabWP_Tenancy_Installer::get_mu_plugin_content() ); ?></pre>
			<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
				<button type="button" class="button button-small grabwp-copy-code-btn">
					<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
				</button>
				<?php if ( $grabwp_status_mu_plugin['dir_writable'] || wp_is_writable( dirname( $grabwp_status_mu_plugin['dir'] ) ) ) : ?>
					<button type="button" class="button button-small button-primary grabwp-fix-btn"
						data-fix-action="grabwp_install_mu_plugin"
						data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_install_mu_plugin' ) ); ?>">
						<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
					</button>
				<?php else : ?>
					<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'mu-plugins directory is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php
	// 3. Root .htaccess (Apache/LiteSpeed only)
	if ( $grabwp_status_server['is_apache'] ) :
	?>
	<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
			<strong><?php esc_html_e( '3. Root .htaccess Rewrite Rules', 'grabwp-tenancy' ); ?></strong>
			<?php if ( $grabwp_status_htaccess['has_block'] && $grabwp_status_htaccess['block_positioned'] && $grabwp_status_htaccess['content_valid'] ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Installed', 'grabwp-tenancy' ); ?></span>
			<?php elseif ( $grabwp_status_htaccess['has_block'] && ! $grabwp_status_htaccess['block_positioned'] ) : ?>
				<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Wrong position', 'grabwp-tenancy' ); ?></span>
			<?php elseif ( $grabwp_status_htaccess['has_block'] && ! $grabwp_status_htaccess['content_valid'] ) : ?>
				<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Invalid content', 'grabwp-tenancy' ); ?></span>
			<?php elseif ( $grabwp_status_htaccess['exists'] ) : ?>
				<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Missing block', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ No .htaccess', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! ( $grabwp_status_htaccess['has_block'] && $grabwp_status_htaccess['block_positioned'] && $grabwp_status_htaccess['content_valid'] ) ) : ?>
		<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
			<?php esc_html_e( 'These Apache rewrite rules convert clean URLs like /site/abc123/wp-admin into internal WordPress requests with a ?site=abc123 parameter. This is how path-based tenant routing works.', 'grabwp-tenancy' ); ?>
		</p>
		<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
			<?php printf( esc_html__( 'File: %s — must appear BEFORE "# BEGIN WordPress"', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_htaccess['path'] ) . '</code>' ); ?>
		</p>
		<div class="grabwp-manual-code">
			<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"># BEGIN GrabWP Tenancy
&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On
RewriteRule ^site/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]
RewriteRule ^site/([a-z0-9]{6})/(.+)$ /$2?site=$1 [QSA,L,NE]
&lt;/IfModule&gt;
# END GrabWP Tenancy</pre>
			<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
				<button type="button" class="button button-small grabwp-copy-code-btn">
					<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
				</button>
				<?php if ( $grabwp_status_htaccess['writable'] || ( ! $grabwp_status_htaccess['exists'] && $grabwp_status_htaccess['dir_writable'] ) ) : ?>
					<button type="button" class="button button-small button-primary grabwp-fix-btn"
						data-fix-action="grabwp_fix_root_htaccess"
						data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_fix_component' ) ); ?>">
						<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
					</button>
				<?php else : ?>
					<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Root directory or .htaccess is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	// 4. Data Dir .htaccess
	$grabwp_step_num = $grabwp_status_server['is_apache'] ? '4' : '3';
	?>
	<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
			<strong><?php echo esc_html( $grabwp_step_num . '. ' . __( 'Data Directory .htaccess', 'grabwp-tenancy' ) ); ?></strong>
			<?php if ( $grabwp_status_data_htaccess['exists'] && $grabwp_status_data_htaccess['has_deny'] ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Protected', 'grabwp-tenancy' ); ?></span>
			<?php elseif ( $grabwp_status_data_htaccess['exists'] ) : ?>
				<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Incomplete', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ Missing', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! ( $grabwp_status_data_htaccess['exists'] && $grabwp_status_data_htaccess['has_deny'] ) ) : ?>
		<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
			<?php esc_html_e( 'Prevents direct HTTP access to PHP files and directory listing in the tenant data directory. This is a security measure to protect tenant configuration files from being accessed via URL.', 'grabwp-tenancy' ); ?>
		</p>
		<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
			<?php printf( esc_html__( 'File: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_data_htaccess['path'] ) . '</code>' ); ?>
		</p>
		<div class="grabwp-manual-code">
			<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;"># GrabWP Tenancy Security Protection
# Prevent directory listing
Options -Indexes

# Deny access to PHP files
&lt;FilesMatch "\.php$"&gt;
    &lt;IfModule mod_authz_core.c&gt;
        Require all denied
    &lt;/IfModule&gt;
    &lt;IfModule !mod_authz_core.c&gt;
        Order allow,deny
        Deny from all
    &lt;/IfModule&gt;
&lt;/FilesMatch&gt;</pre>
			<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
				<button type="button" class="button button-small grabwp-copy-code-btn">
					<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
				</button>
				<?php if ( $grabwp_status_base_dir_writable ) : ?>
					<button type="button" class="button button-small button-primary grabwp-fix-btn"
						data-fix-action="grabwp_fix_data_htaccess"
						data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_fix_component' ) ); ?>">
						<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
					</button>
				<?php else : ?>
					<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Data directory is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php
	// 5. index.php Protection
	$grabwp_step_num_idx = $grabwp_status_server['is_apache'] ? '5' : '4';
	?>
	<div class="grabwp-env-card" style="margin-top: 12px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
			<strong><?php echo esc_html( $grabwp_step_num_idx . '. ' . __( 'index.php Protection', 'grabwp-tenancy' ) ); ?></strong>
			<?php if ( $grabwp_status_index_exists ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Present', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Missing', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! $grabwp_status_index_exists ) : ?>
		<p style="margin: 6px 0 4px; color: #50575e; font-size: 13px;">
			<?php esc_html_e( 'A blank index.php file that prevents web servers from listing tenant directory contents when .htaccess is not supported or misconfigured. Standard WordPress security practice.', 'grabwp-tenancy' ); ?>
		</p>
		<p style="margin: 2px 0 8px; color: #787c82; font-size: 12px;">
			<?php printf( esc_html__( 'File: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_base_path . '/index.php' ) . '</code>' ); ?>
		</p>
		<div class="grabwp-manual-code">
			<pre style="background: #1d2327; color: #50c878; padding: 10px; overflow-x: auto; font-size: 12px; border-radius: 3px; margin: 0;">&lt;?php
/**
 * GrabWP_Tenancy - Directory Protection
 *
 * @package GrabWP_Tenancy
 */

// Silence is golden.</pre>
			<div style="margin-top: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
				<button type="button" class="button button-small grabwp-copy-code-btn">
					<?php esc_html_e( '📋 Copy Code', 'grabwp-tenancy' ); ?>
				</button>
				<?php if ( $grabwp_status_base_dir_writable ) : ?>
					<button type="button" class="button button-small button-primary grabwp-fix-btn"
						data-fix-action="grabwp_fix_index_protection"
						data-fix-nonce="<?php echo esc_attr( wp_create_nonce( 'grabwp_fix_component' ) ); ?>">
						<?php esc_html_e( '⚡ Auto Fix', 'grabwp-tenancy' ); ?>
					</button>
				<?php else : ?>
					<span style="color: #787c82; font-size: 12px;"><?php esc_html_e( 'Data directory is not writable — manual install required', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'GrabWP Tenancy Information', 'grabwp-tenancy' ); ?></h3>
	<table class="form-table">
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
						— <?php echo esc_html( $grabwp_status_pro_version ); ?>
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
					— <a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>"><?php esc_html_e( 'View all', 'grabwp-tenancy' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</div>

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'System Information', 'grabwp-tenancy' ); ?></h3>
	<table class="form-table">
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
					<br><small><?php esc_html_e( 'Path routing (/site/id) requires mod_rewrite. Query string routing (?site=id) will be used as fallback.', 'grabwp-tenancy' ); ?></small>
				<?php else : ?>
					<span style="color: #999;"><?php esc_html_e( '— Cannot detect (apache_get_modules unavailable)', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'WordPress Multisite', 'grabwp-tenancy' ); ?></th>
			<td>
				<?php if ( $grabwp_status_server['is_multisite'] ) : ?>
					<span style="color: #ff8c00;"><?php esc_html_e( '⚠ Yes — GrabWP Tenancy is not designed for Multisite', 'grabwp-tenancy' ); ?></span>
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
</div>
