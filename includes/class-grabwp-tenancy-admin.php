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
	 * Build a help tip for plain-text supporting copy.
	 *
	 * Uses the core toggle tip (WordPress 7.1+) when available. Core reserves
	 * wp_get_tooltip() for a control's accessible name; our tips explain context,
	 * which core assigns to wp_get_toggletip(). Older WordPress gets the dashicon
	 * with a native title tooltip.
	 *
	 * @since 1.1.13
	 * @param string $text Translated plain-text tip. Markup is shown as text, not rendered.
	 * @return string Escaped HTML, or an empty string when $text is empty.
	 */
	public static function get_help_tip( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}

		/**
		 * Filters whether GrabWP help tips use the core toggle tip.
		 *
		 * @since 1.1.13
		 * @param bool   $use_core True when wp_get_toggletip() exists.
		 * @param string $text     Tip text.
		 */
		if ( apply_filters( 'grabwp_tenancy_use_core_help_tip', function_exists( 'wp_get_toggletip' ), $text ) && function_exists( 'wp_get_toggletip' ) ) {
			wp_enqueue_style( 'wp-tooltip' );
			return wp_get_toggletip( $text, array( 'class' => 'grabwp-help-tip' ) );
		}

		return sprintf(
			'<span class="dashicons dashicons-editor-help grabwp-help-tip" title="%1$s" aria-label="%1$s"></span>',
			esc_attr( $text )
		);
	}

	/**
	 * Print a help tip. See get_help_tip().
	 *
	 * @since 1.1.13
	 * @param string $text Translated plain-text tip.
	 */
	public static function help_tip( $text ) {
		echo self::get_help_tip( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_help_tip() and core wp_get_toggletip().
	}

	/**
	 * Build a one-line notice when a constant set before GrabWP makes a setting ineffective.
	 *
	 * @since 1.1.13
	 * @param string $key   Setting key.
	 * @param mixed  $value Value GrabWP would apply (global or per-tenant).
	 * @return string Escaped HTML, or an empty string when nothing overrules the setting.
	 */
	public static function get_constant_pin_notice( $key, $value ) {
		$preset = GrabWP_Tenancy_Settings::get_preset_constant( $key, $value );
		if ( null === $preset ) {
			return '';
		}

		if ( is_bool( $preset['value'] ) ) {
			$shown = $preset['value'] ? 'true' : 'false';
		} elseif ( is_string( $preset['value'] ) ) {
			$shown = "'" . $preset['value'] . "'";
		} else {
			$shown = (string) $preset['value'];
		}
		$name  = '<code dir="ltr">' . esc_html( $preset['name'] ) . '</code>';
		$shown = '<code dir="ltr">' . esc_html( $shown ) . '</code>';

		if ( self::wp_config_defines( $preset['name'] ) ) {
			$text = sprintf(
				/* translators: 1: constant name, e.g. DISALLOW_FILE_EDIT, 2: its value, e.g. false */
				esc_html__( 'Your wp-config.php already sets %1$s to %2$s, so this setting has no effect. Remove that line to let GrabWP control it.', 'grabwp-tenancy' ),
				$name,
				$shown
			);
		} else {
			$text = sprintf(
				/* translators: 1: constant name, e.g. DISALLOW_FILE_EDIT, 2: its value, e.g. false */
				esc_html__( 'Another plugin or file already sets %1$s to %2$s, so this setting has no effect.', 'grabwp-tenancy' ),
				$name,
				$shown
			);
		}

		return '<div class="notice notice-warning inline grabwp-constant-pin"><p>' . $text . '</p></div>';
	}

	/**
	 * Print the constant notice. See get_constant_pin_notice().
	 *
	 * @since 1.1.13
	 * @param string $key   Setting key.
	 * @param mixed  $value Value GrabWP would apply.
	 */
	public static function constant_pin_notice( $key, $value ) {
		echo self::get_constant_pin_notice( $key, $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_constant_pin_notice().
	}

	/**
	 * Whether wp-config.php has a define() for a constant, outside comments. Read-only.
	 *
	 * @since 1.1.13
	 * @param string $name Constant name.
	 * @return bool
	 */
	private static function wp_config_defines( $name ) {
		static $code = null;
		if ( null === $code ) {
			$code = '';
			$file = ABSPATH . 'wp-config.php';
			if ( ! file_exists( $file ) && file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
				$file = dirname( ABSPATH ) . '/wp-config.php';
			}
			$source = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local read-only check.
			if ( false !== $source ) {
				foreach ( token_get_all( $source ) as $token ) {
					if ( ! is_array( $token ) ) {
						$code .= $token;
					} elseif ( T_COMMENT !== $token[0] && T_DOC_COMMENT !== $token[0] ) {
						$code .= $token[1];
					}
				}
			}
		}
		return (bool) preg_match( '/\bdefine\s*\(\s*[\'"]' . preg_quote( $name, '/' ) . '[\'"]/i', $code );
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 5 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		GrabWP_Tenancy_Setup_Wizard::register();

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

		wp_enqueue_script(
			'grabwp-admin-shared',
			$this->plugin->plugin_url . 'admin/js/grabwp-admin-shared.js',
			array(),
			$this->plugin->version,
			true
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
			array( 'grabwp-admin-shared' ),
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below.
		$routing_to = isset( $_GET['routing_confirm'] ) ? sanitize_key( wp_unslash( $_GET['routing_confirm'] ) ) : '';
		if ( '' !== $routing_to && GrabWP_Tenancy_Routing::is_valid( $routing_to ) && ! GrabWP_Tenancy_Routing::is_locked()
			&& isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_tenancy_routing_confirm' ) ) {
			$this->render_admin_page( 'settings-routing-confirm', array( 'plan' => GrabWP_Tenancy_Routing::plan_transition( $routing_to ) ) );
			return;
		}

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
			if ( in_array( $message, array( 'created', 'updated', 'deleted', 'settings_saved', 'routing_saved' ), true ) ) {
				$messages_map = array(
					'created'        => __( 'Tenant created successfully.', 'grabwp-tenancy' ),
					'updated'        => __( 'Tenant updated successfully.', 'grabwp-tenancy' ),
					'deleted'        => __( 'Tenant deleted successfully.', 'grabwp-tenancy' ),
					'settings_saved' => __( 'Settings saved successfully.', 'grabwp-tenancy' ),
					'routing_saved'  => __( 'Routing mode saved.', 'grabwp-tenancy' ),
				);

				if ( isset( $messages_map[ $message ] ) ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html( $messages_map[ $message ] )
					);
				}

				// One-time install result (password shown once) after Add New.
				if ( 'created' === $message ) {
					$grabwp_site_result = GrabWP_Tenancy_Tenant_Installer::pull_one_time_result();
					if ( $grabwp_site_result ) {
						include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/partials/site-result.php';
					}
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
