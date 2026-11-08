<?php
/**
 * GrabWP Tenancy - Edit Tenant Admin Page Template
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1>
		<?php
		/* translators: %s: tenant ID */
		printf( esc_html__( 'Edit Tenant: %s', 'grabwp-tenancy' ), '<code>' . esc_html( $tenant->get_id() ) . '</code>' );
		?>
	</h1>
	<?php
	/**
	 * Fires after the Edit Tenant page title, before the form.
	 * Used by Pro plugin to render tenant action navigation tabs.
	 *
	 * @since 1.0.8
	 * @param object $tenant The tenant object being edited.
	 */
	do_action( 'grabwp_tenancy_after_edit_title', $tenant );
	?>
	<p><?php esc_html_e( 'Edit tenant settings and domain mappings.', 'grabwp-tenancy' ); ?></p>
	<?php
	// Check for success parameter with nonce verification.
	$grabwp_tenancy_notice_nonce = isset( $_GET['_notice_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_notice_nonce'] ) ) : '';
	if ( isset( $_GET['message'] ) && 'updated' === $_GET['message']
		&& wp_verify_nonce( $grabwp_tenancy_notice_nonce, 'grabwp_tenancy_notice' ) ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Tenant updated successfully.', 'grabwp-tenancy' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	// Check for error parameter with nonce verification.
	$grabwp_tenancy_error_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( isset( $_GET['error'] ) && wp_verify_nonce( $grabwp_tenancy_error_nonce, 'grabwp_tenancy_error' ) ) :
		?>
		<?php
		$grabwp_tenancy_error_message = get_transient( 'grabwp_tenancy_error' );
		if ( $grabwp_tenancy_error_message ) :
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $grabwp_tenancy_error_message ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php $grabwp_routing_mode = grabwp_tenancy_get_routing_mode(); ?>
	<div>
		<form method="post" class="grabwp-tenancy-form" data-routing-mode="<?php echo esc_attr( $grabwp_routing_mode ); ?>">
			<?php wp_nonce_field( 'grabwp_tenancy_update' ); ?>
			<input type="hidden" name="action" value="update_tenant" />
			<input type="hidden" name="tenant_id" value="<?php echo esc_attr( $tenant->get_id() ); ?>" />
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Tenant ID', 'grabwp-tenancy' ); ?></th>
					<td><code><?php echo esc_html( $tenant->get_id() ); ?></code></td>
				</tr>
<?php
if ( GrabWP_Tenancy_Management::is_enabled() ) {
	$grabwp_management_tenant_id = $tenant->get_id();
	require __DIR__ . '/partials/tenant-management-rows.php';
}
?>
				<?php if ( 'domain' !== $grabwp_routing_mode ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Path URL', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php $grabwp_tenancy_path_url = $tenant->get_path_url() . '/'; ?>
						<code id="grabwp-path-url-value" dir="ltr"><?php echo esc_html( $grabwp_tenancy_path_url ); ?></code>
						<button type="button" class="button button-small grabwp-copy-path-url" data-copy-value="<?php echo esc_attr( $grabwp_tenancy_path_url ); ?>">
							<?php esc_html_e( 'Copy', 'grabwp-tenancy' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'This URL is always available, no DNS configuration needed.', 'grabwp-tenancy' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Domains', 'grabwp-tenancy' ); ?></th>
					<td>
						<?php if ( 'path' === $grabwp_routing_mode ) : ?>
						<details class="grabwp-stored-domains">
							<summary>
								<?php
								$grabwp_routing_stored = count( $tenant->get_real_domains() );
								printf(
									/* translators: %d: number of domains stored for the site. */
									esc_html( _n( 'Stored domains (%d): kept, not used for links in Path mode', 'Stored domains (%d): kept, not used for links in Path mode', $grabwp_routing_stored, 'grabwp-tenancy' ) ),
									(int) $grabwp_routing_stored
								);
								?>
							</summary>
						<?php endif; ?>
						<div class="grabwp-edit-domain-inputs">
							<?php
							$grabwp_tenancy_domains = $tenant->get_domains();
							if ( ! empty( $grabwp_tenancy_domains ) ) :
								foreach ( $grabwp_tenancy_domains as $grabwp_tenancy_domain ) :
									?>
									<div class="grabwp-edit-domain-input">
										<input type="text" name="domains[]" value="<?php echo esc_attr( strtolower( $grabwp_tenancy_domain ) ); ?>" placeholder="<?php esc_attr_e( 'Enter domain (e.g., tenant1.grabwp.local)', 'grabwp-tenancy' ); ?>" class="regular-text" autocapitalize="none" spellcheck="false" />
										<button type="button" class="button grabwp-remove-edit-domain grabwp-ml-sm"><?php esc_html_e( 'Remove', 'grabwp-tenancy' ); ?></button>
									</div>
									<?php
								endforeach;
							else :
								?>
								<div class="grabwp-edit-domain-input">
									<input type="text" name="domains[]" placeholder="<?php esc_attr_e( 'Enter domain (e.g., tenant1.grabwp.local)', 'grabwp-tenancy' ); ?>" class="regular-text" autocapitalize="none" spellcheck="false" />
									<button type="button" class="button grabwp-remove-edit-domain grabwp-ml-sm"><?php esc_html_e( 'Remove', 'grabwp-tenancy' ); ?></button>
								</div>
							<?php endif; ?>
						</div>
						<button type="button" class="button grabwp-add-edit-domain grabwp-mt-sm">
							<?php esc_html_e( 'Add New Domain', 'grabwp-tenancy' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'Valid format: example.com, subdomain.example.com (no http:// or www)', 'grabwp-tenancy' ); ?></p>
						<?php if ( 'path' === $grabwp_routing_mode ) : ?>
						</details>
						<?php elseif ( 'domain' === $grabwp_routing_mode ) : ?>
						<p class="description"><?php esc_html_e( 'Domain mode needs at least one domain.', 'grabwp-tenancy' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<?php
				/**
				 * Add extra fields to tenant edit form
				 *
				 * @since 1.0.4
				 * @param object $tenant The tenant object being edited
				 */
				do_action( 'grabwp_tenancy_edit_form_fields', $tenant );
				?>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Update Tenant', 'grabwp-tenancy' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>" class="button grabwp-ml-sm">
					<?php esc_html_e( 'Cancel', 'grabwp-tenancy' ); ?>
				</a>
			</p>
		</form>
	</div>
	<?php
	GrabWP_Tenancy_Admin::render_upsell_card(
		array(
			'utm_content' => 'create-edit-form',
			'message' => __( 'Need more control? GrabWP Tenancy Pro adds isolated databases (MySQL or SQLite), scheduled backups, cloud storage offload, and more for every client site.', 'grabwp-tenancy' ),
			'title' => __( 'GrabWP Tenancy', 'grabwp-tenancy' ),
			'cta_label' => __( 'Learn about Pro', 'grabwp-tenancy' ),
			'margin' => 'grabwp-mt-md',
		)
	);
	?>
</div>
