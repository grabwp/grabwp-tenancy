<?php
/**
 * GrabWP Tenancy - Create Tenant Admin Page Template
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
	<h1><?php esc_html_e( 'Add New Tenant', 'grabwp-tenancy' ); ?></h1>
	<p><?php esc_html_e( 'Create a new tenant. A path-based URL will be assigned automatically.', 'grabwp-tenancy' ); ?></p>

	<?php
	// Clone source passthrough: if coming from clone page, show info and redirect back after creation.
	$grabwp_clone_source = isset( $_GET['clone_source'] ) ? sanitize_key( wp_unslash( $_GET['clone_source'] ) ) : '';
	?>

	<?php if ( $grabwp_clone_source ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				$grabwp_clone_source_label = ( defined( 'GRABWP_MAINSITE_ID' ) && GRABWP_MAINSITE_ID === $grabwp_clone_source )
					? __( 'Main Site', 'grabwp-tenancy' )
					: $grabwp_clone_source;
				printf(
					/* translators: %s: source tenant ID or "Main Site" */
					esc_html__( 'After creating this tenant, you will be redirected to clone %s into it.', 'grabwp-tenancy' ),
					'<code>' . esc_html( $grabwp_clone_source_label ) . '</code>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php
	// Check for error parameter with nonce verification
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

	<div>
		<form method="post" class="grabwp-tenancy-form">
			<?php wp_nonce_field( 'grabwp_tenancy_create' ); ?>
			<input type="hidden" name="action" value="create_tenant" />
			<?php if ( $grabwp_clone_source ) : ?>
				<input type="hidden" name="clone_source" value="<?php echo esc_attr( $grabwp_clone_source ); ?>" />
			<?php endif; ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Domain Setup', 'grabwp-tenancy' ); ?></th>
					<td>
						<fieldset>
							<div class="grabwp-mb-md">
								<label>
									<input type="radio" name="domain_option" value="has_domain" checked />
									<?php esc_html_e( 'I have a domain', 'grabwp-tenancy' ); ?>
								</label>
								<div id="grabwp-domain-section">
									<div class="grabwp-domain-inputs">
										<div class="grabwp-domain-input">
											<input type="text" name="domains[]" class="regular-text" placeholder="<?php esc_attr_e( 'Enter domain (e.g. mysite.com)', 'grabwp-tenancy' ); ?>" />
											<button type="button" class="button grabwp-clear-domain grabwp-ml-sm"><?php esc_html_e( 'Clear', 'grabwp-tenancy' ); ?></button>
											<button type="button" class="button grabwp-remove-domain grabwp-ml-sm"><?php esc_html_e( 'Remove', 'grabwp-tenancy' ); ?></button>
										</div>
									</div>
									
									<p class="description"><?php esc_html_e( 'Enter without http:// or www (e.g. mysite.com, blog.mysite.com)', 'grabwp-tenancy' ); ?></p>
								</div>
							</div>
							<div>
								<label>
									<input type="radio" name="domain_option" value="map_later" />
									<?php esc_html_e( "I'll set up a domain later", 'grabwp-tenancy' ); ?>
								</label>
								<div id="grabwp-no-domain-section" class="grabwp-path-url-info hidden">
									<p>
										<strong><?php esc_html_e( 'Your site will be accessible at:', 'grabwp-tenancy' ); ?></strong><br />
										<code><?php echo esc_html( site_url( '/site/{tenant-id}/' ) ); ?></code>
									</p>
									<p class="description"><?php esc_html_e( 'You can add a domain anytime from the tenant edit page.', 'grabwp-tenancy' ); ?></p>
								</div>
							</div>
						</fieldset>
					</td>
				</tr>
				<?php
				/**
				 * Add extra fields to tenant creation form
				 *
				 * @since 1.0.4
				 */
				do_action( 'grabwp_tenancy_create_form_fields' );
				?>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php
					echo $grabwp_clone_source
						? esc_html__( 'Create Tenant & Clone', 'grabwp-tenancy' )
						: esc_html__( 'Create Tenant', 'grabwp-tenancy' );
					?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ); ?>" class="button grabwp-ml-sm">
					<?php esc_html_e( 'Cancel', 'grabwp-tenancy' ); ?>
				</a>
			</p>
		</form>
	</div>
</div>
