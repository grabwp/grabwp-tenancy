<?php
/**
 * GrabWP Tenancy - Add or edit one tenant tag.
 *
 * @package GrabWP_Tenancy
 *
 * @var bool       $grabwp_tag_is_edit Edit (true) or Add New (false).
 * @var string     $grabwp_tag_slug    Slug of the tag being edited; empty on Add New.
 * @var array|null $grabwp_tag         Tag being edited; null on Add New or when not found.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_tag_list_url = admin_url( 'admin.php?page=' . GrabWP_Tenancy_Tenant_Tags_Admin::PAGE );
?>
<div class="wrap">
	<h1><?php echo esc_html( $grabwp_tag_is_edit ? __( 'Edit Tag', 'grabwp-tenancy' ) : __( 'Add New Tag', 'grabwp-tenancy' ) ); ?></h1>

	<?php if ( $grabwp_tag_is_edit && null === $grabwp_tag ) : ?>
		<div class="notice notice-error inline">
			<p><?php esc_html_e( 'Tag not found. It may have been deleted.', 'grabwp-tenancy' ); ?></p>
		</div>
		<p><a href="<?php echo esc_url( $grabwp_tag_list_url ); ?>">&larr; <?php esc_html_e( 'Back to Tags', 'grabwp-tenancy' ); ?></a></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( $grabwp_tag_list_url ); ?>">
			<?php wp_nonce_field( 'grabwp_tenancy_tag_save' ); ?>
			<input type="hidden" name="action" value="grabwp_tenancy_tag_save" />
			<?php if ( $grabwp_tag_is_edit ) : ?>
				<input type="hidden" name="old_slug" value="<?php echo esc_attr( $grabwp_tag_slug ); ?>" />
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="grabwp-tag-name"><?php esc_html_e( 'Name', 'grabwp-tenancy' ); ?></label></th>
					<td>
						<input type="text" id="grabwp-tag-name" name="tag_name" class="regular-text" required maxlength="<?php echo esc_attr( (string) GrabWP_Tenancy_Management::TAG_NAME_MAX ); ?>" value="<?php echo esc_attr( $grabwp_tag_is_edit ? $grabwp_tag['name'] : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="grabwp-tag-slug"><?php esc_html_e( 'Slug', 'grabwp-tenancy' ); ?></label>
						<?php
						GrabWP_Tenancy_Admin::help_tip(
							$grabwp_tag_is_edit
								? __( 'Lowercase letters, numbers and hyphens. Changing it updates every tenant that has this tag.', 'grabwp-tenancy' )
								: __( 'Lowercase letters, numbers and hyphens. Leave it empty to make it from the name.', 'grabwp-tenancy' )
						);
						?>
					</th>
					<td>
						<input type="text" id="grabwp-tag-slug" name="tag_slug" class="regular-text grabwp-slug-input" dir="ltr" pattern="[a-z0-9\-]+" spellcheck="false" autocapitalize="none" maxlength="<?php echo esc_attr( (string) GrabWP_Tenancy_Management::SLUG_MAX ); ?>" <?php echo $grabwp_tag_is_edit ? 'required' : ''; ?> value="<?php echo esc_attr( $grabwp_tag_slug ); ?>" />
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php submit_button( $grabwp_tag_is_edit ? __( 'Update Tag', 'grabwp-tenancy' ) : __( 'Add Tag', 'grabwp-tenancy' ), 'primary', 'submit', false ); ?>
				<a href="<?php echo esc_url( $grabwp_tag_list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'grabwp-tenancy' ); ?></a>
			</p>
		</form>
	<?php endif; ?>
</div>
