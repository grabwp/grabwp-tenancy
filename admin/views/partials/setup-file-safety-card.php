<?php
/**
 * Tenant file safety card (setup wizard Check screen and Status page). Shows OK
 * when both file protections are on for every tenant.
 *
 * Variables available from partials/system-check.php:
 * @var array $grabwp_check_unsafe_files   Result of GrabWP_Tenancy_Status_Checker::get_unsafe_file_settings().
 * @var array $grabwp_check_unsafe_tenants Result of GrabWP_Tenancy_Status_Checker::get_unsafe_tenant_file_overrides().
 *
 * @package GrabWP_Tenancy
 * @since 1.1.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $grabwp_check_unsafe_tenants ) ) {
	$grabwp_check_unsafe_tenants = array();
}
if ( ! $grabwp_check_unsafe_files && ! $grabwp_check_unsafe_tenants ) :
	?>
<div class="grabwp-env-card grabwp-setup-file-safety" data-ok="1">
	<div class="grabwp-env-card-header">
		<strong><?php esc_html_e( 'Tenant file safety', 'grabwp-tenancy' ); ?></strong>
		<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( 'OK', 'grabwp-tenancy' ); ?></span>
	</div>
	<p class="grabwp-env-note"><?php esc_html_e( 'Tenant admins cannot install or edit plugin and theme code.', 'grabwp-tenancy' ); ?></p>
</div>
	<?php
	return;
endif;

$grabwp_check_fields = GrabWP_Tenancy_Settings::get_setting_fields();
?>
<div class="grabwp-env-card grabwp-setup-file-safety" data-ok="0">
	<div class="grabwp-env-card-header">
		<strong><?php esc_html_e( 'Tenant file safety', 'grabwp-tenancy' ); ?></strong>
		<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php echo esc_html_x( 'Unsafe', 'file safety status', 'grabwp-tenancy' ); ?></span>
	</div>
	<p class="grabwp-env-note"><?php esc_html_e( 'Tenant admins can install or edit plugin and theme code. That code runs on this server and can read the secret keys every site shares.', 'grabwp-tenancy' ); ?></p>
<?php if ( $grabwp_check_unsafe_files ) : ?>
	<ul class="grabwp-setup-file-safety-list">
		<?php foreach ( $grabwp_check_unsafe_files as $grabwp_check_key => $grabwp_check_constant ) : ?>
			<li>
				<?php
				if ( '' === $grabwp_check_constant ) {
					printf(
						/* translators: %s: setting name, e.g. Disable Plugin & Theme File Editor */
						esc_html__( 'Turn on %s in Settings.', 'grabwp-tenancy' ),
						'<strong>' . esc_html( $grabwp_check_fields[ $grabwp_check_key ]['label'] ) . '</strong>'
					);
				} else {
					printf(
						/* translators: 1: constant name, e.g. DISALLOW_FILE_EDIT, 2: the value false, 3: the value true */
						esc_html__( 'Code that loads before GrabWP, usually wp-config.php, sets %1$s to %2$s. Change it to %3$s or remove that line.', 'grabwp-tenancy' ),
						'<code dir="ltr">' . esc_html( $grabwp_check_constant ) . '</code>',
						'<code dir="ltr">false</code>',
						'<code dir="ltr">true</code>'
					);
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
<?php if ( $grabwp_check_unsafe_tenants ) : ?>
	<?php
	$grabwp_check_tenant_mappings = class_exists( 'GrabWP_Tenancy_Tenant_Installer' ) ? GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() : array();
	?>
	<ul class="grabwp-setup-file-safety-tenants">
		<?php foreach ( $grabwp_check_unsafe_tenants as $grabwp_check_tenant_id => $grabwp_check_constants ) : ?>
			<li>
				<?php
				$grabwp_check_site   = '<code dir="ltr">' . esc_html( $grabwp_check_tenant_id ) . '</code>';
				$grabwp_check_domain = function_exists( 'grabwp_tenancy_first_real_domain' )
					? grabwp_tenancy_first_real_domain( $grabwp_check_tenant_mappings, $grabwp_check_tenant_id )
					: '';
				if ( '' !== $grabwp_check_domain ) {
					$grabwp_check_site .= ' (<span dir="ltr">' . esc_html( $grabwp_check_domain ) . '</span>)';
				}
				printf(
					/* translators: 1: site ID, followed by its domain when known, 2: constant names, e.g. DISALLOW_FILE_EDIT, 3: the value false */
					esc_html__( 'A per-site override sets %2$s to %3$s for site %1$s. In that site\'s Overrides screen (GrabWP Pro), choose Yes or Use global setting.', 'grabwp-tenancy' ),
					$grabwp_check_site, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html() above.
					'<code dir="ltr">' . esc_html( implode( ', ', $grabwp_check_constants ) ) . '</code>',
					'<code dir="ltr">false</code>'
				);
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
	<?php if ( in_array( '', $grabwp_check_unsafe_files, true ) ) : ?>
		<p><a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'grabwp-tenancy' ); ?></a></p>
	<?php endif; ?>
</div>
