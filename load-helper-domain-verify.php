<?php
/**
 * Early HTTP probes: custom-domain verification and the installer loopback marker.
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

/**
 * Tenant ID for the installer loopback marker of this request, or ''.
 *
 * Only an installer loopback (?grabwp-loopback=1) that reached a tenant by
 * its path or query route gets one: that URL already holds the tenant ID.
 * A path for a tenant this server does not know yet falls through to the
 * main site, which gets none.
 *
 * @return string
 */
function grabwp_tenancy_loopback_marker() {
	if ( ! isset( $_GET['grabwp-loopback'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '';
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return '';
	}
	if ( ! defined( 'GRABWP_TENANCY_IS_TENANT' ) || ! GRABWP_TENANCY_IS_TENANT || ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		return '';
	}
	if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) || ! in_array( GRABWP_TENANCY_ROUTING_METHOD, array( 'path', 'query' ), true ) ) {
		return '';
	}
	if ( defined( 'GRABWP_TENANCY_TENANT_ALIAS' ) && '' !== GRABWP_TENANCY_TENANT_ALIAS ) {
		return '';
	}
	$tenant_id = (string) GRABWP_TENANCY_TENANT_ID;
	// The validator strips control characters first; a header needs the raw value clean.
	return ( 1 === preg_match( '/^[a-z0-9]+$/', $tenant_id ) && grabwp_tenancy_validate_tenant_id( $tenant_id ) ) ? $tenant_id : '';
}

/**
 * Send the installer loopback marker (X-GrabWP-Tenant) when it applies.
 *
 * Sent before WordPress loads, so it is also on core's redirect to install.php.
 */
function grabwp_tenancy_maybe_send_loopback_marker() {
	$tenant_id = grabwp_tenancy_loopback_marker();
	if ( '' !== $tenant_id && ! headers_sent() ) {
		header( 'X-GrabWP-Tenant: ' . $tenant_id );
	}
}
