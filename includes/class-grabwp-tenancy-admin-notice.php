<?php
/**
 * GrabWP Tenancy Admin Notice Class
 *
 * Short admin notices that point to the status page for details and fixes.
 * AJAX handlers delegate to GrabWP_Tenancy_Installer.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Admin_Notice {

	/**
	 * Register admin notice hooks and AJAX handlers.
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );

		// AJAX actions (used by status page Fix Now buttons and admin notice auto-install).
		add_action( 'wp_ajax_grabwp_install_mu_plugin', array( __CLASS__, 'ajax_install_mu_plugin' ) );
		add_action( 'wp_ajax_grabwp_install_loader', array( __CLASS__, 'ajax_install_loader' ) );
		add_action( 'wp_ajax_grabwp_fix_root_htaccess', array( __CLASS__, 'ajax_fix_root_htaccess' ) );
		add_action( 'wp_ajax_grabwp_fix_data_htaccess', array( __CLASS__, 'ajax_fix_data_htaccess' ) );
		add_action( 'wp_ajax_grabwp_fix_index_protection', array( __CLASS__, 'ajax_fix_index_protection' ) );
		add_action( 'wp_ajax_grabwp_lock_shared_files', array( __CLASS__, 'ajax_lock_shared_files' ) );
		add_action( 'wp_ajax_grabwp_unlock_shared_files', array( __CLASS__, 'ajax_unlock_shared_files' ) );

		// Hosting setup card. Not named grabwp_install_* / grabwp_fix_*, so
		// "Fix automatically" never runs them: each one needs a confirmation.
		add_action( 'wp_ajax_grabwp_hosting_proxy_https', array( __CLASS__, 'ajax_hosting_action' ) );
		add_action( 'wp_ajax_grabwp_hosting_proxy_https_undo', array( __CLASS__, 'ajax_hosting_action' ) );
		add_action( 'wp_ajax_grabwp_hosting_https_urls', array( __CLASS__, 'ajax_hosting_action' ) );
		add_action( 'wp_ajax_grabwp_hosting_https_urls_undo', array( __CLASS__, 'ajax_hosting_action' ) );
		add_action( 'wp_ajax_grabwp_hosting_multisite_cleanup', array( __CLASS__, 'ajax_hosting_action' ) );
		add_action( 'wp_ajax_grabwp_hosting_multisite_cleanup_undo', array( __CLASS__, 'ajax_hosting_action' ) );
	}

	// =========================================================================
	// Admin Notices (short — details on status page)
	// =========================================================================

	/**
	 * Show short admin notices for critical plugin issues.
	 */
	public static function show_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status_url = admin_url( 'admin.php?page=grabwp-tenancy-status' );

		// wp-config.php loader not active.
		if ( ! defined( 'GRABWP_TENANCY_LOADED' ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'load.php is not included in wp-config.php.', 'grabwp-tenancy' ),
				esc_url( $status_url ),
				esc_html__( 'View Status →', 'grabwp-tenancy' )
			);
		}

		// MU-plugin not installed (only on plugin pages to avoid noise).
		if ( self::is_plugin_page() && ! file_exists( GrabWP_Tenancy_Installer::get_mu_plugin_path() ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'MU-plugin is not installed. Tenant features may not work.', 'grabwp-tenancy' ),
				esc_url( $status_url ),
				esc_html__( 'View Status →', 'grabwp-tenancy' )
			);
		}

		// Base directory missing.
		if ( class_exists( 'GrabWP_Tenancy_Path_Manager' ) && ! is_dir( GrabWP_Tenancy_Path_Manager::get_tenants_base_dir() ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Data directory is missing.', 'grabwp-tenancy' ),
				esc_url( $status_url ),
				esc_html__( 'View Status →', 'grabwp-tenancy' )
			);
		}

		// .htaccess check (only on plugin pages to avoid noise).
		global $is_apache;
		if ( self::is_plugin_page() && $is_apache ) {
			$root_htaccess_path = ABSPATH . '.htaccess';
			$htaccess_needs_fix = false;

			if ( file_exists( $root_htaccess_path ) && is_readable( $root_htaccess_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
				$root_htaccess_content = file_get_contents( $root_htaccess_path );
				if ( false !== $root_htaccess_content ) {
					$grabwp_pos = strpos( $root_htaccess_content, '# BEGIN GrabWP Tenancy' );
					$wp_pos     = strpos( $root_htaccess_content, '# BEGIN WordPress' );

					$has_rewrite_rule = GrabWP_Tenancy_Installer::htaccess_has_site_path_rules(
						$root_htaccess_content,
						grabwp_tenancy_get_path_prefix(),
						grabwp_tenancy_get_routing_mode()
					);

					if ( false === $grabwp_pos || ! $has_rewrite_rule || ( false !== $wp_pos && $grabwp_pos > $wp_pos ) ) {
						$htaccess_needs_fix = true;
					}
				}
			} elseif ( ! file_exists( $root_htaccess_path ) ) {
				$htaccess_needs_fix = true;
			}

			if ( $htaccess_needs_fix ) {
				printf(
					'<div class="notice notice-warning"><p><strong>GrabWP Tenancy:</strong> %s <a href="%s">%s</a></p></div>',
					esc_html__( 'Root .htaccess needs to be updated for tenant routing.', 'grabwp-tenancy' ),
					esc_url( $status_url ),
					esc_html__( 'View Status →', 'grabwp-tenancy' )
				);
			}
		}
	}

	// =========================================================================
	// AJAX Handlers (thin wrappers → Installer)
	// =========================================================================

	public static function ajax_install_mu_plugin() {
		check_ajax_referer( 'grabwp_install_mu_plugin' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::install_mu_plugin() );
	}

	public static function ajax_install_loader() {
		check_ajax_referer( 'grabwp_install_loader' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::install_loader() );
	}

	public static function ajax_fix_root_htaccess() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::fix_root_htaccess() );
	}

	public static function ajax_fix_data_htaccess() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::fix_data_htaccess() );
	}

	public static function ajax_fix_index_protection() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::fix_index_protection() );
	}

	public static function ajax_lock_shared_files() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::protect_shared_files() );
	}

	public static function ajax_unlock_shared_files() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		self::send_result( GrabWP_Tenancy_Installer::unprotect_shared_files( true ) );
	}

	/** Hosting setup card fixes and their Undo. Main site only. */
	public static function ajax_hosting_action() {
		check_ajax_referer( 'grabwp_fix_component' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( __( 'Permission denied.', 'grabwp-tenancy' ) ); }
		if ( defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT ) { wp_send_json_error( __( 'Run this from the main site.', 'grabwp-tenancy' ) ); }

		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
		switch ( $action ) {
			case 'grabwp_hosting_proxy_https':
				$result = GrabWP_Tenancy_Installer::install_proxy_https();
				break;
			case 'grabwp_hosting_proxy_https_undo':
				$result = GrabWP_Tenancy_Installer::remove_proxy_https();
				break;
			case 'grabwp_hosting_https_urls':
				$result = GrabWP_Tenancy_Installer::fix_https_urls();
				break;
			case 'grabwp_hosting_https_urls_undo':
				$result = GrabWP_Tenancy_Installer::revert_https_urls();
				break;
			case 'grabwp_hosting_multisite_cleanup':
				$result = GrabWP_Tenancy_Installer::remove_multisite_settings();
				break;
			case 'grabwp_hosting_multisite_cleanup_undo':
				$result = GrabWP_Tenancy_Installer::undo_multisite_settings();
				break;
			default:
				$result = array(
					'success' => false,
					'message' => __( 'Unknown action.', 'grabwp-tenancy' ),
				);
		}
		self::send_result( $result );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private static function is_plugin_page() {
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, 'grabwp-tenancy' ) !== false;
	}

	private static function send_result( $result ) {
		if ( $result['success'] ) {
			// The next request (the reload after a fix) must run the new files.
			require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
			GrabWP_Tenancy_Status_Checker::refresh_boot_files();
		}
		$result['success'] ? wp_send_json_success( $result['message'] ) : wp_send_json_error( $result['message'] );
	}
}
