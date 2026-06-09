<?php
/**
 * GrabWP Tenancy Admin Form Handler
 *
 * Routes form submissions to appropriate CRUD operations.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Admin_Form_Handler {

	/**
	 * @var GrabWP_Tenancy_Tenant_Crud
	 */
	private $crud;

	public function __construct( $plugin ) {
		$this->crud = new GrabWP_Tenancy_Tenant_Crud( $plugin );
	}

	public function handle_form_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

		if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'grabwp-tenancy' ) === false ) {
			return;
		}

		switch ( $action ) {
			case 'create_tenant':
				$this->process_create_tenant();
				break;
			case 'update_tenant':
				$this->process_update_tenant();
				break;
			case 'delete_tenant':
				$this->process_delete_tenant();
				break;
			case 'save_settings':
				$this->process_save_settings();
				break;
			default:
				do_action( 'grabwp_tenancy_handle_custom_action', $action, $_POST );
				break;
		}
	}

	private function process_create_tenant() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grabwp_tenancy_create' ) ) {
			return;
		}

		$domains = $this->extract_domains_from_post();
		do_action( 'grabwp_tenancy_process_create_form_data', $domains, $_POST );

		$result = $this->crud->create( $domains );

		if ( $result['type'] === 'success' ) {
			$clone_source = isset( $_POST['clone_source'] ) ? sanitize_key( wp_unslash( $_POST['clone_source'] ) ) : '';
			if ( $clone_source && ! empty( $result['tenant_id'] ) ) {
				$clone_page   = class_exists( 'GrabWP_Tenancy_Pro_Clone_Admin' ) ? 'grabwp-tenancy-pro-clone' : 'grabwp-tenancy-clone';
				$nonce_action = class_exists( 'GrabWP_Tenancy_Pro_Clone_Admin' )
					? 'grabwp_tenancy_pro_clone_' . $clone_source
					: 'grabwp_tenancy_clone_' . $clone_source;

				wp_safe_redirect( add_query_arg( [
					'page'             => $clone_page,
					'tenant_id'        => $clone_source,
					'target_tenant_id' => $result['tenant_id'],
					'_wpnonce'         => wp_create_nonce( $nonce_action ),
				], admin_url( 'admin.php' ) ) );
				exit;
			}

			$this->redirect_success( 'grabwp-tenancy', 'created' );
		} else {
			$this->redirect_error( 'grabwp-tenancy-create', $result['message'] );
		}
	}

	private function process_update_tenant() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grabwp_tenancy_update' ) ) {
			return;
		}

		$tenant_id = isset( $_POST['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) ) : '';
		$domains   = $this->extract_domains_from_post();
		do_action( 'grabwp_tenancy_process_update_form_data', $tenant_id, $domains, $_POST );

		$result = $this->crud->update( $tenant_id, $domains );

		if ( $result['type'] === 'success' ) {
			$success_nonce = wp_create_nonce( 'grabwp_tenancy_notice' );
			$edit_nonce    = wp_create_nonce( 'grabwp_tenancy_edit' );
			wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy-edit&tenant_id=' . urlencode( $tenant_id ) . '&message=updated&_wpnonce=' . urlencode( $edit_nonce ) . '&_notice_nonce=' . urlencode( $success_nonce ) ) );
			exit;
		} else {
			$this->redirect_error_with_tenant( 'grabwp-tenancy-edit', $tenant_id, $result['message'] );
		}
	}

	private function process_delete_tenant() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grabwp_tenancy_delete' ) ) {
			return;
		}

		$tenant_id = isset( $_POST['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) ) : '';
		$result    = $this->crud->delete( $tenant_id );

		if ( $result['type'] === 'success' ) {
			$this->redirect_success( 'grabwp-tenancy', 'deleted' );
		} else {
			$this->redirect_error( 'grabwp-tenancy', $result['message'] );
		}
	}

	private function process_save_settings() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'grabwp_tenancy_save_settings' ) ) {
			return;
		}

		$settings_instance = GrabWP_Tenancy_Settings::get_instance();
		$raw_settings      = array();
		foreach ( array_keys( GrabWP_Tenancy_Settings::get_defaults() ) as $key ) {
			$raw_settings[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}

		if ( $settings_instance->save( $raw_settings ) ) {
			$this->redirect_success( 'grabwp-tenancy-settings', 'settings_saved' );
		} else {
			$this->redirect_error( 'grabwp-tenancy-settings', __( 'Failed to save settings. Please check file permissions.', 'grabwp-tenancy' ) );
		}
	}

	private function extract_domains_from_post() {
		$domains = array();
		if ( isset( $_POST['domains'] ) && is_array( $_POST['domains'] ) ) {
			$raw_domains = array_map( 'sanitize_text_field', wp_unslash( $_POST['domains'] ) );
			if ( count( $raw_domains ) > 10 ) {
				$raw_domains = array_slice( $raw_domains, 0, 10 );
			}
			$domains = array_filter( $raw_domains );
		}
		return $domains;
	}

	private function redirect_success( $page, $message ) {
		$nonce = wp_create_nonce( 'grabwp_tenancy_notice' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&message=' . $message . '&_wpnonce=' . urlencode( $nonce ) ) );
		exit;
	}

	private function redirect_error( $page, $error_message ) {
		set_transient( 'grabwp_tenancy_error', $error_message, 60 );
		$nonce = wp_create_nonce( 'grabwp_tenancy_error' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&error=1&_wpnonce=' . urlencode( $nonce ) ) );
		exit;
	}

	private function redirect_error_with_tenant( $page, $tenant_id, $error_message ) {
		set_transient( 'grabwp_tenancy_error', $error_message, 60 );
		$nonce = wp_create_nonce( 'grabwp_tenancy_error' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&tenant_id=' . urlencode( $tenant_id ) . '&error=1&_wpnonce=' . urlencode( $nonce ) ) );
		exit;
	}
}
