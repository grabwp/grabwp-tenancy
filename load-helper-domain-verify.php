<?php
/**
 * Early HTTP probe for custom-domain verification.
 *
 * Responds to ?grabwp-verify-domain=1 before WordPress boots so the
 * super-admin / dashboard can confirm Host routing without loading plugins.
 *
 * @package GrabWP_Tenancy
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exit with a tenant token when this request is a domain-verify probe.
 */
function grabwp_tenancy_maybe_handle_domain_verify_probe() {
	if ( empty( $_GET['grabwp-verify-domain'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	$is_tenant = defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT;
	$tenant_id = ( $is_tenant && defined( 'GRABWP_TENANCY_TENANT_ID' ) ) ? GRABWP_TENANCY_TENANT_ID : '';

	if ( ! headers_sent() ) {
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		if ( $tenant_id ) {
			header( 'X-GrabWP-Verify: ' . $tenant_id );
		} else {
			header( 'HTTP/1.1 404 Not Found' );
		}
	}

	echo $tenant_id ? ( 'grabwp-verify:' . $tenant_id ) : 'grabwp-verify:none';
	exit;
}
