<?php
/**
 * GrabWP Tenancy Diagnostics
 *
 * One redacted, read-only summary of the setup checks, used by the admin-only
 * REST route GET /wp-json/grabwp/v1/diagnostics and by the "Copy diagnostics"
 * button on the Status page. tests/scripts/diagnose-site.sh reads the same
 * payload, so both give the same analysis.
 *
 * Building it never writes: no options, no transients (the Hosting setup
 * card cache is only read), no files. It never returns salts, keys, database
 * credentials, license or API keys, webhook secrets or customer emails.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Diagnostics {

	/** Payload schema name, checked by the runner. */
	const SCHEMA = 'grabwp-diagnostics';

	/** Payload schema version. */
	const SCHEMA_VERSION = 1;

	/** REST namespace. */
	const REST_NAMESPACE = 'grabwp/v1';

	/** REST route. */
	const REST_ROUTE = '/diagnostics';

	/** Debug log lines returned at most. */
	const LOG_LINES = 50;

	/** Characters kept per debug log line. */
	const LOG_LINE_LENGTH = 300;

	/** Bytes read from the end of the debug log at most, in LOG_CHUNK_BYTES steps. */
	const LOG_READ_BYTES = 524288;

	/** Bytes read per step, from the end of the debug log backwards. */
	const LOG_CHUNK_BYTES = 65536;

	/**
	 * System Information rows exported as shown, without redact_text(): their
	 * values are settings built here, not log text (a permalink structure
	 * looks like a file path).
	 */
	const VERBATIM_ROWS = array( 'permalinks' );

	/**
	 * Key names that never appear in the payload, at any depth.
	 */
	const FORBIDDEN_KEY_PATTERN = '/pass|pwd|secret|salt|token|auth|licen[cs]e|api_?key|private|credential|cookie|nonce|e_?mail|dsn|hash|session|signature|jwt|^db_(user|host|name)|^key$|_key$/i';

	/**
	 * Whether the route hook is added.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Add the REST route on main sites and tenants (tenants refuse it).
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register GET /wp-json/grabwp/v1/diagnostics.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
			)
		);
	}

	/**
	 * Administrators of the main site only.
	 *
	 * @return true|WP_Error
	 */
	public static function rest_permission() {
		if ( self::is_tenant() ) {
			return new WP_Error(
				'grabwp_diagnostics_tenant',
				__( 'Diagnostics are available on the main site only.', 'grabwp-tenancy' ),
				array( 'status' => 403 )
			);
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to read diagnostics.', 'grabwp-tenancy' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * REST callback.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_get() {
		$response = new WP_REST_Response( self::build( 'rest' ), 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	/**
	 * Whether this request runs on a tenant.
	 *
	 * @return bool
	 */
	private static function is_tenant() {
		return defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT;
	}

	/**
	 * The redacted diagnostics payload.
	 *
	 * @param string     $source  'rest' or 'status-page'.
	 * @param array|null $hosting Result of get_hosting_card_data(); null reads it without storing.
	 * @return array
	 */
	public static function build( $source, $hosting = null ) {
		$raw   = self::collect( $source, $hosting );
		$clean = self::redact( $raw );
		foreach ( $raw['system_info'] as $g => $group ) {
			foreach ( $group['rows'] as $r => $row ) {
				if ( in_array( $row['id'], self::VERBATIM_ROWS, true ) ) {
					$clean['system_info'][ $g ]['rows'][ $r ]['value'] = $row['value'];
				}
			}
		}
		return $clean;
	}

	/**
	 * The payload before redaction. Its keys are a fixed list, so a test can
	 * prove none of them is a forbidden name.
	 *
	 * @param string     $source  'rest' or 'status-page'.
	 * @param array|null $hosting Result of get_hosting_card_data(); null reads it without storing.
	 * @return array
	 */
	public static function collect( $source, $hosting = null ) {
		require_once __DIR__ . '/class-grabwp-tenancy-status-checker.php';
		if ( null === $hosting ) {
			$hosting = GrabWP_Tenancy_Status_Checker::get_hosting_card_data( false, false );
		}
		$environment = self::environment();
		$grabwp      = self::grabwp();
		return array(
			'schema'         => self::SCHEMA,
			'schema_version' => self::SCHEMA_VERSION,
			'source'         => (string) $source,
			'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'site'           => array(
				'home'    => home_url(),
				'siteurl' => site_url(),
				'is_ssl'  => is_ssl(),
			),
			'environment'    => $environment,
			'grabwp'         => $grabwp,
			'system_info'    => self::system_info( $environment, $grabwp ),
			'checks'         => self::checks( $hosting, 'rest' === $source ),
			'log'            => self::log_tail( GrabWP_Tenancy_Path_Manager::get_configured_base_path() . '/grabwp_debug.log' ),
			'wp_log'         => self::log_tail( self::wp_debug_log_file() ),
		);
	}

	/**
	 * System Information rows, shown on the Status page and the setup wizard
	 * Check screen and copied into the diagnostics. Core values only, read
	 * directly (no WP_Debug_Data: it also sizes directories and checks updates).
	 *
	 * @param array|null $environment Result of environment(); null reads it.
	 * @param array|null $grabwp      Result of grabwp(); null reads it.
	 * @return array[] Groups: id, label, rows (id, label, value, optional ok and note).
	 *                 A value is a string, a number, a bool (Yes/No) or null (Not set).
	 */
	public static function system_info( $environment = null, $grabwp = null ) {
		require_once __DIR__ . '/class-grabwp-tenancy-status-checker.php';
		$environment = null === $environment ? self::environment() : $environment;
		$grabwp      = null === $grabwp ? self::grabwp() : $grabwp;
		$server      = GrabWP_Tenancy_Status_Checker::get_server_environment();
		$mu          = GrabWP_Tenancy_Status_Checker::get_mu_plugin_status();
		$content     = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$uploads     = function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false ) : array();
		$pro_version = defined( 'GRABWP_TENANCY_PRO_VERSION' ) ? GRABWP_TENANCY_PRO_VERSION : true;
		$wpdb        = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;

		$grabwp_rows = array(
			self::row( 'version', __( 'Base Plugin Version', 'grabwp-tenancy' ), $grabwp['version'] ),
			self::row( 'pro', __( 'Pro Plugin', 'grabwp-tenancy' ), $grabwp['pro_active'] ? $pro_version : false ),
			self::row( 'tenants', __( 'Registered Tenants', 'grabwp-tenancy' ), $grabwp['tenant_count'] ),
			self::row( 'routing', __( 'Routing mode', 'grabwp-tenancy' ), $grabwp['routing_mode'] ),
			self::row( 'path_prefix', __( 'Path prefix', 'grabwp-tenancy' ), $grabwp['path_prefix'] ),
		);
		foreach ( $grabwp['plugins'] as $plugin ) {
			if ( ! in_array( $plugin['slug'], array( 'grabwp-tenancy', 'grabwp-tenancy-pro' ), true ) ) {
				/* translators: 1: plugin version, 2: Active or Inactive */
				$grabwp_rows[] = self::row( $plugin['slug'], $plugin['name'], sprintf( __( '%1$s, %2$s', 'grabwp-tenancy' ), $plugin['version'], $plugin['active'] ? __( 'Active', 'grabwp-tenancy' ) : __( 'Inactive', 'grabwp-tenancy' ) ) );
			}
		}

		$web_server = self::row( 'web_server', __( 'Web Server', 'grabwp-tenancy' ), $server['server_software'] );
		if ( $server['is_nginx'] ) {
			$web_server['note'] = __( '.htaccess files are not used by Nginx. Configure tenant routing in your server block instead.', 'grabwp-tenancy' );
		}
		$server_rows = array( $web_server );
		if ( $server['is_apache'] ) {
			$server_rows[] = self::row( 'mod_rewrite', __( 'mod_rewrite', 'grabwp-tenancy' ), null === $server['mod_rewrite'] ? __( 'Cannot detect (apache_get_modules unavailable)', 'grabwp-tenancy' ) : $server['mod_rewrite'], false !== $server['mod_rewrite'], __( 'Path routing requires mod_rewrite. Query string routing (?site=id) will be used as fallback.', 'grabwp-tenancy' ) );
		}
		$server_rows = array_merge(
			$server_rows,
			array(
				self::row( 'php_version', __( 'PHP Version', 'grabwp-tenancy' ), PHP_VERSION ),
				self::row( 'php_sapi', __( 'PHP SAPI', 'grabwp-tenancy' ), PHP_SAPI ),
				self::row( 'memory_limit', __( 'PHP memory limit', 'grabwp-tenancy' ), (string) ini_get( 'memory_limit' ) ),
				self::row( 'max_execution_time', __( 'PHP time limit', 'grabwp-tenancy' ), (string) ini_get( 'max_execution_time' ) ),
				self::row( 'upload_max_filesize', __( 'Max upload size', 'grabwp-tenancy' ), (string) ini_get( 'upload_max_filesize' ) ),
				self::row( 'post_max_size', __( 'PHP post max size', 'grabwp-tenancy' ), (string) ini_get( 'post_max_size' ) ),
			)
		);

		// What GrabWP code calls; a missing one only turns off the feature in the note.
		// Rows only GrabWP Pro needs are not a problem while Pro is inactive.
		$pro      = (bool) $grabwp['pro_active'];
		$sodium   = function_exists( 'sodium_crypto_secretbox' );
		$symlink  = function_exists( 'symlink' );
		$zip      = class_exists( 'ZipArchive' );
		$sqlite   = extension_loaded( 'pdo_sqlite' );
		$xml      = function_exists( 'simplexml_load_string' );
		$php_rows = array(
			self::row( 'sodium', __( 'Sodium', 'grabwp-tenancy' ), $sodium, $sodium || ! $pro, __( 'GrabWP Pro needs it to encrypt tenant database passwords.', 'grabwp-tenancy' ) ),
			self::row( 'symlink', __( 'symlink()', 'grabwp-tenancy' ), $symlink, $symlink || ! $pro, __( 'GrabWP Pro needs it for domain links.', 'grabwp-tenancy' ) ),
			self::row( 'zip', __( 'ZipArchive', 'grabwp-tenancy' ), $zip, $zip || ! $pro, __( 'Backups, restores and add-on installs need it.', 'grabwp-tenancy' ) ),
			self::row( 'pdo_sqlite', __( 'PDO SQLite', 'grabwp-tenancy' ), $sqlite, $sqlite || ! $pro, __( 'Tenants with SQLite databases need it.', 'grabwp-tenancy' ) ),
			self::row( 'tokenizer', __( 'Tokenizer', 'grabwp-tenancy' ), function_exists( 'token_get_all' ), null, __( 'The wp-config.php checks need it.', 'grabwp-tenancy' ) ),
			self::row( 'simplexml', __( 'SimpleXML', 'grabwp-tenancy' ), $xml, $xml || ! $pro, __( 'Backups to S3 storage need it.', 'grabwp-tenancy' ) ),
			self::row( 'opcache', __( 'OPcache', 'grabwp-tenancy' ), function_exists( 'opcache_invalidate' ) ),
			self::row( 'curl', __( 'cURL', 'grabwp-tenancy' ), extension_loaded( 'curl' ) ),
			self::row( 'mbstring', __( 'mbstring', 'grabwp-tenancy' ), extension_loaded( 'mbstring' ) ),
		);

		$debug_log = defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : null;
		if ( is_string( $debug_log ) && ! in_array( strtolower( $debug_log ), array( 'true', '1', 'false', '0', '' ), true ) ) {
			$debug_log = __( 'Custom file', 'grabwp-tenancy' );
		}
		$constant_rows = array();
		foreach ( array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'WP_CACHE', 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT', 'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS', 'FORCE_SSL_ADMIN' ) as $name ) {
			$constant_rows[] = self::row( strtolower( $name ), $name, 'WP_DEBUG_LOG' === $name ? $debug_log : ( defined( $name ) ? constant( $name ) : null ) );
		}

		$base_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$writable = array(
			'abspath'     => array( __( 'WordPress directory', 'grabwp-tenancy' ), ABSPATH ),
			'wp_content'  => array( __( 'wp-content directory', 'grabwp-tenancy' ), $content ),
			'plugins_dir' => array( __( 'Plugins directory', 'grabwp-tenancy' ), defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : $content . '/plugins' ),
			'uploads_dir' => array( __( 'Uploads directory', 'grabwp-tenancy' ), isset( $uploads['basedir'] ) ? $uploads['basedir'] : '' ),
		);
		$fs_rows  = array();
		foreach ( $writable as $id => $item ) {
			$fs_rows[] = self::row( $id, $item[0], is_dir( $item[1] ) && wp_is_writable( $item[1] ) );
		}
		$fs_rows[] = self::row( 'mu_plugins_dir', __( 'MU-Plugins Dir Writable', 'grabwp-tenancy' ), (bool) $mu['dir_writable'], (bool) $mu['dir_writable'], __( 'Auto-install of MU-plugin will not work. Manual installation required.', 'grabwp-tenancy' ) );
		$fs_rows[] = self::row( 'data_dir', __( 'Data Dir Writable', 'grabwp-tenancy' ), (bool) $grabwp['base_dir_writable'], (bool) $grabwp['base_dir_writable'], __( 'Plugin needs write access to create tenant directories and manage configuration files.', 'grabwp-tenancy' ) );

		return array(
			array(
				'id'    => 'grabwp',
				'label' => __( 'GrabWP Tenancy', 'grabwp-tenancy' ),
				'rows'  => $grabwp_rows,
			),
			array(
				'id'    => 'wordpress',
				'label' => __( 'WordPress', 'grabwp-tenancy' ),
				'rows'  => array(
					self::row( 'wp_version', __( 'WordPress Version', 'grabwp-tenancy' ), $environment['wp_version'] ),
					self::row( 'locale', __( 'Site language', 'grabwp-tenancy' ), function_exists( 'get_locale' ) ? get_locale() : '' ),
					self::row( 'environment_type', __( 'Environment type', 'grabwp-tenancy' ), function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '' ),
					self::row( 'permalinks', __( 'Permalink structure', 'grabwp-tenancy' ), (string) get_option( 'permalink_structure', '' ) ),
					self::row( 'https', __( 'HTTPS', 'grabwp-tenancy' ), is_ssl() ),
					self::row( 'multisite', __( 'WordPress Multisite', 'grabwp-tenancy' ), $environment['is_multisite'], ! $environment['is_multisite'], __( 'GrabWP Tenancy is not designed for Multisite.', 'grabwp-tenancy' ) ),
					self::row( 'object_cache', __( 'Object cache drop-in', 'grabwp-tenancy' ), '' === $environment['object_cache']['type'] ? false : $environment['object_cache']['type'] ),
					self::row( 'page_cache', __( 'Page cache drop-in', 'grabwp-tenancy' ), '' === $environment['page_cache']['type'] ? false : $environment['page_cache']['type'] ),
				),
			),
			array(
				'id'    => 'server',
				'label' => __( 'Server', 'grabwp-tenancy' ),
				'rows'  => $server_rows,
			),
			array(
				'id'    => 'php_extensions',
				'label' => __( 'PHP extensions', 'grabwp-tenancy' ),
				'rows'  => $php_rows,
			),
			array(
				'id'    => 'database',
				'label' => __( 'Database', 'grabwp-tenancy' ),
				'rows'  => array(
					self::row( 'db_engine', __( 'Database Engine', 'grabwp-tenancy' ), ucfirst( $environment['db_engine'] ) ),
					self::row( 'db_server', __( 'Database server version', 'grabwp-tenancy' ), $wpdb && method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : '' ),
					self::row( 'table_prefix', __( 'Table prefix', 'grabwp-tenancy' ), $wpdb ? (string) $wpdb->prefix : '' ),
					self::row( 'charset', __( 'Database charset', 'grabwp-tenancy' ), $wpdb ? (string) $wpdb->charset : '' ),
				),
			),
			array(
				'id'    => 'constants',
				'label' => __( 'WordPress constants', 'grabwp-tenancy' ),
				'rows'  => $constant_rows,
			),
			array(
				'id'    => 'filesystem',
				'label' => __( 'Writable directories', 'grabwp-tenancy' ),
				'rows'  => $fs_rows,
			),
		);
	}

	/**
	 * One System Information row. A note shows only when the row is not OK.
	 *
	 * @param string    $id    Stable id, for support tooling.
	 * @param string    $label Translated label.
	 * @param mixed     $value Value.
	 * @param bool|null $ok    False marks a problem; null means the value itself (for bool values).
	 * @param string    $note  Why a problem matters.
	 * @return array
	 */
	private static function row( $id, $label, $value, $ok = true, $note = '' ) {
		$ok  = null === $ok ? (bool) $value : (bool) $ok;
		$row = array(
			'id'    => (string) $id,
			'label' => (string) $label,
			'value' => $value,
			'ok'    => $ok,
		);
		if ( ! $ok && '' !== $note ) {
			$row['note'] = $note;
		}
		return $row;
	}

	/**
	 * WordPress debug log: the WP_DEBUG_LOG path when it names a file, else
	 * wp-content/debug.log (the rule wp_debug_mode() follows).
	 *
	 * @return string
	 */
	private static function wp_debug_log_file() {
		$content = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && ! in_array( strtolower( WP_DEBUG_LOG ), array( 'true', '1', 'false', '0', '' ), true ) ) {
			return WP_DEBUG_LOG;
		}
		return $content . '/debug.log';
	}

	/**
	 * PHP, WordPress, web server and cache layers.
	 *
	 * @return array
	 */
	private static function environment() {
		$server  = GrabWP_Tenancy_Status_Checker::get_server_environment();
		$content = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';

		if ( defined( 'DB_ENGINE' ) ) {
			$db_engine = strtolower( (string) DB_ENGINE );
		} elseif ( defined( 'DATABASE_TYPE' ) ) {
			$db_engine = strtolower( (string) DATABASE_TYPE );
		} else {
			$db_engine = 'mysql';
		}

		$object_cache = self::dropin_signature( $content . '/object-cache.php' );
		$page_cache   = self::dropin_signature( $content . '/advanced-cache.php' );

		return array(
			'php_version'     => PHP_VERSION,
			'wp_version'      => (string) get_bloginfo( 'version' ),
			'server_software' => $server['server_software'],
			'is_apache'       => (bool) $server['is_apache'],
			'is_nginx'        => (bool) $server['is_nginx'],
			'is_litespeed'    => false !== stripos( (string) $server['server_software'], 'litespeed' ),
			'mod_rewrite'     => $server['mod_rewrite'],
			'is_multisite'    => (bool) $server['is_multisite'],
			'wp_debug'        => (bool) $server['wp_debug'],
			'db_engine'       => $db_engine,
			'object_cache'    => array(
				'dropin'   => '' !== $object_cache,
				'type'     => $object_cache,
				'external' => function_exists( 'wp_using_ext_object_cache' ) ? (bool) wp_using_ext_object_cache() : false,
			),
			'page_cache'      => array(
				'dropin'   => '' !== $page_cache,
				'type'     => $page_cache,
				'wp_cache' => defined( 'WP_CACHE' ) && WP_CACHE,
			),
		);
	}

	/**
	 * Which cache product a drop-in comes from, '' when the file is missing.
	 *
	 * @param string $file Drop-in path.
	 * @return string 'xspeed', 'redis-cache', 'litespeed', 'memcached', 'wp-rocket',
	 *                'w3-total-cache', 'wp-super-cache', 'other' or ''.
	 */
	private static function dropin_signature( $file ) {
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$head  = (string) file_get_contents( $file, false, null, 0, 4096 );
		$known = array(
			'XSPEED_OBJECT_CACHE_DROPIN' => 'xspeed',
			'Redis Object Cache'         => 'redis-cache',
			'LiteSpeed'                  => 'litespeed',
			'Memcache'                   => 'memcached',
			'WP Rocket'                  => 'wp-rocket',
			'W3 Total Cache'             => 'w3-total-cache',
			'WP Super Cache'             => 'wp-super-cache',
		);
		foreach ( $known as $needle => $type ) {
			if ( false !== stripos( $head, $needle ) ) {
				return $type;
			}
		}
		return 'other';
	}

	/**
	 * GrabWP plugins, routing and tenant count.
	 *
	 * @return array
	 */
	private static function grabwp() {
		$tenants_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		$tenant_count = 0;
		if ( is_file( $tenants_file ) && is_readable( $tenants_file ) ) {
			$tenant_mappings = array();
			ob_start();
			include $tenants_file;
			ob_end_clean();
			$tenant_count = is_array( $tenant_mappings ) ? count( $tenant_mappings ) : 0;
		}

		$base_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();

		return array(
			'version'           => defined( 'GRABWP_TENANCY_VERSION' ) ? GRABWP_TENANCY_VERSION : '',
			'pro_active'        => defined( 'GRABWP_TENANCY_PRO_ACTIVE' ) && GRABWP_TENANCY_PRO_ACTIVE,
			'plugins'           => self::plugins(),
			'routing_mode'      => GrabWP_Tenancy_Routing::get_mode(),
			'routing_stored'    => GrabWP_Tenancy_Routing::get_stored_mode(),
			'routing_constant'  => GrabWP_Tenancy_Routing::is_constant_defined(),
			'path_prefix'       => grabwp_tenancy_get_path_prefix(),
			'tenant_count'      => $tenant_count,
			'tenants_file'      => is_file( $tenants_file ),
			'base_dir_exists'   => is_dir( $base_dir ),
			'base_dir_writable' => is_dir( $base_dir ) ? wp_is_writable( $base_dir ) : false,
		);
	}

	/**
	 * Installed GrabWP plugins: slug, version, active.
	 *
	 * Reads the plugin headers directly, so nothing is cached or stored.
	 *
	 * @return array[]
	 */
	private static function plugins() {
		$dir    = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : ABSPATH . 'wp-content/plugins';
		$active = (array) get_option( 'active_plugins', array() );
		$list   = array();
		foreach ( (array) glob( $dir . '/grabwp*/grabwp*.php' ) as $file ) {
			$headers = get_file_data(
				$file,
				array(
					'name'    => 'Plugin Name',
					'version' => 'Version',
				)
			);
			if ( '' === $headers['name'] ) {
				continue;
			}
			$basename = basename( dirname( $file ) ) . '/' . basename( $file );
			$list[]   = array(
				'slug'    => basename( dirname( $file ) ),
				'name'    => $headers['name'],
				'version' => $headers['version'],
				'active'  => in_array( $basename, $active, true ),
			);
		}
		usort(
			$list,
			function ( $a, $b ) {
				return strcmp( $a['slug'], $b['slug'] );
			}
		);
		return $list;
	}

	/**
	 * Environment cards and Hosting setup rows, without file paths or file content.
	 *
	 * @param array $hosting Result of get_hosting_card_data().
	 * @param bool  $live    Run the loopback check when no cached answer exists.
	 * @return array
	 */
	private static function checks( $hosting, $live ) {
		$base_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$config   = GrabWP_Tenancy_Status_Checker::get_wp_config_status();
		$mu       = GrabWP_Tenancy_Status_Checker::get_mu_plugin_status();
		$htaccess = GrabWP_Tenancy_Status_Checker::get_root_htaccess_status();
		$data_ht  = GrabWP_Tenancy_Status_Checker::get_data_htaccess_status( $base_dir );
		$rows     = $hosting['rows'];

		return array(
			'wp_config'              => array(
				'readable'       => (bool) $config['readable'],
				'writable'       => (bool) $config['writable'],
				'loader_active'  => (bool) $config['loader_active'],
				'loader_line'    => (bool) $config['loader_line'],
				'loader_pending' => (bool) $config['loader_pending'],
				'stop_editing'   => (bool) $config['stop_editing'],
			),
			'mu_plugin'              => array(
				'exists'       => (bool) $mu['exists'],
				'valid'        => (bool) $mu['valid'],
				'dir_writable' => (bool) $mu['dir_writable'],
			),
			'root_htaccess'          => array(
				'exists'           => (bool) $htaccess['exists'],
				'has_block'        => (bool) $htaccess['has_block'],
				'block_positioned' => (bool) $htaccess['block_positioned'],
				'content_valid'    => (bool) $htaccess['content_valid'],
			),
			'data_htaccess'          => array(
				'exists'   => (bool) $data_ht['exists'],
				'has_deny' => (bool) $data_ht['has_deny'],
			),
			'index_exists'           => file_exists( $base_dir . '/index.php' ),
			'loopback'               => self::loopback( $live ),
			'proxy_https_undetected' => GrabWP_Tenancy_Status_Checker::is_proxy_https_undetected(),
			'unsafe_file_settings'   => array_keys( GrabWP_Tenancy_Status_Checker::get_unsafe_file_settings() ),
			'unsafe_tenant_count'    => count( GrabWP_Tenancy_Status_Checker::get_unsafe_tenant_file_overrides() ),
			'hosting'                => array(
				'issues'     => (int) $hosting['issues'],
				'xspeed'     => (bool) $hosting['xspeed'],
				'multisite'  => array(
					'state'    => $rows['multisite']['state'],
					'ok'       => (bool) $rows['multisite']['ok'],
					'defines'  => array_values( array_unique( array_column( $rows['multisite']['defines'], 'name' ) ) ),
					'external' => array_values( $rows['multisite']['external'] ),
					'sites'    => (int) $rows['multisite']['sites'],
				),
				'https_urls' => array(
					'state'   => $rows['https_urls']['state'],
					'ok'      => (bool) $rows['https_urls']['ok'],
					'home'    => $rows['https_urls']['home'],
					'siteurl' => $rows['https_urls']['siteurl'],
					'tls'     => is_array( $rows['https_urls']['probe'] ) ? $rows['https_urls']['probe']['tls'] : '',
					'code'    => is_array( $rows['https_urls']['probe'] ) ? (int) $rows['https_urls']['probe']['code'] : 0,
				),
				'proxy'      => array(
					'state' => $rows['proxy']['state'],
					'ok'    => (bool) $rows['proxy']['ok'],
					'line'  => (int) $rows['proxy']['line'],
					'ours'  => (bool) $rows['proxy']['ours'],
				),
				'wildcard'   => array(
					'state'  => $rows['wildcard']['state'],
					'ok'     => (bool) $rows['wildcard']['ok'],
					'domain' => $rows['wildcard']['domain'],
					'dns'    => (bool) $rows['wildcard']['dns'],
					'route'  => (bool) $rows['wildcard']['route'],
					'tls'    => $rows['wildcard']['tls'],
				),
			),
		);
	}

	/**
	 * Whether the site reaches itself: the setup wizard's cached answer when
	 * there is one, else a live check that is not stored. The Status page
	 * never waits for a live check (a broken loopback hangs until timeout).
	 *
	 * @param bool $live Run the live check when nothing is cached.
	 * @return bool|null Null when not checked.
	 */
	public static function loopback( $live ) {
		$cached = class_exists( 'GrabWP_Tenancy_Setup_Wizard' ) ? get_transient( GrabWP_Tenancy_Setup_Wizard::LOOPBACK_TRANSIENT ) : false;
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}
		return $live ? GrabWP_Tenancy_Tenant_Installer::can_loopback() : null;
	}

	/**
	 * Up to the last LOG_LINES lines of a debug log, redacted and bounded:
	 * read backwards in chunks until enough lines or LOG_READ_BYTES.
	 *
	 * @param string $file Log path.
	 * @return array {
	 *     @type bool     $available Whether the log exists and is readable.
	 *     @type int      $size      Log size in bytes.
	 *     @type string[] $lines     Last lines, oldest first.
	 * }
	 */
	private static function log_tail( $file ) {
		$result = array(
			'available' => false,
			'size'      => 0,
			'lines'     => array(),
		);
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return $result;
		}
		$size   = max( 0, (int) filesize( $file ) );
		$offset = $size;
		$text   = '';
		$read   = 0;
		while ( $offset > 0 && $read < self::LOG_READ_BYTES && substr_count( $text, "\n" ) <= self::LOG_LINES ) {
			$length = min( self::LOG_CHUNK_BYTES, $offset, self::LOG_READ_BYTES - $read );
			$offset = $offset - $length;
			$read   = $read + $length;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$chunk = file_get_contents( $file, false, null, $offset, $length );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$text = $chunk . $text;
		}
		$lines = preg_split( '/\r\n|\r|\n/', rtrim( $text, "\r\n" ) );
		if ( $offset > 0 && count( $lines ) > 1 ) {
			array_shift( $lines ); // Partial first line, kept when it is the only one.
		}
		$lines = array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
		$cut   = $offset > 0 || count( $lines ) > self::LOG_LINES;
		$lines = self::join_query_lines( array_slice( $lines, -self::LOG_LINES ), $cut );

		$result['available'] = true;
		$result['size']      = $size;
		foreach ( $lines as $line ) {
			$line              = self::redact_text( $line );
			$result['lines'][] = function_exists( 'mb_substr' ) ? mb_substr( $line, 0, self::LOG_LINE_LENGTH, 'UTF-8' ) : substr( $line, 0, self::LOG_LINE_LENGTH );
		}
		return $result;
	}

	/**
	 * Fold the lines that follow a database query entry, up to the next
	 * timestamped entry, into that entry, so redact_text() removes a query
	 * that spans lines up to core's "made by" caller, or to the end. When the
	 * tail starts inside an entry, the lines before the first timestamped one
	 * are dropped, and a cut tail with no timestamped line shows nothing: they
	 * may be the rest of a query whose start was not read.
	 *
	 * @param string[] $lines Log lines, oldest first.
	 * @param bool     $cut   Whether earlier lines of the log were not kept.
	 * @return string[]
	 */
	private static function join_query_lines( $lines, $cut ) {
		$entry = '/^\[[^\]]*\d:\d\d:\d\d[^\]]*\]/';
		$seen  = ! $cut;
		$query = false;
		$out   = array();
		foreach ( $lines as $line ) {
			$head = (bool) preg_match( $entry, $line );
			$seen = $seen || $head;
			if ( ! $seen ) {
				continue;
			}
			if ( $query && ! $head ) {
				$out[ count( $out ) - 1 ] .= ' ' . $line;
				continue;
			}
			$out[] = $line;
			$query = (bool) preg_match( '/Query failed:|\bfor query|WordPress database error/i', $line );
		}
		return $out;
	}

	/**
	 * Drop forbidden keys and redact secrets in every string, at any depth.
	 *
	 * @param mixed $value Payload part.
	 * @return mixed
	 */
	public static function redact( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) && preg_match( self::FORBIDDEN_KEY_PATTERN, $key ) ) {
					continue;
				}
				$clean[ $key ] = self::redact( $item );
			}
			return $clean;
		}
		return is_string( $value ) ? self::redact_text( $value ) : $value;
	}

	/**
	 * Redact a line of text: SQL after "Query failed:" or core's "for query",
	 * database user, host and name in connection errors, Unix and Windows
	 * file paths, emails,
	 * credentials in URLs, Authorization values, define() values, secret
	 * assignments (quoted, unquoted to the end of the line, PHP serialized),
	 * provider keys, password hashes and long random tokens.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function redact_text( $text ) {
		// JSON-encoded log context escapes slashes.
		$text     = str_replace( '\\/', '/', (string) $text );
		$secret   = '(?:pass(?:word|wd)?|pwd|secret|token|salt|api[_-]?key|licen[cs]e(?:[_-]?key)?|auth(?:orization)?|[a-z_]*_key)';
		$patterns = array(
			// Queries can hold any option value. Core's "for query ... made by <caller>"
			// keeps the caller; MySQL's "near '...' at line N" quotes the query.
			'/(Query failed:|\bfor query)(?:.*(\smade by\s)|.*$)/i'     => '$1 [query removed]$2',
			"/(\\bnear\\s+)'.*'(\\s+at line \\d+)/i"                    => '$1[query removed]$2',
			// Database user, host and name in connection errors.
			"/(\\bfor user\\s+)'[^']*'@'[^']*'/i"                       => "\$1'[redacted]'@'[redacted]'",
			"/(\\b(?:to database|server host|server on)\\s+)'[^']*'/i"  => "\$1'[redacted]'",
			'/(\bgetaddrinfo for\s+)[^\s:]+/i'                          => '$1[host]',
			'#\b[A-Za-z]:[\\\\/][^\s"\'),;]*#'                          => '[path]',
			'#(://)[^/\s:@]+:[^/\s@]+@#'                               => '$1[redacted]@',
			'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i'               => '[email]',
			'#(?<![\w:/.\-~])/(?:[^\s"\'/]+/)+[^\s"\',;)]*#'            => '[path]',
			'/\b(Basic|Bearer)\s+[A-Za-z0-9+\/=._\-]+/i'               => '$1 [redacted]',
			'/(define\(\s*([\'"])[A-Za-z0-9_]+\2\s*,\s*)(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/' => '$1\'[redacted]\'',
			'/(s:\d+:"[^"]*' . $secret . '[^"]*";s:\d+:")[^"]*/i'       => '$1[redacted]',
			'/(' . $secret . '["\']?\s*(?:=>|[:=])\s*)("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|.*$)/i' => '$1[redacted]',
			'/\b(?:sk|rk|pk)_(?:live|test)_\S+|\bxnd_\S+|\bwhsec_\S+/'  => '[redacted]',
			'/\$(?:2[abxy]|P|H|argon2[a-z]*|wp\$2y)\$\S+/'              => '[hash]',
		);
		$text = (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), $text );

		// Long runs of letters and digits mixed (keys, hashes), not plain words or slugs.
		return (string) preg_replace_callback(
			'/(?<![A-Za-z0-9])[A-Za-z0-9_\-]{24,}/',
			function ( $m ) {
				return preg_match( '/\d/', $m[0] ) && preg_match( '/[A-Za-z]/', $m[0] ) ? '[redacted]' : $m[0];
			},
			$text
		);
	}

	/**
	 * Keys in a payload that match the forbidden pattern, as dotted paths.
	 * Empty for every payload build() returns.
	 *
	 * @param array  $data   Payload.
	 * @param string $prefix Path so far.
	 * @return string[]
	 */
	public static function forbidden_keys( $data, $prefix = '' ) {
		$found = array();
		foreach ( (array) $data as $key => $item ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( is_string( $key ) && preg_match( self::FORBIDDEN_KEY_PATTERN, $key ) ) {
				$found[] = $path;
			}
			if ( is_array( $item ) ) {
				$found = array_merge( $found, self::forbidden_keys( $item, $path ) );
			}
		}
		return $found;
	}
}
