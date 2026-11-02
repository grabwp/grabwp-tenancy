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

$grabwp_check_env = GrabWP_Tenancy_Status_Checker::get_env_card_data();
extract( $grabwp_check_env ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

$grabwp_check_unsafe_files   = GrabWP_Tenancy_Status_Checker::get_unsafe_file_settings();
$grabwp_check_unsafe_tenants = GrabWP_Tenancy_Status_Checker::get_unsafe_tenant_file_overrides();
$grabwp_check_proxy_https    = GrabWP_Tenancy_Status_Checker::is_proxy_https_undetected();
// Check again (?recheck=) runs the outbound hosting probes again.
$grabwp_hosting              = GrabWP_Tenancy_Status_Checker::get_hosting_card_data( isset( $_GET['recheck'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$grabwp_check_counts         = GrabWP_Tenancy_Status_Checker::count_setup_check_issues( $grabwp_check_env, $grabwp_check_loopback, $grabwp_check_unsafe_files, $grabwp_check_unsafe_tenants, $grabwp_check_proxy_https, $grabwp_hosting );
$grabwp_check_issues         = $grabwp_check_counts['issues'];
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
				<?php if ( $grabwp_check_counts['fixable'] > 0 ) : ?>
					<button type="button" class="button grabwp-setup-fix-all"><?php esc_html_e( 'Fix automatically', 'grabwp-tenancy' ); ?></button>
				<?php endif; ?>
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
		<?php
		$grabwp_diagnostics_source = 'setup-check';
		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/partials/system-check.php';
		?>
	</details>
</div>
