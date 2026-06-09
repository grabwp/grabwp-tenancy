<?php
/**
 * GrabWP Tenancy Tenant Class
 *
 * Handles tenant data structure, validation, and lifecycle management.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Tenant Class
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_Tenant {

	/**
	 * Tenant ID
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Tenant domains
	 *
	 * @var array
	 */
	private $domains;

	/**
	 * Tenant status
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Created date
	 *
	 * @var string
	 */
	private $created_date;

	/**
	 * Configuration array
	 *
	 * @var array
	 */
	private $configuration;

	/**
	 * Constructor
	 *
	 * @param string $id Tenant ID
	 * @param array  $data Tenant data
	 */
	public function __construct( $id = '', $data = array() ) {
		$this->id            = $id;
		$this->domains       = isset( $data['domains'] ) ? $data['domains'] : array();
		$this->status        = isset( $data['status'] ) ? $data['status'] : 'active';
		$this->created_date  = isset( $data['created_date'] ) ? $data['created_date'] : current_time( 'mysql' );
		$this->configuration = isset( $data['configuration'] ) ? $data['configuration'] : array();
	}

	/**
	 * Generate unique tenant ID
	 *
	 * @return string Unique tenant ID
	 */
	public static function generate_id() {
		$letters = 'abcdefghijklmnopqrstuvwxyz';
		$alphanumeric = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$id         = '';

		for ( $i = 0; $i < 5; $i++ ) {
			$id .= $alphanumeric[ wp_rand( 0, strlen( $alphanumeric ) - 1 ) ];
		}
		$id = $letters[ wp_rand( 0, strlen( $letters ) - 1 ) ] . $id;

		return $id;
	}

	/**
	 * Validate tenant ID format
	 *
	 * @param string $id Tenant ID
	 * @return bool Valid status
	 */
	public static function validate_id( $id ) {
		return preg_match( '/^[a-z0-9]{6}$/', $id );
	}

	/**
	 * Validate domain format
	 *
	 * @param string $domain Domain name
	 * @return bool Valid status
	 */
	public static function validate_domain( $domain ) {
		return filter_var( $domain, FILTER_VALIDATE_DOMAIN ) !== false;
	}

	/**
	 * Get tenant ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get tenant domains
	 *
	 * @return array
	 */
	public function get_domains() {
		return $this->domains;
	}

	/**
	 * Get primary domain
	 *
	 * @return string
	 */
	public function get_primary_domain() {
		return isset( $this->domains[0] ) ? $this->domains[0] : '';
	}

	/**
	 * Get real (non-placeholder) domains.
	 *
	 * @return array
	 */
	public function get_real_domains() {
		return array_filter(
			$this->domains,
			function ( $d ) {
				return 'nodomain.local' !== $d;
			}
		);
	}

	/**
	 * Get tenant site URL (front-end home page).
	 *
	 * Domain-based: https://tenant-domain.com
	 * Path-based:   https://mainsite.com/site/{tenant_id}/
	 *
	 * @return string
	 */
	public function get_site_url() {
		$real_domains = $this->get_real_domains();
		$protocol     = is_ssl() ? 'https' : 'http';

		if ( ! empty( $real_domains ) ) {
			return $protocol . '://' . reset( $real_domains );
		}

		return site_url( '/site/' . $this->id );
	}

	/**
	 * Get tenant status
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Check if tenant is active
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->status === 'active';
	}

	/**
	 * Generate domain hash for token security
	 *
	 * @param string $domain Domain name
	 * @param string $tenant_id Tenant ID
	 * @return string Hash
	 */
	public static function generate_domain_hash( $domain, $tenant_id ) {
		// Normalize domain (lowercase, remove www)
		$normalized_domain = strtolower( $domain );
		$normalized_domain = preg_replace( '/^www\./', '', $normalized_domain );

		// Generate secure hash using domain + tenant_id + WordPress salt
		return hash( 'sha256', $normalized_domain . $tenant_id . AUTH_SALT );
	}

	/**
	 * Generate or get global admin access token
	 *
	 * @return string|false Token on success, false on failure
	 */
	public static function get_global_admin_token() {
		$config_file = GrabWP_Tenancy_Path_Manager::get_tokens_file_path();

		// Check if valid token exists
		if ( file_exists( $config_file ) ) {
			$admin_token = null;
			include $config_file;

			if ( isset( $admin_token ) &&
				isset( $admin_token['token'] ) &&
				isset( $admin_token['expires'] ) &&
				$admin_token['expires'] > time() ) {
				return $admin_token['token'];
			}
		}

		// Generate new token if none exists or expired
		$token = wp_generate_password( 32, false );

		// Store token with expiration (24 hours)
		$token_data = array(
			'token'     => $token,
			'expires'   => time() + ( 24 * 60 * 60 ), // 24 hours
			'generated' => current_time( 'timestamp' ),
		);

		$content  = "<?php\n";
		$content .= '// Generated: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
		$content .= '// Expires: ' . gmdate( 'Y-m-d H:i:s', $token_data['expires'] ) . " UTC\n";
		$content .= '$admin_token = ' . self::format_php_array( $token_data ) . ";\n";

		if ( GrabWP_Tenancy_Path_Manager::atomic_put_php_file( $config_file, $content ) ) {
			return $token;
		}

		return false;
	}

	/**
	 * Get admin access URL with token and hash.
	 *
	 * Handles both domain-based and path-based (nodomain.local) routing.
	 *
	 * @return string
	 */
	public function get_admin_access_url() {
		$real_domains = $this->get_real_domains();
		$protocol     = is_ssl() ? 'https' : 'http';
		$token        = self::get_global_admin_token();

		if ( ! empty( $real_domains ) ) {
			$domain    = reset( $real_domains );
			$admin_url = $protocol . '://' . $domain . '/wp-admin/';

			if ( $token ) {
				$hash       = self::generate_domain_hash( $domain, $this->id );
				$admin_url .= '?grabwp_token=' . rawurlencode( $token ) . '&grabwp_hash=' . rawurlencode( $hash );
			}

			return $admin_url;
		}

		// Path-based routing fallback.
		$path_url  = site_url( '/site/' . $this->id );
		$admin_url = $path_url . '/wp-admin/';

		if ( $token ) {
			$hash       = self::generate_domain_hash( 'nodomain.local', $this->id );
			$admin_url .= '?grabwp_token=' . rawurlencode( $token ) . '&grabwp_hash=' . rawurlencode( $hash );
		}

		return $admin_url;
	}

	/**
	 * Validate admin token and domain hash.
	 *
	 * @param string $token Token to validate.
	 * @param string $hash  Domain hash (mandatory).
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_admin_token( $token, $hash = '' ) {
		if ( empty( $token ) || empty( $hash ) ) {
			return false;
		}

		$config_file = GrabWP_Tenancy_Path_Manager::get_tokens_file_path();
		if ( ! file_exists( $config_file ) ) {
			return false;
		}

		$admin_token = null;
		include $config_file;

		if ( ! isset( $admin_token ) ||
			! isset( $admin_token['token'] ) ||
			! isset( $admin_token['expires'] ) ||
			! hash_equals( $admin_token['token'], $token ) ||
			$admin_token['expires'] <= time() ) {
			return false;
		}

		if ( defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) && in_array( GRABWP_TENANCY_ROUTING_METHOD, array( 'path', 'query' ), true ) ) {
			$current_domain = 'nodomain.local';
		} else {
			$current_domain = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		}

		$tenant_id = defined( 'GRABWP_TENANCY_TENANT_ID' ) ? GRABWP_TENANCY_TENANT_ID : '';

		if ( empty( $current_domain ) || empty( $tenant_id ) ) {
			return false;
		}

		$expected_hash = self::generate_domain_hash( $current_domain, $tenant_id );
		if ( ! hash_equals( $expected_hash, $hash ) ) {
			return false;
		}

		// Atomically consume — only the first request to rename() succeeds
		$consumed = $config_file . '.consumed.' . uniqid( '', true );
		if ( ! @rename( $config_file, $consumed ) ) {
			return false;
		}
		@unlink( $consumed );

		return true;
	}

	/**
	 * Load tenant domains from the mappings file.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return array Domains array (may be empty).
	 */
	private static function load_domains( $tenant_id ) {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		if ( ! file_exists( $mappings_file ) || ! is_readable( $mappings_file ) ) {
			return array();
		}

		$tenant_mappings = array();
		ob_start();
		include $mappings_file;
		ob_end_clean();

		if ( is_array( $tenant_mappings ) && isset( $tenant_mappings[ $tenant_id ] ) ) {
			return (array) $tenant_mappings[ $tenant_id ];
		}

		return array();
	}

	/**
	 * Resolve tenant site URL from a tenant ID (no Tenant object needed).
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return string Site URL, or empty string if tenant not found.
	 */
	public static function resolve_site_url( $tenant_id ) {
		$domains = self::load_domains( $tenant_id );
		if ( empty( $domains ) ) {
			return '';
		}

		$tenant = new self( $tenant_id, array( 'domains' => $domains ) );
		return $tenant->get_site_url();
	}

	/**
	 * Resolve tenant admin URL from a tenant ID (no Tenant object needed).
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return string Admin URL, or empty string if tenant not found.
	 */
	public static function resolve_admin_url( $tenant_id ) {
		$domains = self::load_domains( $tenant_id );
		if ( empty( $domains ) ) {
			return '';
		}

		$tenant = new self( $tenant_id, array( 'domains' => $domains ) );
		return $tenant->get_admin_access_url();
	}

	/**
	 * Resolve tenant site URL from domains array + tenant ID.
	 *
	 * Useful in clone/fix-urls contexts where domains are already known.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @param array  $domains   Domains array.
	 * @return string Site URL.
	 */
	public static function build_site_url( $tenant_id, $domains ) {
		$tenant = new self( $tenant_id, array( 'domains' => $domains ) );
		return $tenant->get_site_url();
	}

	/**
	 * Format a PHP array for safe output in configuration files
	 *
	 * @param array $array Array to format
	 * @return string Formatted PHP array string
	 */
	private static function format_php_array( $array ) {
		if ( ! is_array( $array ) ) {
			if ( is_string( $array ) ) {
				return "'" . addslashes( $array ) . "'";
			} elseif ( is_bool( $array ) ) {
				return $array ? 'true' : 'false';
			} elseif ( is_null( $array ) ) {
				return 'null';
			} elseif ( is_numeric( $array ) ) {
				return (string) $array;
			} else {
				return "'" . addslashes( maybe_serialize( $array ) ) . "'";
			}
		}

		$output = "array(\n";
		foreach ( $array as $key => $value ) {
			$formatted_key = is_string( $key ) ? "'" . addslashes( $key ) . "'" : $key;
			if ( is_string( $value ) ) {
				$formatted_value = "'" . addslashes( $value ) . "'";
			} elseif ( is_bool( $value ) ) {
				$formatted_value = $value ? 'true' : 'false';
			} elseif ( is_null( $value ) ) {
				$formatted_value = 'null';
			} elseif ( is_numeric( $value ) ) {
				$formatted_value = (string) $value;
			} else {
				$formatted_value = "'" . addslashes( maybe_serialize( $value ) ) . "'";
			}
			$output .= "    {$formatted_key} => {$formatted_value},\n";
		}
		$output .= ')';

		return $output;
	}
}

/**
 * Get the front-end URL for a tenant by ID.
 *
 * @param string $tenant_id Tenant ID.
 * @return string Site URL, or empty string if tenant not found.
 */
function grabwp_tenancy_get_tenant_url( $tenant_id ) {
	return GrabWP_Tenancy_Tenant::resolve_site_url( $tenant_id );
}
