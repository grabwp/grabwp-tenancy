<?php
/**
 * Status Page - General Tab
 *
 * The server checks the setup wizard Check screen also shows
 * (partials/system-check.php).
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 *
 * Variables available from parent scope:
 * @var array $grabwp_status_* Result of GrabWP_Tenancy_Status_Checker::get_env_card_data(), extracted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A unique arg, so no page cache answers for the new check.
$grabwp_check_again        = add_query_arg(
	array(
		'page'    => 'grabwp-tenancy-status',
		'tab'     => 'general',
		'recheck' => time(),
	),
	admin_url( 'admin.php' )
);
$grabwp_diagnostics_source = 'status-page';
?>

<div class="grabwp-tenancy-form grabwp-status-general">
	<h3><?php esc_html_e( 'Environment Checks', 'grabwp-tenancy' ); ?></h3>
	<?php include __DIR__ . '/partials/system-check.php'; ?>
</div>
