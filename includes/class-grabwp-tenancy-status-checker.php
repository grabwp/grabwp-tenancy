<?php
/**
 * GrabWP Tenancy Status Checker
 *
 * Gathers environment data for the status page display.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Status_Checker {

	/**
	 * Get wp-config.php loader status.
	 *
	 * @return array
	 */
	public static function get_wp_config_status() {
		$wp_config_path     = ABSPATH . 'wp-config.php';
		$readable           = is_readable( $wp_config_path );
		$writable           = wp_is_writable( $wp_config_path );
		$loader_active      = defined( 'GRABWP_TENANCY_LOADED' ) && GRABWP_TENANCY_LOADED;
		$loader_line        = false;
		$stop_editing       = false;

		if ( $readable ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$content = file_get_contents( $wp_config_path );
			if ( false !== $content ) {
				$loader_line  = ( strpos( $content, 'grabwp-tenancy/load.php' ) !== false );
				$stop_editing = (
					strpos( $content, "/* That's all, stop editing! Happy publishing. */" ) !== false
					|| strpos( $content, "/* That's all, stop editing! */" ) !== false
				);
			}
		}

		return array(
			'path'          => $wp_config_path,
			'readable'      => $readable,
			'writable'      => $writable,
			'loader_active' => $loader_active,
			'loader_line'   => $loader_line,
			'stop_editing'  => $stop_editing,
		);
	}

	/**
	 * Get MU-Plugin status.
	 *
	 * @return array
	 */
	public static function get_mu_plugin_status() {
		$filename    = GrabWP_Tenancy_Installer::MU_PLUGIN_FILE;
		$mu_dir      = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( ABSPATH . 'wp-content/mu-plugins' );
		$path        = $mu_dir . '/' . $filename;
		$dir_exists  = is_dir( $mu_dir );
		$dir_writable = $dir_exists ? wp_is_writable( $mu_dir ) : wp_is_writable( dirname( $mu_dir ) );
		$exists      = file_exists( $path );
		$valid       = false;

		if ( $exists && is_readable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$content = file_get_contents( $path );
			$valid   = ( false !== $content && strpos( $content, 'grabwp-tenancy' ) !== false );
		}

		return array(
			'path'         => $path,
			'dir'          => $mu_dir,
			'dir_exists'   => $dir_exists,
			'dir_writable' => $dir_writable,
			'exists'       => $exists,
			'valid'        => $valid,
		);
	}

	/**
	 * Get root .htaccess status.
	 *
	 * @return array
	 */
	public static function get_root_htaccess_status() {
		$path      = ABSPATH . '.htaccess';
		$exists    = file_exists( $path );
		$writable  = $exists ? wp_is_writable( $path ) : false;
		$dir_writable     = wp_is_writable( ABSPATH );
		$has_block        = false;
		$block_positioned = false;
		$content_valid    = false;

		if ( $exists && is_readable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$content  = file_get_contents( $path );
			if ( false !== $content ) {
				$pos    = strpos( $content, '# BEGIN GrabWP Tenancy' );
				$wp_pos = strpos( $content, '# BEGIN WordPress' );
				$has_block        = ( false !== $pos );
				$block_positioned = $has_block && ( false === $wp_pos || $pos < $wp_pos );
				$prefix           = grabwp_tenancy_get_path_prefix();
				// Match either alias-compatible regex or legacy 6-char regex.
				$content_valid    = (
					false !== strpos( $content, 'RewriteRule ^' . $prefix . '/([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)/?$ /index.php?site=$1 [QSA,L]' )
					|| false !== strpos( $content, 'RewriteRule ^' . $prefix . '/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]' )
				);
			}
		}

		return array(
			'path'             => $path,
			'exists'           => $exists,
			'writable'         => $writable,
			'dir_writable'     => $dir_writable,
			'has_block'        => $has_block,
			'block_positioned' => $block_positioned,
			'content_valid'    => $content_valid,
		);
	}

	/**
	 * Get data directory .htaccess status.
	 *
	 * @param string $base_path Base data directory path.
	 * @return array
	 */
	public static function get_data_htaccess_status( $base_path ) {
		$path     = $base_path . '/.htaccess';
		$exists   = file_exists( $path );
		$has_deny = false;

		if ( $exists && is_readable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$content = file_get_contents( $path );
			if ( false !== $content ) {
				$has_deny = ( strpos( $content, 'Deny from all' ) !== false
					|| strpos( $content, 'Require all denied' ) !== false );
			}
		}

		return array(
			'path'     => $path,
			'exists'   => $exists,
			'has_deny' => $has_deny,
		);
	}

	/**
	 * Detect path configuration warnings for status page display.
	 *
	 * Scans all known legacy/new locations for tenants.php, config.php,
	 * and directory structures. Returns warnings about duplicates, legacy
	 * paths inside uploads/, and wp-config.php overrides.
	 *
	 * @since 1.1.4
	 * @return array[] Each item: [ type, title, message, paths, active ]
	 */
	public static function get_path_confusion_warnings() {
		$warnings    = array();
		$content_dir = ABSPATH . ( defined( 'GRABWP_WORDPRESS_CONTENT_DIR' ) ? GRABWP_WORDPRESS_CONTENT_DIR : 'wp-content' );
		$base        = defined( 'GRABWP_TENANCY_BASE_DIR' ) ? GRABWP_TENANCY_BASE_DIR : '';
		$pro_base    = defined( 'GRABWP_TENANCY_PRO_BASE_DIR' ) ? GRABWP_TENANCY_PRO_BASE_DIR : '';

		// --- Multiple tenants.php files ---
		$tenants_candidates = array(
			$content_dir . '/grabwp/tenants.php',
			$content_dir . '/uploads/grabwp-tenancy/tenants.php',
			$content_dir . '/grabwp-tenancy/tenants.php',
			$base . '/tenants.php',
		);
		$tenants_found = array_filter( $tenants_candidates, 'file_exists' );
		if ( count( $tenants_found ) > 1 ) {
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'Multiple tenants.php files detected', 'grabwp-tenancy' ),
				'message' => __( 'Only one tenants.php is loaded at boot. Stale copies at other locations may cause confusion during debugging or migration. Remove unused copies.', 'grabwp-tenancy' ),
				'paths'   => array_values( $tenants_found ),
				'active'  => $base ? $base . '/tenants.php' : '',
			);
		}

		// --- Multiple global config.php files (Pro) ---
		$configs_candidates = array(
			$content_dir . '/grabwp-tenancy-pro/config.php',
			$content_dir . '/uploads/grabwp-tenancy-pro/config.php',
			$pro_base . '/config.php',
		);
		$configs_found = array_filter( $configs_candidates, 'file_exists' );
		if ( count( $configs_found ) > 1 ) {
			// Determine the active config.php for Pro, with priority: $pro_base, then new, then legacy location.
			if ( file_exists( $pro_base . '/config.php' ) && $pro_base ) {
				$active_config = $pro_base . '/config.php';
			} elseif ( file_exists( $content_dir . '/grabwp-tenancy-pro/config.php' ) ) {
				$active_config = $content_dir . '/grabwp-tenancy-pro/config.php';
			} elseif ( file_exists( $content_dir . '/uploads/grabwp-tenancy-pro/config.php' ) ) {
				$active_config = $content_dir . '/uploads/grabwp-tenancy-pro/config.php';
			} else {
				$active_config = '';
			}
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'Multiple global config.php files detected', 'grabwp-tenancy' ),
				'message' => __( 'Pro global configuration exists at both new and legacy locations. Boot loads the new path first; changes saved to the other copy are silently ignored. Remove the unused copy.', 'grabwp-tenancy' ),
				'paths'   => array_values( $configs_found ),
				'active'  => $active_config,
			);
		}

		// --- Base dir inside uploads/ (legacy v2) ---
		if ( $base && $base === $content_dir . '/uploads/grabwp-tenancy' ) {
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'Data directory inside uploads/', 'grabwp-tenancy' ),
				'message' => __( 'Tenant data is inside wp-content/uploads/ — exposed to media upload scanners, backup tools, and CDN sync. Recommended: wp-content/grabwp-tenancy/.', 'grabwp-tenancy' ),
				'paths'   => array( $base ),
			);
		}

		// --- Pro config dir inside uploads/ ---
		if ( $pro_base && $pro_base === $content_dir . '/uploads/grabwp-tenancy-pro' ) {
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'Pro config directory inside uploads/', 'grabwp-tenancy' ),
				'message' => __( 'Pro tenant configs are inside wp-content/uploads/. Recommended: wp-content/grabwp-tenancy-pro/.', 'grabwp-tenancy' ),
				'paths'   => array( $pro_base ),
			);
		}

		// --- SQLite dir inside uploads/ ---
		$sqlite_dir = defined( 'GRABWP_TENANCY_SQLITE_DIR' ) ? GRABWP_TENANCY_SQLITE_DIR : '';
		if ( $sqlite_dir && $sqlite_dir === $content_dir . '/uploads/grabwp-tenancy-sqlite' ) {
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'SQLite directory inside uploads/', 'grabwp-tenancy' ),
				'message' => __( 'SQLite databases are inside wp-content/uploads/. Recommended: wp-content/grabwp-tenancy-sqlite/.', 'grabwp-tenancy' ),
				'paths'   => array( $sqlite_dir ),
			);
		}

		// --- wp-config.php overrides auto-detection ---
		if ( defined( 'GRABWP_TENANCY_DIRS_FROM_WPCONFIG' ) && GRABWP_TENANCY_DIRS_FROM_WPCONFIG ) {
			$override_paths = array();
			if ( $base ) {
				$override_paths[] = 'GRABWP_TENANCY_BASE_DIR = ' . $base;
			}
			if ( $pro_base ) {
				$override_paths[] = 'GRABWP_TENANCY_PRO_BASE_DIR = ' . $pro_base;
			}
			if ( $sqlite_dir ) {
				$override_paths[] = 'GRABWP_TENANCY_SQLITE_DIR = ' . $sqlite_dir;
			}
			$warnings[] = array(
				'type'    => 'info',
				'title'   => __( 'Directory paths hardcoded in wp-config.php', 'grabwp-tenancy' ),
				'message' => __( 'Constants defined before load.php override all auto-detection and Pro admin directory settings.', 'grabwp-tenancy' ),
				'paths'   => $override_paths,
			);
		}

		// --- Multiple tenant-aliases.php files (Pro path routing) ---
		$aliases_candidates = array(
			$content_dir . '/grabwp/tenant-aliases.php',
			$content_dir . '/uploads/grabwp-tenancy/tenant-aliases.php',
			$content_dir . '/grabwp-tenancy/tenant-aliases.php',
		);
		$aliases_found = array_filter( $aliases_candidates, 'file_exists' );
		if ( count( $aliases_found ) > 1 ) {
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'Multiple tenant-aliases.php files detected', 'grabwp-tenancy' ),
				'message' => __( 'Only the copy at the active base directory is loaded. Remove stale copies to prevent confusion.', 'grabwp-tenancy' ),
				'paths'   => array_values( $aliases_found ),
				'active'  => $base ? $base . '/tenant-aliases.php' : '',
			);
		}

		return $warnings;
	}

	/**
	 * Get server environment info.
	 *
	 * @return array
	 */
	public static function get_server_environment() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'Unknown', 'grabwp-tenancy' );
		$is_apache       = ( stripos( $server_software, 'apache' ) !== false || stripos( $server_software, 'litespeed' ) !== false );
		$is_nginx        = ( stripos( $server_software, 'nginx' ) !== false );
		$mod_rewrite     = ( $is_apache && function_exists( 'apache_get_modules' ) ) ? in_array( 'mod_rewrite', apache_get_modules(), true ) : null;

		return array(
			'server_software' => $server_software,
			'is_apache'       => $is_apache,
			'is_nginx'        => $is_nginx,
			'mod_rewrite'     => $mod_rewrite,
			'is_multisite'    => is_multisite(),
			'wp_debug'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
		);
	}
}
