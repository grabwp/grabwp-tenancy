<?php
/**
 * GrabWP Tenancy - Cache Isolation
 *
 * Disables page cache and isolates object cache keys per tenant.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable page cache drop-in for tenant context.
 *
 * @since 1.2.0
 */
function grabwp_tenancy_boot_disable_page_cache() {
	if ( ! defined( 'WP_CACHE' ) ) {
		define( 'WP_CACHE', false );
	}
}

/**
 * Set WP_CACHE_KEY_SALT for object cache isolation.
 *
 * @since 1.2.0
 */
function grabwp_tenancy_boot_set_cache_key_salt() {
	if ( ! defined( 'WP_CACHE_KEY_SALT' ) && defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'WP_CACHE_KEY_SALT', 'tenant_' . GRABWP_TENANCY_TENANT_ID . '_' );
	}
}
