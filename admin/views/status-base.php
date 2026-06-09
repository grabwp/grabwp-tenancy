<?php
/**
 * Status Page - Base Plugin Tab
 *
 * File structure, settings, and tenant capabilities.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 *
 * Variables available from parent scope:
 * @var string $grabwp_status_base_path      Base data directory path
 * @var string $grabwp_status_mappings_file  Tenant mappings file path
 * @var string $grabwp_status_settings_file  Settings file path
 * @var array  $grabwp_status_path_status    Path status from PathManager
 * @var GrabWP_Tenancy_Settings $grabwp_status_settings_inst Settings instance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'File Structure', 'grabwp-tenancy' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Base Directory', 'grabwp-tenancy' ); ?></th>
			<td>
				<code><?php echo esc_html( $grabwp_status_base_path ); ?></code>
				<?php if ( is_dir( $grabwp_status_base_path ) ) : ?>
					<br><span style="color: #46b450;"><?php esc_html_e( '✓ Directory exists', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<br><span style="color: #dc3232;"><?php esc_html_e( '✗ Directory does not exist', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
				<?php if ( $grabwp_status_path_status['using_old'] ) : ?>
					<br><span style="color: #ff8c00;"><?php esc_html_e( '⚠ Using legacy path structure', 'grabwp-tenancy' ); ?></span>
				<?php elseif ( $grabwp_status_path_status['is_custom'] ) : ?>
					<br><span style="color: #0073aa;"><?php esc_html_e( 'ℹ Using custom path configuration', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Tenant Mappings File', 'grabwp-tenancy' ); ?></th>
			<td>
				<code><?php echo esc_html( $grabwp_status_mappings_file ); ?></code>
				<?php if ( file_exists( $grabwp_status_mappings_file ) ) : ?>
					<br><span style="color: #46b450;"><?php esc_html_e( '✓ File exists and is readable', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<br><span style="color: #dc3232;"><?php esc_html_e( '✗ File does not exist', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Settings File', 'grabwp-tenancy' ); ?></th>
			<td>
				<code><?php echo esc_html( $grabwp_status_settings_file ); ?></code>
				<?php if ( file_exists( $grabwp_status_settings_file ) ) : ?>
					<br><span style="color: #46b450;"><?php esc_html_e( '✓ File exists', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<br><span style="color: #ff8c00;"><?php esc_html_e( '⚠ Not created yet (using defaults)', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Tenant Uploads Pattern', 'grabwp-tenancy' ); ?></th>
			<td>
				<code><?php echo esc_html( $grabwp_status_base_path . '/{tenant_id}/uploads' ); ?></code>
			</td>
		</tr>
	</table>
</div>

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'Tenant Capabilities (Settings)', 'grabwp-tenancy' ); ?></h3>
	<p class="description">
		<?php
		printf(
			esc_html__( 'These settings are configured on the %s page.', 'grabwp-tenancy' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=grabwp-tenancy-settings' ) ) . '">' . esc_html__( 'Settings', 'grabwp-tenancy' ) . '</a>'
		);
		?>
	</p>

	<table class="form-table">
		<?php
		$grabwp_capability_settings = $grabwp_status_settings_inst->get_all();
		$grabwp_capability_labels   = array(
			'disallow_file_mods'     => __( 'Disallow File Mods', 'grabwp-tenancy' ),
			'disallow_file_edit'     => __( 'Disallow File Edit', 'grabwp-tenancy' ),
			'hide_plugin_management' => __( 'Hide Plugin Management', 'grabwp-tenancy' ),
			'hide_theme_management'  => __( 'Hide Theme Management', 'grabwp-tenancy' ),
			'hide_grabwp_plugins'    => __( 'Hide GrabWP Plugins', 'grabwp-tenancy' ),
		);
		foreach ( $grabwp_capability_labels as $grabwp_cap_key => $grabwp_cap_label ) :
			$grabwp_cap_value = isset( $grabwp_capability_settings[ $grabwp_cap_key ] ) ? $grabwp_capability_settings[ $grabwp_cap_key ] : false;
			?>
		<tr>
			<th scope="row"><?php echo esc_html( $grabwp_cap_label ); ?></th>
			<td>
				<?php if ( $grabwp_cap_value ) : ?>
					<span style="color: #46b450;"><?php esc_html_e( '✓ Enabled', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<span style="color: #999;"><?php esc_html_e( '— Disabled', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
</div>
