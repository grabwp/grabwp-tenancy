<?php
/**
 * Setup wizard: Check screen.
 *
 * Variables available from GrabWP_Tenancy_Setup_Wizard::render_check_step():
 * @var bool   $grabwp_check_loopback Whether the site can reach itself.
 * @var string $grabwp_check_again    URL that runs the checks again.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

extract( GrabWP_Tenancy_Status_Checker::get_env_card_data() ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

$grabwp_check_issues = 0;
if ( ! $grabwp_status_wp_config['loader_active'] ) {
	++$grabwp_check_issues;
}
if ( ! ( $grabwp_status_mu_plugin['exists'] && $grabwp_status_mu_plugin['valid'] ) ) {
	++$grabwp_check_issues;
}
if ( $grabwp_status_server['is_apache'] && ! ( $grabwp_status_htaccess['has_block'] && $grabwp_status_htaccess['block_positioned'] && $grabwp_status_htaccess['content_valid'] ) ) {
	++$grabwp_check_issues;
}
if ( ! ( $grabwp_status_data_htaccess['exists'] && $grabwp_status_data_htaccess['has_deny'] ) ) {
	++$grabwp_check_issues;
}
if ( ! $grabwp_status_index_exists ) {
	++$grabwp_check_issues;
}
if ( ! $grabwp_check_loopback ) {
	++$grabwp_check_issues;
}
?>
<h2><?php esc_html_e( 'Welcome to GrabWP', 'grabwp-tenancy' ); ?></h2>
<p class="description"><?php esc_html_e( 'A few short steps get your first site running. Every step has a working default, and you can skip any of them.', 'grabwp-tenancy' ); ?></p>

<ul class="grabwp-setup-intro">
	<li>
		<strong><?php esc_html_e( 'One WordPress, many websites', 'grabwp-tenancy' ); ?></strong>
		<?php esc_html_e( 'GrabWP runs many separate websites from this one WordPress install. Each one is called a site (a "tenant" in some GrabWP screens).', 'grabwp-tenancy' ); ?>
	</li>
	<li>
		<strong><?php esc_html_e( 'Each site stands on its own', 'grabwp-tenancy' ); ?></strong>
		<?php esc_html_e( 'A site has its own posts, pages, media, users and admin area. Sites share the plugins and themes installed here, so you update them once.', 'grabwp-tenancy' ); ?>
	</li>
	<li>
		<strong><?php esc_html_e( 'Not WordPress Multisite', 'grabwp-tenancy' ); ?></strong>
		<?php esc_html_e( 'You do not need to know or turn on Multisite. You manage every site from the Tenancy menu of this admin.', 'grabwp-tenancy' ); ?>
	</li>
</ul>

<?php
/**
 * More on the Welcome screen, after the introduction (e.g. how the WaaS service works).
 *
 * @since 1.1.13
 */
do_action( 'grabwp_tenancy_setup_welcome' );
?>

<div class="grabwp-setup-section grabwp-tenancy-form grabwp-status-general grabwp-setup-check">
	<h3><?php esc_html_e( 'Server check', 'grabwp-tenancy' ); ?></h3>
	<?php if ( $grabwp_check_issues > 0 ) : ?>
		<div class="notice notice-warning inline grabwp-setup-check-summary">
			<p>
				<?php
				printf(
					/* translators: %d: number of items that need attention. */
					esc_html( _n( '%d item needs attention.', '%d items need attention.', $grabwp_check_issues, 'grabwp-tenancy' ) ),
					(int) $grabwp_check_issues
				);
				?>
				<button type="button" class="button grabwp-setup-fix-all"><?php esc_html_e( 'Fix automatically', 'grabwp-tenancy' ); ?></button>
				<a class="button-link grabwp-setup-recheck" href="<?php echo esc_url( $grabwp_check_again ); ?>"><?php esc_html_e( 'Check again', 'grabwp-tenancy' ); ?></a>
			</p>
			<?php if ( $grabwp_status_wp_config['loader_pending'] ) : ?>
				<p class="grabwp-setup-loader-pending"><?php esc_html_e( 'The loader is in wp-config.php, but this page loaded before it was read. Check again to confirm.', 'grabwp-tenancy' ); ?></p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="notice notice-success inline grabwp-setup-check-summary">
			<p>
				<?php esc_html_e( 'Everything is ready. Nothing to do here.', 'grabwp-tenancy' ); ?>
				<a class="button-link grabwp-setup-recheck" href="<?php echo esc_url( $grabwp_check_again ); ?>"><?php esc_html_e( 'Check again', 'grabwp-tenancy' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<details class="grabwp-accordion grabwp-setup-check-details"<?php echo $grabwp_check_issues > 0 ? ' open' : ''; ?>>
		<summary class="grabwp-accordion-summary"><?php esc_html_e( 'Show the server checks', 'grabwp-tenancy' ); ?></summary>
		<p class="description">
			<?php
			printf(
				/* translators: %s: example site address such as /site/{id}/ */
				esc_html__( 'GrabWP set these up when you activated it. Site addresses like %s need every item.', 'grabwp-tenancy' ),
				'<code dir="ltr">/' . esc_html( grabwp_tenancy_get_path_prefix() ) . '/{id}/</code>'
			);
			?>
		</p>
		<?php include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/partials/status-env-cards.php'; ?>

		<div class="grabwp-env-cards-grid">
			<div class="grabwp-env-card grabwp-setup-loopback" data-ok="<?php echo $grabwp_check_loopback ? '1' : '0'; ?>">
				<div class="grabwp-env-card-header">
					<strong><?php esc_html_e( 'Site can reach itself (loopback)', 'grabwp-tenancy' ); ?></strong>
					<?php if ( $grabwp_check_loopback ) : ?>
						<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( 'OK', 'grabwp-tenancy' ); ?></span>
					<?php else : ?>
						<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( 'Failed', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				</div>
				<p class="grabwp-env-note">
					<?php
					echo $grabwp_check_loopback
						? esc_html__( 'GrabWP can install WordPress in new sites for you.', 'grabwp-tenancy' )
						: esc_html__( 'This server cannot open its own address, so new sites must be installed in the browser. Check firewall, basic auth, or the server routing rules.', 'grabwp-tenancy' );
					?>
				</p>
			</div>
		</div>

		<?php if ( $grabwp_status_server['is_nginx'] ) : ?>
			<p class="description"><?php esc_html_e( '.htaccess files are not used by Nginx. Configure tenant routing in your server block instead.', 'grabwp-tenancy' ); ?></p>
		<?php endif; ?>
	</details>
</div>
