<?php
/**
 * GrabWP Tenancy - Name and Tags rows for the Add New and Edit tenant forms.
 *
 * Works without JavaScript (checkboxes plus a "New tags" field); the tag
 * script turns the checkboxes into chips. The hidden marker tells the form
 * handler the rows were posted, so unticking every tag still clears them.
 *
 * @package GrabWP_Tenancy
 *
 * @var string $grabwp_management_tenant_id Tenant ID being edited; empty on Add New.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_management_entry = GrabWP_Tenancy_Management::get_tenant( $grabwp_management_tenant_id );
$grabwp_management_tags  = GrabWP_Tenancy_Management::get_tags();
// Lookup by slug: isset() also matches an all-digit slug that PHP keys as an int.
$grabwp_management_ticked = array_flip( $grabwp_management_entry['tags'] );
?>
<tr>
	<th scope="row"><label for="grabwp-tenant-name"><?php esc_html_e( 'Name', 'grabwp-tenancy' ); ?></label></th>
	<td>
		<input type="hidden" name="<?php echo esc_attr( GrabWP_Tenancy_Management::MARKER ); ?>" value="1" />
		<input type="text" id="grabwp-tenant-name" name="tenant_name" value="<?php echo esc_attr( $grabwp_management_entry['name'] ); ?>" class="regular-text" maxlength="<?php echo esc_attr( (string) GrabWP_Tenancy_Management::NAME_MAX ); ?>" />
		<p class="description"><?php esc_html_e( 'Optional. A short name to tell this site apart in the tenant list.', 'grabwp-tenancy' ); ?></p>
	</td>
</tr>
<tr>
	<th scope="row"><span id="grabwp-tenant-tags-title"><?php esc_html_e( 'Tags', 'grabwp-tenancy' ); ?></span></th>
	<td>
		<div class="grabwp-tenant-tags" data-grabwp-tenant-tags>
			<fieldset class="grabwp-tenant-tags__list" aria-labelledby="grabwp-tenant-tags-title">
				<?php if ( empty( $grabwp_management_tags ) ) : ?>
					<span class="grabwp-tenant-tags__none"><?php esc_html_e( 'No tags yet.', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
				<?php foreach ( $grabwp_management_tags as $grabwp_tag_slug => $grabwp_tag ) : ?>
					<label class="grabwp-tenant-tags__option">
						<input type="checkbox" name="tenant_tags[]" value="<?php echo esc_attr( $grabwp_tag_slug ); ?>" <?php checked( isset( $grabwp_management_ticked[ $grabwp_tag_slug ] ) ); ?> />
						<?php echo esc_html( $grabwp_tag['name'] ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="grabwp-tenant-tags__new">
				<label for="grabwp-tenant-new-tags"><?php esc_html_e( 'New tags, comma separated', 'grabwp-tenancy' ); ?></label><br />
				<input type="text" id="grabwp-tenant-new-tags" name="tenant_new_tags" class="regular-text" />
			</p>
		</div>
		<p class="description">
			<?php esc_html_e( 'Optional. Group sites with your own tags, then filter the tenant list by tag.', 'grabwp-tenancy' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GrabWP_Tenancy_Tenant_Tags_Admin::PAGE ) ); ?>"><?php esc_html_e( 'Manage tags', 'grabwp-tenancy' ); ?></a>
		</p>
	</td>
</tr>
