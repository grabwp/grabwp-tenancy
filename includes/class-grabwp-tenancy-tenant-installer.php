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

	/** Install states of a tenant. */
	const INSTALLED     = 'installed';
	const NOT_INSTALLED = 'not_installed';
	const UNKNOWN       = 'unknown';

	/** Response header that names the tenant answering a loopback (see load.php). */
	const MARKER_HEADER = 'x-grabwp-tenant';

	/** Default total wait for routing in WP-CLI, in seconds. */
	const ROUTE_WAIT_CLI = 70;

	/** Default total wait for routing in a web request: a proxy such as Cloudflare ends it after 100 s. */
	const ROUTE_WAIT_WEB = 20;

	/** Longest total wait a filter can set, in seconds. */
	const ROUTE_WAIT_MAX = 300;

	/** Seconds after a tenants.php write during which a stale OPcache copy may still answer. */
	const ROUTE_FRESH_WINDOW = 90;

	/** @var callable|null Current time in seconds; null for microtime( true ). */
	private static $clock = null;

	/** @var callable|null Sleep for a number of seconds; null for usleep(). */
	private static $sleeper = null;

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
	 * @param string $context One of 'loopback', 'status', 'install', 'probe'.
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
		 * @param string $context 'loopback', 'status', 'install' or 'probe' (hosting checks, since 1.1.14).
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
	 * Send a loopback request, retrying it once over HTTPS when the server only
	 * upgrades the scheme.
	 *
	 * WP-CLI and cron see no proxy headers, so site_url() can be http while the
	 * proxy or CDN (e.g. Cloudflare "Always Use HTTPS") answers http with a
	 * redirect to the same URL over https. The same method and body are sent
	 * again, so an install POST stays a POST.
	 *
	 * @param string $method 'GET' or 'POST'.
	 * @param string $url    URL. Set to the URL that gave the returned response.
	 * @param array  $args   Result of request_args().
	 * @return array|WP_Error
	 */
	private static function request( $method, &$url, $args ) {
		$args['method'] = $method;
		$response       = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$https_url = self::https_upgrade_url( $url, $response );
		if ( '' === $https_url ) {
			return $response;
		}
		$url = $https_url;
		return wp_remote_request( $url, $args );
	}

	/**
	 * The https URL to retry, when a response redirects an http URL to the same
	 * host and path over https. Empty for any other response.
	 *
	 * @param string $url      Requested URL.
	 * @param array  $response Response.
	 * @return string
	 */
	private static function https_upgrade_url( $url, $response ) {
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 300 || $code >= 400 || 0 !== stripos( $url, 'http://' ) ) {
			return '';
		}

		$from = wp_parse_url( $url );
		$to   = wp_parse_url( self::location( $response ) );
		if ( ! is_array( $from ) || ! is_array( $to ) || empty( $from['host'] ) || isset( $from['port'] ) || empty( $to['host'] ) ) {
			return '';
		}
		if ( ! isset( $to['scheme'] ) || 'https' !== strtolower( $to['scheme'] ) || strtolower( $to['host'] ) !== strtolower( $from['host'] ) ) {
			return '';
		}
		if ( isset( $to['port'] ) && 443 !== (int) $to['port'] ) {
			return '';
		}
		$from_path = isset( $from['path'] ) ? $from['path'] : '';
		$to_path   = isset( $to['path'] ) ? $to['path'] : '';
		if ( $from_path !== $to_path ) {
			return '';
		}

		return 'https://' . substr( $url, strlen( 'http://' ) );
	}

	/**
	 * Location header of a response ('' when none).
	 *
	 * @param array $response Response.
	 * @return string
	 */
	private static function location( $response ) {
		return self::header( $response, 'location' );
	}

	/**
	 * Last value of a response header ('' when none).
	 *
	 * @param array  $response Response.
	 * @param string $name     Header name.
	 * @return string
	 */
	private static function header( $response, $name ) {
		$value = wp_remote_retrieve_header( $response, $name );
		if ( is_array( $value ) ) {
			$value = end( $value );
		}
		return (string) $value;
	}

	/**
	 * Whether a redirect stays on the requested scheme, host and port, and goes
	 * somewhere other than the requested URL.
	 *
	 * @param string $url      Requested URL.
	 * @param string $location Location header.
	 * @return bool
	 */
	private static function is_same_origin_redirect( $url, $location ) {
		if ( $location === $url ) {
			return false;
		}
		$from = wp_parse_url( $url );
		$to   = wp_parse_url( $location );
		if ( ! is_array( $from ) || ! is_array( $to ) ) {
			return false;
		}
		if ( ! isset( $to['host'] ) ) {
			return true;
		}

		$from_scheme = isset( $from['scheme'] ) ? strtolower( $from['scheme'] ) : '';
		$to_scheme   = isset( $to['scheme'] ) ? strtolower( $to['scheme'] ) : $from_scheme;
		$from_port   = isset( $from['port'] ) ? (int) $from['port'] : ( 'https' === $from_scheme ? 443 : 80 );
		$to_port     = isset( $to['port'] ) ? (int) $to['port'] : ( 'https' === $to_scheme ? 443 : 80 );
		return $to_scheme === $from_scheme
			&& isset( $from['host'] )
			&& strtolower( $to['host'] ) === strtolower( $from['host'] )
			&& $to_port === $from_port;
	}

	/**
	 * Whether the main site can reach itself over HTTP.
	 *
	 * @return bool
	 */
	public static function can_loopback() {
		$url      = self::no_cache_url( home_url( '/wp-login.php' ) );
		$response = self::request( 'GET', $url, self::request_args( 'loopback' ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code > 0 && $code < 500;
	}

	/**
	 * One short GET for the hosting checks, with the same cache bypass as the
	 * loopback checks and an explicit certificate check setting.
	 *
	 * @since 1.1.14
	 * @param string $url       URL.
	 * @param bool   $sslverify Whether the certificate must be valid.
	 * @return array|WP_Error
	 */
	public static function probe_url( $url, $sslverify ) {
		$url  = self::no_cache_url( $url );
		$args = self::request_args( 'probe', array( 'timeout' => 8 ) );
		// After the filter: a certificate check must stay a certificate check.
		$args['sslverify'] = (bool) $sslverify;
		return self::request( 'GET', $url, $args );
	}

	/**
	 * Ask the tenant whether WordPress is installed.
	 *
	 * Only an answer that carries the tenant's marker header (sent by load.php)
	 * comes from the tenant. Without it, the path fell through to the main site
	 * (e.g. the web server still has an old tenants.php) or something in front
	 * of the site answered, so the state is unknown.
	 *
	 * From the tenant: a 2xx or a same-origin redirect is installed; a redirect
	 * to install.php (core does it for every request of an uninstalled site) is
	 * not installed. Anything else (another scheme, host or port, the same URL,
	 * no Location, 4xx, 5xx) is unknown.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return array {
	 *     @type string         $state    INSTALLED, NOT_INSTALLED or UNKNOWN.
	 *     @type bool           $unrouted An answer came, but not from the tenant.
	 *     @type array|WP_Error $response Last response (null without a request).
	 * }
	 */
	private static function probe( $tenant_id ) {
		$probe = array(
			'state'    => self::UNKNOWN,
			'unrouted' => false,
			'response' => null,
		);
		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return $probe;
		}

		$url               = self::no_cache_url( self::loopback_url( $tenant_id, 'wp-login.php' ) );
		$response          = self::request( 'GET', $url, self::request_args( 'status' ) );
		$probe['response'] = $response;
		if ( is_wp_error( $response ) ) {
			return $probe;
		}
		if ( self::header( $response, self::MARKER_HEADER ) !== $tenant_id ) {
			$probe['unrouted'] = true;
			return $probe;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			$probe['state'] = self::INSTALLED;
		} elseif ( $code >= 300 && $code < 400 ) {
			$location = self::location( $response );
			if ( '' === $location ) {
				return $probe;
			}
			if ( false !== strpos( $location, 'install.php' ) ) {
				$probe['state'] = self::NOT_INSTALLED;
			} elseif ( self::is_same_origin_redirect( $url, $location ) ) {
				$probe['state'] = self::INSTALLED;
			}
		}
		return $probe;
	}

	/**
	 * Total time install() may wait for a new tenant to be routed, in seconds.
	 *
	 * Waiting only helps while the web server can still hold an old copy of
	 * tenants.php, so the default is 0 once the file is older than
	 * ROUTE_FRESH_WINDOW. Otherwise it is ROUTE_WAIT_CLI in WP-CLI and
	 * ROUTE_WAIT_WEB in other requests.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return float Between 0 and ROUTE_WAIT_MAX.
	 */
	private static function route_wait_budget( $tenant_id ) {
		$default = ( defined( 'WP_CLI' ) && WP_CLI ) ? self::ROUTE_WAIT_CLI : self::ROUTE_WAIT_WEB;
		$default = min( (float) $default, self::fresh_window_left() );

		/**
		 * Filter how long install() waits, in total, for a new tenant to answer at its own address.
		 *
		 * @param float  $seconds   Maximum total wait in seconds, capped at 300. Default 70 in
		 *                          WP-CLI and 20 in other requests, and 0 when tenants.php
		 *                          was written more than 90 seconds ago.
		 * @param string $tenant_id Tenant ID.
		 */
		$limit = (float) apply_filters( 'grabwp_tenancy_tenant_install_route_wait', $default, $tenant_id );
		if ( ! is_finite( $limit ) ) {
			$limit = $default;
		}
		return max( 0.0, min( $limit, (float) self::ROUTE_WAIT_MAX ) );
	}

	/**
	 * Seconds left until tenants.php is older than ROUTE_FRESH_WINDOW (0 when missing).
	 *
	 * @return float
	 */
	private static function fresh_window_left() {
		$file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		clearstatcache( true, $file );
		if ( ! file_exists( $file ) ) {
			return 0.0;
		}
		$mtime = filemtime( $file );
		if ( false === $mtime ) {
			return 0.0;
		}
		return max( 0.0, (float) ( $mtime + self::ROUTE_FRESH_WINDOW - time() ) );
	}

	/**
	 * Probe the tenant until it answers itself, within a wait budget.
	 *
	 * A tenant created from WP-CLI or another PHP pool can be answered by the
	 * main site until the web server reloads tenants.php (OPcache keeps the old
	 * copy for up to opcache.revalidate_freq seconds). Errors and answers from
	 * the tenant are returned at once.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @param float  $budget    Seconds left to wait; reduced by the time spent here.
	 * @return array See probe().
	 */
	private static function wait_for_tenant( $tenant_id, &$budget ) {
		$start = self::now();
		$delay = min( 1.0, $budget / 8 );
		$probe = self::probe( $tenant_id );
		while ( $probe['unrouted'] ) {
			$left = $budget - ( self::now() - $start );
			if ( $left < 0.01 ) {
				break;
			}
			self::sleep( min( $delay, $left ) );
			$delay = min( $delay * 2, 10.0 );
			$probe = self::probe( $tenant_id );
		}
		$budget = max( 0.0, $budget - ( self::now() - $start ) );
		return $probe;
	}

	/**
	 * Current time in seconds.
	 *
	 * @return float
	 */
	private static function now() {
		return null === self::$clock ? microtime( true ) : (float) call_user_func( self::$clock );
	}

	/**
	 * Sleep for a number of seconds.
	 *
	 * @param float $seconds Seconds.
	 */
	private static function sleep( $seconds ) {
		if ( null === self::$sleeper ) {
			usleep( (int) ( $seconds * 1000000 ) );
			return;
		}
		call_user_func( self::$sleeper, $seconds );
	}

	/**
	 * Why a probe did not prove the tenant state, for the admin.
	 *
	 * @param array $probe Result of probe().
	 * @return string
	 */
	private static function probe_reason( $probe ) {
		if ( is_wp_error( $probe['response'] ) ) {
			return $probe['response']->get_error_message();
		}
		$code = (int) wp_remote_retrieve_response_code( $probe['response'] );
		if ( $probe['unrouted'] ) {
			return sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The address of the new site was answered by the main site or a proxy (HTTP %d), not by the new site. The web server may not have loaded the new site yet; wait a minute and try again.', 'grabwp-tenancy' ),
				$code
			);
		}
		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'The site answered with HTTP %d.', 'grabwp-tenancy' ),
			$code
		);
	}

	/**
	 * Whether a request failed by timing out (cURL and fsockopen transports).
	 *
	 * @param array|WP_Error $response Response.
	 * @return bool
	 */
	private static function is_timeout( $response ) {
		if ( ! is_wp_error( $response ) ) {
			return false;
		}
		$message = $response->get_error_message();
		return false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'cURL error 28' );
	}

	/**
	 * Text ending with sentence punctuation, so another sentence can follow it.
	 *
	 * @param string $text Text, e.g. a transport error message.
	 * @return string
	 */
	private static function sentence( $text ) {
		$text = rtrim( (string) $text );
		if ( '' === $text || preg_match( '/[.!?\x{3002}\x{FF01}\x{FF1F}\x{061F}]$/u', $text ) ) {
			return $text;
		}
		return $text . '.';
	}

	/**
	 * Install state of a tenant: INSTALLED, NOT_INSTALLED or UNKNOWN.
	 *
	 * One probe, no waiting. Callers should block only on NOT_INSTALLED.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return string
	 */
	public static function install_state( $tenant_id ) {
		$probe = self::probe( $tenant_id );
		return $probe['state'];
	}

	/**
	 * Whether WordPress is installed in the tenant, proven by the tenant itself.
	 *
	 * False also when the state is unknown; see install_state().
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return bool
	 */
	public static function is_installed( $tenant_id ) {
		return self::INSTALLED === self::install_state( $tenant_id );
	}

	/**
	 * Install WordPress in a tenant with an administrator named after the tenant ID.
	 *
	 * The password is random, returned to the caller once and never stored.
	 * A failure never deletes the tenant.
	 *
	 * ok is true only when the tenant itself proves it is installed. Nothing is
	 * posted until the tenant proves it is not installed. When the install was
	 * posted but the result cannot be confirmed (or the POST timed out), ok is
	 * false and the password is still returned with the error, since the
	 * install may have worked.
	 *
	 * The waits for routing before and after the POST share one budget, see
	 * route_wait_budget().
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

		$budget = self::route_wait_budget( $tenant_id );
		$before = self::wait_for_tenant( $tenant_id, $budget );
		if ( self::INSTALLED === $before['state'] ) {
			$result['ok']                = true;
			$result['already_installed'] = true;
			return $result;
		}
		if ( self::NOT_INSTALLED !== $before['state'] ) {
			$result['error'] = self::probe_reason( $before );
			GrabWP_Tenancy_Logger::log( 'GrabWP Tenancy: install not started for tenant ' . $tenant_id . ', state unknown: ' . $result['error'] );
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

		$install_url = self::loopback_url( $tenant_id, 'wp-admin/install.php?step=2' );
		$response    = self::request(
			'POST',
			$install_url,
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
		$after = self::wait_for_tenant( $tenant_id, $budget );
		if ( self::INSTALLED === $after['state'] ) {
			GrabWP_Tenancy_Logger::log( 'GrabWP Tenancy: install ok for tenant ' . $tenant_id );
			$result['ok']       = true;
			$result['password'] = $password;
			return $result;
		}

		// A POST that timed out may still be running install.php with this password.
		if ( self::UNKNOWN === $after['state'] || self::is_timeout( $response ) ) {
			$reason             = self::UNKNOWN === $after['state'] ? self::probe_reason( $after ) : $response->get_error_message();
			$result['password'] = $password;
			$result['error']    = sprintf(
				/* translators: 1: reason, 2: administrator username. */
				__( 'WordPress may be installed, but GrabWP could not confirm it: %1$s If it is, log in as %2$s with the password shown here.', 'grabwp-tenancy' ),
				self::sentence( $reason ),
				$tenant_id
			);
			GrabWP_Tenancy_Logger::log( 'GrabWP Tenancy: install unconfirmed for tenant ' . $tenant_id . ': ' . $reason );
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
	 * @return array {
	 *     ok: bool (the tenant was created), tenant_id: string, password: string,
	 *     error: string, install_error: string,
	 *     installed: bool (WordPress install proven by the tenant)
	 * }
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
			'installed'     => false,
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
			// An unconfirmed install still returns its password with the error.
			$out['password']  = $install['password'];
			$out['installed'] = $install['ok'];
			if ( ! $install['ok'] ) {
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
