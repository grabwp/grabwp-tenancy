<?php
/**
 * GrabWP Tenancy - Tenant Detection & Mapping
 *
 * Domain, path, query, and CLI tenant identification.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load tenant domain mappings from file (cached).
 *
 * @return array
 */
function grabwp_tenancy_load_tenant_mappings() {
	if ( function_exists( 'grabwp_tenancy_pro_load_tenant_mappings' ) ) {
		return grabwp_tenancy_pro_load_tenant_mappings();
	}

	static $tenant_mappings = null;
	if ( null !== $tenant_mappings ) {
		return $tenant_mappings;
	}

	$mappings_file = GRABWP_TENANCY_BASE_DIR . '/tenants.php';
	if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
		$tenant_mappings = array();
		include $mappings_file;
		return $tenant_mappings;
	}

	$tenant_mappings = array();
	return $tenant_mappings;
}

/**
 * Identify tenant by domain.
 *
 * @param string $domain   Current domain.
 * @param array  $mappings Tenant domain mappings.
 * @return string|false Tenant ID or false.
 */
function grabwp_tenancy_identify_tenant_from_domain( $domain, $mappings ) {
	if ( function_exists( 'grabwp_tenancy_pro_identify_tenant_from_domain' ) ) {
		return grabwp_tenancy_pro_identify_tenant_from_domain( $domain, $mappings );
	}
	if ( empty( $domain ) || ! is_array( $mappings ) ) {
		return false;
	}

	foreach ( $mappings as $tenant_id => $domains ) {
		if ( is_array( $domains ) ) {
			foreach ( $domains as $domain_entry ) {
				if ( $domain === $domain_entry ) {
					define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
					if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
						define( 'GRABWP_TENANCY_ROUTING_METHOD', 'domain' );
					}
					return $tenant_id;
				}
			}
		}
	}

	return false;
}

/**
 * Identify tenant from URL path (/site/[tenant-id]).
 *
 * @return string|false Tenant ID or false.
 */
function grabwp_tenancy_identify_tenant_from_path() {
	if ( function_exists( 'grabwp_tenancy_pro_identify_tenant_from_path' ) ) {
		return grabwp_tenancy_pro_identify_tenant_from_path();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$raw_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	$uri     = grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $raw_uri ) );

	if ( ! preg_match( '#^/site/([a-z0-9]{6})(/|$)#', $uri, $matches ) ) {
		return false;
	}

	$tenant_id       = $matches[1];
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
	if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
		return false;
	}

	if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
	}

	if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
		define( 'GRABWP_TENANCY_ROUTING_METHOD', 'path' );
	}

	return $tenant_id;
}

/**
 * Identify tenant from query string (?site=[tenant-id]).
 *
 * @return string|false Tenant ID or false.
 */
function grabwp_tenancy_identify_tenant_from_query() {
	if ( function_exists( 'grabwp_tenancy_pro_identify_tenant_from_query' ) ) {
		return grabwp_tenancy_pro_identify_tenant_from_query();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$raw_site  = isset( $_GET['site'] ) ? $_GET['site'] : '';
	$tenant_id = grabwp_tenancy_sanitize_text_field( grabwp_tenancy_wp_unslash( $raw_site ) );

	if ( empty( $tenant_id ) ) {
		return false;
	}

	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
	if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
		return false;
	}

	if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
	}

	if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
		define( 'GRABWP_TENANCY_ROUTING_METHOD', 'query' );
	}

	return $tenant_id;
}

/**
 * Set tenant context constants.
 */
function grabwp_tenancy_boot_set_tenant_context() {
	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		if ( ! defined( 'GRABWP_TENANCY_IS_TENANT' ) ) {
			define( 'GRABWP_TENANCY_IS_TENANT', true );
		}
		return;
	}
}

/**
 * Configure CLI and development environment constants.
 */
function grabwp_tenancy_configure_cli_environment() {
	if ( function_exists( 'grabwp_tenancy_pro_configure_cli_environment' ) ) {
		grabwp_tenancy_pro_configure_cli_environment();
		return;
	}
	if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
		define( 'DISALLOW_FILE_MODS', false );
	}
	if ( ! defined( 'WP_DEBUG' ) ) {
		define( 'WP_DEBUG', false );
	}
	if ( ! defined( 'WP_DEBUG_LOG' ) ) {
		define( 'WP_DEBUG_LOG', false );
	}
	if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
		define( 'WP_DEBUG_DISPLAY', false );
	}
}

/**
 * Get fallback domain for CLI operations.
 *
 * @param string $tenant_id       Tenant identifier.
 * @param array  $tenant_mappings Available tenant mappings.
 * @return string Domain for CLI context.
 */
function grabwp_tenancy_get_cli_domain( $tenant_id, $tenant_mappings ) {
	if ( function_exists( 'grabwp_tenancy_pro_get_cli_domain' ) ) {
		return grabwp_tenancy_pro_get_cli_domain( $tenant_id, $tenant_mappings );
	}
	if ( isset( $tenant_mappings[ $tenant_id ] ) && ! empty( $tenant_mappings[ $tenant_id ][0] ) ) {
		return $tenant_mappings[ $tenant_id ][0];
	}
	return $tenant_id . '.grabwp.local';
}

/**
 * Detect tenant from CLI, domain mapping, URL path, or query string.
 * Priority: CLI > domain > path > query.
 */
function grabwp_tenancy_boot_detect_tenant() {
	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) && GRABWP_TENANCY_TENANT_ID !== '' ) {
		grabwp_tenancy_configure_cli_environment();
		return GRABWP_TENANCY_TENANT_ID;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		//return false;
	}

	$server_info     = grabwp_tenancy_get_server_info();
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
	$tenant_id       = grabwp_tenancy_identify_tenant_from_domain( $server_info['host'], $tenant_mappings );
	if ( $tenant_id ) {
		return $tenant_id;
	}

	$tenant_id = grabwp_tenancy_identify_tenant_from_path();
	if ( $tenant_id ) {
		return $tenant_id;
	}

	$tenant_id = grabwp_tenancy_identify_tenant_from_query();
	if ( $tenant_id ) {
		return $tenant_id;
	}

	return false;
}
