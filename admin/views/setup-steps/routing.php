<?php
/**
 * Setup wizard: Routing screen (path or domain; both is set in Settings).
 *
 * Variables available from GrabWP_Tenancy_Setup_Wizard::render_routing_step():
 * @var string   $grabwp_routing_current   Selected mode.
 * @var bool     $grabwp_routing_disabled  Mode set in wp-config.php (read-only).
 * @var bool     $grabwp_routing_has_site  At least one site exists.
 * @var bool     $grabwp_routing_subdomain The wildcard check passed.
 * @var string[] $grabwp_routing_modes     Modes offered.
 * @var string   $grabwp_routing_file      tenants.php status, see GrabWP_Tenancy_Routing::file_status().
 *
 * @package GrabWP_Tenancy
 * @since 1.1.13
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_routing_settings_url = admin_url( 'admin.php?page=grabwp-tenancy-settings#tab-routing' );
$grabwp_routing_path_url     = site_url( '/' . grabwp_tenancy_get_path_prefix() . '/my-shop/' );
$grabwp_routing_code         = '<code dir="ltr">%s</code>';
$grabwp_routing_options      = array(
	'path'   => array(
		'title' => GrabWP_Tenancy_Routing::label( 'path' ),
		/* translators: %s: example path URL of a site. */
		'desc'  => sprintf( __( 'Every site lives under this WordPress address. Works right away, no DNS needed. For example: %s', 'grabwp-tenancy' ), sprintf( $grabwp_routing_code, esc_html( $grabwp_routing_path_url ) ) ),
		'level' => 'simple',
	),
	'domain' => array(
		'title'       => GrabWP_Tenancy_Routing::label( 'domain' ),
		/* translators: %s: example address of a site. */
		'desc'        => sprintf( __( 'Every site has its own subdomain or its own domain. Point that address at this server (DNS) before the site opens. For example: %s', 'grabwp-tenancy' ), sprintf( $grabwp_routing_code, 'https://my-shop.'.parse_url( site_url( '/' ), PHP_URL_HOST ).'/' ) ),
		'level'       => 'advanced',
		'recommended' => true,
	),
	'both'   => array(
		'title' => GrabWP_Tenancy_Routing::label( 'both' ),
		'desc'  => __( 'A site opens on the path address until you give it a domain; then it opens on the domain and the path keeps working. Works now; add domains whenever you like.', 'grabwp-tenancy' ),
		'level' => 'simple',
	),
);
$grabwp_routing_options      = array_intersect_key( $grabwp_routing_options, array_flip( $grabwp_routing_modes ) );
$grabwp_routing_file_message = GrabWP_Tenancy_Routing::file_message( $grabwp_routing_file );
?>
<h2><?php esc_html_e( 'Choose the web address of your sites', 'grabwp-tenancy' ); ?></h2>
<p class="description"><?php esc_html_e( 'This decides how visitors reach each site. Pick one. You can change this later in Tenancy > Settings > Routing, which also lets sites use both a path and a domain.', 'grabwp-tenancy' ); ?></p>

<?php if ( '' !== $grabwp_routing_file_message && ! $grabwp_routing_disabled ) : ?>
	<div class="notice <?php echo 'missing' === $grabwp_routing_file ? 'notice-info' : 'notice-warning'; ?> inline grabwp-setup-routing-file" data-status="<?php echo esc_attr( $grabwp_routing_file ); ?>">
		<p dir="auto"><?php echo esc_html( $grabwp_routing_file_message ); ?></p>
	</div>
<?php endif; ?>

<?php if ( $grabwp_routing_disabled ) : ?>
	<div class="notice notice-info inline grabwp-setup-routing-locked">
		<p>
			<?php
			printf(
				/* translators: %s: constant name. */
				esc_html__( 'The routing mode is set by %s in wp-config.php. Remove the constant to change it here.', 'grabwp-tenancy' ),
				'<code dir="ltr">GRABWP_TENANCY_ROUTING_MODE</code>'
			);
			?>
		</p>
	</div>
<?php elseif ( $grabwp_routing_has_site ) : ?>
	<div class="notice notice-info inline grabwp-setup-routing-has-sites">
		<p>
			<?php esc_html_e( 'Sites already exist. A new mode changes their addresses, so switch it in Settings, which lists every site that changes.', 'grabwp-tenancy' ); ?>
			<a href="<?php echo esc_url( $grabwp_routing_settings_url ); ?>"><?php esc_html_e( 'Open Settings > Routing', 'grabwp-tenancy' ); ?></a>
		</p>
	</div>
<?php endif; ?>

<?php
GrabWP_Tenancy_Setup_Wizard::render_choices( 'routing_mode', $grabwp_routing_options, $grabwp_routing_current, __( 'Routing mode', 'grabwp-tenancy' ), 'grabwp-setup-routing', $grabwp_routing_disabled );
