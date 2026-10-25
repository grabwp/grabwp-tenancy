<?php
/**
 * Status Page - Base Plugin Tab
 *
 * File structure and every GrabWP setting (capabilities, performance and security).
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

<div class="grabwp-settings-grid-2">
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
		<h3>
			<?php esc_html_e( 'Tenant Capabilities', 'grabwp-tenancy' ); ?>
			<?php GrabWP_Tenancy_Admin::help_tip( __( 'Configured on the Settings page.', 'grabwp-tenancy' ) ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy-settings' ) ); ?>" class="button button-small" style="margin-left: 8px; vertical-align: middle;"><?php esc_html_e( 'Settings', 'grabwp-tenancy' ); ?></a>
		</h3>

		<?php
		// Same labels and order as the Settings screen; Yes means the label applies.
		$grabwp_status_values = $grabwp_status_settings_inst->get_all();
		$grabwp_status_groups = array(
			'capabilities' => '',
			'performance'  => __( 'Performance & Security', 'grabwp-tenancy' ),
		);
		foreach ( $grabwp_status_groups as $grabwp_status_group => $grabwp_status_heading ) :
			if ( '' !== $grabwp_status_heading ) :
				?>
				<h3><?php echo esc_html( $grabwp_status_heading ); ?></h3>
			<?php endif; ?>
		<table class="form-table">
			<?php
			foreach ( GrabWP_Tenancy_Settings::get_setting_fields() as $grabwp_cap_key => $grabwp_cap_field ) :
				if ( $grabwp_status_group !== $grabwp_cap_field['group'] ) {
					continue;
				}
				$grabwp_cap_value = isset( $grabwp_status_values[ $grabwp_cap_key ] ) ? $grabwp_status_values[ $grabwp_cap_key ] : false;
				?>
			<tr>
				<th scope="row"><?php echo esc_html( $grabwp_cap_field['label'] ); ?></th>
				<td>
					<?php if ( 'int' === $grabwp_cap_field['type'] ) : ?>
						<?php echo esc_html( (string) (int) $grabwp_cap_value ); ?>
					<?php elseif ( 'string' === $grabwp_cap_field['type'] ) : ?>
						<?php if ( '' === (string) $grabwp_cap_value ) : ?>
							<span style="color: #999;"><?php esc_html_e( 'empty', 'grabwp-tenancy' ); ?></span>
						<?php else : ?>
							<code dir="ltr"><?php echo esc_html( (string) $grabwp_cap_value ); ?></code>
						<?php endif; ?>
					<?php elseif ( $grabwp_cap_value ) : ?>
						<span style="color: #46b450;"><?php esc_html_e( '✓ Yes', 'grabwp-tenancy' ); ?></span>
					<?php else : ?>
						<span style="color: #999;"><?php esc_html_e( 'No', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
					<?php GrabWP_Tenancy_Admin::constant_pin_notice( $grabwp_cap_key, $grabwp_cap_value ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php endforeach; ?>
	</div>
</div>
