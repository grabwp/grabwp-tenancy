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
			'path'           => $wp_config_path,
			'readable'       => $readable,
			'writable'       => $writable,
			'loader_active'  => $loader_active,
			'loader_line'    => $loader_line,
			// Written in the last few minutes, but this request started before
			// it was read (or ran an older cached copy): the next request
			// confirms. An older line that still does not load is an error.
			'loader_pending' => ! $loader_active && $loader_line && time() - (int) @filemtime( $wp_config_path ) < 10 * MINUTE_IN_SECONDS, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'stop_editing'   => $stop_editing,
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
				$content_valid    = GrabWP_Tenancy_Installer::htaccess_has_site_path_rules(
					$content,
					grabwp_tenancy_get_path_prefix(),
					grabwp_tenancy_get_routing_mode()
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
		$tenants_found = array_values( array_unique( array_filter( $tenants_candidates, 'file_exists' ) ) );
		if ( count( $tenants_found ) > 1 ) {
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'Multiple tenants.php files detected', 'grabwp-tenancy' ),
				'message' => __( 'Only one tenants.php is loaded at boot. Stale copies at other locations may cause confusion during debugging or migration. Remove unused copies.', 'grabwp-tenancy' ),
				'paths'   => $tenants_found,
				'active'  => $base ? $base . '/tenants.php' : '',
			);
		}

		// --- Multiple global config.php files (Pro) ---
		$configs_candidates = array(
			$content_dir . '/grabwp-tenancy-pro/config.php',
			$content_dir . '/uploads/grabwp-tenancy-pro/config.php',
			$pro_base . '/config.php',
		);
		$configs_found = array_values( array_unique( array_filter( $configs_candidates, 'file_exists' ) ) );
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
				'paths'   => $configs_found,
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
		$aliases_found = array_values( array_unique( array_filter( $aliases_candidates, 'file_exists' ) ) );
		if ( count( $aliases_found ) > 1 ) {
			$warnings[] = array(
				'type'    => 'warning',
				'title'   => __( 'Multiple tenant-aliases.php files detected', 'grabwp-tenancy' ),
				'message' => __( 'Only the copy at the active base directory is loaded. Remove stale copies to prevent confusion.', 'grabwp-tenancy' ),
				'paths'   => $aliases_found,
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

	/**
	 * Read files fresh: clear PHP's stat cache, and drop wp-config.php and the
	 * MU loader from OPcache so the next request compiles the files on disk
	 * (hosts often keep compiled copies without checking timestamps).
	 *
	 * @since 1.1.13
	 */
	public static function refresh_boot_files() {
		clearstatcache( true );
		if ( ! function_exists( 'opcache_invalidate' ) || ! filter_var( ini_get( 'opcache.enable' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return;
		}
		// With restrict_api set, only requests whose main script is under that path may call the API.
		$restrict = (string) ini_get( 'opcache.restrict_api' );
		$script   = isset( $_SERVER['SCRIPT_FILENAME'] ) ? (string) $_SERVER['SCRIPT_FILENAME'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $restrict && 0 !== strpos( $script, $restrict ) ) {
			return;
		}
		foreach ( array( ABSPATH . 'wp-config.php', dirname( ABSPATH ) . '/wp-config.php', GrabWP_Tenancy_Installer::get_mu_plugin_path() ) as $file ) {
			// @: the parent directory may be outside open_basedir (core checks it the same way).
			if ( @is_file( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@opcache_invalidate( $file, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * Tenant file settings that let tenant admins run their own PHP code.
	 *
	 * When tenants may install or edit plugin and theme files, a tenant admin can run
	 * PHP on this server and read the secret keys every site shares. A constant set
	 * before GrabWP overrules the saved setting on tenant requests too, so it decides
	 * the effective value when present. A missing or null setting means the default
	 * (on), the same as GrabWP_Tenancy_Settings::get().
	 *
	 * Scope: the global setting plus constants defined in wp-config.php or mu-plugins,
	 * which tenant requests share. Per-tenant overrides are reported separately by
	 * get_unsafe_tenant_file_overrides(). Constants set by regular plugins or by
	 * wp-config.php code that branches per host are not covered.
	 *
	 * @since  1.1.14
	 * @param  array|null $settings Global settings. Default: the saved settings.
	 * @param  array|null $presets  Constants set before GrabWP, keyed by setting. Default: the captured ones.
	 * @return array Setting key => constant name when a constant forces it off, or '' when the setting is off.
	 *               Empty when both settings are safe.
	 */
	public static function get_unsafe_file_settings( $settings = null, $presets = null ) {
		if ( null === $settings ) {
			$settings = GrabWP_Tenancy_Settings::get_instance()->get_all();
		}
		if ( null === $presets ) {
			$presets = GrabWP_Tenancy_Settings::get_preset_constants();
		}

		$constants = GrabWP_Tenancy_Settings::get_setting_constants();
		$unsafe    = array();
		foreach ( array( 'disallow_file_mods', 'disallow_file_edit' ) as $key ) {
			if ( array_key_exists( $key, $presets ) ) {
				if ( ! $presets[ $key ] ) {
					$unsafe[ $key ] = $constants[ $key ];
				}
			} elseif ( isset( $settings[ $key ] ) && ! $settings[ $key ] ) {
				$unsafe[ $key ] = '';
			}
		}
		return $unsafe;
	}

	/**
	 * Tenants whose own override turns a tenant file setting off.
	 *
	 * Add-ons that store per-tenant overrides of the 'grabwp_tenancy_effective_setting'
	 * filter report them through 'grabwp_tenancy_unsafe_tenant_file_overrides'. A
	 * constant set before GrabWP wins over any override, so those settings are skipped.
	 *
	 * @since  1.1.14
	 * @param  array|null $overrides Tenant ID => list of setting keys overridden off. Default: the filtered ones.
	 * @param  array|null $presets   Constants set before GrabWP, keyed by setting. Default: the captured ones.
	 * @return array Tenant ID => list of constant names the override forces off. Empty when none.
	 */
	public static function get_unsafe_tenant_file_overrides( $overrides = null, $presets = null ) {
		if ( null === $overrides ) {
			/**
			 * Tenants whose stored override turns a tenant file setting off.
			 *
			 * Read stored overrides only; do not connect to tenant databases.
			 *
			 * @since 1.1.14
			 * @param array $overrides Tenant ID => list of setting keys
			 *                         ('disallow_file_mods', 'disallow_file_edit') overridden off.
			 */
			$overrides = apply_filters( 'grabwp_tenancy_unsafe_tenant_file_overrides', array() );
		}
		if ( ! $overrides || ! is_array( $overrides ) ) {
			return array();
		}
		if ( null === $presets ) {
			$presets = GrabWP_Tenancy_Settings::get_preset_constants();
		}

		$constants = GrabWP_Tenancy_Settings::get_setting_constants();
		$unsafe    = array();
		foreach ( $overrides as $tenant_id => $keys ) {
			$names = array();
			foreach ( array( 'disallow_file_mods', 'disallow_file_edit' ) as $key ) {
				if ( is_array( $keys ) && in_array( $key, $keys, true ) && ! array_key_exists( $key, $presets ) ) {
					$names[] = $constants[ $key ];
				}
			}
			if ( $names ) {
				$unsafe[ (string) $tenant_id ] = $names;
			}
		}
		return $unsafe;
	}

	/**
	 * Whether a reverse proxy or CDN reports HTTPS for this request while
	 * WordPress sees HTTP, e.g. Cloudflare Flexible SSL without HTTPS detection
	 * in wp-config.php. Read only; GrabWP never edits wp-config.php for this.
	 *
	 * @param  array|null $server Server variables; null reads $_SERVER.
	 * @return bool
	 */
	public static function is_proxy_https_undetected( $server = null ) {
		$server = self::server_https_vars( $server );

		// Same test as is_ssl().
		if ( isset( $server['HTTPS'] ) && ( 'on' === strtolower( (string) $server['HTTPS'] ) || '1' === (string) $server['HTTPS'] ) ) {
			return false;
		}
		if ( isset( $server['SERVER_PORT'] ) && '443' === (string) $server['SERVER_PORT'] ) {
			return false;
		}

		return self::is_forwarded_https( $server );
	}

	/**
	 * Server variables the proxy HTTPS checks read.
	 *
	 * @param  array|null $server Server variables; null reads $_SERVER.
	 * @return array
	 */
	private static function server_https_vars( $server ) {
		if ( null !== $server ) {
			return $server;
		}
		$server = array();
		foreach ( array( 'HTTPS', 'SERVER_PORT', 'HTTP_X_FORWARDED_PROTO', 'HTTP_CF_VISITOR' ) as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$server[ $key ] = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}
		return $server;
	}

	/**
	 * Whether a proxy or CDN reports that the visitor used HTTPS
	 * (X-Forwarded-Proto or Cloudflare's CF-Visitor).
	 *
	 * @param  array $server Server variables.
	 * @return bool
	 */
	private static function is_forwarded_https( $server ) {
		$proto   = isset( $server['HTTP_X_FORWARDED_PROTO'] ) ? strtolower( (string) $server['HTTP_X_FORWARDED_PROTO'] ) : '';
		$proto   = trim( (string) strtok( $proto, ',' ) );
		$visitor = isset( $server['HTTP_CF_VISITOR'] ) ? strtolower( (string) $server['HTTP_CF_VISITOR'] ) : '';
		return 'https' === $proto || false !== strpos( $visitor, '"https"' );
	}

	/**
	 * Count the setup wizard Check screen items that need attention.
	 *
	 * Fixable items are the environment cards "Fix automatically" can repair. The
	 * loopback, proxy HTTPS and tenant file safety items count as issues but are
	 * never auto-fixed.
	 *
	 * @since  1.1.14
	 * @param  array $env          Result of get_env_card_data().
	 * @param  bool  $loopback     Whether the site can reach itself.
	 * @param  array $unsafe_files   Result of get_unsafe_file_settings().
	 * @param  array $unsafe_tenants Result of get_unsafe_tenant_file_overrides().
	 * @param  bool  $proxy_https_undetected Result of is_proxy_https_undetected().
	 * @param  array|null $hosting   Result of get_hosting_card_data(). Its failing rows count
	 *                               as issues, never as fixable: their fixes need a confirmation.
	 * @return array {
	 *     @type int $issues  All items that need attention.
	 *     @type int $fixable Items "Fix automatically" can repair.
	 * }
	 */
	public static function count_setup_check_issues( $env, $loopback, $unsafe_files, $unsafe_tenants = array(), $proxy_https_undetected = false, $hosting = null ) {
		$fixable = 0;
		if ( ! $env['grabwp_status_wp_config']['loader_active'] ) {
			++$fixable;
		}
		if ( ! ( $env['grabwp_status_mu_plugin']['exists'] && $env['grabwp_status_mu_plugin']['valid'] ) ) {
			++$fixable;
		}
		$htaccess = $env['grabwp_status_htaccess'];
		if ( $env['grabwp_status_server']['is_apache'] && ! ( $htaccess['has_block'] && $htaccess['block_positioned'] && $htaccess['content_valid'] ) ) {
			++$fixable;
		}
		if ( ! ( $env['grabwp_status_data_htaccess']['exists'] && $env['grabwp_status_data_htaccess']['has_deny'] ) ) {
			++$fixable;
		}
		if ( ! $env['grabwp_status_index_exists'] ) {
			++$fixable;
		}

		$issues = $fixable;
		// With the Hosting setup card, the proxy item is one of its rows.
		if ( ! $loopback || ( $proxy_https_undetected && null === $hosting ) ) {
			++$issues;
		}
		if ( $unsafe_files || $unsafe_tenants ) {
			++$issues;
		}
		if ( is_array( $hosting ) && isset( $hosting['issues'] ) ) {
			$issues += (int) $hosting['issues'];
		}
		return array(
			'issues'  => $issues,
			'fixable' => $fixable,
		);
	}

	/**
	 * Data used by the environment check cards (Status page and setup wizard).
	 *
	 * @since 1.1.12
	 * @return array Variables keyed by the names the cards partial expects.
	 */
	public static function get_env_card_data() {
		self::refresh_boot_files();
		$base_path = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		return array(
			'grabwp_status_base_path'         => $base_path,
			'grabwp_status_wp_config'         => self::get_wp_config_status(),
			'grabwp_status_mu_plugin'         => self::get_mu_plugin_status(),
			'grabwp_status_htaccess'          => self::get_root_htaccess_status(),
			'grabwp_status_data_htaccess'     => self::get_data_htaccess_status( $base_path ),
			'grabwp_status_server'            => self::get_server_environment(),
			'grabwp_status_base_dir_writable' => is_dir( $base_path ) ? wp_is_writable( $base_path ) : false,
			'grabwp_status_index_exists'      => file_exists( $base_path . '/index.php' ),
		);
	}

	// =========================================================================
	// Hosting setup (managed hosts: Multisite bootstrap, HTTPS, proxy, wildcard)
	// =========================================================================

	/** Transient that keeps the outbound hosting probes for a few minutes. */
	const HOSTING_TRANSIENT = 'grabwp_tenancy_hosting_check';

	/** Option that keeps home and siteurl from before the switch to https, for Undo. */
	const HTTPS_URL_UNDO_OPTION = 'grabwp_tenancy_https_url_undo';

	/**
	 * Native WordPress Multisite constants. WP_ALLOW_MULTISITE alone only shows
	 * the Network Setup screen, so it is optional cleanup.
	 */
	const MULTISITE_CONSTANTS = array( 'MULTISITE', 'SUBDOMAIN_INSTALL', 'VHOST', 'DOMAIN_CURRENT_SITE', 'PATH_CURRENT_SITE', 'SITE_ID_CURRENT_SITE', 'BLOG_ID_CURRENT_SITE', 'SUNRISE', 'NOBLOGREDIRECT', 'WP_ALLOW_MULTISITE' );

	/**
	 * Constants that stop GrabWP when wp-config.php defines them, whatever the
	 * value: the Multisite ones, plus the address and cookie constants GrabWP
	 * sets for each tenant (a constant defined first wins). The single list
	 * read by the Status check, the removal fix and other conflict checks.
	 *
	 * @since 1.1.14
	 */
	const CONFLICT_CONSTANTS = array( 'MULTISITE', 'SUBDOMAIN_INSTALL', 'VHOST', 'DOMAIN_CURRENT_SITE', 'PATH_CURRENT_SITE', 'SITE_ID_CURRENT_SITE', 'BLOG_ID_CURRENT_SITE', 'SUNRISE', 'NOBLOGREDIRECT', 'WP_ALLOW_MULTISITE', 'WP_HOME', 'WP_SITEURL', 'COOKIE_DOMAIN', 'COOKIEPATH', 'SITECOOKIEPATH', 'ADMIN_COOKIE_PATH' );

	/**
	 * Top-level wp-config.php variables that conflict the same way (names
	 * without $): $base is left by old Multisite installs.
	 *
	 * @since 1.1.14
	 */
	const CONFLICT_VARIABLES = array( 'base' );

	/**
	 * wp-config.php in use: next to WordPress, or one folder up (core rule).
	 *
	 * @return string
	 */
	public static function get_wp_config_file() {
		$path = ABSPATH . 'wp-config.php';
		$up   = dirname( ABSPATH ) . '/wp-config.php';
		// @: the parent directory may be outside open_basedir (core checks it the same way).
		if ( ! file_exists( $path ) && @file_exists( $up ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $up;
		}
		return $path;
	}

	/**
	 * Read a file, '' when it is missing or unreadable.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function read_text( $path ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		return @is_readable( $path ) ? (string) @file_get_contents( $path ) : '';
	}

	/**
	 * Scan wp-config.php code with the PHP tokenizer, so comments and strings
	 * never count as code.
	 *
	 * @param string        $content wp-config.php content.
	 * @param string[]|null $names   Constants whose define() calls are listed, and '$name' variables whose
	 *                               assignments are listed; null for the conflict constants and variables.
	 * @return array {
	 *     @type array[] $defines      define() calls: name, line, conditional, code, and for a whole
	 *                                 top-level statement (statement true) its byte range start/end
	 *                                 (end after the ;) and the value code. A variable is named '$name'.
	 *     @type int     $loader_line  Line of the GrabWP loader, 0 when none.
	 *     @type int     $proxy_line   First line of code that reads a proxy HTTPS header and sets HTTPS, 0 when none.
	 *     @type int     $proxy_block  Line of the GrabWP HTTPS proxy block, 0 when none.
	 *     @type int     $settings_line Line that loads wp-settings.php, 0 when none.
	 * }
	 */
	public static function scan_wp_config( $content, $names = null ) {
		$names  = null === $names ? array_merge( self::CONFLICT_CONSTANTS, preg_filter( '/^/', '$', self::CONFLICT_VARIABLES ) ) : $names;
		$result = array(
			'defines'       => array(),
			'loader_line'   => 0,
			'proxy_line'    => 0,
			'proxy_block'   => 0,
			'settings_line' => 0,
		);
		if ( '' === $content || ! function_exists( 'token_get_all' ) ) {
			return $result;
		}

		$lines   = preg_split( '/\r\n|\n|\r/', $content );
		$tokens  = @token_get_all( $content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$count   = count( $tokens );
		$depth   = 0;
		$guarded = false;
		$header  = 0;
		$sets_on = false;
		$prev    = null;
		$name    = '';
		$guards  = array( T_IF, T_ELSEIF, T_ELSE, T_SWITCH, T_FUNCTION, T_BOOLEAN_OR, T_BOOLEAN_AND, T_LOGICAL_OR, T_LOGICAL_AND );
		$define  = array( 'define', '\\define' );
		$offsets = array();
		$offset  = 0;
		foreach ( $tokens as $index => $token ) {
			$offsets[ $index ] = $offset;
			$offset           += strlen( is_array( $token ) ? $token[1] : $token );
		}
		$offsets[ $count ] = $offset;

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				if ( '{' === $token ) {
					++$depth;
					$guarded = false;
				} elseif ( '}' === $token ) {
					$depth   = max( 0, $depth - 1 );
					$guarded = false;
				} elseif ( ';' === $token ) {
					$guarded = false;
				} elseif ( '?' === $token ) {
					$guarded = true;
				}
				$prev = $token;
				continue;
			}

			list( $id, $text, $line ) = $token;
			if ( T_WHITESPACE === $id ) {
				continue;
			}
			if ( T_COMMENT === $id ) {
				if ( 0 === strpos( $text, '# BEGIN GrabWP Tenancy' ) && ! $result['loader_line'] ) {
					$result['loader_line'] = $line;
				} elseif ( 0 === strpos( $text, GrabWP_Tenancy_Installer::PROXY_MARKER_START ) && ! $result['proxy_block'] ) {
					$result['proxy_block'] = $line;
				}
				continue;
			}
			if ( T_DOC_COMMENT === $id ) {
				continue;
			}
			if ( T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id ) {
				++$depth;
			} elseif ( in_array( $id, $guards, true ) ) {
				$guarded = true;
			} elseif ( T_CONSTANT_ENCAPSED_STRING === $id ) {
				$value = substr( $text, 1, -1 );
				if ( false !== strpos( $value, 'grabwp-tenancy/load.php' ) && ! $result['loader_line'] ) {
					$result['loader_line'] = $line;
				} elseif ( false !== strpos( $value, 'wp-settings.php' ) && ! $result['settings_line'] ) {
					$result['settings_line'] = $line;
				} elseif ( 'HTTP_X_FORWARDED_PROTO' === $value || 'HTTP_CF_VISITOR' === $value ) {
					$header = $header ? $header : $line;
				} elseif ( 'HTTPS' === $value && $header && '[' === $prev ) {
					$sets_on = true;
				}
				if ( $header && $sets_on && ! $result['proxy_line'] ) {
					$result['proxy_line'] = $header;
				}
			} elseif ( ( T_STRING === $id || ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $id ) )
				&& in_array( strtolower( $text ), $define, true )
				&& ! ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true ) ) ) {
				$name = self::define_name( $tokens, $i + 1 );
			} elseif ( T_VARIABLE === $id && in_array( $text, $names, true ) && '=' === self::next_code( $tokens, $i + 1 )[1] ) {
				$name = $text;
			}
			if ( '' !== $name && in_array( $name, $names, true ) ) {
				// A statement of its own: nothing but a statement end or the open tag before it.
				$starts = null === $prev || in_array( $prev, array( ';', '{', '}' ), true )
					|| ( is_array( $prev ) && in_array( $prev[0], array( T_OPEN_TAG, T_CLOSE_TAG ), true ) );
				$parts  = $starts ? self::define_parts( $tokens, $offsets, $i + 1, '$' === $name[0] ) : null;
				$result['defines'][] = array(
					'name'        => $name,
					'line'        => $line,
					'conditional' => $depth > 0 || $guarded,
					'code'        => isset( $lines[ $line - 1 ] ) ? trim( $lines[ $line - 1 ] ) : '',
					'statement'   => null !== $parts,
					'start'       => null !== $parts ? $offsets[ $i ] : 0,
					'end'         => null !== $parts ? $parts['end'] : 0,
					'value'       => null !== $parts ? $parts['value'] : '',
				);
			}
			$name = '';
			$prev = $token;
		}
		return $result;
	}

	/**
	 * Constant name of a define( 'NAME', ... ) call starting after the function name.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $i      Index after the function name.
	 * @return string '' when it is not a literal name.
	 */
	private static function define_name( $tokens, $i ) {
		$seen_paren = false;
		for ( $count = count( $tokens ); $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			if ( ! $seen_paren ) {
				if ( '(' !== $token ) {
					return '';
				}
				$seen_paren = true;
				continue;
			}
			return ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) ? substr( $token[1], 1, -1 ) : '';
		}
		return '';
	}

	/**
	 * Parts of a define( 'NAME', value ); statement starting after the function
	 * name, or of a $name = value; statement starting after the variable.
	 *
	 * @param array $tokens  Tokens.
	 * @param int[] $offsets Byte offset of each token.
	 * @param int   $i       Index after the function name or variable.
	 * @param bool  $assign  A variable assignment instead of define().
	 * @return array|null end (byte after the ;), value (code); null when it is
	 *                    not exactly define( name, value ) or $name = value followed by ;.
	 */
	private static function define_parts( $tokens, $offsets, $i, $assign = false ) {
		$depth = 0;
		$args  = $assign ? 1 : 0;
		$first = -1;
		$last  = -1;
		for ( $count = count( $tokens ); $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			$text  = is_array( $token ) ? $token[1] : $token;
			$blank = is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
			if ( 0 === $depth ) {
				if ( $blank ) {
					continue;
				}
				if ( ( $assign ? '=' : '(' ) !== $text ) {
					return null;
				}
				$depth = 1;
				continue;
			}
			if ( $assign && 1 === $depth && ';' === $text ) {
				--$i;
				break;
			}
			if ( in_array( $text, array( '(', '[', '{' ), true ) || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
			} elseif ( in_array( $text, array( ')', ']', '}' ), true ) ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $text && 1 === $depth ) {
				++$args;
				continue;
			}
			if ( 1 === $args && ! $blank ) {
				$first = -1 === $first ? $i : $first;
				$last  = $i;
			}
		}
		if ( $i >= $count || 1 !== $args || -1 === $first ) {
			return null;
		}
		// The ; right after the closing parenthesis or the value.
		list( $i, $text ) = self::next_code( $tokens, $i + 1 );
		return ';' === $text ? array(
			'end'   => $offsets[ $i + 1 ],
			'value' => self::token_text( $tokens, $first, $last ),
		) : null;
	}

	/**
	 * Next token that is not whitespace or a comment.
	 *
	 * @param array $tokens Tokens.
	 * @param int   $i      Index to start at.
	 * @return array Index and text; text '' at the end.
	 */
	private static function next_code( $tokens, $i ) {
		for ( $count = count( $tokens ); $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				return array( $i, $token );
			}
			if ( ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				return array( $i, $token[1] );
			}
		}
		return array( $i, '' );
	}

	/** Source text of tokens $from to $to. */
	private static function token_text( $tokens, $from, $to ) {
		$text = '';
		for ( $i = $from; $i <= $to; $i++ ) {
			$text .= is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ];
		}
		return $text;
	}

	/**
	 * Number of sites in the native Multisite table, 0 when there is none.
	 *
	 * @return int
	 */
	private static function count_network_sites() {
		global $wpdb;
		if ( empty( $wpdb ) ) {
			return 0;
		}
		$table    = $wpdb->base_prefix . 'blogs';
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) : 0;
		$wpdb->suppress_errors( $suppress );
		return $count;
	}

	/**
	 * Native Multisite: running, or still configured in wp-config.php, plus the
	 * other CONFLICT_CONSTANTS / CONFLICT_VARIABLES there (present at all,
	 * whatever the value).
	 *
	 * GrabWP runs only on regular WordPress. Nothing is removed here: the
	 * card lists the exact lines, and offers the confirmed removal of
	 * GrabWP_Tenancy_Installer::remove_multisite_settings() when it is safe.
	 * .htaccess is not read: Multisite rewrite rules there do not affect
	 * GrabWP, which routes tenants through its own "GrabWP Tenancy" block.
	 *
	 * @param string|null $config   wp-config.php content; null reads the file.
	 * @param bool|null   $runtime  Whether WordPress runs as Multisite; null asks is_multisite().
	 * @param int|null    $sites    Sites in the network table; null counts them.
	 * @param array|null  $defined  Multisite constants set to a non-empty value at runtime; null checks them.
	 * @return array {
	 *     @type string  $state    'ok', 'cleanup' (WP_ALLOW_MULTISITE only), 'residue' (conflicts in the file), 'active' or 'network' (more than one site).
	 *     @type bool    $ok
	 *     @type bool    $fixable  GrabWP_Tenancy_Installer::remove_multisite_settings() can remove them.
	 *     @type array[] $manual   Lines that must be removed by hand: file, line, code.
	 *     @type array[] $defines  Conflict lines in wp-config.php: name, line, conditional, code.
	 *     @type array   $external Constants defined outside wp-config.php.
	 *     @type int     $sites
	 *     @type string  $file     wp-config.php path.
	 * }
	 */
	public static function get_multisite_status( $config = null, $runtime = null, $sites = null, $defined = null ) {
		$file     = self::get_wp_config_file();
		$config  = null === $config ? self::read_text( $file ) : (string) $config;
		$runtime = null === $runtime ? is_multisite() : (bool) $runtime;
		if ( null === $defined ) {
			// Set to a value: core itself defines MULTISITE as false on every single site.
			$defined = array_values(
				array_filter(
					self::MULTISITE_CONSTANTS,
					function ( $name ) {
						return defined( $name ) && constant( $name );
					}
				)
			);
		}

		$defines  = self::scan_wp_config( $config )['defines'];
		$in_file  = array_unique( array_column( $defines, 'name' ) );
		$external = array_values( array_diff( (array) $defined, $in_file ) );
		$blockers = array_diff( array_merge( $in_file, $external ), array( 'WP_ALLOW_MULTISITE' ) );
		if ( null === $sites ) {
			$sites = ( $runtime || $blockers ) ? self::count_network_sites() : 0;
		}

		if ( $sites > 1 ) {
			$state = 'network';
		} elseif ( $runtime ) {
			$state = 'active';
		} elseif ( $blockers ) {
			$state = 'residue';
		} elseif ( $in_file || $external ) {
			$state = 'cleanup';
		} else {
			$state = 'ok';
		}

		// The confirmed "Remove these settings" fix: wp-config.php next to WordPress, main site, nothing left by hand.
		$fixable = false;
		$manual  = array();
		if ( ! in_array( $state, array( 'ok', 'network' ), true ) && ABSPATH . 'wp-config.php' === $file
			&& ! ( defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT ) ) {
			$plan    = GrabWP_Tenancy_Installer::plan_multisite_cleanup( $config );
			$manual  = $plan['manual'];
			$fixable = $plan['changed'] && ! $manual;
		}

		return array(
			'state'    => $state,
			'ok'       => in_array( $state, array( 'ok', 'cleanup' ), true ),
			'fixable'  => $fixable,
			'manual'   => $manual,
			'defines'  => $defines,
			'external' => $external,
			'sites'    => (int) $sites,
			'file'     => $file,
		);
	}

	/**
	 * Request a URL and tell a valid certificate from a missing or wrong one.
	 *
	 * The first request checks the certificate. Only when it fails is the URL
	 * asked again without the check, to tell "certificate not valid" from
	 * "not reachable".
	 *
	 * @param string $url HTTPS URL.
	 * @return array {
	 *     @type string $tls  'ok', 'cert' (answers, certificate not valid) or 'unreachable'.
	 *     @type int    $code HTTP status, 0 when unreachable.
	 *     @type string $body First bytes of the body.
	 * }
	 */
	public static function probe_tls( $url ) {
		$tls      = 'ok';
		$response = GrabWP_Tenancy_Tenant_Installer::probe_url( $url, true );
		if ( is_wp_error( $response ) ) {
			$tls      = 'cert';
			$response = GrabWP_Tenancy_Tenant_Installer::probe_url( $url, false );
		}
		if ( is_wp_error( $response ) ) {
			return array(
				'tls'  => 'unreachable',
				'code' => 0,
				'body' => '',
			);
		}
		return array(
			'tls'  => $tls,
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => substr( (string) wp_remote_retrieve_body( $response ), 0, 200 ),
		);
	}

	/**
	 * Whether home and siteurl use https, and whether they can be switched here.
	 *
	 * The switch is offered only when this site already answers on https with
	 * a valid certificate, this admin page was opened over https as WordPress
	 * sees it (so no redirect loop follows), the addresses are not set by
	 * WP_HOME / WP_SITEURL constants, and this is the main site.
	 *
	 * @param array|null $probe   Result of probe_tls() for the home address; null probes when needed.
	 * @param array|null $context home, siteurl, constant (bool), is_ssl (bool), tenant (bool); null reads WordPress.
	 * @return array {
	 *     @type string     $state   'ok', 'fixable', 'open_https', 'constant', 'cert' or 'no_https'.
	 *     @type bool       $ok
	 *     @type bool       $fixable
	 *     @type string     $home
	 *     @type string     $siteurl
	 *     @type array|null $probe
	 * }
	 */
	public static function get_https_url_status( $probe = null, $context = null ) {
		if ( null === $context ) {
			$context = array(
				'home'     => defined( 'WP_HOME' ) ? WP_HOME : (string) get_option( 'home' ),
				'siteurl'  => defined( 'WP_SITEURL' ) ? WP_SITEURL : (string) get_option( 'siteurl' ),
				'constant' => defined( 'WP_HOME' ) || defined( 'WP_SITEURL' ),
				'is_ssl'   => is_ssl(),
				'tenant'   => defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT,
			);
		}
		$home    = (string) $context['home'];
		$siteurl = (string) $context['siteurl'];
		$result  = array(
			'state'   => 'ok',
			'ok'      => true,
			'fixable' => false,
			'home'    => $home,
			'siteurl' => $siteurl,
			'probe'   => null,
		);
		if ( 0 === stripos( $home, 'https://' ) && 0 === stripos( $siteurl, 'https://' ) ) {
			return $result;
		}

		if ( null === $probe ) {
			$host  = (string) wp_parse_url( $home, PHP_URL_HOST );
			$probe = '' === $host ? array(
				'tls'  => 'unreachable',
				'code' => 0,
				'body' => '',
			) : self::probe_tls( 'https://' . $host . '/wp-login.php' );
		}
		$result['probe'] = $probe;
		$result['ok']    = false;
		$reachable       = $probe['code'] > 0 && $probe['code'] < 500;

		if ( ! empty( $context['constant'] ) ) {
			$result['state'] = 'constant';
		} elseif ( 'cert' === $probe['tls'] ) {
			$result['state'] = 'cert';
		} elseif ( 'ok' !== $probe['tls'] || ! $reachable ) {
			$result['state'] = 'no_https';
		} elseif ( empty( $context['is_ssl'] ) ) {
			$result['state'] = 'open_https';
		} else {
			$result['state']   = 'fixable';
			$result['fixable'] = empty( $context['tenant'] );
		}
		return $result;
	}

	/**
	 * Whether WordPress learns from the proxy or CDN that a visit uses HTTPS.
	 *
	 * Only a browser request through the proxy carries its headers, so WP-CLI,
	 * cron and direct visits report 'no_proxy'.
	 *
	 * @param array|null  $server Server variables; null reads $_SERVER.
	 * @param string|null $config wp-config.php content; null reads the file.
	 * @return array {
	 *     @type string $state   'ok', 'no_proxy', 'missing', 'ineffective' (code exists, HTTPS still off)
	 *                           or 'after_loader' (code runs after the GrabWP loader).
	 *     @type bool   $ok
	 *     @type bool   $fixable The GrabWP HTTPS proxy block can be added.
	 *     @type int    $line    Line of the existing HTTPS code, 0 when none.
	 *     @type bool   $ours    The GrabWP HTTPS proxy block is in wp-config.php (Undo).
	 * }
	 */
	public static function get_proxy_https_status( $server = null, $config = null ) {
		$server = self::server_https_vars( $server );
		$config = null === $config ? self::read_text( self::get_wp_config_file() ) : (string) $config;
		$scan   = self::scan_wp_config( $config );
		$line   = $scan['proxy_line'];
		$ours   = $scan['proxy_block'] > 0;

		if ( ! self::is_forwarded_https( $server ) ) {
			$state = 'no_proxy';
		} elseif ( self::is_proxy_https_undetected( $server ) ) {
			$state = $line ? 'ineffective' : 'missing';
		} elseif ( $line && $scan['loader_line'] && $line > $scan['loader_line'] ) {
			$state = 'after_loader';
		} else {
			$state = 'ok';
		}

		$ok = in_array( $state, array( 'ok', 'no_proxy' ), true );
		return array(
			'state'   => $state,
			'ok'      => $ok,
			// The writer only edits the wp-config.php next to WordPress, which the watchdog protects.
			'fixable' => ! $ok && ! $ours && ABSPATH . 'wp-config.php' === self::get_wp_config_file()
				&& ! ( defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT ),
			'line'    => $line,
			'ours'    => $ours,
		);
	}

	/**
	 * Whether new sites at *.domain can work: wildcard DNS, a route to this
	 * GrabWP install, and a valid certificate.
	 *
	 * Asks a random subdomain for the early ?grabwp-verify-domain=1 answer,
	 * which only a GrabWP install gives. Writes nothing.
	 *
	 * @param bool          $needed   Whether sites may use subdomains (routing is not path only).
	 * @param string        $domain   Domain whose subdomains new sites use.
	 * @param callable|null $probe    URL => probe_tls() result; null uses probe_tls().
	 * @param callable|null $resolver Host => whether it resolves; null asks DNS.
	 * @return array {
	 *     @type string $state  'not_needed', 'no_domain', 'dns', 'unreachable', 'not_ours', 'cert' or 'ok'.
	 *     @type bool   $ok
	 *     @type string $domain
	 *     @type string $host   Random host that was checked.
	 *     @type bool   $dns
	 *     @type bool   $route
	 *     @type string $tls    'ok', 'cert', 'unreachable' or ''.
	 * }
	 */
	public static function get_wildcard_status( $needed, $domain = '', $probe = null, $resolver = null ) {
		$domain = strtolower( trim( (string) $domain, ". \t\n\r\0\x0B" ) );
		$result = array(
			'state'  => 'not_needed',
			'ok'     => true,
			'domain' => $domain,
			'host'   => '',
			'dns'    => false,
			'route'  => false,
			'tls'    => '',
		);
		if ( ! $needed ) {
			return $result;
		}
		$result['ok'] = false;
		if ( false === strpos( $domain, '.' ) || filter_var( $domain, FILTER_VALIDATE_IP ) ) {
			$result['state'] = 'no_domain';
			return $result;
		}

		$result['host'] = 'grabwp-check-' . bin2hex( random_bytes( 5 ) ) . '.' . $domain;
		$resolver       = $resolver ? $resolver : array( __CLASS__, 'resolves' );
		$result['dns']  = (bool) call_user_func( $resolver, $result['host'] );
		if ( ! $result['dns'] ) {
			$result['state'] = 'dns';
			return $result;
		}

		$probe           = $probe ? $probe : array( __CLASS__, 'probe_tls' );
		$answer          = call_user_func( $probe, 'https://' . $result['host'] . '/?grabwp-verify-domain=1' );
		$result['tls']   = $answer['tls'];
		$result['route'] = 0 === strpos( ltrim( (string) $answer['body'] ), 'grabwp-verify:' );
		if ( 'unreachable' === $answer['tls'] ) {
			$result['state'] = 'unreachable';
		} elseif ( ! $result['route'] ) {
			$result['state'] = 'not_ours';
		} elseif ( 'cert' === $answer['tls'] ) {
			$result['state'] = 'cert';
		} else {
			$result['state'] = 'ok';
			$result['ok']    = true;
		}
		return $result;
	}

	/**
	 * Whether a host name resolves in DNS.
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	public static function resolves( $host ) {
		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_A | DNS_AAAA | DNS_CNAME ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! empty( $records ) ) {
				return true;
			}
		}
		return gethostbyname( $host ) !== $host;
	}

	/**
	 * Whether the xSpeed object cache drop-in (used on xCloud) is installed.
	 * Only decides whether the xCloud help starts open; it never claims the host.
	 *
	 * @return bool
	 */
	private static function has_xspeed_dropin() {
		$file = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) . '/object-cache.php';
		if ( ! is_readable( $file ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		return false !== strpos( (string) file_get_contents( $file, false, null, 0, 4096 ), 'XSPEED_OBJECT_CACHE_DROPIN' );
	}

	/**
	 * Data of the Hosting setup card (setup wizard Check screen and Status page).
	 *
	 * The outbound probes (https address, wildcard) are kept for 10 minutes;
	 * $refresh runs them again. Multisite and proxy checks are always fresh.
	 * With $store false the cache is only read: missing or stale probes run
	 * and are not kept (read-only diagnostics).
	 *
	 * @param bool $refresh Run the outbound probes again.
	 * @param bool $store   Keep fresh probe results in the cache.
	 * @return array {
	 *     @type array $rows    multisite, https_urls, proxy, wildcard.
	 *     @type int   $issues  Rows that need attention.
	 *     @type int   $fixable Rows with a fix button.
	 *     @type bool  $xspeed  The xSpeed drop-in is installed.
	 *     @type bool  $https_undo  An https switch can be undone.
	 *     @type bool  $multisite_undo The Multisite cleanup can be undone.
	 * }
	 */
	public static function get_hosting_card_data( $refresh = false, $store = true ) {
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$needed    = 'path' !== GrabWP_Tenancy_Routing::get_mode();
		/**
		 * Domain whose subdomains new sites use, for the wildcard check.
		 *
		 * @since 1.1.14
		 * @param string $domain Default: the host of the home address.
		 */
		$domain = $needed ? (string) apply_filters( 'grabwp_tenancy_wildcard_domain', $home_host ) : '';
		$key    = md5( $home_host . '|' . $domain . '|' . (int) $needed . '|' . home_url() . '|' . site_url() );

		$cache = $refresh ? false : get_transient( self::HOSTING_TRANSIENT );
		if ( ! is_array( $cache ) || ! isset( $cache['key'] ) || $cache['key'] !== $key ) {
			$https = self::get_https_url_status();
			$cache = array(
				'key'         => $key,
				'https_probe' => $https['probe'],
				'wildcard'    => self::get_wildcard_status( $needed, $domain ),
			);
			if ( $store ) {
				set_transient( self::HOSTING_TRANSIENT, $cache, 10 * MINUTE_IN_SECONDS );
			}
		} else {
			$https = self::get_https_url_status( $cache['https_probe'] );
		}

		$rows    = array(
			'multisite'  => self::get_multisite_status(),
			'https_urls' => $https,
			'proxy'      => self::get_proxy_https_status(),
			'wildcard'   => $cache['wildcard'],
		);
		$issues  = 0;
		$fixable = 0;
		foreach ( $rows as $row ) {
			$issues  += $row['ok'] ? 0 : 1;
			$fixable += empty( $row['fixable'] ) ? 0 : 1;
		}

		return array(
			'rows'           => $rows,
			'issues'         => $issues,
			'fixable'        => $fixable,
			'xspeed'         => self::has_xspeed_dropin(),
			'https_undo'     => is_array( get_option( self::HTTPS_URL_UNDO_OPTION ) ),
			'multisite_undo' => is_array( get_option( GrabWP_Tenancy_Installer::MULTISITE_UNDO_OPTION ) ),
		);
	}
}
