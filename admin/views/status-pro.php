<?php
/**
 * Status Page - Pro Plugin Tab
 *
 * Pro plugin information, content isolation, and database config.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 *
 * Variables available from parent scope:
 * @var bool   $grabwp_status_is_pro            Whether Pro plugin is active
 * @var string $grabwp_status_pro_version       Pro plugin version
 * @var array  $grabwp_status_pro_default_config Pro default config
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php if ( ! $grabwp_status_is_pro ) : ?>

<div class="grabwp-tenancy-form" style="text-align: center; padding: 40px 20px;">
	<h3><?php esc_html_e( 'GrabWP Tenancy Pro', 'grabwp-tenancy' ); ?></h3>
	<p style="font-size: 14px; color: #666; max-width: 500px; margin: 10px auto;">
		<?php esc_html_e( 'Upgrade to GrabWP Tenancy Pro for advanced features including complete content isolation, separate databases per tenant, and enhanced management capabilities.', 'grabwp-tenancy' ); ?>
	</p>
	<p>
		<a href="https://grabwp.com/pro" target="_blank" class="button button-primary button-hero">
			<?php esc_html_e( 'Upgrade to Pro', 'grabwp-tenancy' ); ?>
		</a>
	</p>
</div>

<?php else : ?>

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'Pro Plugin Information', 'grabwp-tenancy' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Pro Version', 'grabwp-tenancy' ); ?></th>
			<td><?php echo esc_html( $grabwp_status_pro_version ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'grabwp-tenancy' ); ?></th>
			<td><span style="color: #46b450;"><?php esc_html_e( '✓ Active', 'grabwp-tenancy' ); ?></span></td>
		</tr>
	</table>
</div>

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'Content Isolation (Default Config)', 'grabwp-tenancy' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Default content isolation settings applied when creating new tenants.', 'grabwp-tenancy' ); ?></p>
	<table class="form-table">
		<?php
		$grabwp_isolation_defaults = isset( $grabwp_status_pro_default_config['content_isolation'] ) ? $grabwp_status_pro_default_config['content_isolation'] : array();
		$grabwp_isolation_labels   = array(
			'isolate_content' => __( 'Content Isolation', 'grabwp-tenancy' ),
			'isolate_themes'  => __( 'Theme Isolation', 'grabwp-tenancy' ),
			'isolate_plugins' => __( 'Plugin Isolation', 'grabwp-tenancy' ),
			'isolate_uploads' => __( 'Upload Isolation', 'grabwp-tenancy' ),
		);
		foreach ( $grabwp_isolation_labels as $grabwp_iso_key => $grabwp_iso_label ) :
			$grabwp_iso_value = isset( $grabwp_isolation_defaults[ $grabwp_iso_key ] ) ? $grabwp_isolation_defaults[ $grabwp_iso_key ] : false;
			?>
		<tr>
			<th scope="row"><?php echo esc_html( $grabwp_iso_label ); ?></th>
			<td>
				<?php if ( $grabwp_iso_value ) : ?>
					<span style="color: #46b450;"><?php esc_html_e( '✓ Isolated', 'grabwp-tenancy' ); ?></span>
				<?php else : ?>
					<span style="color: #999;"><?php esc_html_e( '— Shared', 'grabwp-tenancy' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
</div>

<div class="grabwp-tenancy-form">
	<h3><?php esc_html_e( 'Database (Default Config)', 'grabwp-tenancy' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Default database configuration for new tenants.', 'grabwp-tenancy' ); ?></p>
	<table class="form-table">
		<?php
		$grabwp_db_defaults = isset( $grabwp_status_pro_default_config['database'] ) ? $grabwp_status_pro_default_config['database'] : array();
		$grabwp_db_type     = isset( $grabwp_db_defaults['database_type'] ) ? $grabwp_db_defaults['database_type'] : 'shared';
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Database Type', 'grabwp-tenancy' ); ?></th>
			<td>
				<?php if ( 'mysql_isolated' === $grabwp_db_type ) : ?>
					<code><?php esc_html_e( 'Isolated MySQL Database', 'grabwp-tenancy' ); ?></code>
				<?php elseif ( 'sqlite_isolated' === $grabwp_db_type ) : ?>
					<code><?php esc_html_e( 'Isolated SQLite Database', 'grabwp-tenancy' ); ?></code>
				<?php else : ?>
					<code><?php esc_html_e( 'Shared Database (with table prefixes)', 'grabwp-tenancy' ); ?></code>
				<?php endif; ?>
			</td>
		</tr>

		<?php if ( 'mysql_isolated' === $grabwp_db_type ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'MySQL Host', 'grabwp-tenancy' ); ?></th>
			<td>
				<?php
				$grabwp_mysql_host = isset( $grabwp_db_defaults['tenant_mysql_host'] ) ? $grabwp_db_defaults['tenant_mysql_host'] : '';
				echo $grabwp_mysql_host ? '<code>' . esc_html( $grabwp_mysql_host ) . '</code>' : '<span style="color: #999;">—</span>';
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'MySQL Database', 'grabwp-tenancy' ); ?></th>
			<td>
				<?php
				$grabwp_mysql_db = isset( $grabwp_db_defaults['tenant_mysql_database'] ) ? $grabwp_db_defaults['tenant_mysql_database'] : '';
				echo $grabwp_mysql_db ? '<code>' . esc_html( $grabwp_mysql_db ) . '</code>' : '<span style="color: #999;">—</span>';
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'MySQL Username', 'grabwp-tenancy' ); ?></th>
			<td>
				<?php
				$grabwp_mysql_user = isset( $grabwp_db_defaults['tenant_mysql_username'] ) ? $grabwp_db_defaults['tenant_mysql_username'] : '';
				echo $grabwp_mysql_user ? '<code>' . esc_html( $grabwp_mysql_user ) . '</code>' : '<span style="color: #999;">—</span>';
				?>
			</td>
		</tr>
		<?php endif; ?>
	</table>
</div>

<?php endif; ?>
