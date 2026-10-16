<?php
/**
 * Early block for suspended tenants. The main site writes
 * BASE_DIR/suspended/<tenant_id>.php, which returns { reason, status, html }.
 *
 * @package GrabWP_Tenancy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a flag file. Missing, unreadable or corrupt files yield null, so a bad flag never takes a tenant down.
 *
 * @param string $file Flag file path.
 * @return array|null
 */
function grabwp_tenancy_read_suspension_flag( $file ) {
	ob_start();
	try {
		$flag = is_readable( $file ) ? include $file : null;
	} catch ( Throwable $e ) {
		$flag = null;
	}
	ob_end_clean();
	return ( is_array( $flag ) && is_string( $flag['html'] ?? null ) && '' !== $flag['html'] ) ? $flag : null;
}

/**
 * Serve the stored page and exit when the current tenant is flagged.
 * Active tenants pay one is_file() on a missing path; WP-CLI always passes.
 */
function grabwp_tenancy_maybe_serve_suspended_page() {
	if ( 'cli' === PHP_SAPI || ! defined( 'GRABWP_TENANCY_IS_TENANT' ) || ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		return;
	}
	$file = GRABWP_TENANCY_BASE_DIR . '/suspended/' . GRABWP_TENANCY_TENANT_ID . '.php';
	$flag = is_file( $file ) ? grabwp_tenancy_read_suspension_flag( $file ) : null;
	if ( ! $flag ) {
		return;
	}
	if ( ! headers_sent() ) {
		$status = (int) ( $flag['status'] ?? 503 );
		http_response_code( $status >= 400 && $status < 600 ? $status : 503 );
		header( 'Retry-After: 3600' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-GrabWP-Suspended: ' . preg_replace( '/[^a-z_]/', '', (string) ( $flag['reason'] ?? '' ) ) );
	}
	echo $flag['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped on the main site.
	exit;
}
