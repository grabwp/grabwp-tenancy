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
		grabwp_tenancy_refresh_mappings_for_loopback( $mappings_file );
		$tenant_mappings        = array();
		$grabwp_tenancy_routing = null;
		include $mappings_file;
		grabwp_tenancy_routing_config( is_array( $grabwp_tenancy_routing ) ? $grabwp_tenancy_routing : array() );
		return $tenant_mappings;
	}

	grabwp_tenancy_routing_config( array() );
	$tenant_mappings = array();
	return $tenant_mappings;
}

/**
 * Drop a stale OPcache copy of tenants.php before an installer loopback reads it.
 *
 * WP-CLI (or another PHP pool) that writes tenants.php only clears its own
 * OPcache. The web server can keep the old copy for opcache.revalidate_freq
 * seconds, and a new tenant's path then falls through to the main site.
 *
 * Without force, opcache_invalidate() drops the copy only when the file
 * changed, so a repeated ?grabwp-loopback costs one stat. Skipped when OPcache
 * does not validate timestamps (every call would then drop the copy) or when
 * opcache.restrict_api does not allow this script.
 *
 * @param string $mappings_file Path of tenants.php.
 */
function grabwp_tenancy_refresh_mappings_for_loopback( $mappings_file ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['grabwp-loopback'] ) || ! function_exists( 'opcache_invalidate' ) ) {
		return;
	}
	if ( ! filter_var( ini_get( 'opcache.validate_timestamps' ), FILTER_VALIDATE_BOOLEAN ) ) {
		return;
	}
	$restrict = (string) ini_get( 'opcache.restrict_api' );
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? (string) $_SERVER['SCRIPT_FILENAME'] : '';
	if ( '' !== $restrict && 0 !== strpos( $script, $restrict ) ) {
		return;
	}

	opcache_invalidate( $mappings_file, false );
}

/**
 * Routing block of tenants.php ($grabwp_tenancy_routing), cached per request.
 *
 * The mappings loader stores it while it includes tenants.php, so reading the
 * mode costs no extra I/O. Without a stored value the loader is called first.
 *
 * @param array|null $set Array to store, or null to read.
 * @return array Key 'mode' when stored.
 */
function grabwp_tenancy_routing_config( $set = null ) {
	static $config = null;

	if ( null !== $set ) {
		$config = is_array( $set ) ? $set : array();
		return $config;
	}

	if ( null === $config ) {
		grabwp_tenancy_load_tenant_mappings();
	}

	return $config;
}

/**
 * Valid routing modes.
 *
 * @return string[]
 */
function grabwp_tenancy_routing_modes() {
	return array( 'path', 'domain', 'both' );
}

/**
 * Site-wide routing mode: 'path', 'domain' or 'both'.
 *
 * This is the site policy. It differs from GRABWP_TENANCY_ROUTING_METHOD,
 * which says how the current request matched a tenant.
 *
 * A valid GRABWP_TENANCY_ROUTING_MODE constant (set in wp-config.php) wins.
 * Otherwise the mode stored in tenants.php applies. No stored mode means
 * 'both', which is how GrabWP routed before routing modes existed.
 *
 * @return string
 */
function grabwp_tenancy_get_routing_mode() {
	$modes = grabwp_tenancy_routing_modes();

	if ( defined( 'GRABWP_TENANCY_ROUTING_MODE' ) && in_array( GRABWP_TENANCY_ROUTING_MODE, $modes, true ) ) {
		return GRABWP_TENANCY_ROUTING_MODE;
	}

	$config = grabwp_tenancy_routing_config();
	if ( isset( $config['mode'] ) && in_array( $config['mode'], $modes, true ) ) {
		return $config['mode'];
	}

	return 'both';
}

/**
 * First real (non-placeholder) domain of a tenant mapping.
 *
 * Mapping arrays may also hold a 'protocol' string key, which is skipped.
 *
 * @param array  $tenant_mappings Tenant mappings.
 * @param string $tenant_id       Tenant ID.
 * @return string Domain, or '' when the tenant has none.
 */
function grabwp_tenancy_first_real_domain( $tenant_mappings, $tenant_id ) {
	if ( ! isset( $tenant_mappings[ $tenant_id ] ) || ! is_array( $tenant_mappings[ $tenant_id ] ) ) {
		return '';
	}

	foreach ( $tenant_mappings[ $tenant_id ] as $key => $domain ) {
		if ( is_int( $key ) && is_string( $domain ) && '' !== $domain && 'nodomain.local' !== $domain ) {
			return $domain;
		}
	}

	return '';
}

/**
 * Domain mode redirect target for a path or query request, or ''.
 *
 * Only a GET or HEAD request in domain mode, for a tenant that has a domain,
 * gets a target. The verify probe, installer loopbacks, cron and CLI never do.
 *
 * @param string $tenant_id        Matched tenant ID.
 * @param string $matched_path     URL path prefix that matched, e.g. '/site/abc123'; '' for the query route.
 * @param bool   $strip_site_param Drop the site= pair from the query (query route).
 * @return string
 */
function grabwp_tenancy_domain_redirect_target( $tenant_id, $matched_path, $strip_site_param = false ) {
	if ( 'domain' !== grabwp_tenancy_get_routing_mode() ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['grabwp-verify-domain'] ) || isset( $_GET['grabwp-loopback'] ) ) {
		return '';
	}

	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return '';
	}

	$domain = grabwp_tenancy_first_real_domain( grabwp_tenancy_load_tenant_mappings(), $tenant_id );
	if ( '' === $domain ) {
		return '';
	}

	$server_info = grabwp_tenancy_get_server_info();
	if ( strtolower( $domain ) === strtolower( (string) $server_info['host'] ) ) {
		return '';
	}

	// Raw REQUEST_URI only: behind the Apache rule QUERY_STRING also carries the rewritten site= value.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$qpos  = strpos( $uri, '?' );
	$path  = false === $qpos ? $uri : substr( $uri, 0, $qpos );
	$query = false === $qpos ? '' : substr( $uri, $qpos + 1 );

	if ( '' !== $matched_path ) {
		if ( 0 !== strpos( $path, $matched_path ) ) {
			return '';
		}
		$path = (string) substr( $path, strlen( $matched_path ) );
	}

	if ( '' === $path || '/' !== $path[0] ) {
		$path = '/' . $path;
	}

	if ( $strip_site_param && '' !== $query ) {
		$pairs = array();
		foreach ( explode( '&', $query ) as $pair ) {
			if ( '' === $pair || 'site' === $pair || 0 === strpos( $pair, 'site=' ) ) {
				continue;
			}
			$pairs[] = $pair;
		}
		$query = implode( '&', $pairs );
	}

	return $server_info['protocol'] . '://' . $domain . $path . ( '' !== $query ? '?' . $query : '' );
}

/**
 * Send the domain mode 301 for a path or query request when it applies.
 *
 * Returns without output when no redirect applies; the request is then
 * served exactly as before routing modes existed.
 *
 * @param string $tenant_id        Matched tenant ID.
 * @param string $matched_path     URL path prefix that matched; '' for the query route.
 * @param bool   $strip_site_param Drop the site= pair from the query.
 */
function grabwp_tenancy_maybe_redirect_to_domain( $tenant_id, $matched_path, $strip_site_param = false ) {
	$target = grabwp_tenancy_domain_redirect_target( $tenant_id, $matched_path, $strip_site_param );
	if ( '' === $target ) {
		return;
	}

	header( 'X-Redirect-By: GrabWP Tenancy' );
	// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	header( 'Location: ' . $target, true, 301 );
	exit;
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
 * Get the path routing prefix slug (e.g. 'site', 'u', 'web').
 *
 * Pro plugin defines GRABWP_TENANCY_PATH_PREFIX via config; base falls back to 'site'.
 *
 * @since 1.1.4
 * @return string Prefix slug without slashes.
 */
function grabwp_tenancy_get_path_prefix() {
	return defined( 'GRABWP_TENANCY_PATH_PREFIX' ) ? GRABWP_TENANCY_PATH_PREFIX : 'site';
}

/**
 * Identify tenant from URL path (/{prefix}/[tenant-id]).
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

	$prefix = grabwp_tenancy_get_path_prefix();
	if ( ! preg_match( '#^/' . preg_quote( $prefix, '#' ) . '/([a-z0-9]{6})(/|$)#', $uri, $matches ) ) {
		return false;
	}

	$tenant_id       = $matches[1];
	$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
	if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
		return false;
	}

	grabwp_tenancy_maybe_redirect_to_domain( $tenant_id, '/' . $prefix . '/' . $tenant_id );

	if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'GRABWP_TENANCY_TENANT_ID', $tenant_id );
	}

	if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
		define( 'GRABWP_TENANCY_ROUTING_METHOD', 'path' );
	}

	return $tenant_id;
}

/**
 * Get the slug currently used in the URL path for the active tenant.
 *
 * Returns the alias when GRABWP_TENANCY_TENANT_ALIAS is defined (Pro alias
 * routing matched), otherwise falls back to the tenant ID.
 *
 * @since 1.1.4
 * @return string Path slug (alias or tenant ID), or empty string if no tenant context.
 */
function grabwp_tenancy_get_tenant_path_slug() {
	if ( defined( 'GRABWP_TENANCY_TENANT_ALIAS' ) && '' !== GRABWP_TENANCY_TENANT_ALIAS ) {
		return GRABWP_TENANCY_TENANT_ALIAS;
	}

	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		return GRABWP_TENANCY_TENANT_ID;
	}

	return '';
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

	grabwp_tenancy_maybe_redirect_to_domain( $tenant_id, '', true );

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
 * Derive protocol for a tenant in CLI context.
 *
 * @param string $tenant_id       Tenant identifier.
 * @param array  $tenant_mappings Tenant mappings from tenants.php.
 * @return string 'https' or 'http'.
 */
function grabwp_tenancy_get_cli_tenant_protocol( $tenant_id, $tenant_mappings ) {
	if ( isset( $tenant_mappings[ $tenant_id ]['protocol'] ) ) {
		return $tenant_mappings[ $tenant_id ]['protocol'];
	}
	return 'https';
}

/**
 * Detect tenant from CLI, domain mapping, URL path, or query string.
 * Priority: CLI > domain > path > query.
 */
function grabwp_tenancy_boot_detect_tenant() {
	// Env var available before wp-config.php loads — enables CLI tenant boot.
	if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) && getenv( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'GRABWP_TENANCY_TENANT_ID', getenv( 'GRABWP_TENANCY_TENANT_ID' ) );
	}

	if ( defined( 'GRABWP_TENANCY_TENANT_ID' ) && GRABWP_TENANCY_TENANT_ID !== '' ) {
		$tenant_id = GRABWP_TENANCY_TENANT_ID;

		if ( ! defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) ) {
			define( 'GRABWP_TENANCY_ROUTING_METHOD', 'cli' );
		}

		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			$tenant_mappings = grabwp_tenancy_load_tenant_mappings();
			$is_mapped       = isset( $tenant_mappings[ $tenant_id ] ) && ! empty( $tenant_mappings[ $tenant_id ][0] );

			if ( ! $is_mapped && defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
				WP_CLI::warning( "Tenant '{$tenant_id}' not found in tenant mappings. Using fallback domain." );
			}

			$cli_domain           = grabwp_tenancy_get_cli_domain( $tenant_id, $tenant_mappings );
			$_SERVER['HTTP_HOST'] = $cli_domain;

			if ( ! isset( $_SERVER['HTTPS'] ) ) {
				$tenant_protocol  = grabwp_tenancy_get_cli_tenant_protocol( $tenant_id, $tenant_mappings );
				$_SERVER['HTTPS'] = ( $tenant_protocol === 'https' ) ? 'on' : '';
			}

			grabwp_tenancy_reset_server_info_cache();
		}

		grabwp_tenancy_configure_cli_environment();
		return $tenant_id;
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
