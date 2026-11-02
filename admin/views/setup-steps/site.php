<?php
/**
 * Setup wizard: First site (Base) / Starter site (WaaS) screen.
 *
 * Variables available from GrabWP_Tenancy_Setup_Wizard::render_site_step():
 * @var array  $grabwp_site_args    heading, description, default_title, default_mode, choices.
 * @var bool   $grabwp_can_loop     Whether the site can reach itself.
 * @var string $grabwp_routing_mode Routing mode: path, domain or both.
 * @var string $grabwp_domain_base  Main domain new site domains are suggested under ('' for none).
 *
 * @package GrabWP_Tenancy
 * @since 1.1.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_has_choices = ! empty( $grabwp_site_args['choices'] );
$grabwp_site_mode   = GrabWP_Tenancy_Setup_Wizard::field_value( 'site_mode', $grabwp_has_choices ? $grabwp_site_args['default_mode'] : 'new' );
$grabwp_site_state  = GrabWP_Tenancy_Setup_Wizard::get_state();
$grabwp_existing    = GrabWP_Tenancy_Setup_Wizard::field_value( 'existing_tenant', $grabwp_site_state['starter_tenant'] );
$grabwp_site_title  = GrabWP_Tenancy_Setup_Wizard::field_value( 'site_title', $grabwp_site_args['default_title'] );
$grabwp_address     = GrabWP_Tenancy_Setup_Wizard::field_value( 'address', 'path' );
$grabwp_domain      = GrabWP_Tenancy_Setup_Wizard::field_value( 'domain', GrabWP_Tenancy_Setup_Wizard::suggest_domain( $grabwp_site_title, $grabwp_domain_base ) );
$grabwp_email       = GrabWP_Tenancy_Setup_Wizard::field_value( 'admin_email', wp_get_current_user()->user_email );
$grabwp_install     = GrabWP_Tenancy_Setup_Wizard::has_posted_values()
	? (bool) GrabWP_Tenancy_Setup_Wizard::field_value( 'install_wp', false )
	: $grabwp_can_loop;
$grabwp_path_url    = site_url( '/' . grabwp_tenancy_get_path_prefix() . '/{id}/' );
?>
<h2><?php echo esc_html( $grabwp_site_args['heading'] ); ?></h2>
<p class="description"><?php echo esc_html( $grabwp_site_args['description'] ); ?></p>

<table class="form-table grabwp-setup-site" role="presentation" data-existing-label="<?php esc_attr_e( 'Save and continue', 'grabwp-tenancy' ); ?>">
	<?php if ( $grabwp_has_choices ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Site', 'grabwp-tenancy' ); ?></th>
			<td>
				<?php
				GrabWP_Tenancy_Setup_Wizard::render_choices(
					'site_mode',
					array(
						'new'      => array(
							'title' => __( 'Create a new site', 'grabwp-tenancy' ),
							'desc'  => __( 'Start from an empty WordPress site.', 'grabwp-tenancy' ),
							'level' => 'simple',
						),
						'existing' => array(
							'title' => __( 'Use an existing site', 'grabwp-tenancy' ),
							'desc'  => __( 'Pick one of your sites from the list below.', 'grabwp-tenancy' ),
							'level' => '',
						),
					),
					$grabwp_site_mode,
					__( 'Site', 'grabwp-tenancy' )
				);
				?>
				<select name="existing_tenant" class="grabwp-setup-existing<?php echo 'existing' === $grabwp_site_mode ? '' : ' hidden'; ?>" aria-label="<?php esc_attr_e( 'Existing site', 'grabwp-tenancy' ); ?>">
					<?php foreach ( $grabwp_site_args['choices'] as $grabwp_tid => $grabwp_label ) : ?>
						<option value="<?php echo esc_attr( $grabwp_tid ); ?>" <?php selected( $grabwp_existing, $grabwp_tid ); ?> dir="ltr"><?php echo esc_html( $grabwp_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	<?php else : ?>
		<input type="hidden" name="site_mode" value="new" />
	<?php endif; ?>

	<tr class="grabwp-setup-new-only">
		<th scope="row"><label for="grabwp-setup-site-title"><?php esc_html_e( 'Site title', 'grabwp-tenancy' ); ?></label></th>
		<td><input type="text" id="grabwp-setup-site-title" name="site_title" class="regular-text" maxlength="100" value="<?php echo esc_attr( $grabwp_site_title ); ?>" /></td>
	</tr>

	<tr class="grabwp-setup-new-only">
		<th scope="row"><?php esc_html_e( 'Address', 'grabwp-tenancy' ); ?></th>
		<td>
			<?php if ( 'path' === $grabwp_routing_mode ) : ?>
				<input type="hidden" name="address" value="path" />
				<code dir="ltr"><?php echo esc_html( $grabwp_path_url ); ?></code>
				<p class="description"><?php esc_html_e( 'Works now. No DNS needed. The ID is created on save.', 'grabwp-tenancy' ); ?></p>
			<?php elseif ( 'domain' === $grabwp_routing_mode ) : ?>
				<input type="hidden" name="address" value="domain" />
				<input type="text" name="domain" class="large-text" dir="ltr" autocapitalize="none" spellcheck="false" placeholder="shop.example.com" value="<?php echo esc_attr( $grabwp_domain ); ?>" data-domain-base="<?php echo esc_attr( $grabwp_domain_base ); ?>" aria-label="<?php esc_attr_e( 'Custom domain', 'grabwp-tenancy' ); ?>" />
				<p class="description"><?php esc_html_e( "Point the domain's DNS to this server first.", 'grabwp-tenancy' ); ?></p>
			<?php else : ?>
				<?php
				GrabWP_Tenancy_Setup_Wizard::render_choices(
					'address',
					array(
						'path'   => array(
							'title' => __( 'Path address', 'grabwp-tenancy' ),
							/* translators: %s: path URL of the new site, {id} stands for its ID. */
							'desc'  => sprintf( __( 'Works now, no DNS needed. The site ID is created when you save: %s', 'grabwp-tenancy' ), '<code dir="ltr">' . esc_html( $grabwp_path_url ) . '</code>' ),
							'level' => 'simple',
						),
						'domain' => array(
							'title' => __( 'Custom domain', 'grabwp-tenancy' ),
							'desc'  => __( 'Type the domain below. Point its DNS to this server first.', 'grabwp-tenancy' ),
							'level' => 'advanced',
						),
					),
					$grabwp_address,
					__( 'Address', 'grabwp-tenancy' )
				);
				?>
				<input type="text" name="domain" class="large-text grabwp-mt-sm" dir="ltr" autocapitalize="none" spellcheck="false" placeholder="shop.example.com" value="<?php echo esc_attr( $grabwp_domain ); ?>" data-domain-base="<?php echo esc_attr( $grabwp_domain_base ); ?>" aria-label="<?php esc_attr_e( 'Custom domain', 'grabwp-tenancy' ); ?>" />
			<?php endif; ?>
		</td>
	</tr>

	<tr class="grabwp-setup-new-only">
		<th scope="row"><label for="grabwp-setup-admin-email"><?php esc_html_e( 'Site admin email', 'grabwp-tenancy' ); ?></label></th>
		<td>
			<input type="email" id="grabwp-setup-admin-email" name="admin_email" class="regular-text" dir="ltr" value="<?php echo esc_attr( $grabwp_email ); ?>" />
			<p class="description"><?php esc_html_e( "The new site's administrator account uses this email.", 'grabwp-tenancy' ); ?></p>
		</td>
	</tr>

	<tr class="grabwp-setup-new-only">
		<th scope="row"><?php esc_html_e( 'WordPress', 'grabwp-tenancy' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="install_wp" value="1" <?php checked( $grabwp_install ); ?> />
				<?php esc_html_e( 'Set up WordPress in the new site now', 'grabwp-tenancy' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'The site is ready to use right away, with an administrator account named after the site ID. You log in with the 1-click button and never need its password.', 'grabwp-tenancy' ); ?></p>
			<?php if ( ! $grabwp_can_loop ) : ?>
				<p class="description grabwp-setup-loopback-warning"><?php esc_html_e( 'Turned off because this server cannot reach itself (see the Check step). You can finish the install in the browser after the site is created.', 'grabwp-tenancy' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>

	<?php
	/**
	 * Extra read-only rows on the First site screen (e.g. Pro database type).
	 *
	 * @since 1.1.12
	 */
	do_action( 'grabwp_tenancy_setup_site_rows' );
	?>
</table>
