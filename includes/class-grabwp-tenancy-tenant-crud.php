<?php
/**
 * GrabWP Tenancy Tenant CRUD Operations
 *
 * Handles create, update, and delete business logic for tenants.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Tenant_Crud {

	/**
	 * @var GrabWP_Tenancy
	 */
	private $plugin;

	/**
	 * @var GrabWP_Tenancy_Domain_Validator
	 */
	private $domain_validator;

	public function __construct( $plugin ) {
		$this->plugin           = $plugin;
		$this->domain_validator = new GrabWP_Tenancy_Domain_Validator();
	}

	/**
	 * @param array $domains Domains for the new tenant.
	 * @return array Result with 'message', 'type', and optionally 'tenant_id'.
	 */
	public function create( $domains ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'message' => __( 'Insufficient permissions.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		if ( empty( $domains ) ) {
			$domains = array( 'nodomain.local' );
		}

		$result = $this->validate_and_check_domains( $domains );
		if ( $result !== true ) {
			return $result;
		}
		$validated_domains = $this->resolve_validated_domains( $domains );

		$domain_error = $this->check_domain_mode( $validated_domains );
		if ( true !== $domain_error ) {
			return $domain_error;
		}

		$current = self::read_tenants_file();
		if ( false === $current ) {
			return self::read_error();
		}
		$tenant_mappings = $current['mappings'];

		$tenant_id    = GrabWP_Tenancy_Tenant::generate_id();
		$max_attempts = 10;
		$attempts     = 0;
		while ( isset( $tenant_mappings[ $tenant_id ] ) && $attempts < $max_attempts ) {
			$tenant_id = GrabWP_Tenancy_Tenant::generate_id();
			++$attempts;
		}

		if ( isset( $tenant_mappings[ $tenant_id ] ) ) {
			return array( 'message' => __( 'Failed to generate a unique tenant ID. Please try again.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		do_action( 'grabwp_tenancy_before_create_tenant', $tenant_id, $validated_domains );

		$tenant_mappings[ $tenant_id ] = $validated_domains;

		if ( $this->save_tenant_mappings( $tenant_mappings ) ) {
			$loader = new GrabWP_Tenancy_Loader( $this->plugin );
			$loader->create_tenant_directories( $tenant_id );

			do_action( 'grabwp_tenancy_after_create_tenant', $tenant_id, $validated_domains );

			return array(
				'message'   => __( 'Tenant created successfully.', 'grabwp-tenancy' ),
				'type'      => 'success',
				'tenant_id' => $tenant_id,
			);
		}

		return array( 'message' => __( 'Failed to create tenant.', 'grabwp-tenancy' ), 'type' => 'error' );
	}

	/**
	 * @param string $tenant_id Tenant ID to delete.
	 * @return array Result with 'message' and 'type'.
	 */
	public function delete( $tenant_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'message' => __( 'Insufficient permissions.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return array( 'message' => __( 'Invalid tenant ID.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		$current = self::read_tenants_file();
		if ( false === $current ) {
			return self::read_error();
		}
		$tenant_mappings = $current['mappings'];

		if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
			return array( 'message' => __( 'Tenant not found.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		do_action( 'grabwp_tenancy_before_delete_tenant', $tenant_id );
		unset( $tenant_mappings[ $tenant_id ] );

		if ( ! $this->save_tenant_mappings( $tenant_mappings ) ) {
			return array( 'message' => __( 'Failed to delete tenant.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		GrabWP_Tenancy_Path_Manager::clear_suspension( $tenant_id );

		$loader              = new GrabWP_Tenancy_Loader( $this->plugin );
		$directories_removed = $loader->remove_tenant_directories( $tenant_id );
		$tables_removed      = $loader->remove_tenant_database_tables( $tenant_id );

		do_action( 'grabwp_tenancy_after_delete_tenant', $tenant_id );

		if ( $directories_removed && $tables_removed ) {
			return array( 'message' => __( 'Tenant deleted successfully. All files and database tables removed.', 'grabwp-tenancy' ), 'type' => 'success' );
		} elseif ( $directories_removed ) {
			return array( 'message' => __( 'Tenant deleted successfully. Files removed, but some database tables may remain.', 'grabwp-tenancy' ), 'type' => 'warning' );
		}

		return array( 'message' => __( 'Tenant deleted, but some files or database tables may remain.', 'grabwp-tenancy' ), 'type' => 'warning' );
	}

	/**
	 * @param string $tenant_id Tenant ID to update.
	 * @param array  $domains   New domains.
	 * @return array Result with 'message' and 'type'.
	 */
	public function update( $tenant_id, $domains ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'message' => __( 'Insufficient permissions.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return array( 'message' => __( 'Invalid tenant ID.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		if ( empty( $domains ) ) {
			$domains = array( 'nodomain.local' );
		}

		$result = $this->validate_and_check_domains( $domains, $tenant_id );
		if ( $result !== true ) {
			return $result;
		}
		$validated_domains = $this->resolve_validated_domains( $domains );

		$domain_error = $this->check_domain_mode( $validated_domains );
		if ( true !== $domain_error ) {
			return $domain_error;
		}

		$current = self::read_tenants_file();
		if ( false === $current ) {
			return self::read_error();
		}
		$tenant_mappings = $current['mappings'];

		if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
			return array( 'message' => __( 'Tenant not found.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		$tenant_mappings[ $tenant_id ] = $validated_domains;

		if ( $this->save_tenant_mappings( $tenant_mappings ) ) {
			do_action( 'grabwp_tenancy_after_update_tenant', $tenant_id, $validated_domains );
			return array( 'message' => __( 'Tenant updated successfully.', 'grabwp-tenancy' ), 'type' => 'success' );
		}

		return array( 'message' => __( 'Failed to update tenant.', 'grabwp-tenancy' ), 'type' => 'error' );
	}

	/**
	 * @return true|array True if valid, error array otherwise.
	 */
	private function validate_and_check_domains( $domains, $exclude_tenant_id = '' ) {
		$validation = $this->domain_validator->validate_domains( $domains );
		if ( ! empty( $validation['invalid'] ) ) {
			$msg_key = $exclude_tenant_id
				? 'Invalid domain format(s): %s. Please use valid domain names (e.g., example.com, subdomain.example.com).'
				: 'Invalid domain format(s): %s. Please use valid domain names (e.g., example.com, subdomain.example.com).';
			return array(
				'message' => sprintf( __( $msg_key, 'grabwp-tenancy' ), implode( ', ', $validation['invalid'] ) ),
				'type'    => 'error',
			);
		}

		$validated_domains = $this->resolve_validated_domains( $domains );
		if ( empty( $validated_domains ) ) {
			return array( 'message' => __( 'Please enter at least one valid domain.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		$real_domains = $this->domain_validator->filter_real_domains( $validated_domains );
		if ( ! empty( $real_domains ) ) {
			$duplicate_check = $this->domain_validator->check_domain_uniqueness( $real_domains, $exclude_tenant_id );
			if ( ! $duplicate_check['unique'] ) {
				$msg = $exclude_tenant_id
					? __( 'Domain(s) already in use by other tenants: %s. Each domain can only be assigned to one tenant.', 'grabwp-tenancy' )
					: __( 'Domain(s) already in use: %s. Each domain can only be assigned to one tenant.', 'grabwp-tenancy' );
				return array( 'message' => sprintf( $msg, implode( ', ', $duplicate_check['duplicates'] ) ), 'type' => 'error' );
			}
		}

		return true;
	}

	private function resolve_validated_domains( $domains ) {
		$validation        = $this->domain_validator->validate_domains( $domains );
		$validated_domains = $validation['valid'];

		if ( empty( $validated_domains ) && in_array( 'nodomain.local', $domains, true ) ) {
			$validated_domains = array( 'nodomain.local' );
		}

		return $validated_domains;
	}

	/**
	 * Domain mode needs at least one real domain for each site.
	 *
	 * @param array $validated_domains Validated domains.
	 * @return true|array True when allowed, error array otherwise.
	 */
	private function check_domain_mode( $validated_domains ) {
		if ( 'domain' !== grabwp_tenancy_get_routing_mode() ) {
			return true;
		}

		if ( empty( $this->domain_validator->filter_real_domains( $validated_domains ) ) ) {
			return array( 'message' => __( 'Domain mode needs at least one domain for each site.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		return true;
	}

	/**
	 * @return array Error result for an unreadable tenants.php.
	 */
	private static function read_error() {
		return array( 'message' => __( 'Could not read tenants.php, so nothing was changed. Check the file and its permissions.', 'grabwp-tenancy' ), 'type' => 'error' );
	}

	/**
	 * Read tenants.php fresh from disk.
	 *
	 * A missing file reads as empty. A file that exists but cannot be read, does
	 * not parse, or does not define the mappings array returns false, so a
	 * writer can abort instead of saving empty mappings over real ones.
	 *
	 * @return array|false { exists: bool, mappings: array, routing: array|null } or false.
	 */
	public static function read_tenants_file() {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		clearstatcache( true, $mappings_file );

		if ( ! file_exists( $mappings_file ) ) {
			return array(
				'exists'   => false,
				'mappings' => array(),
				'routing'  => null,
			);
		}

		if ( ! is_readable( $mappings_file ) ) {
			return false;
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $mappings_file, true );
		}

		$parsed = self::parse_tenants_file( $mappings_file );
		return false === $parsed ? false : array_merge( array( 'exists' => true ), $parsed );
	}

	/**
	 * Include a tenants.php file and return what it defines.
	 *
	 * @param string $file Path.
	 * @return array|false { mappings: array, routing: array|null } or false when it does not parse.
	 */
	private static function parse_tenants_file( $file ) {
		$tenant_mappings        = null;
		$grabwp_tenancy_routing = null;
		ob_start();
		try {
			include $file;
		} catch ( \Throwable $e ) {
			ob_end_clean();
			return false;
		}
		ob_end_clean();

		if ( ! is_array( $tenant_mappings ) ) {
			return false;
		}

		return array(
			'mappings' => $tenant_mappings,
			'routing'  => is_array( $grabwp_tenancy_routing ) ? $grabwp_tenancy_routing : null,
		);
	}

	/**
	 * Routing mode of a routing block, '' when none is valid.
	 *
	 * @param array|null $routing Routing block.
	 * @return string
	 */
	private static function routing_mode_of( $routing ) {
		return ( is_array( $routing ) && isset( $routing['mode'] ) && in_array( $routing['mode'], grabwp_tenancy_routing_modes(), true ) ) ? $routing['mode'] : '';
	}

	/**
	 * The only writer of tenants.php.
	 *
	 * With $routing null, the routing block currently on disk is read right
	 * before the write and emitted again, so no mappings change can drop the
	 * routing mode. When that read fails, nothing is written. The new file is
	 * read back before it replaces the old one: it must parse and hold the
	 * same sites and routing mode, or the old file stays.
	 *
	 * @param array      $tenant_mappings Tenant ID => domains.
	 * @param array|null $routing         Routing block to write, or null to keep the stored one.
	 * @return bool
	 */
	public static function write_tenants_file( array $tenant_mappings, $routing = null ) {
		if ( null === $routing ) {
			$current = self::read_tenants_file();
			if ( false === $current ) {
				return false;
			}
			$routing = $current['routing'];
		}

		$content  = "<?php\n";
		$content .= "/**\n";
		$content .= " * Tenant Domain Mappings\n";
		$content .= " * Format: \$tenant_mappings['tenant_id'] = array( 'domain1', 'domain2' );\n";
		$content .= " */\n\n";
		$content .= self::routing_block( $routing );
		$content .= "\$tenant_mappings = array(\n";

		foreach ( $tenant_mappings as $tenant_id => $domains ) {
			$content .= "    '" . $tenant_id . "' => array(\n";
			foreach ( (array) $domains as $key => $domain ) {
				if ( is_string( $key ) ) {
					// Named settings such as 'protocol' keep their key.
					if ( is_scalar( $domain ) ) {
						$content .= '        ' . var_export( $key, true ) . ' => ' . var_export( $domain, true ) . ",\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					}
					continue;
				}
				$content .= "        '" . $domain . "',\n";
			}
			$content .= "    ),\n";
		}

		$content .= ");\n";

		$count  = count( $tenant_mappings );
		$mode   = self::routing_mode_of( $routing );
		$verify = function ( $tmp_file ) use ( $count, $mode ) {
			$parsed = self::parse_tenants_file( $tmp_file );
			if ( function_exists( 'opcache_invalidate' ) ) {
				opcache_invalidate( $tmp_file, true );
			}
			return false !== $parsed && count( $parsed['mappings'] ) === $count && self::routing_mode_of( $parsed['routing'] ) === $mode;
		};

		return GrabWP_Tenancy_Path_Manager::atomic_put_php_file( GrabWP_Tenancy_Path_Manager::get_tenants_file_path(), $content, $verify );
	}

	/**
	 * PHP source of the routing block; '' when there is nothing valid to keep.
	 *
	 * @param array|null $routing Key 'mode'.
	 * @return string
	 */
	private static function routing_block( $routing ) {
		if ( ! is_array( $routing ) ) {
			return '';
		}

		$lines = '';
		if ( isset( $routing['mode'] ) && in_array( $routing['mode'], grabwp_tenancy_routing_modes(), true ) ) {
			$lines .= "    'mode' => " . var_export( $routing['mode'], true ) . ",\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		if ( '' === $lines ) {
			return '';
		}

		return "// Routing mode (path, domain or both).\n\$grabwp_tenancy_routing = array(\n" . $lines . ");\n\n";
	}

	private function save_tenant_mappings( $tenant_mappings ) {
		return self::write_tenants_file( $tenant_mappings );
	}
}
