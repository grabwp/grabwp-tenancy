<?php
/**
 * GrabWP Tenancy Tenant Installer
 *
 * Installs WordPress inside a new tenant by calling core's own installer
 * (wp-admin/install.php?step=2) over an HTTP loopback to the tenant's path URL.
 * The tenant request boots the tenant's own database connection, so core does
 * all the work. Also creates a ready-to-use site in one call (create_site()).
 *
 * @package GrabWP_Tenancy
 * @since 1.1.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Tenant_Installer {

	/**
	 * Path URL of a tenant by ID (works without DNS), with trailing slash.
	 * GrabWP_Tenancy_Tenant::get_path_url() is alias-aware and has no trailing slash.
	 *
	 * The path URL is used even when a domain is mapped, because the domain
	 * may not resolve from this server yet.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return string
	 */
	public static function get_path_url( $tenant_id ) {
		return trailingslashit( site_url( '/' . grabwp_tenancy_get_path_prefix() . '/' . $tenant_id ) );
	}

	/**
	 * Path URL of a tenant page for a request from this server or the installer.
	 *
	 * Carries grabwp-loopback=1, so the domain mode redirect does not send it
	 * to a domain that may not resolve yet. The flag only skips that redirect.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @param string $path      Path below the tenant root, e.g. 'wp-login.php'.
	 * @return string
	 */
	public static function loopback_url( $tenant_id, $path ) {
		return add_query_arg( 'grabwp-loopback', '1', self::get_path_url( $tenant_id ) . ltrim( $path, '/' ) );
	}

	/**
	 * Arguments for loopback requests (filterable for hosts that need e.g. basic auth).
	 *
	 * @param string $context One of 'loopback', 'status', 'install'.
	 * @param array  $args    Extra arguments.
	 * @return array
	 */
	private static function request_args( $context, $args = array() ) {
		$args = array_merge(
			array(
				'timeout'     => 'install' === $context ? 60 : 15,
				'redirection' => 0,
				// Same convention as core loopback checks (Site Health, cron).
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
				// Ask page caches (FastCGI, proxy, CDN) for a live answer. The
				// cookie name is the one common WordPress cache rules skip on.
				'headers'     => array(
					'Cache-Control' => 'no-cache',
					'Pragma'        => 'no-cache',
				),
				'cookies'     => array( 'wordpress_no_cache' => '1' ),
			),
			$args
		);

		/**
		 * Filter the HTTP request arguments used to reach the site itself.
		 *
		 * @since 1.1.12
		 * @param array  $args    wp_remote_request() arguments.
		 * @param string $context 'loopback', 'status' or 'install'.
		 */
		return apply_filters( 'grabwp_tenancy_tenant_install_request_args', $args, $context );
	}

	/**
	 * URL with a unique query arg, so no page cache can answer for it.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function no_cache_url( $url ) {
		return add_query_arg( 'grabwp-nocache', wp_rand(), $url );
	}

	/**
	 * Whether the main site can reach itself over HTTP.
	 *
	 * @return bool
	 */
	public static function can_loopback() {
		$response = wp_remote_get( self::no_cache_url( home_url( '/wp-login.php' ) ), self::request_args( 'loopback' ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code > 0 && $code < 500;
	}

	/**
	 * Whether WordPress is installed in the tenant.
	 *
	 * An uninstalled tenant redirects every request to wp-admin/install.php.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return bool
	 */
	public static function is_installed( $tenant_id ) {
		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return false;
		}

		$response = wp_remote_get( self::no_cache_url( self::loopback_url( $tenant_id, 'wp-login.php' ) ), self::request_args( 'status' ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$location = (string) wp_remote_retrieve_header( $response, 'location' );
		if ( $code >= 300 && $code < 400 && false !== strpos( $location, 'install.php' ) ) {
			return false;
		}

		return $code >= 200 && $code < 400;
	}

	/**
	 * Install WordPress in a tenant with an administrator named after the tenant ID.
	 *
	 * The password is random, returned to the caller once and never stored.
	 * A failure never deletes the tenant.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @param array  $args {
	 *     @type string $title       Site title.
	 *     @type string $admin_email Administrator email.
	 * }
	 * @return array { ok: bool, password: string, error: string, already_installed: bool }
	 */
	public static function install( $tenant_id, $args = array() ) {
		$result = array(
			'ok'                => false,
			'password'          => '',
			'error'             => '',
			'already_installed' => false,
		);

		if ( ! current_user_can( 'manage_options' ) ) {
			$result['error'] = __( 'Insufficient permissions.', 'grabwp-tenancy' );
			return $result;
		}
		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			$result['error'] = __( 'Invalid tenant ID.', 'grabwp-tenancy' );
			return $result;
		}

		if ( self::is_installed( $tenant_id ) ) {
			$result['ok']                = true;
			$result['already_installed'] = true;
			return $result;
		}

		$args = wp_parse_args(
			$args,
			array(
				'title'       => $tenant_id,
				'admin_email' => wp_get_current_user()->user_email,
			)
		);

		// Use the main site's language only when its core pack is installed.
		$locale   = get_locale();
		$language = ( 'en_US' !== $locale && in_array( $locale, get_available_languages(), true ) ) ? $locale : '';
		$password = wp_generate_password( 24, true, false );

		$response = wp_remote_post(
			self::loopback_url( $tenant_id, 'wp-admin/install.php?step=2' ),
			self::request_args(
				'install',
				array(
					'body' => array(
						'weblog_title'    => $args['title'],
						'user_name'       => $tenant_id,
						'admin_password'  => $password,
						'admin_password2' => $password,
						'pw_weak'         => '1',
						'admin_email'     => $args['admin_email'],
						'blog_public'     => '1',
						'language'        => $language,
					),
				)
			)
		);

		// Trust the tenant state, not the response text.
		if ( self::is_installed( $tenant_id ) ) {
			GrabWP_Tenancy_Logger::log( 'GrabWP Tenancy: install ok for tenant ' . $tenant_id );
			$result['ok']       = true;
			$result['password'] = $password;
			return $result;
		}

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
		} else {
			$result['error'] = sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The site answered with HTTP %d.', 'grabwp-tenancy' ),
				(int) wp_remote_retrieve_response_code( $response )
			);
		}
		GrabWP_Tenancy_Logger::log( 'GrabWP Tenancy: install failed for tenant ' . $tenant_id . ': ' . $result['error'] );

		return $result;
	}

	/**
	 * Create a tenant and optionally install WordPress in it.
	 *
	 * Uses the same create path as Add New and WaaS provisioning. With Pro
	 * active, the tenant gets the global defaults (database type, isolation).
	 *
	 * @param array $args {
	 *     @type array  $domains     Domains; empty for a path URL site.
	 *     @type string $title       Site title.
	 *     @type string $admin_email Administrator email.
	 *     @type bool   $install     Install WordPress now.
	 * }
	 * @return array { ok: bool, tenant_id: string, password: string, error: string, install_error: string }
	 */
	public static function create_site( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'domains'     => array(),
				'title'       => '',
				'admin_email' => '',
				'install'     => true,
			)
		);

		$out = array(
			'ok'            => false,
			'tenant_id'     => '',
			'password'      => '',
			'error'         => '',
			'install_error' => '',
		);

		$crud   = new GrabWP_Tenancy_Tenant_Crud( grabwp_tenancy() );
		$result = $crud->create( (array) $args['domains'] );
		if ( 'success' !== $result['type'] || empty( $result['tenant_id'] ) ) {
			$out['error'] = $result['message'];
			return $out;
		}

		$tenant_id        = $result['tenant_id'];
		$out['ok']        = true;
		$out['tenant_id'] = $tenant_id;

		if ( class_exists( 'GrabWP_Tenancy_Pro_Config' ) ) {
			$pro      = GrabWP_Tenancy_Pro_Config::get_instance();
			$database = $pro->get_default_config( 'database', array() );
			/**
			 * Filter the Pro config of a site created with create_site() (setup wizard).
			 *
			 * @since 1.1.13
			 * @param array  $config    content_isolation, database, storage.
			 * @param string $tenant_id New tenant ID.
			 */
			$tenant_config = apply_filters(
				'grabwp_tenancy_create_site_pro_config',
				array(
					'content_isolation' => $pro->get_default_config( 'content_isolation', array() ),
					'database'          => is_array( $database ) ? $database : array(),
					'storage'           => array( 'driver' => 'local' ),
				),
				$tenant_id
			);
			$pro->save_tenant_config_from_form( $tenant_id, $tenant_config );
		}

		if ( $args['install'] ) {
			$install = self::install(
				$tenant_id,
				array(
					'title'       => '' !== $args['title'] ? $args['title'] : $tenant_id,
					'admin_email' => '' !== $args['admin_email'] ? $args['admin_email'] : wp_get_current_user()->user_email,
				)
			);
			if ( $install['ok'] ) {
				$out['password'] = $install['password'];
			} else {
				$out['install_error'] = $install['error'];
			}
		}

		return $out;
	}

	/**
	 * Current tenant mappings, read fresh from disk (tenants may have been
	 * created earlier in this request).
	 *
	 * @return array Tenant ID => domains.
	 */
	public static function get_tenant_mappings() {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		if ( ! file_exists( $mappings_file ) || ! is_readable( $mappings_file ) ) {
			return array();
		}
		clearstatcache( true, $mappings_file );
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $mappings_file, true );
		}
		$tenant_mappings = array();
		ob_start();
		include $mappings_file;
		ob_end_clean();
		return is_array( $tenant_mappings ) ? $tenant_mappings : array();
	}

	/**
	 * Load one tenant object.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return GrabWP_Tenancy_Tenant|null
	 */
	public static function get_tenant( $tenant_id ) {
		$mappings = self::get_tenant_mappings();
		if ( ! isset( $mappings[ $tenant_id ] ) ) {
			return null;
		}
		return new GrabWP_Tenancy_Tenant( $tenant_id, array( 'domains' => (array) $mappings[ $tenant_id ] ) );
	}

	/**
	 * Store a one-time result for the current user (shown once, 10 minutes max).
	 *
	 * @param array $data Result data (may contain a password; never stored in options).
	 */
	public static function set_one_time_result( $data ) {
		set_transient( 'grabwp_tenancy_setup_result_' . get_current_user_id(), $data, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Read and delete the one-time result of the current user.
	 *
	 * @return array Empty when none is pending.
	 */
	public static function pull_one_time_result() {
		$key  = 'grabwp_tenancy_setup_result_' . get_current_user_id();
		$data = get_transient( $key );
		if ( false === $data ) {
			return array();
		}
		delete_transient( $key );
		return is_array( $data ) ? $data : array();
	}
}
