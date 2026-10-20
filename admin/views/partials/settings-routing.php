<?php
/**
 * GrabWP Tenancy - Routing settings tab body.
 *
 * @package GrabWP_Tenancy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_routing_current  = GrabWP_Tenancy_Routing::get_mode();
$grabwp_routing_disabled = GrabWP_Tenancy_Routing::is_locked();
?>
<?php if ( $grabwp_routing_disabled ) : ?>
	<div class="notice notice-info inline">
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
<?php elseif ( GrabWP_Tenancy_Routing::lock_is_invalid() ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<?php
			printf(
				/* translators: %s: constant name. */
				esc_html__( '%s in wp-config.php is not a valid mode (path, domain or both), so it is ignored.', 'grabwp-tenancy' ),
				'<code dir="ltr">GRABWP_TENANCY_ROUTING_MODE</code>'
			);
			?>
		</p>
	</div>
<?php endif; ?>

<table class="form-table">
	<tr>
		<th scope="row"><?php esc_html_e( 'Routing mode', 'grabwp-tenancy' ); ?></th>
		<td>
			<?php if ( ! $grabwp_routing_disabled && '' === GrabWP_Tenancy_Routing::get_stored_mode() ) : ?>
				<p class="description grabwp-mb-sm"><?php esc_html_e( 'Not saved yet. Your sites use Path and domain, the behaviour from before routing modes.', 'grabwp-tenancy' ); ?></p>
			<?php endif; ?>
			<?php require __DIR__ . '/routing-mode-options.php'; ?>
		</td>
	</tr>
	<tbody class="grabwp-routing-path-rows"<?php echo 'domain' === $grabwp_routing_current ? ' hidden' : ''; ?>>
		<?php if ( has_action( 'grabwp_tenancy_settings_routing_rows' ) ) : ?>
			<?php
			/**
			 * Render extra Routing rows (path prefix, aliases) as <tr> elements.
			 *
			 * @param string $mode Current routing mode.
			 */
			do_action( 'grabwp_tenancy_settings_routing_rows', $grabwp_routing_current );
			?>
		<?php else : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Path prefix', 'grabwp-tenancy' ); ?></th>
				<td>
					<code dir="ltr">/<?php echo esc_html( grabwp_tenancy_get_path_prefix() ); ?>/</code>
					<p class="description"><?php esc_html_e( 'GrabWP Tenancy Pro can change the prefix and give each site a readable alias.', 'grabwp-tenancy' ); ?></p>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
