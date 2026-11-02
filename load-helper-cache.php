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
 * Set WP_CACHE_KEY_SALT for object cache isolation, and decide whether the
 * tenant may use the main site's object-cache.php drop-in.
 *
 * @since 1.2.0
 */
function grabwp_tenancy_boot_set_cache_key_salt() {
	if ( ! defined( 'WP_CACHE_KEY_SALT' ) && defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
		define( 'WP_CACHE_KEY_SALT', 'tenant_' . GRABWP_TENANCY_TENANT_ID . '_' );
	}

	// Pro dedicated-MySQL tenants and callers after WordPress has loaded
	// already have plugin.php, so hooks are WP_Hook objects there.
	if ( class_exists( 'WP_Hook', false ) && function_exists( 'add_filter' ) ) {
		add_filter( 'enable_loading_object_cache_dropin', 'grabwp_tenancy_filter_object_cache_dropin' );
		return;
	}

	// plugin.php is not loaded yet: register through the preinitialized hooks array.
	$GLOBALS['wp_filter']['enable_loading_object_cache_dropin'][10][] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Only way to hook before plugin.php loads.
		'function'      => 'grabwp_tenancy_filter_object_cache_dropin',
		'accepted_args' => 1,
	);
}

/**
 * Turn a hook registered from wp-config.php as a plain array into a WP_Hook.
 *
 * WordPress converts such entries when plugin.php loads. Pro dedicated-MySQL
 * tenants load plugin.php inside the GrabWP wp-config.php block, so a plain
 * array entry added below that block stays an array, and apply_filters()
 * would fatal on it. An entry that cannot be converted is dropped.
 *
 * @param string $tag Hook name.
 */
function grabwp_tenancy_normalize_early_hook( $tag ) {
	global $wp_filter;
	if ( ! isset( $wp_filter[ $tag ] ) || ! class_exists( 'WP_Hook', false ) || $wp_filter[ $tag ] instanceof WP_Hook ) {
		return;
	}

	$hooks = array();
	if ( is_array( $wp_filter[ $tag ] ) ) {
		try {
			$hooks = WP_Hook::build_preinitialized_hooks( array( $tag => $wp_filter[ $tag ] ) );
		} catch ( Throwable $e ) {
			$hooks = array();
		}
	}

	if ( isset( $hooks[ $tag ] ) ) {
		$wp_filter[ $tag ] = $hooks[ $tag ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same callbacks, as a WP_Hook.
	} else {
		unset( $wp_filter[ $tag ] );
	}
}

/**
 * Keep the shared object-cache.php drop-in on a tenant only when its keys are
 * known to be separate from the main site and from other tenants.
 *
 * Some drop-ins (for example xSpeed on xCloud) build keys from a fixed salt
 * in wp-config.php only, ignoring WP_CACHE_KEY_SALT and the table prefix.
 * A tenant would then read the main site's cached options and users: it runs
 * the main site's plugins, its install is skipped and auto-login finds no
 * administrator. Unknown drop-ins are therefore skipped. Operators can
 * override with the `grabwp_tenancy_tenant_object_cache_dropin` filter,
 * registered from wp-config.php. A drop-in in the tenant's own wp-content is
 * always loaded.
 *
 * @param bool $enable Whether to load object-cache.php.
 * @return bool
 */
function grabwp_tenancy_filter_object_cache_dropin( $enable ) {
	$file   = WP_CONTENT_DIR . '/object-cache.php';
	$shared = ABSPATH . ( defined( 'GRABWP_WORDPRESS_CONTENT_DIR' ) ? GRABWP_WORDPRESS_CONTENT_DIR : 'wp-content' ) . '/object-cache.php';
	if ( ! $enable || ! is_file( $file ) || realpath( $file ) !== realpath( $shared ) ) {
		return $enable;
	}

	$kind = grabwp_tenancy_object_cache_dropin_kind( $file );
	grabwp_tenancy_normalize_early_hook( 'grabwp_tenancy_tenant_object_cache_dropin' );
	$keep = (bool) apply_filters( 'grabwp_tenancy_tenant_object_cache_dropin', '' !== $kind, $file );

	// Declared before wp-settings.php includes the drop-in, which only
	// defines its own xspeed_oc_salt() when none exists yet.
	if ( $keep && 'xspeed' === $kind && ! function_exists( 'xspeed_oc_salt' ) ) {
		/**
		 * Salt xSpeed uses for every cache key, scoped to this tenant.
		 *
		 * @return string
		 */
		function xspeed_oc_salt() {
			return grabwp_tenancy_xspeed_tenant_salt();
		}
	}
	return $keep;
}

/**
 * Identify a shared drop-in whose keys stay separate per tenant.
 *
 * @param string $file Path to object-cache.php.
 * @return string 'xspeed', 'redis-cache', or '' when separation is not shown.
 */
function grabwp_tenancy_object_cache_dropin_kind( $file ) {
	$head = (string) file_get_contents( $file, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read before WordPress loads.

	// xSpeed: only when its salt function is guarded, or defining ours would
	// fatal, and when the salt really comes from it, or ours would be ignored.
	if ( false !== strpos( $head, 'XSPEED_OBJECT_CACHE_DROPIN' ) ) {
		$src = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read before WordPress loads.
		$guarded = preg_match( '/!\s*function_exists\(\s*[\'"]xspeed_oc_salt[\'"]\s*\)/', $src );
		$used    = preg_match( '/\$this->salt\s*=\s*xspeed_oc_salt\(\s*\)/', $src );
		return $guarded && $used ? 'xspeed' : '';
	}

	// Redis Object Cache keys single-site entries as {WP_REDIS_PREFIX}{table
	// prefix}:{group}:{key}, so the tenant's own `{id}_` prefix keeps them
	// apart even when the host defines one WP_REDIS_PREFIX for the install.
	// Pro tenants with a custom table prefix may share one, so they are skipped.
	if ( false !== strpos( $head, 'Plugin Name: Redis Object Cache Drop-In' ) ) {
		return ! is_multisite() && GRABWP_TENANCY_TENANT_ID . '_' === $GLOBALS['table_prefix'] ? 'redis-cache' : '';
	}

	return '';
}

/**
 * Tenant salt for the xSpeed drop-in: `{host salt}:tenant-{id}`.
 *
 * The xSpeed keys are `{salt}:{blog id}:{group}:{key}` (or `{salt}.g{n}:...`
 * after a Memcached flush), with a numeric blog id. Nesting the tenant under
 * the host salt keeps its writes inside the host's Redis ACL namespace
 * (`{salt}:*` on xCloud), while the `tenant-` segment can never equal the
 * main site's numeric one. Tenant ids are 6 characters of [a-z0-9], so
 * tenants never collide with each other. Called lazily from wp_cache_init(),
 * after wp-config.php has defined every constant.
 *
 * @return string
 */
function grabwp_tenancy_xspeed_tenant_salt() {
	$own  = 'tenant_' . GRABWP_TENANCY_TENANT_ID . '_';
	$host = function_exists( 'xspeed_oc_config' ) ? (string) xspeed_oc_config( 'salt', '' ) : '';

	// GrabWP's own WP_CACHE_KEY_SALT answered, so the host defined no salt
	// constant: the main site then uses the sidecar salt, if any.
	if ( $own === $host ) {
		$sidecar = function_exists( 'xspeed_oc_sidecar' ) ? xspeed_oc_sidecar() : array();
		$host    = isset( $sidecar['key_prefix'] ) ? (string) $sidecar['key_prefix'] : '';
	}

	// Without a host salt the main site derives `xs{hash}`, which never starts with "tenant-".
	return ( '' !== $host ? $host . ':' : '' ) . 'tenant-' . GRABWP_TENANCY_TENANT_ID;
}
