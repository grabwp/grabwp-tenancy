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
				$content_valid    = ( false !== strpos( $content, 'RewriteRule ^site/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]' ) );
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
