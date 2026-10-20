<?php
/**
 * One-time result panel after a site is created (Add New and setup wizard).
 *
 * Variables available from the including scope:
 * @var array $grabwp_site_result { tenant_id, password, install_error }
 *
 * @package GrabWP_Tenancy
 * @since 1.1.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_result_tid    = isset( $grabwp_site_result['tenant_id'] ) ? (string) $grabwp_site_result['tenant_id'] : '';
$grabwp_result_tenant = '' !== $grabwp_result_tid ? GrabWP_Tenancy_Tenant_Installer::get_tenant( $grabwp_result_tid ) : null;
if ( ! $grabwp_result_tenant ) {
	return;
}

$grabwp_result_site_url  = $grabwp_result_tenant->get_site_url();
$grabwp_result_admin_url = $grabwp_result_tenant->get_admin_access_url();
$grabwp_result_password  = isset( $grabwp_site_result['password'] ) ? (string) $grabwp_site_result['password'] : '';
$grabwp_result_error     = isset( $grabwp_site_result['install_error'] ) ? (string) $grabwp_site_result['install_error'] : '';
?>
<?php if ( '' !== $grabwp_result_error ) : ?>
	<div class="notice notice-warning grabwp-site-result">
		<p>
			<?php
			printf(
				/* translators: %s: reason the install failed. */
				esc_html__( 'The site was created but WordPress could not be installed automatically: %s', 'grabwp-tenancy' ),
				esc_html( $grabwp_result_error )
			);
			?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( GrabWP_Tenancy_Tenant_Installer::loopback_url( $grabwp_result_tid, 'wp-admin/install.php' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Finish install in browser', 'grabwp-tenancy' ); ?></a>
		</p>
	</div>
<?php else : ?>
	<div class="notice notice-success grabwp-site-result">
		<p>
			<strong>
				<?php
				printf(
					/* translators: %s: tenant ID. */
					esc_html__( 'Site created: %s', 'grabwp-tenancy' ),
					esc_html( $grabwp_result_tid )
				);
				?>
			</strong>
			<a href="<?php echo esc_url( $grabwp_result_site_url ); ?>" target="_blank" rel="noopener noreferrer" dir="ltr"><?php echo esc_html( $grabwp_result_site_url ); ?></a>
		</p>
		<?php if ( '' !== $grabwp_result_password ) : ?>
			<p class="grabwp-site-result-credentials">
				<?php esc_html_e( 'Username', 'grabwp-tenancy' ); ?>
				<code dir="ltr"><?php echo esc_html( $grabwp_result_tid ); ?></code>
				<label for="grabwp-site-result-password"><?php esc_html_e( 'Password', 'grabwp-tenancy' ); ?></label>
				<input type="password" id="grabwp-site-result-password" class="regular-text code grabwp-site-result-password" value="<?php echo esc_attr( $grabwp_result_password ); ?>" readonly dir="ltr" autocomplete="off" />
				<button type="button" class="button grabwp-reveal-password" data-target="grabwp-site-result-password" data-show-label="<?php esc_attr_e( 'Show', 'grabwp-tenancy' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide', 'grabwp-tenancy' ); ?>"><?php esc_html_e( 'Show', 'grabwp-tenancy' ); ?></button>
				<button type="button" class="button grabwp-copy-path-url" data-copy-value="<?php echo esc_attr( $grabwp_result_password ); ?>"><?php esc_html_e( 'Copy', 'grabwp-tenancy' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'This password is shown once. You do not need it: the 1-click Admin button logs you in.', 'grabwp-tenancy' ); ?></p>
		<?php endif; ?>
		<p>
			<a class="button button-primary grabwp-open-site-admin" href="<?php echo esc_url( $grabwp_result_admin_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open site admin (1-click)', 'grabwp-tenancy' ); ?></a>
		</p>
	</div>
<?php endif; ?>
