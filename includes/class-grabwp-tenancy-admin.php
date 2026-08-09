<?php
/**
 * GrabWP Tenancy Admin Class
 *
 * Handles WordPress admin interface for tenant management.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '../admin/class-grabwp-tenancy-list-table.php';
require_once plugin_dir_path( __FILE__ ) . 'class-grabwp-tenancy-domain-validator.php';
require_once plugin_dir_path( __FILE__ ) . 'class-grabwp-tenancy-tenant-crud.php';
require_once plugin_dir_path( __FILE__ ) . 'class-grabwp-tenancy-admin-form-handler.php';

class GrabWP_Tenancy_Admin {

	/**
	 * @var GrabWP_Tenancy
	 */
	private $plugin;

	/**
	 * @var GrabWP_Tenancy_Admin_Form_Handler
	 */
	private $form_handler;

	public function __construct( $plugin ) {
		$this->plugin       = $plugin;
		$this->form_handler = new GrabWP_Tenancy_Admin_Form_Handler( $plugin );

		if ( ! $this->plugin->is_tenant() ) {
			$this->init_hooks();
		}
	}

	/**
	 * Whether to display upsell UI promoting GrabWP Tenancy Pro.
	 *
	 * Returns false when the Pro plugin is active so all upsell
	 * entry points are suppressed via a single guard.
	 *
	 * @since 1.3.1
	 * @return bool
	 */
	public static function should_show_upsell() {
		return ! class_exists( 'GrabWP_Tenancy_Pro' );
	}

	/**
	 * Build the upgrade landing page URL with UTM query parameters.
	 *
	 * @since 1.3.1
	 * @param string $utm_content Per-entry-point UTM content value.
	 * @return string Full URL with UTM params.
	 */
	public static function get_upgrade_url( $utm_content = '' ) {
		$params = array(
			'utm_source'   => 'grabwp-free',
			'utm_medium'   => 'plugin',
			'utm_campaign' => 'upgrade',
		);
		if ( '' !== $utm_content ) {
			$params['utm_content'] = $utm_content;
		}
		return add_query_arg( $params, 'https://grabwp.com/pro' );
	}

	/**
	 * Render the shared Pro upsell card partial.
	 *
	 * @since 1.3.1
	 * @param array $args {
	 *     Optional. Upsell card arguments.
	 *
	 *     @type string $utm_content UTM content slug (used when url is empty).
	 *     @type string $url         Full upgrade URL. Optional if utm_content set.
	 *     @type string $title       Optional heading. Empty keeps badge inline with message.
	 *     @type string $message     Body copy. Defaults to the generic Pro pitch.
	 *     @type string $cta_label   Button label.
	 *     @type string $margin      Margin utility class. Default grabwp-mt-sm.
	 * }
	 */
	public static function render_upsell_card( $args = array() ) {
		if ( ! self::should_show_upsell() ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'utm_content' => '',
				'url'         => '',
				'title'       => '',
				'message'     => __( 'GrabWP Tenancy Pro adds scheduled auto backups, offsite cloud storage, dedicated database isolation, and more for every client site.', 'grabwp-tenancy' ),
				'cta_label'   => __( 'Learn about Pro', 'grabwp-tenancy' ),
				'margin'      => 'grabwp-mt-sm',
			)
		);

		$upsell_url = $args['url'];
		if ( '' === $upsell_url ) {
			$upsell_url = self::get_upgrade_url( $args['utm_content'] );
		}

		$upsell_title   = $args['title'];
		$upsell_message = $args['message'];
		$upsell_cta     = $args['cta_label'];
		$upsell_margin  = $args['margin'];

		$partial = GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/partials/upsell-card.php';
		if ( file_exists( $partial ) ) {
			include $partial;
		}
	}

	private function init_hooks() {
		add_action( 'admin_init', array( $this->form_handler, 'handle_form_submissions' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/backup/class-grabwp-tenancy-clone-admin.php';
		GrabWP_Tenancy_Clone_Admin::get_instance();

		do_action( 'grabwp_tenancy_admin_init', $this );

		if ( self::should_show_upsell() ) {
			add_filter( 'plugin_action_links_' . GRABWP_TENANCY_PLUGIN_BASENAME, array( $this, 'add_upgrade_action_link' ) );
			add_filter( 'admin_footer_text', array( $this, 'replace_admin_footer_text' ) );
		}
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'GrabWP Tenancy', 'grabwp-tenancy' ),
			__( 'Tenancy', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy',
			array( $this, 'admin_page' ),
			'dashicons-admin-multisite',
			3
		);

		add_submenu_page(
			'grabwp-tenancy',
			__( 'All Tenants', 'grabwp-tenancy' ),
			__( 'All Tenants', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'grabwp-tenancy',
			__( 'Add New Tenant', 'grabwp-tenancy' ),
			__( 'Add New', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-create',
			array( $this, 'create_page' )
		);

		add_submenu_page(
			'Edit Tenant',
			__( 'Edit Tenant', 'grabwp-tenancy' ),
			__( 'Edit Tenant', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-edit',
			array( $this, 'edit_page' )
		);

		add_submenu_page(
			'grabwp-tenancy',
			__( 'Settings', 'grabwp-tenancy' ),
			__( 'Settings', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-settings',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			'grabwp-tenancy',
			__( 'Status', 'grabwp-tenancy' ),
			__( 'Status', 'grabwp-tenancy' ),
			'manage_options',
			'grabwp-tenancy-status',
			array( $this, 'status_page' )
		);

		if ( self::should_show_upsell() ) {
			add_submenu_page(
				'grabwp-tenancy',
				__( 'Upgrade to Pro', 'grabwp-tenancy' ),
				__( 'Upgrade to Pro', 'grabwp-tenancy' ),
				'manage_options',
				'grabwp-tenancy-upgrade',
				array( $this, 'upgrade_page' )
			);
		}

		do_action( 'grabwp_tenancy_admin_menu' );
	}

	public function enqueue_admin_scripts( $hook ) {
		$needs_notice = ! defined( 'GRABWP_TENANCY_LOADED' );

		if ( strpos( $hook, 'grabwp-tenancy' ) === false && ! $needs_notice ) {
			return;
		}

		wp_enqueue_style(
			'grabwp-admin-shared',
			$this->plugin->plugin_url . 'admin/css/grabwp-admin-shared.css',
			array(),
			$this->plugin->version
		);

		wp_enqueue_style(
			'grabwp-tenancy-admin',
			$this->plugin->plugin_url . 'admin/css/grabwp-admin.css',
			array( 'grabwp-admin-shared' ),
			$this->plugin->version
		);

		wp_enqueue_script(
			'grabwp-tenancy-admin',
			$this->plugin->plugin_url . 'admin/js/grabwp-admin.js',
			array(),
			$this->plugin->version,
			true
		);

		wp_localize_script(
			'grabwp-tenancy-admin',
			'grabwpTenancyAdmin',
			array(
				'enterDomainPlaceholder' => __( 'Enter domain (e.g., tenant1.grabwp.local)', 'grabwp-tenancy' ),
				'removeText'             => __( 'Remove', 'grabwp-tenancy' ),
				'confirmMessage'         => __( 'To confirm deletion, type the tenant ID:', 'grabwp-tenancy' ),
				'incorrectIdMessage'     => __( 'Incorrect tenant ID. Deletion cancelled.', 'grabwp-tenancy' ),
				'muPluginNonce'          => wp_create_nonce( 'grabwp_install_mu_plugin' ),
				'loaderNonce'            => wp_create_nonce( 'grabwp_install_loader' ),
				'fixComponentNonce'      => wp_create_nonce( 'grabwp_fix_component' ),
			)
		);
	}

	public function admin_page() {
		$list_table_class = apply_filters( 'grabwp_tenancy_list_table_class', 'GrabWP_Tenancy_List_Table' );
		$list_table       = new $list_table_class( $this->plugin );
		$list_table->prepare_items();

		$this->render_admin_page( 'tenants', array( 'list_table' => $list_table ) );
	}

	public function create_page() {
		$this->render_admin_page( 'tenant-create' );
	}

	public function edit_page() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_tenancy_edit' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'grabwp-tenancy' ) );
		}

		$tenant_id = isset( $_GET['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_GET['tenant_id'] ) ) : '';

		if ( ! $tenant_id ) {
			wp_die( esc_html__( 'Tenant ID is required.', 'grabwp-tenancy' ) );
		}

		$tenant = $this->get_tenant( $tenant_id );
		if ( ! $tenant ) {
			wp_die( esc_html__( 'Tenant not found.', 'grabwp-tenancy' ) );
		}

		$this->render_admin_page( 'tenant-edit', array( 'tenant' => $tenant ) );
	}

	public function settings_page() {
		$settings = GrabWP_Tenancy_Settings::get_instance();
		$this->render_admin_page( 'settings', array( 'settings' => $settings->get_all() ) );
	}

	public function status_page() {
		$this->render_admin_page( 'status' );
	}

	public function upgrade_page() {
		$data = array( 'upgrade_url' => self::get_upgrade_url( 'upgrade-tab' ) );
		$this->render_admin_page( 'upgrade', $data );
	}

	/**
	 * Add "Upgrade to Pro" action link on the Plugins list page.
	 *
	 * @since 1.3.1
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_upgrade_action_link( $links ) {
		$links['upgrade'] = '<a href="' . esc_url( admin_url( 'admin.php?page=grabwp-tenancy-upgrade' ) ) . '">' . esc_html__( 'Upgrade to Pro', 'grabwp-tenancy' ) . '</a>';
		return $links;
	}

	/**
	 * Replace the default WordPress admin footer text with an upsell line
	 * on GrabWP Tenancy admin pages.
	 *
	 * Only fires when Pro is inactive and the current screen is a Tenancy page.
	 *
	 * @since 1.3.1
	 * @param string $text Default footer text.
	 * @return string
	 */
	public function replace_admin_footer_text( $text ) {
		if ( ! self::should_show_upsell() ) {
			return $text;
		}
		$screen = get_current_screen();
		$page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! $screen || strpos( $page, 'grabwp-tenancy' ) !== 0 ) {
			return $text;
		}
		$url = self::get_upgrade_url( 'footer-thankyou' );
		return sprintf(
			'Want automatic backups for every client site? <a href="%s" target="_blank" rel="noopener noreferrer">Try GrabWP Pro free</a>.',
			esc_url( $url )
		);
	}

	private function render_admin_page( $template, $data = array() ) {
		$template_file = $this->plugin->plugin_dir . 'admin/views/' . $template . '.php';

		if ( file_exists( $template_file ) ) {
			extract( $data );
			include $template_file;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'GrabWP Tenancy', 'grabwp-tenancy' ) . '</h1><p>' . esc_html__( 'Template not found.', 'grabwp-tenancy' ) . '</p></div>';
		}
	}

	private function get_tenant( $tenant_id ) {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();

		if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
			clearstatcache( true, $mappings_file );

			$tenant_mappings = array();
			ob_start();
			include $mappings_file;
			ob_end_clean();

			if ( is_array( $tenant_mappings ) && isset( $tenant_mappings[ $tenant_id ] ) ) {
				return new GrabWP_Tenancy_Tenant(
					$tenant_id,
					array( 'domains' => $tenant_mappings[ $tenant_id ] )
				);
			}
		}

		return null;
	}

	/**
	 * Backward-compatible delegation to TenantCrud.
	 * WaaS plugin calls these methods on the Admin instance.
	 */
	public function handle_create_tenant( $domains ) {
		$crud = new GrabWP_Tenancy_Tenant_Crud( $this->plugin );
		return $crud->create( $domains );
	}

	public function handle_update_tenant( $tenant_id, $domains ) {
		$crud = new GrabWP_Tenancy_Tenant_Crud( $this->plugin );
		return $crud->update( $tenant_id, $domains );
	}

	public function handle_delete_tenant( $tenant_id ) {
		$crud = new GrabWP_Tenancy_Tenant_Crud( $this->plugin );
		return $crud->delete( $tenant_id );
	}

	public function admin_notices() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! $page || strpos( $page, 'grabwp-tenancy' ) === false ) {
			return;
		}

		if ( isset( $_GET['message'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_tenancy_notice' ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
			if ( in_array( $message, array( 'created', 'updated', 'deleted', 'settings_saved' ), true ) ) {
				$messages_map = array(
					'created'        => __( 'Tenant created successfully.', 'grabwp-tenancy' ),
					'updated'        => __( 'Tenant updated successfully.', 'grabwp-tenancy' ),
					'deleted'        => __( 'Tenant deleted successfully.', 'grabwp-tenancy' ),
					'settings_saved' => __( 'Settings saved successfully.', 'grabwp-tenancy' ),
				);

				if ( isset( $messages_map[ $message ] ) ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html( $messages_map[ $message ] )
					);
				}
			}
		}

		$grabwp_tenancy_error_message = get_transient( 'grabwp_tenancy_error' );
		if ( $grabwp_tenancy_error_message ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $grabwp_tenancy_error_message )
			);
			delete_transient( 'grabwp_tenancy_error' );
		}
	}
}
