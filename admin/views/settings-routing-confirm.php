<?php
/**
 * GrabWP Tenancy - Confirm a routing mode switch.
 *
 * Expects $plan from GrabWP_Tenancy_Routing::plan_transition().
 *
 * @package GrabWP_Tenancy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_routing_back = admin_url( 'admin.php?page=grabwp-tenancy-settings#tab-routing' );
?>
<div class="wrap grabwp-tenancy-settings grabwp-routing-confirm">
	<h1><?php esc_html_e( 'Change routing mode', 'grabwp-tenancy' ); ?></h1>
	<p>
		<?php
		printf(
			/* translators: 1: current routing mode, 2: new routing mode. */
			esc_html__( 'From %1$s to %2$s.', 'grabwp-tenancy' ),
			'<strong>' . esc_html( GrabWP_Tenancy_Routing::label( $plan['from'] ) ) . '</strong>',
			'<strong>' . esc_html( GrabWP_Tenancy_Routing::label( $plan['to'] ) ) . '</strong>'
		);
		?>
	</p>

	<?php if ( ! empty( $plan['blocked'] ) ) : ?>
		<div class="notice notice-error inline">
			<p><?php esc_html_e( 'Domain mode needs a domain for every site. These sites have none yet:', 'grabwp-tenancy' ); ?></p>
		</div>
		<table class="widefat striped grabwp-routing-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Site', 'grabwp-tenancy' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'grabwp-tenancy' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $plan['blocked'] as $grabwp_routing_tid ) : ?>
					<tr>
						<td><code dir="ltr"><?php echo esc_html( $grabwp_routing_tid ); ?></code></td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=grabwp-tenancy-edit&tenant_id=' . rawurlencode( $grabwp_routing_tid ) ), 'grabwp_tenancy_edit' ) ); ?>"><?php esc_html_e( 'Add a domain', 'grabwp-tenancy' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="submit">
			<a class="button" href="<?php echo esc_url( $grabwp_routing_back ); ?>"><?php esc_html_e( 'Back to Routing settings', 'grabwp-tenancy' ); ?></a>
		</p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Sites whose address changes', 'grabwp-tenancy' ); ?></h2>
		<?php if ( empty( $plan['changes'] ) ) : ?>
			<p><?php esc_html_e( 'No site address changes.', 'grabwp-tenancy' ); ?></p>
		<?php else : ?>
			<table class="widefat striped grabwp-routing-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Site', 'grabwp-tenancy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Current address', 'grabwp-tenancy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'New address', 'grabwp-tenancy' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plan['changes'] as $grabwp_routing_tid => $grabwp_routing_urls ) : ?>
						<tr>
							<td><code dir="ltr"><?php echo esc_html( $grabwp_routing_tid ); ?></code></td>
							<td><code dir="ltr"><?php echo esc_html( $grabwp_routing_urls[0] ); ?></code></td>
							<td><code dir="ltr"><?php echo esc_html( $grabwp_routing_urls[1] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'What else happens', 'grabwp-tenancy' ); ?></h2>
		<ul class="ul-disc">
			<?php if ( 'domain' === $plan['to'] ) : ?>
				<li><?php esc_html_e( 'Visitors who open a path URL of a site are redirected (301) to its domain. Form posts and server calls to path URLs keep working.', 'grabwp-tenancy' ); ?></li>
			<?php endif; ?>
			<?php if ( 'domain' === $plan['from'] ) : ?>
				<li><?php esc_html_e( 'Path URLs stop redirecting and open the site again. Browsers and CDNs may keep a cached redirect until you purge them.', 'grabwp-tenancy' ); ?></li>
			<?php endif; ?>
			<li><?php esc_html_e( 'Links stored inside site content are not changed. Old addresses keep working.', 'grabwp-tenancy' ); ?></li>
			<?php if ( ! empty( $plan['changes'] ) ) : ?>
				<li><?php esc_html_e( 'People logged in to a site whose address changes are signed out once.', 'grabwp-tenancy' ); ?></li>
			<?php endif; ?>
			<li><?php esc_html_e( 'Purge page and CDN caches after the switch.', 'grabwp-tenancy' ); ?></li>
		</ul>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=grabwp-tenancy-settings' ) ); ?>">
			<?php wp_nonce_field( 'grabwp_tenancy_switch_routing' ); ?>
			<input type="hidden" name="action" value="switch_routing_mode" />
			<input type="hidden" name="routing_mode" value="<?php echo esc_attr( $plan['to'] ); ?>" />
			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php
					printf(
						/* translators: %s: new routing mode. */
						esc_html__( 'Switch to %s', 'grabwp-tenancy' ),
						esc_html( GrabWP_Tenancy_Routing::label( $plan['to'] ) )
					);
					?>
				</button>
				<a class="button grabwp-ml-sm" href="<?php echo esc_url( $grabwp_routing_back ); ?>"><?php esc_html_e( 'Cancel', 'grabwp-tenancy' ); ?></a>
			</p>
		</form>
	<?php endif; ?>
</div>
