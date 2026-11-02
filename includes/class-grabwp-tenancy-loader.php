<?php
/**
 * GrabWP Tenancy Loader Class
 *
 * Handles WordPress integration, content path management, and upload directory isolation.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Loader Class
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_Loader {

	/**
	 * Plugin instance
	 *
	 * @var GrabWP_Tenancy
	 */
	private $plugin;

	/**
	 * Constructor
	 *
	 * @param GrabWP_Tenancy $plugin Plugin instance
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Admin access token handling - early priority for tenant sites
		if ( $this->plugin->is_tenant() ) {
			add_action( 'init', array( $this, 'handle_admin_token' ), 5 );
			add_filter( 'login_message', array( $this, 'display_login_error_message' ) );

			// Fixes for path routing where REQUEST_URI is stripped of /{prefix}/{tenant_id}
			if ( defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) && 'path' === GRABWP_TENANCY_ROUTING_METHOD ) {
				add_filter( 'wp_admin_canonical_url', array( $this, 'fix_tenant_admin_canonical_url' ) );

				// redirect_canonical() compares WP_HOME against the stripped REQUEST_URI
				// and always finds a mismatch, causing an infinite 301 loop.
				add_filter( 'redirect_canonical', '__return_false' );

				// Fix redirects that use the stripped REQUEST_URI (e.g. _wp_http_referer,
				// wp_get_referer, admin form redirects) — prepend tenant path prefix.
				add_filter( 'wp_redirect', array( $this, 'fix_tenant_admin_redirect' ), 10, 1 );
			}
		}
		// Allow pro plugin to extend
		do_action( 'grabwp_tenancy_loader_init', $this );
	}

	/**
	 * Fix the admin canonical URL for path-based tenant routing.
	 *
	 * WordPress derives the canonical URL from WP_SITEURL (which is the main site URL,
	 * e.g. http://localhost/wp-admin/), but tenant pages are served under
	 * /site/{tenant_id}/wp-admin/. This filter replaces the base with the tenant path.
	 *
	 * @param string $url The canonical URL.
	 * @return string Corrected canonical URL.
	 */
	public function fix_tenant_admin_canonical_url( $url ) {
		if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
			return $url;
		}

		$server_info  = grabwp_tenancy_get_server_info();
		$base         = $server_info['protocol'] . '://' . $server_info['host'];
		$tenant_base  = $base . '/' . grabwp_tenancy_get_path_prefix() . '/' . grabwp_tenancy_get_tenant_path_slug();

		// Replace the origin + /wp-admin prefix with the tenant-prefixed equivalent
		if ( strpos( $url, $base . '/wp-admin' ) === 0 ) {
			$url = $tenant_base . substr( $url, strlen( $base ) );
		}

		return $url;
	}

	/**
	 * Fix admin redirects for path-based tenant routing.
	 *
	 * Because REQUEST_URI is stripped of /site/{tenant_id}, any WordPress redirect
	 * built from it (e.g. _wp_http_referer, wp_get_referer) will lack the tenant
	 * prefix and land on the main site — causing auth failure and reauth loop.
	 *
	 * @param string $location Redirect URL.
	 * @return string Corrected redirect URL with tenant path prefix.
	 */
	public function fix_tenant_admin_redirect( $location ) {
		if ( ! defined( 'GRABWP_TENANCY_TENANT_ID' ) ) {
			return $location;
		}

		$tenant_prefix = '/' . grabwp_tenancy_get_path_prefix() . '/' . grabwp_tenancy_get_tenant_path_slug();

		// Only fix relative URLs or same-host absolute URLs that target wp-admin/wp-login
		// and don't already have the tenant prefix
		if ( strpos( $location, $tenant_prefix ) !== false ) {
			return $location;
		}

		// Relative URL starting with /wp-admin or /wp-login.php
		if ( preg_match( '#^/(wp-admin|wp-login\.php)#', $location ) ) {
			return $tenant_prefix . $location;
		}

		// Absolute URL on the same host
		$server_info = grabwp_tenancy_get_server_info();
		$base        = $server_info['protocol'] . '://' . $server_info['host'];
		if ( strpos( $location, $base . '/wp-admin' ) === 0 || strpos( $location, $base . '/wp-login.php' ) === 0 ) {
			return $base . $tenant_prefix . substr( $location, strlen( $base ) );
		}

		return $location;
	}

	/**
	 * Create tenant directories
	 *
	 * @param string $tenant_id Tenant ID
	 * @return bool Success status
	 */
	public function create_tenant_directories( $tenant_id ) {
		$upload_dir = GrabWP_Tenancy_Path_Manager::get_tenant_upload_dir( $tenant_id );

		if ( ! file_exists( $upload_dir ) ) {
			return wp_mkdir_p( $upload_dir );
		}

		return true;
	}

	/**
	 * Remove tenant directories
	 *
	 * @param string $tenant_id Tenant ID
	 * @return bool Success status
	 */
	public function remove_tenant_directories( $tenant_id ) {
		$base_path = GrabWP_Tenancy_Path_Manager::get_configured_base_path();
		$tenant_dir = $base_path . '/' . $tenant_id;

		if ( file_exists( $tenant_dir ) ) {
			return $this->recursive_rmdir( $tenant_dir );
		}

		return true;
	}

	/**
	 * Remove tenant database tables
	 *
	 * @param string $tenant_id Tenant ID
	 * @return bool Success status
	 */
	public function remove_tenant_database_tables( $tenant_id ) {
		global $wpdb;

		// Validate tenant ID
		if ( ! grabwp_tenancy_validate_tenant_id( $tenant_id ) ) {
			return false;
		}

		// Get all tables with tenant prefix
		$tenant_prefix = $tenant_id . '_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for tenant table cleanup, no caching needed for administrative operation
		$tables = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$tenant_prefix . '%'
			),
			ARRAY_N
		);

		if ( empty( $tables ) ) {
			return true; // No tables to remove
		}

		$success = true;
		foreach ( $tables as $table ) {
			$table_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $table[0] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for tenant table cleanup, no caching needed for administrative operation
			$result = $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
			
			if ( false === $result ) {
				$success = false;
				GrabWP_Tenancy_Logger::log( 'Failed to drop table '.$table_name );
			}
		}

		return $success;
	}

	/**
	 * Recursively remove directory, safely handling symlinks.
	 *
	 * WP_Filesystem's rmdir() follows symlinks when deleting recursively, which
	 * would destroy the original shared plugin/theme that a tenant symlink points
	 * to. We therefore walk the tree manually: symlinks are unlink()ed (not
	 * followed), real files/dirs are handled by WP_Filesystem.
	 *
	 * @param string $dir Directory path
	 * @return bool Success status
	 */
	private function recursive_rmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		// Use WordPress filesystem API
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return false;
		}

		// Walk the directory manually so we can detect symlinks before
		// WP_Filesystem sees them (is_dir() follows symlinks in PHP).
		$items = @scandir( $dir );
		if ( false === $items ) {
			return false;
		}

		$success = true;
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;

			if ( is_link( $path ) ) {
				// Remove only the symlink — never follow it into the target.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				if ( ! @unlink( $path ) ) {
					$success = false;
				}
			} elseif ( is_dir( $path ) ) {
				if ( ! $this->recursive_rmdir( $path ) ) {
					$success = false;
				}
			} else {
				if ( ! $wp_filesystem->delete( $path ) ) {
					$success = false;
				}
			}
		}

		// Remove the (now-empty) directory itself.
		if ( $success ) {
			return $wp_filesystem->rmdir( $dir, false );
		}

		return false;
	}

	/**
	 * Handle admin access token for auto-login on tenant sites
	 */
	public function handle_admin_token() {
		// Only handle on login page or admin pages
		if ( ! is_admin() && ! $this->is_login_page() ) {
			return;
		}

		// Only process on tenant sites
		if ( ! $this->plugin->is_tenant() ) {
			return;
		}

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Auto-login tokens from main site, validated via hash
		$token = isset( $_GET['grabwp_token'] ) ? sanitize_text_field( wp_unslash( $_GET['grabwp_token'] ) ) : '';

		$hash = isset( $_GET['grabwp_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['grabwp_hash'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $token ) ) {
			return;
		}

		// Neither the token response nor its redirect may be cached.
		nocache_headers();

		// Validate global token and hash using tenant class methods
		$reason = '';
		if ( ! GrabWP_Tenancy_Tenant::validate_admin_token( $token, $hash, $reason ) ) {
			$this->handle_token_error( $reason );
			return;
		}

		// Get tenant ID
		$tenant_id = $this->plugin->get_tenant_id();
		if ( ! $tenant_id ) {
			$this->handle_token_error( 'tenant' );
			return;
		}

		// Get admin user with lowest ID
		$admin_user = $this->get_lowest_admin_user();
		if ( ! $admin_user ) {
			$this->handle_token_error( 'no_admin' );
			return;
		}

		// Log the user in
		wp_set_current_user( $admin_user->ID, $admin_user->user_login );
		wp_set_auth_cookie( $admin_user->ID, true );

		GrabWP_Tenancy_Logger::log( 'Admin user logged in: ' . $admin_user->user_login );

		// Redirect to wp-admin to remove token from URL.
		// For path routing, admin_url() returns the base domain URL (e.g. http://localhost/wp-admin/)
		// which misses the tenant path prefix and loads the main site instead of the tenant.
		// We must manually construct the tenant admin URL with /{prefix}/{tenant_id}/ prefix.
		if ( defined( 'GRABWP_TENANCY_ROUTING_METHOD' ) && 'path' === GRABWP_TENANCY_ROUTING_METHOD ) {
			$server_info  = grabwp_tenancy_get_server_info();
			$redirect_url = $server_info['protocol'] . '://' . $server_info['host'] . '/' . grabwp_tenancy_get_path_prefix() . '/' . GRABWP_TENANCY_TENANT_ID . '/wp-admin/';
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $server_info ) {
					$hosts[] = $server_info['host'];
					return $hosts;
				}
			);
		} else {
			$redirect_url = admin_url();
		}
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Redirect a failed token login to the login page with the reason.
	 *
	 * The reason travels in the URL, so it shows even when the tenant
	 * database cannot store an option.
	 *
	 * @param string $reason Key of get_token_error_messages().
	 */
	private function handle_token_error( $reason ) {
		GrabWP_Tenancy_Logger::log( 'Admin token login failed: ' . $reason );

		$login_url = add_query_arg(
			array(
				'grabwp_token_error'  => '1',
				'grabwp_token_reason' => $reason,
			),
			wp_login_url()
		);

		wp_redirect( $login_url );
		exit;
	}

	/**
	 * Login page messages for failed token logins, keyed by reason.
	 *
	 * @return array
	 */
	private function get_token_error_messages() {
		return array(
			'invalid'  => __( 'This admin access link is not valid. Open the site admin again from the main site.', 'grabwp-tenancy' ),
			'expired'  => __( 'This admin access link has expired. Open the site admin again from the main site.', 'grabwp-tenancy' ),
			'address'  => __( 'This admin access link was made for a different site address.', 'grabwp-tenancy' ),
			'tenant'   => __( 'This site could not be identified.', 'grabwp-tenancy' ),
			'no_admin' => __( 'No administrator account was found in this site. The site may not be installed yet.', 'grabwp-tenancy' ),
		);
	}

	/**
	 * Display token error message on login page
	 *
	 * @param string $message Existing login message
	 * @return string Modified login message
	 */
	public function display_login_error_message( $message ) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only check for error display parameter
		if ( isset( $_GET['grabwp_token_error'] ) ) {
			$reason   = isset( $_GET['grabwp_token_reason'] ) ? sanitize_key( wp_unslash( $_GET['grabwp_token_reason'] ) ) : '';
			$messages = $this->get_token_error_messages();
			$error    = isset( $messages[ $reason ] ) ? $messages[ $reason ] : $messages['invalid'];
			$message .= '<div id="login_error">' . esc_html( $error ) . '</div>';

			// Older versions stored the message in an option that was never shown.
			delete_option( 'grabwp_tenancy_token_error' );
		}
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $message;
	}

	/**
	 * Check if current page is login page
	 */
	private function is_login_page() {
		return in_array( $GLOBALS['pagenow'], array( 'wp-login.php' ) );
	}

	/**
	 * Get admin user with lowest ID
	 *
	 * @return WP_User|false Admin user object or false if not found
	 */
	private function get_lowest_admin_user() {
		$admin_users = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
			)
		);

		return ! empty( $admin_users ) ? $admin_users[0] : false;
	}
}
