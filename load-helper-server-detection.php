<?php
/**
 * GrabWP Tenancy - Server & Environment Detection
 *
 * Sanitizes HTTP_HOST, detects protocol, and provides server info.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restrict host to letters, digits, dots, and ASCII hyphens.
 *
 * @param string $host Host string.
 * @return string Sanitized host, or empty string.
 */
function grabwp_tenancy_sanitize_local_network_host( $host ) {
	if ( empty( $host ) || ! is_string( $host ) ) {
		return '';
	}

	$host = grabwp_tenancy_strip_control_chars( $host );
	$host = preg_replace( '/[^a-zA-Z0-9.\-]/', '', $host );
	$host = strtolower( trim( $host, ".-\t\n\r\0\x0B" ) );

	if ( '' === $host || strlen( $host ) > 253 ) {
		return '';
	}

	if ( ! preg_match( '/[a-z0-9]/', $host ) ) {
		return '';
	}

	return $host;
}

/**
 * Get sanitized and validated server information (cached).
 *
 * @return array ['host' => string, 'protocol' => string]
 */
function grabwp_tenancy_get_server_info() {
	if ( function_exists( 'grabwp_tenancy_pro_get_server_info' ) ) {
		return grabwp_tenancy_pro_get_server_info();
	}

	static $server_info = null;
	if ( null !== $server_info ) {
		return $server_info;
	}

	$server_info = array(
		'host'     => '',
		'protocol' => 'http',
	);

	if ( isset( $_SERVER['HTTP_HOST'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$raw_host = $_SERVER['HTTP_HOST'];
		$host     = grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $raw_host ) );

		if ( grabwp_tenancy_validate_domain( $host ) ) {
			$server_info['host'] = $host;
		} else {
			$fallback_host       = grabwp_tenancy_sanitize_local_network_host( $host );
			$server_info['host'] = '' !== $fallback_host ? $fallback_host : 'localhost';
		}
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$https_value = isset( $_SERVER['HTTPS'] ) ? grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $_SERVER['HTTPS'] ) ) : '';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$forwarded_proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) : '';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$forwarded_ssl = isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ? grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) : '';

	if (
		( ! empty( $https_value ) && in_array( strtolower( $https_value ), array( 'on', '1', 'true' ), true ) ) ||
		( strtolower( $forwarded_proto ) === 'https' ) ||
		( strtolower( $forwarded_ssl ) === 'on' )
	) {
		$server_info['protocol'] = 'https';
	}

	return $server_info;
}
