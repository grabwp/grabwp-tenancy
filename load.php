<?php
/**
 * GrabWP Tenancy - Early Loading System
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double loading
if ( defined( 'GRABWP_TENANCY_LOADED' ) ) {
	return;
}

define( 'GRABWP_TENANCY_LOADED', true );

// Include helper functions
require_once __DIR__ . '/load-helper.php';

// Initialize
grabwp_tenancy_early_init();

// Synchronize file-scope $table_prefix with the global set by tenancy boot.
//
// WP-CLI eval()s wp-config.php, which sets $table_prefix as a local variable.
// Tenancy functions modify $GLOBALS['table_prefix'] via `global`, but the
// eval-local variable stays stale.  After eval, WP-CLI iterates new local
// vars and globalizes them — overwriting $GLOBALS['table_prefix'] with the
// stale original.  By declaring `global $table_prefix` here (at the file
// scope of a require'd file = inside the eval scope), we re-bind the local
// to the global so WP-CLI's globalization loop picks up the correct value.
if ( defined( 'GRABWP_TENANCY_IS_TENANT' ) ) {
	global $table_prefix;
}

