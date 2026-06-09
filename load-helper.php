<?php
/**
 * GrabWP Tenancy - Helper Functions
 *
 * Orchestrates early tenant initialization by loading focused helper modules.
 * This file is included by load.php before WordPress loads.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// PRO PLUGIN INTEGRATION
// =============================================================================

/**
 * Load pro plugin helper functions if available.
 * Pro plugin functions take priority over base plugin functions.
 */
function grabwp_tenancy_load_pro_helper() {
	$pro_helper_path = __DIR__ . '/../grabwp-tenancy-pro/load-helper-pro.php';
	if ( file_exists( $pro_helper_path ) && is_readable( $pro_helper_path ) ) {
		require_once $pro_helper_path;
		return true;
	}

	return false;
}

// Load pro helper immediately if available
$grabwp_tenancy_pro_loaded = grabwp_tenancy_load_pro_helper();

// =============================================================================
// LOAD HELPER MODULES (order matters — security first, then detection, then boot)
// =============================================================================

require_once __DIR__ . '/load-helper-security.php';
require_once __DIR__ . '/load-helper-server-detection.php';
require_once __DIR__ . '/load-helper-tenant-detection.php';
require_once __DIR__ . '/load-helper-boot-constants.php';
require_once __DIR__ . '/load-helper-cache.php';

// =============================================================================
// BASE DIR RESOLUTION
// =============================================================================

/**
 * Define GRABWP_TENANCY_BASE_DIR immediately when this file loads.
 * Required for early loading tenant config detection.
 */
if ( ! defined( 'GRABWP_TENANCY_BASE_DIR' ) ) {
	if ( function_exists( 'grabwp_tenancy_pro_define_base_dir' ) ) {
		$grabwp_tenancy_dirs = grabwp_tenancy_pro_define_base_dir();
		define( 'GRABWP_TENANCY_BASE_DIR', $grabwp_tenancy_dirs['grabwp_base_dir'] );
		define( 'GRABWP_TENANCY_DIRS_FROM_PLUGIN', true );
		return;
	}

	if ( file_exists( ABSPATH . 'wp-content/grabwp/tenants.php' ) ) {
		define( 'GRABWP_TENANCY_BASE_DIR', ABSPATH . 'wp-content/grabwp' );
	} elseif ( is_dir( ABSPATH . 'wp-content/uploads/grabwp-tenancy' ) ) {
		define( 'GRABWP_TENANCY_BASE_DIR', ABSPATH . 'wp-content/uploads/grabwp-tenancy' );
	} else {
		define( 'GRABWP_TENANCY_BASE_DIR', ABSPATH . 'wp-content/grabwp-tenancy' );
	}
	define( 'GRABWP_TENANCY_DIRS_FROM_PLUGIN', true );
}

// =============================================================================
// BOOT ORCHESTRATION
// =============================================================================

/**
 * Initialize tenant system.
 */
function grabwp_tenancy_early_init() {
	if ( function_exists( 'grabwp_tenancy_pro_early_init' ) ) {
		grabwp_tenancy_pro_early_init();
		return;
	}

	$tenant_id = grabwp_tenancy_boot_detect_tenant();

	if ( ! $tenant_id || ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
		return;
	}

	grabwp_tenancy_boot_set_tenant_context();
	grabwp_tenancy_boot_disable_page_cache();
	grabwp_tenancy_boot_set_cache_key_salt();
	grabwp_tenancy_boot_define_constants();
}
