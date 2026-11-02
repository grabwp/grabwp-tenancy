<?php
/**
 * Setup wizard: Done screen.
 *
 * Variables available from GrabWP_Tenancy_Setup_Wizard::render_done_step():
 * @var GrabWP_Tenancy_Tenant|null $grabwp_done_tenant Starter / first site.
 * @var array                      $grabwp_done_items  Each: ok, label, step.
 * @var array                      $grabwp_done_links  WaaS links: url, label, target, optional desc.
 * @var array                      $grabwp_done_next   "Grow later" links: url, label, desc.
 * @var array                      $grabwp_done_result   One-time result of the site just created (may be empty).
 * @var string                     $grabwp_done_password Admin password of the site (stored, or one-time without sodium), '' when none.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( "You're ready", 'grabwp-tenancy' ); ?></h2>
<p class="description"><?php esc_html_e( 'Your setup is saved. Open your site, then grow it whenever you like.', 'grabwp-tenancy' ); ?></p>

<?php if ( $grabwp_done_tenant ) : ?>
	<?php
	$grabwp_done_tid     = $grabwp_done_tenant->get_id();
	$grabwp_done_url     = $grabwp_done_tenant->get_site_url();
	$grabwp_done_new     = isset( $grabwp_done_result['tenant_id'] ) && $grabwp_done_result['tenant_id'] === $grabwp_done_tid;
	$grabwp_done_error   = $grabwp_done_new && ! empty( $grabwp_done_result['install_error'] ) ? (string) $grabwp_done_result['install_error'] : '';
	$grabwp_done_forget  = wp_nonce_url( add_query_arg( 'action', 'grabwp_tenancy_setup_forget_password', admin_url( 'admin-post.php' ) ), 'grabwp_tenancy_setup_forget_password' );

	// An error that comes with a password: the install was posted but not confirmed, and may have worked.
	$grabwp_done_unconfirmed = '' !== $grabwp_done_error && ! empty( $grabwp_done_result['password'] );
	?>
	<div class="notice notice-<?php echo esc_attr( '' !== $grabwp_done_error ? 'warning' : 'success' ); ?> inline grabwp-site-result grabwp-setup-done-site">
		<p>
			<strong>
				<?php
				printf(
					/* translators: %s: tenant ID. */
					( $grabwp_done_new || '' !== $grabwp_done_password ) ? esc_html__( 'Site created: %s', 'grabwp-tenancy' ) : esc_html__( 'Your site: %s', 'grabwp-tenancy' ),
					esc_html( $grabwp_done_tid )
				);
				?>
			</strong>
			<a href="<?php echo esc_url( $grabwp_done_url ); ?>" target="_blank" rel="noopener noreferrer" dir="ltr"><?php echo esc_html( $grabwp_done_url ); ?></a>
		</p>
		<?php if ( '' !== $grabwp_done_error ) : ?>
			<p>
				<?php
				if ( $grabwp_done_unconfirmed ) {
					echo esc_html( $grabwp_done_error );
				} else {
					printf(
						/* translators: %s: reason the install failed. */
						esc_html__( 'The site was created but WordPress could not be installed automatically: %s', 'grabwp-tenancy' ),
						esc_html( $grabwp_done_error )
					);
				}
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( GrabWP_Tenancy_Tenant_Installer::loopback_url( $grabwp_done_tid, 'wp-admin/install.php' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Finish install in browser', 'grabwp-tenancy' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $grabwp_done_password ) : ?>
			<p class="grabwp-site-result-credentials">
				<?php esc_html_e( 'Username', 'grabwp-tenancy' ); ?>
				<code dir="ltr"><?php echo esc_html( $grabwp_done_tid ); ?></code>
				<label for="grabwp-site-result-password"><?php esc_html_e( 'Password', 'grabwp-tenancy' ); ?></label>
				<input type="password" id="grabwp-site-result-password" class="regular-text code grabwp-site-result-password" value="<?php echo esc_attr( $grabwp_done_password ); ?>" readonly dir="ltr" autocomplete="off" />
				<button type="button" class="button grabwp-reveal-password" data-target="grabwp-site-result-password" data-show-label="<?php esc_attr_e( 'Show', 'grabwp-tenancy' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide', 'grabwp-tenancy' ); ?>"><?php esc_html_e( 'Show', 'grabwp-tenancy' ); ?></button>
				<button type="button" class="button grabwp-copy-path-url" data-copy-value="<?php echo esc_attr( $grabwp_done_password ); ?>"><?php esc_html_e( 'Copy', 'grabwp-tenancy' ); ?></button>
				<a class="button grabwp-setup-forget-password" href="<?php echo esc_url( $grabwp_done_forget ); ?>"><?php esc_html_e( 'I saved it, hide this', 'grabwp-tenancy' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'Keep this password somewhere safe. It stays on this screen until you hide it. You do not need it to log in: the 1-click Admin button does that.', 'grabwp-tenancy' ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<h3 class="grabwp-mt-md"><?php esc_html_e( 'What you set up', 'grabwp-tenancy' ); ?></h3>
<ul class="grabwp-setup-done-list">
	<?php foreach ( $grabwp_done_items as $grabwp_item ) : ?>
		<li class="<?php echo $grabwp_item['ok'] ? 'is-ok' : 'is-skipped'; ?>">
			<span class="dashicons <?php echo $grabwp_item['ok'] ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>" aria-hidden="true"></span>
			<?php echo esc_html( $grabwp_item['label'] ); ?>
			<?php if ( ! empty( $grabwp_item['step'] ) ) : ?>
				<a href="<?php echo esc_url( GrabWP_Tenancy_Setup_Wizard::get_step_url( $grabwp_item['step'] ) ); ?>"><?php esc_html_e( 'Open this step', 'grabwp-tenancy' ); ?></a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>

<div class="grabwp-setup-next">
	<?php
	$grabwp_done_site_links = array();
	if ( $grabwp_done_tenant ) {
		$grabwp_done_site_links[] = array(
			'url'    => $grabwp_done_tenant->get_admin_access_url(),
			'label'  => __( 'Open site admin (1-click)', 'grabwp-tenancy' ),
			'desc'   => __( 'Log in to this site without the password.', 'grabwp-tenancy' ),
			'target' => '_blank',
			'class'  => 'grabwp-open-site-admin',
		);
		$grabwp_done_site_links[] = array(
			'url'    => $grabwp_done_tenant->get_site_url(),
			'label'  => __( 'Visit site', 'grabwp-tenancy' ),
			'desc'   => __( 'Open the site as a visitor.', 'grabwp-tenancy' ),
			'target' => '_blank',
		);
	}
	$grabwp_done_site_links[] = array(
		'url'   => admin_url( 'admin.php?page=grabwp-tenancy' ),
		'label' => __( 'All sites', 'grabwp-tenancy' ),
		'desc'  => __( 'See and manage every site.', 'grabwp-tenancy' ),
	);
	?>
	<div class="grabwp-setup-next-group grabwp-setup-try grabwp-setup-done-actions">
		<h3><?php esc_html_e( 'Manage sites', 'grabwp-tenancy' ); ?></h3>
		<?php GrabWP_Tenancy_Setup_Wizard::render_link_list( $grabwp_done_site_links ); ?>
	</div>
	<?php if ( $grabwp_done_links ) : ?>
		<div class="grabwp-setup-next-group grabwp-setup-done-waas">
			<h3><?php esc_html_e( 'Manage WaaS', 'grabwp-tenancy' ); ?></h3>
			<?php GrabWP_Tenancy_Setup_Wizard::render_link_list( $grabwp_done_links ); ?>
		</div>
	<?php endif; ?>
	<?php if ( $grabwp_done_next ) : ?>
		<div class="grabwp-setup-next-group grabwp-setup-done-next">
			<h3><?php esc_html_e( 'Grow later', 'grabwp-tenancy' ); ?></h3>
			<?php GrabWP_Tenancy_Setup_Wizard::render_link_list( $grabwp_done_next ); ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * More on the Done screen, after "What next" (e.g. how the WaaS service works).
 *
 * @since 1.1.13
 */
do_action( 'grabwp_tenancy_setup_done_after' );
