<?php
/**
 * GrabWP Tenancy - Security & Validation Functions
 *
 * Sanitization and validation utilities for early tenant initialization.
 * Loaded before WordPress — no WP functions available.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strip null bytes and control characters from a string.
 *
 * @param string $value String to clean.
 * @return string
 */
function grabwp_tenancy_strip_control_chars( $value ) {
	$value = str_replace( "\0", '', $value );
	$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value );
	return $value;
}

/**
 * Remove slashes from a string or array of strings.
 *
 * @param string|array $value Value to unslash.
 * @return string|array
 */
function grabwp_tenancy_wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'grabwp_tenancy_wp_unslash', $value );
	}
	if ( is_string( $value ) ) {
		return stripslashes( $value );
	}
	return $value;
}

/**
 * Strip all HTML tags from a string.
 *
 * @param string $string          String to strip.
 * @param string $allowable_tags  Optional allowed tags.
 * @return string
 */
function grabwp_tenancy_wp_strip_all_tags( $string, $allowable_tags = '' ) {
	if ( is_object( $string ) || is_array( $string ) ) {
		return '';
	}
	$string = (string) $string;
	$string = grabwp_tenancy_strip_control_chars( $string );
	$string = strip_tags( $string, $allowable_tags );
	return $string;
}

/**
 * Sanitize a string for safe use in text fields.
 *
 * @param string $str String to sanitize.
 * @return string
 */
function grabwp_tenancy_sanitize_text_field( $str ) {
	if ( is_object( $str ) || is_array( $str ) ) {
		return '';
	}
	$str = (string) $str;
	$str = grabwp_tenancy_wp_strip_all_tags( $str );
	$str = trim( $str );
	return $str;
}

/**
 * Sanitize a URL for safe use.
 *
 * @param string $url URL to sanitize.
 * @return string
 */
function grabwp_tenancy_sanitize_url( $url ) {
	if ( is_object( $url ) || is_array( $url ) ) {
		return '';
	}
	$url = (string) $url;
	$url = grabwp_tenancy_strip_control_chars( $url );
	if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return $url;
	}
	return '';
}

/**
 * Validate tenant ID format.
 *
 * @param string $tenant_id Tenant identifier.
 * @return bool
 */
function grabwp_tenancy_validate_tenant_id( $tenant_id ) {
	if ( function_exists( 'grabwp_tenancy_pro_validate_tenant_id' ) ) {
		return grabwp_tenancy_pro_validate_tenant_id( $tenant_id );
	}

	if ( empty( $tenant_id ) || ! is_string( $tenant_id ) ) {
		return false;
	}

	$tenant_id = grabwp_tenancy_strip_control_chars( $tenant_id );
	$tenant_id = trim( $tenant_id );

	if ( ! preg_match( '/^[a-z0-9]{6}$/', $tenant_id ) ) {
		return false;
	}

	$reserved_ids = array(
		'admin1', 'admin2', 'admin3', 'admin4', 'admin5',
		'root01', 'root02', 'root03', 'root04', 'root05',
		'test01', 'test02', 'test03', 'test04', 'test05',
		'guest1', 'guest2', 'guest3', 'guest4', 'guest5',
		'user01', 'user02', 'user03', 'user04', 'user05',
		'public', 'privat', 'system', 'config', 'backup', 'upload', 'assets',
		'000000', '111111', '222222', '333333', '444444',
		'555555', '666666', '777777', '888888', '999999',
		'aaaaaa', 'bbbbbb', 'cccccc', 'dddddd', 'eeeeee',
		'ffffff', 'gggggg', 'hhhhhh', 'iiiiii', 'jjjjjj',
		'123456', '654321', 'abc123', '123abc', 'qwerty',
	);

	if ( in_array( $tenant_id, $reserved_ids, true ) ) {
		return false;
	}

	return true;
}

/**
 * Validate domain name format.
 *
 * @param string $domain Domain to validate.
 * @return bool
 */
function grabwp_tenancy_validate_domain( $domain ) {
	if ( function_exists( 'grabwp_tenancy_pro_validate_domain' ) ) {
		return grabwp_tenancy_pro_validate_domain( $domain );
	}
	if ( empty( $domain ) || ! is_string( $domain ) ) {
		return false;
	}

	$domain = grabwp_tenancy_strip_control_chars( $domain );
	$domain = strtolower( trim( $domain ) );

	if ( strlen( $domain ) > 253 || strlen( $domain ) < 4 ) {
		return false;
	}

	if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$/', $domain ) ) {
		return false;
	}

	$parts = explode( '.', $domain );
	if ( count( $parts ) < 2 ) {
		return false;
	}

	$tld = end( $parts );
	if ( strlen( $tld ) < 2 || strlen( $tld ) > 63 ) {
		return false;
	}

	$invalid_patterns = array(
		'/^[0-9]+$/',
		'/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/',
		'/^localhost$/',
		'/\.localhost$/',
		'/^127\.0\.0\.1$/',
		'/^192\.168\./',
		'/^10\./',
		'/^172\.(1[6-9]|2[0-9]|3[0-1])\./',
	);

	foreach ( $invalid_patterns as $pattern ) {
		if ( preg_match( $pattern, $domain ) ) {
			return false;
		}
	}

	return true;
}
