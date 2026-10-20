<?php
/**
 * GrabWP Tenancy - Routing mode radios.
 *
 * Expects:
 * - $grabwp_routing_current  string Selected mode.
 * - $grabwp_routing_disabled bool   Render the radios disabled (mode set in wp-config.php).
 *
 * @package GrabWP_Tenancy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_routing_example = site_url( '/' . grabwp_tenancy_get_path_prefix() . '/my-shop/' );
$grabwp_routing_options = array(
	'path'   => sprintf(
		/* translators: %s: example path URL of a site. */
		__( 'Every site opens at %s. Works right away, no DNS needed. Domains you add are kept but not used for links.', 'grabwp-tenancy' ),
		'<code dir="ltr">' . esc_html( $grabwp_routing_example ) . '</code>'
	),
	'domain' => __( 'Every site opens on its own domain. Visitors who open a path URL of a site are redirected (301) to its domain. Every site needs a domain.', 'grabwp-tenancy' ),
	'both'   => __( 'Sites with a domain open on it; sites without one open on the path URL. Both addresses keep working. This is how GrabWP worked before routing modes.', 'grabwp-tenancy' ),
);
?>
<fieldset class="grabwp-routing-modes">
	<legend class="screen-reader-text"><?php esc_html_e( 'Routing mode', 'grabwp-tenancy' ); ?></legend>
	<?php foreach ( $grabwp_routing_options as $grabwp_routing_value => $grabwp_routing_text ) : ?>
		<p class="grabwp-routing-mode">
			<label>
				<input type="radio" name="routing_mode" value="<?php echo esc_attr( $grabwp_routing_value ); ?>" <?php checked( $grabwp_routing_current, $grabwp_routing_value ); ?> <?php disabled( $grabwp_routing_disabled ); ?> />
				<strong><?php echo esc_html( GrabWP_Tenancy_Routing::label( $grabwp_routing_value ) ); ?></strong>
			</label>
			<span class="description"><?php echo wp_kses( $grabwp_routing_text, array( 'code' => array( 'dir' => true ) ) ); ?></span>
		</p>
	<?php endforeach; ?>
</fieldset>
