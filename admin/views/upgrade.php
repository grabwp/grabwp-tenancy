<?php
/**
 * GrabWP Tenancy - Upgrade to Pro Admin Page Template
 *
 * Comparison table showing free vs Pro features with a CTA button.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Upgrade to GrabWP Tenancy Pro', 'grabwp-tenancy' ); ?></h1>
	<p>
		<?php esc_html_e( 'GrabWP Tenancy Pro gives you the tools to manage client sites at scale: scheduled backups, dedicated databases, cloud storage offload, and more.', 'grabwp-tenancy' ); ?>
	</p>

	<table class="widefat striped grabwp-mt-md">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Feature', 'grabwp-tenancy' ); ?></th>
				<th class="grabwp-text-center"><?php esc_html_e( 'Free', 'grabwp-tenancy' ); ?></th>
				<th class="grabwp-text-center"><?php esc_html_e( 'Pro', 'grabwp-tenancy' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Multiple tenants via shared MySQL', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Dedicated database per tenant (MySQL / SQLite)', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Full tenant backup and restore', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Scheduled automatic backups (daily, weekly, etc.)', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Cloud offload to S3-compatible storage', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'WP-CLI backup commands', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Cross-database migration (shared, dedicated, SQLite)', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Path alias routing (/site/my-shop/)', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Extension management (symlink/copy/repair)', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Storage driver selection (Local / S3)', 'grabwp-tenancy' ); ?></td>
				<td class="grabwp-text-center grabwp-text-muted">&mdash;</td>
				<td class="grabwp-text-center grabwp-text-success">&#10003;</td>
			</tr>
		</tbody>
	</table>

	<p class="grabwp-mt-md">
		<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero grabwp-cta-gradient" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Get GrabWP Tenancy Pro', 'grabwp-tenancy' ); ?>
		</a>
	</p>
</div>
