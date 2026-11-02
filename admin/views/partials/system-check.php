<?php
/**
 * Server checks shared by the Status page (General tab) and the setup wizard
 * Check screen: environment cards, Hosting setup, loopback, tenant file
 * safety, System Information and Support diagnostics. The last two start
 * collapsed. Variables from the including view:
 *
 * @var string     $grabwp_check_again   URL that runs the checks again.
 * @var array      $grabwp_status_*      Result of GrabWP_Tenancy_Status_Checker::get_env_card_data(), extracted.
 * @var array|null $grabwp_hosting       Optional. Result of get_hosting_card_data().
 * @var bool|null  $grabwp_check_loopback Optional. Whether the site reaches itself; null when not checked.
 * @var array      $grabwp_check_unsafe_files   Optional. Result of get_unsafe_file_settings().
 * @var array      $grabwp_check_unsafe_tenants Optional. Result of get_unsafe_tenant_file_overrides().
 * @var string     $grabwp_diagnostics_source   Diagnostics source label.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.15
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check again (?recheck=) runs the outbound hosting and loopback probes again.
$grabwp_check_recheck = isset( $_GET['recheck'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$grabwp_check_main    = ! ( defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT );
if ( ! isset( $grabwp_hosting ) && $grabwp_check_main ) {
	$grabwp_hosting = GrabWP_Tenancy_Status_Checker::get_hosting_card_data( $grabwp_check_recheck );
}
if ( ! isset( $grabwp_check_loopback ) ) {
	// Without Check again, only a stored answer: a broken loopback waits until timeout.
	$grabwp_check_loopback = $grabwp_check_recheck ? GrabWP_Tenancy_Setup_Wizard::can_loopback( true ) : GrabWP_Tenancy_Diagnostics::loopback( false );
}
if ( ! isset( $grabwp_check_unsafe_files ) ) {
	$grabwp_check_unsafe_files   = GrabWP_Tenancy_Status_Checker::get_unsafe_file_settings();
	$grabwp_check_unsafe_tenants = GrabWP_Tenancy_Status_Checker::get_unsafe_tenant_file_overrides();
}
$grabwp_hosting_recheck = $grabwp_check_again;
$grabwp_system_info     = GrabWP_Tenancy_Diagnostics::system_info();

include __DIR__ . '/status-env-cards.php';
if ( $grabwp_check_main ) {
	include __DIR__ . '/setup-hosting-card.php';
}
?>
<div class="grabwp-env-cards-grid">
	<div class="grabwp-env-card grabwp-setup-loopback" data-ok="<?php echo $grabwp_check_loopback ? '1' : '0'; ?>">
		<div class="grabwp-env-card-header">
			<strong><?php esc_html_e( 'Site can reach itself (loopback)', 'grabwp-tenancy' ); ?></strong>
			<?php if ( $grabwp_check_loopback ) : ?>
				<span style="color: #46b450; font-size: 13px;"><?php esc_html_e( 'OK', 'grabwp-tenancy' ); ?></span>
			<?php elseif ( null === $grabwp_check_loopback ) : ?>
				<span style="color: #787c82; font-size: 13px;"><?php esc_html_e( 'Not checked', 'grabwp-tenancy' ); ?></span>
			<?php else : ?>
				<span class="grabwp-fix-error" style="color: #dc3232; font-size: 13px;"><?php esc_html_e( 'Failed', 'grabwp-tenancy' ); ?></span>
			<?php endif; ?>
		</div>
		<p class="grabwp-env-note">
			<?php
			if ( $grabwp_check_loopback ) {
				esc_html_e( 'GrabWP can install WordPress in new sites for you.', 'grabwp-tenancy' );
			} elseif ( null === $grabwp_check_loopback ) {
				esc_html_e( 'No recent result. Use Check again to test whether this server can open its own address.', 'grabwp-tenancy' );
			} else {
				esc_html_e( 'This server cannot open its own address, so new sites must be installed in the browser. Check firewall, basic auth, or the server routing rules.', 'grabwp-tenancy' );
			}
			?>
		</p>
		<?php if ( null === $grabwp_check_loopback ) : ?>
			<p><a class="button button-small" href="<?php echo esc_url( $grabwp_check_again ); ?>"><?php esc_html_e( 'Check again', 'grabwp-tenancy' ); ?></a></p>
		<?php endif; ?>
	</div>

	<?php include __DIR__ . '/setup-file-safety-card.php'; ?>
</div>

<details class="grabwp-status-sysinfo">
	<summary>
		<strong><?php esc_html_e( 'System Information', 'grabwp-tenancy' ); ?></strong>
		<span class="grabwp-status-sysinfo-peek">
			<?php
			printf(
				/* translators: 1: WordPress version, 2: PHP version, 3: server software label */
				esc_html__( 'WP %1$s · PHP %2$s · %3$s', 'grabwp-tenancy' ),
				esc_html( get_bloginfo( 'version' ) ),
				esc_html( PHP_VERSION ),
				esc_html( $grabwp_status_server['server_software'] )
			);
			?>
		</span>
	</summary>
	<table class="form-table form-table-compact">
		<?php foreach ( $grabwp_system_info as $grabwp_group ) : ?>
			<tr class="grabwp-sysinfo-group">
				<th scope="colgroup" colspan="2"><?php echo esc_html( $grabwp_group['label'] ); ?></th>
			</tr>
			<?php foreach ( $grabwp_group['rows'] as $grabwp_row ) : ?>
				<?php
				if ( is_bool( $grabwp_row['value'] ) ) {
					$grabwp_value = $grabwp_row['value'] ? __( 'Yes', 'grabwp-tenancy' ) : __( 'No', 'grabwp-tenancy' );
				} elseif ( null === $grabwp_row['value'] ) {
					$grabwp_value = __( 'Not set', 'grabwp-tenancy' );
				} else {
					$grabwp_value = (string) $grabwp_row['value'];
				}
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $grabwp_row['label'] ); ?></th>
					<td>
						<span<?php echo $grabwp_row['ok'] ? '' : ' style="color: #dc3232;"'; ?>><?php echo esc_html( $grabwp_value ); ?></span>
						<?php if ( isset( $grabwp_row['note'] ) ) : ?>
							<br><small><?php echo esc_html( $grabwp_row['note'] ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</table>
</details>

<?php
if ( $grabwp_check_main ) :
	// Built here and copied in the browser: nothing is sent anywhere.
	$grabwp_diagnostics = GrabWP_Tenancy_Diagnostics::build( $grabwp_diagnostics_source, $grabwp_hosting );
	?>
	<div class="grabwp-status-diagnostics">
		<strong><?php esc_html_e( 'Support diagnostics', 'grabwp-tenancy' ); ?></strong>
		<p class="description"><?php esc_html_e( 'Copies a summary of these checks, the System Information and the last lines of the GrabWP and WordPress debug logs to your clipboard. Passwords, keys, salts, emails and file paths are left out. Nothing is sent anywhere: paste it into your support request.', 'grabwp-tenancy' ); ?></p>
		<textarea id="grabwp-diagnostics-textarea" class="grabwp-diagnostics-textarea" readonly aria-hidden="true" tabindex="-1" style="display: none;"><?php echo esc_textarea( (string) wp_json_encode( $grabwp_diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
		<button type="button" class="button" id="grabwp-copy-diagnostics-btn"><?php esc_html_e( 'Copy diagnostics', 'grabwp-tenancy' ); ?></button>
	</div>
	<?php
endif;
