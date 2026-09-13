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

	<div class="grabwp-env-cards-grid">
	<?php
	// 1. wp-config.php Loader
	?>
	<div class="grabwp-env-card">
		<div class="grabwp-env-card-header">
			<strong><?php esc_html_e( '1. wp-config.php Loader', 'grabwp-tenancy' ); ?></strong>
			<?php if ( $grabwp_status_wp_config['loader_active'] ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Active', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( '✗ Not loaded', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! $grabwp_status_wp_config['loader_active'] ) : ?>
		<details class="grabwp-env-code">
			<summary><?php esc_html_e( 'Fix steps and install code', 'grabwp-tenancy' ); ?></summary>
			<p class="grabwp-env-note">
				<?php esc_html_e( 'Loads tenant detection before WordPress boots so domain/path routing can identify the tenant.', 'grabwp-tenancy' ); ?>
			</p>
			<p class="grabwp-env-meta">
				<?php
				printf(
					esc_html__( 'File: %1$s, place before %2$s', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_status_wp_config['path'] ) . '</code>',
					'<code>/* That\'s all, stop editing! */</code>'
				);
				?>
			</p>
			<div class="grabwp-manual-code">
				<pre class="grabwp-env-pre"><?php echo esc_html( GrabWP_Tenancy_Installer::get_loader_snippet() ); ?></pre>
				<div class="grabwp-env-actions">
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
						<span class="grabwp-env-meta"><?php esc_html_e( 'wp-config.php is not writable, manual install required', 'grabwp-tenancy' ); ?></span>
					<?php elseif ( ! $grabwp_status_wp_config['stop_editing'] ) : ?>
						<span class="grabwp-env-meta"><?php esc_html_e( 'Stop-editing marker not found, manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</details>
		<?php endif; ?>
	</div>

	<?php
	// 2. MU-Plugin
	?>
	<div class="grabwp-env-card">
		<div class="grabwp-env-card-header">
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
		<details class="grabwp-env-code">
			<summary><?php esc_html_e( 'Fix steps and install code', 'grabwp-tenancy' ); ?></summary>
			<p class="grabwp-env-note">
				<?php esc_html_e( 'MU-plugins load on every request, including tenant admin, for settings sync and management features.', 'grabwp-tenancy' ); ?>
			</p>
			<p class="grabwp-env-meta">
				<?php printf( esc_html__( 'File: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_mu_plugin['path'] ) . '</code>' ); ?>
			</p>
			<div class="grabwp-manual-code">
				<pre class="grabwp-env-pre"><?php echo esc_html( GrabWP_Tenancy_Installer::get_mu_plugin_content() ); ?></pre>
				<div class="grabwp-env-actions">
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
						<span class="grabwp-env-meta"><?php esc_html_e( 'mu-plugins directory is not writable, manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</details>
		<?php endif; ?>
	</div>

	<?php
	// 3. Root .htaccess (Apache/LiteSpeed only)
	if ( $grabwp_status_server['is_apache'] ) :
		?>
	<div class="grabwp-env-card">
		<div class="grabwp-env-card-header">
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
		<details class="grabwp-env-code">
			<summary><?php esc_html_e( 'Fix steps and install code', 'grabwp-tenancy' ); ?></summary>
			<p class="grabwp-env-note">
				<?php
				$grabwp_status_prefix = grabwp_tenancy_get_path_prefix();
				printf(
					esc_html__( 'Apache rewrite rules for path-based tenant URLs like /%s/{id}/wp-admin.', 'grabwp-tenancy' ),
					esc_html( $grabwp_status_prefix )
				);
				?>
			</p>
			<p class="grabwp-env-meta">
				<?php printf( esc_html__( 'File: %s, must appear BEFORE "# BEGIN WordPress"', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_htaccess['path'] ) . '</code>' ); ?>
			</p>
			<div class="grabwp-manual-code">
				<pre class="grabwp-env-pre"># BEGIN GrabWP Tenancy
&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On
# Tenant homepage: /<?php echo esc_html( $grabwp_status_prefix ); ?>/{tenant-id-or-alias}[/] → WordPress front-end with site param
RewriteRule ^<?php echo esc_html( $grabwp_status_prefix ); ?>/([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)/?$ /index.php?site=$1 [QSA,L]
# Tenant sub-paths (wp-admin, wp-login, pages, etc.): strip prefix, pass site param
RewriteRule ^<?php echo esc_html( $grabwp_status_prefix ); ?>/([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)/(.+)$ /$2?site=$1 [QSA,L,NE]
&lt;/IfModule&gt;
# END GrabWP Tenancy</pre>
				<div class="grabwp-env-actions">
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
						<span class="grabwp-env-meta"><?php esc_html_e( 'Root directory or .htaccess is not writable, manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</details>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	// 4. Data Dir .htaccess
	$grabwp_step_num = $grabwp_status_server['is_apache'] ? '4' : '3';
	?>
	<div class="grabwp-env-card">
		<div class="grabwp-env-card-header">
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
		<details class="grabwp-env-code">
			<summary><?php esc_html_e( 'Fix steps and install code', 'grabwp-tenancy' ); ?></summary>
			<p class="grabwp-env-note">
				<?php esc_html_e( 'Blocks direct HTTP access to PHP files and directory listing in the tenant data directory.', 'grabwp-tenancy' ); ?>
			</p>
			<p class="grabwp-env-meta">
				<?php printf( esc_html__( 'File: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_data_htaccess['path'] ) . '</code>' ); ?>
			</p>
			<div class="grabwp-manual-code">
				<pre class="grabwp-env-pre"># GrabWP Tenancy Security Protection
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
				<div class="grabwp-env-actions">
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
						<span class="grabwp-env-meta"><?php esc_html_e( 'Data directory is not writable, manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</details>
		<?php endif; ?>
	</div>

	<?php
	// 5. index.php Protection
	$grabwp_step_num_idx = $grabwp_status_server['is_apache'] ? '5' : '4';
	?>
	<div class="grabwp-env-card">
		<div class="grabwp-env-card-header">
			<strong><?php echo esc_html( $grabwp_step_num_idx . '. ' . __( 'index.php Protection', 'grabwp-tenancy' ) ); ?></strong>
			<?php if ( $grabwp_status_index_exists ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Present', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #ff8c00; font-size: 13px;"><?php esc_html_e( '⚠ Missing', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! $grabwp_status_index_exists ) : ?>
		<details class="grabwp-env-code">
			<summary><?php esc_html_e( 'Fix steps and install code', 'grabwp-tenancy' ); ?></summary>
			<p class="grabwp-env-note">
				<?php esc_html_e( 'Blank index.php prevents directory listing when .htaccess is unavailable.', 'grabwp-tenancy' ); ?>
			</p>
			<p class="grabwp-env-meta">
				<?php printf( esc_html__( 'File: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $grabwp_status_base_path . '/index.php' ) . '</code>' ); ?>
			</p>
			<div class="grabwp-manual-code">
				<pre class="grabwp-env-pre">&lt;?php
/**
 * GrabWP_Tenancy - Directory Protection
 *
 * @package GrabWP_Tenancy
 */

// Silence is golden.</pre>
				<div class="grabwp-env-actions">
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
						<span class="grabwp-env-meta"><?php esc_html_e( 'Data directory is not writable, manual install required', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</details>
		<?php endif; ?>
	</div>
	</div><!-- .grabwp-env-cards-grid -->
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
