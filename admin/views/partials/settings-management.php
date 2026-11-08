<?php
/**
 * GrabWP Tenancy - Management settings tab body.
 *
 * The switch is stored in {base}/management.php, not in settings.php.
 *
 * @package GrabWP_Tenancy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( GrabWP_Tenancy_Management::is_corrupt() ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<?php
			printf(
				/* translators: %s: file name. */
				esc_html__( 'GrabWP cannot read %s in the GrabWP data folder, so names and tags cannot be saved. Fix or remove the file.', 'grabwp-tenancy' ),
				'<code dir="ltr">management.php</code>'
			);
			?>
		</p>
	</div>
<?php endif; ?>
<table class="form-table">
	<tr>
		<th scope="row">
			<span id="grabwp-setting-management-enabled-title"><?php esc_html_e( 'Enable names and tags', 'grabwp-tenancy' ); ?></span>
			<?php GrabWP_Tenancy_Admin::help_tip( __( 'Adds a Name and Tags to each site in the tenant list and the Add New and Edit screens. Turning it off hides them and keeps what you saved.', 'grabwp-tenancy' ) ); ?>
		</th>
		<td>
			<input type="hidden" name="<?php echo esc_attr( GrabWP_Tenancy_Management::MARKER ); ?>" value="1" />
			<label id="grabwp-setting-management-enabled-yes">
				<input type="checkbox" name="grabwp_management_enabled" value="1" aria-labelledby="grabwp-setting-management-enabled-title grabwp-setting-management-enabled-yes" <?php checked( GrabWP_Tenancy_Management::is_enabled() ); ?> />
				<?php esc_html_e( 'Yes', 'grabwp-tenancy' ); ?>
			</label>
		</td>
	</tr>
</table>
