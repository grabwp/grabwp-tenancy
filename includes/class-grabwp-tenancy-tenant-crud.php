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

		$mappings_file   = $this->get_mappings_file_path();
		$tenant_mappings = array();
		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

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

		$mappings_file   = $this->get_mappings_file_path();
		$tenant_mappings = array();
		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

		if ( ! isset( $tenant_mappings[ $tenant_id ] ) ) {
			return array( 'message' => __( 'Tenant not found.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

		do_action( 'grabwp_tenancy_before_delete_tenant', $tenant_id );
		unset( $tenant_mappings[ $tenant_id ] );

		if ( ! $this->save_tenant_mappings( $tenant_mappings ) ) {
			return array( 'message' => __( 'Failed to delete tenant.', 'grabwp-tenancy' ), 'type' => 'error' );
		}

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

		$mappings_file   = $this->get_mappings_file_path();
		$tenant_mappings = array();
		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

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

	private function get_mappings_file_path() {
		return GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
	}

	private function save_tenant_mappings( $tenant_mappings ) {
		$mappings_file = $this->get_mappings_file_path();

		$content  = "<?php\n";
		$content .= "/**\n";
		$content .= " * Tenant Domain Mappings\n";
		$content .= " * Format: \$tenant_mappings['tenant_id'] = array( 'domain1', 'domain2' );\n";
		$content .= " */\n\n";
		$content .= "\$tenant_mappings = array(\n";

		foreach ( $tenant_mappings as $tenant_id => $domains ) {
			$content .= "    '" . $tenant_id . "' => array(\n";
			foreach ( $domains as $domain ) {
				$content .= "        '" . $domain . "',\n";
			}
			$content .= "    ),\n";
		}

		$content .= ");\n";

		return GrabWP_Tenancy_Path_Manager::atomic_put_php_file( $mappings_file, $content );
	}
}
