<?php
/**
 * GrabWP Tenancy - Boot Constants & Path Management
 *
 * Defines WordPress constants and configures paths/DB for tenant isolation.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define ABSPATH if not already defined.
 */
function grabwp_tenancy_boot_define_abspath() {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );
	}
}

/**
 * Define WP_SITEURL, WP_HOME, and cookie-path constants.
 *
 * @param array $server_info Return value of grabwp_tenancy_get_server_info().
 */
function grabwp_tenancy_boot_define_routing_constants( $server_info ) {
	if ( empty( $server_info['host'] ) ) {
		return;
	}

	$base_url = $server_info['protocol'] . '://' . $server_info['host'];

	if ( defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) && in_array( GRABWP_TENANCY_ROUTING_METHOD, array( 'path', 'query' ), true ) ) {
		$tenant_path = defined( 'GRABWP_TENANCY_TENANT_ID' ) ? '/site/' . GRABWP_TENANCY_TENANT_ID : '';
		$site_url    = $base_url . $tenant_path;

		if ( ! defined( 'WP_SITEURL' ) ) {
			define( 'WP_SITEURL', $site_url );
		}
		if ( ! defined( 'WP_HOME' ) ) {
			define( 'WP_HOME', $site_url );
		}

		if ( ! empty( $tenant_path ) ) {
			if ( ! defined( 'COOKIEPATH' ) ) {
				define( 'COOKIEPATH', $tenant_path . '/' );
			}
			if ( ! defined( 'SITECOOKIEPATH' ) ) {
				define( 'SITECOOKIEPATH', $tenant_path . '/' );
			}
			if ( ! defined( 'ADMIN_COOKIE_PATH' ) ) {
				define( 'ADMIN_COOKIE_PATH', $tenant_path . '/wp-admin' );
			}
		}
	} else {
		if ( ! defined( 'WP_SITEURL' ) ) {
			define( 'WP_SITEURL', $base_url );
		}
		if ( ! defined( 'WP_HOME' ) ) {
			define( 'WP_HOME', $base_url );
		}
	}
}

/**
 * Define essential WordPress constants for early loading.
 */
function grabwp_tenancy_boot_define_constants() {
	grabwp_tenancy_boot_define_abspath();

	if ( ! defined( 'WP_CONTENT_DIR' ) ) {
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	}

	$server_info = grabwp_tenancy_get_server_info();

	if ( ! defined( 'WP_CONTENT_URL' ) && ! empty( $server_info['host'] ) ) {
		define( 'WP_CONTENT_URL', $server_info['protocol'] . '://' . $server_info['host'] . '/wp-content' );
	}

	if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
		define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
	}

	grabwp_tenancy_boot_define_routing_constants( $server_info );
	grabwp_tenancy_set_uploads_paths();
	grabwp_tenancy_set_database_prefix();
}

/**
 * Set content paths for tenant isolation.
 */
function grabwp_tenancy_set_uploads_paths() {
	if ( function_exists( 'grabwp_tenancy_pro_set_uploads_paths' ) ) {
		grabwp_tenancy_pro_set_uploads_paths();
		return;
	}

	$upload_dir      = GRABWP_TENANCY_BASE_DIR . '/' . GRABWP_TENANCY_TENANT_ID . '/uploads';
	$upload_relative = str_replace( ABSPATH, '', $upload_dir );

	define( 'GRABWP_TENANCY_UPLOAD_DIR', $upload_dir );

	if ( ! defined( 'UPLOADS' ) ) {
		define( 'UPLOADS', $upload_relative );
	}
}

/**
 * Set database prefix for tenant isolation.
 */
function grabwp_tenancy_set_database_prefix() {
	if ( function_exists( 'grabwp_tenancy_pro_set_database_prefix' ) ) {
		grabwp_tenancy_pro_set_database_prefix();
		return;
	}
	global $table_prefix, $wpdb;

	if ( ! defined( 'GRABWP_TENANCY_ORIGINAL_PREFIX' ) ) {
		define( 'GRABWP_TENANCY_ORIGINAL_PREFIX', $table_prefix );
	}

	$table_prefix = GRABWP_TENANCY_TENANT_ID . '_';

	if ( ! defined( 'GRABWP_TENANCY_TABLE_PREFIX' ) ) {
		define( 'GRABWP_TENANCY_TABLE_PREFIX', $table_prefix );
	}

	if ( isset( $wpdb ) && is_object( $wpdb ) ) {
		$wpdb->prefix = GRABWP_TENANCY_TENANT_ID . '_';
		if ( method_exists( $wpdb, 'set_prefix' ) ) {
			$wpdb->set_prefix( GRABWP_TENANCY_TENANT_ID . '_' );
		}
	}
}
