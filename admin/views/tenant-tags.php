<?php
/**
 * GrabWP Tenancy - Tenant tags list page.
 *
 * @package GrabWP_Tenancy
 *
 * @var GrabWP_Tenancy_Tag_List_Table $list_table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Notice codes are checked against their own nonce below.
$grabwp_tag_message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
$grabwp_tag_nonce   = isset( $_GET['_notice_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_notice_nonce'] ) ) : '';
$grabwp_tag_count   = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
// phpcs:enable
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Tenant Tags', 'grabwp-tenancy' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . GrabWP_Tenancy_Tenant_Tags_Admin::PAGE . '&action=add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Tag', 'grabwp-tenancy' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php
	if ( '' !== $grabwp_tag_message && wp_verify_nonce( $grabwp_tag_nonce, GrabWP_Tenancy_Tenant_Tags_Admin::NOTICE_NONCE ) ) {
		$grabwp_tag_notices = array(
			'saved'        => __( 'Tag saved.', 'grabwp-tenancy' ),
			'deleted'      => __( 'Tag deleted.', 'grabwp-tenancy' ),
			'bulk_deleted' => sprintf(
				/* translators: %d: number of tags deleted. */
				_n( '%d tag deleted.', '%d tags deleted.', $grabwp_tag_count, 'grabwp-tenancy' ),
				$grabwp_tag_count
			),
		);
		if ( isset( $grabwp_tag_notices[ $grabwp_tag_message ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $grabwp_tag_notices[ $grabwp_tag_message ] )
			);
		}
	}
	?>

	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>">&larr; <?php esc_html_e( 'Back to All Tenants', 'grabwp-tenancy' ); ?></a></p>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( GrabWP_Tenancy_Tenant_Tags_Admin::PAGE ); ?>" />
		<?php $list_table->search_box( __( 'Search Tags', 'grabwp-tenancy' ), 'grabwp-tag-search' ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . GrabWP_Tenancy_Tenant_Tags_Admin::PAGE ) ); ?>">
		<?php $list_table->display(); ?>
	</form>

	<form method="post" id="<?php echo esc_attr( GrabWP_Tenancy_Tag_List_Table::DELETE_FORM_ID ); ?>" action="<?php echo esc_url( admin_url( 'admin.php?page=' . GrabWP_Tenancy_Tenant_Tags_Admin::PAGE ) ); ?>">
		<?php wp_nonce_field( 'grabwp_tenancy_tag_delete' ); ?>
		<input type="hidden" name="action" value="grabwp_tenancy_tag_delete" />
	</form>
</div>
