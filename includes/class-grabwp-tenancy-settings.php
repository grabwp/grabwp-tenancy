<?php
/**
 * GrabWP Tenancy Settings Class
 *
 * Handles loading and saving plugin settings to a PHP file.
 * Settings are stored in GRABWP_TENANCY_BASE_DIR/settings.php
 * as a PHP array for early bootstrap access.
 *
 * @package GrabWP_Tenancy
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Settings Class
 *
 * @since 1.1.0
 */
class GrabWP_Tenancy_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var GrabWP_Tenancy_Settings|null
	 */
	private static $instance = null;

	/**
	 * Loaded settings array.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Whether settings have been loaded.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Get singleton instance.
	 *
	 * @since  1.1.0
	 * @return GrabWP_Tenancy_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — loads settings on first instantiation.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		$this->load();
	}

	/**
	 * Get default settings.
	 *
	 * @since  1.1.0
	 * @return array Default settings values.
	 */
	public static function get_defaults() {
		return array(
			'disallow_file_mods'       => true,
			'disallow_file_edit'       => true,
			'hide_plugin_management'   => false,
			'hide_theme_management'    => false,
			'hide_grabwp_plugins'      => true,
			'disable_wp_cron'          => true,
			'disable_xmlrpc'           => true,
			'wp_post_revisions'        => 3,
			'empty_trash_days'         => 7,
			'wp_http_block_external'   => false,
			'wp_accessible_hosts'      => '*.wordpress.org,*.grabwp.com',
		);
	}

	/**
	 * Setting type map. Keys not listed here are boolean.
	 *
	 * @since  1.1.4
	 * @return array
	 */
	public static function get_setting_types() {
		return array(
			'wp_post_revisions'   => 'int',
			'empty_trash_days'    => 'int',
			'wp_accessible_hosts' => 'string',
		);
	}

	/**
	 * Canonical field schema for global settings and per-tenant overrides UI.
	 *
	 * @since  1.1.5
	 * @return array Key => [ label, type, group, description, min?, max? ].
	 */
	public static function get_setting_fields() {
		return array(
			'disallow_file_mods'     => array(
				'label'       => __( 'Install Plugins & Themes', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Disallow tenant admins to install, update, and delete plugins/themes (DISALLOW_FILE_MODS)', 'grabwp-tenancy' ),
			),
			'disallow_file_edit'     => array(
				'label'       => __( 'Edit Plugins & Themes', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Disallow tenant admins to use the built-in plugin/theme file editor (DISALLOW_FILE_EDIT)', 'grabwp-tenancy' ),
			),
			'hide_plugin_management' => array(
				'label'       => __( 'Hide Plugin Management', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Hide the Plugins menu entirely from tenant admin dashboards', 'grabwp-tenancy' ),
			),
			'hide_theme_management'  => array(
				'label'       => __( 'Hide Theme Management', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Hide the Appearance menu entirely from tenant admin dashboards', 'grabwp-tenancy' ),
			),
			'hide_grabwp_plugins'    => array(
				'label'       => __( 'Hide GrabWP Plugins', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Hide GrabWP plugins from the plugin list on tenant sites', 'grabwp-tenancy' ),
			),
			'disable_wp_cron'        => array(
				'label'       => __( 'Disable WP-Cron', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'performance',
				'description' => __( 'Disable WordPress internal cron on tenant sites (DISABLE_WP_CRON). Use system cron instead.', 'grabwp-tenancy' ),
			),
			'disable_xmlrpc'         => array(
				'label'       => __( 'Disable XML-RPC', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'performance',
				'description' => __( 'Disable XML-RPC API on tenant sites. Reduces attack surface and prevents brute-force attempts.', 'grabwp-tenancy' ),
			),
			'wp_post_revisions'      => array(
				'label'       => __( 'Post Revisions', 'grabwp-tenancy' ),
				'type'        => 'int',
				'group'       => 'performance',
				'description' => __( 'Maximum post revisions to keep (WP_POST_REVISIONS). Lower values save database space.', 'grabwp-tenancy' ),
				'min'         => 0,
				'max'         => 100,
			),
			'empty_trash_days'       => array(
				'label'       => __( 'Empty Trash Days', 'grabwp-tenancy' ),
				'type'        => 'int',
				'group'       => 'performance',
				'description' => __( 'Days before trashed posts are permanently deleted (EMPTY_TRASH_DAYS). Set to 0 to disable trash.', 'grabwp-tenancy' ),
				'min'         => 0,
				'max'         => 365,
			),
			'wp_http_block_external' => array(
				'label'       => __( 'Block External HTTP', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'performance',
				'description' => __( 'Block all external HTTP requests from tenant sites (WP_HTTP_BLOCK_EXTERNAL). Allowlist hosts below.', 'grabwp-tenancy' ),
			),
			'wp_accessible_hosts'    => array(
				'label'       => __( 'Accessible Hosts', 'grabwp-tenancy' ),
				'type'        => 'string',
				'group'       => 'performance',
				'description' => __( 'Comma-separated hosts allowed when external HTTP is blocked (WP_ACCESSIBLE_HOSTS). Wildcards supported.', 'grabwp-tenancy' ),
			),
		);
	}

	/**
	 * Get the settings file path.
	 *
	 * @since  1.1.0
	 * @return string Absolute path to the settings file.
	 */
	public function get_settings_file_path() {
		$base_dir = defined( 'GRABWP_TENANCY_BASE_DIR' )
			? GRABWP_TENANCY_BASE_DIR
			: GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();

		return trailingslashit( $base_dir ) . 'settings.php';
	}

	/**
	 * Load settings from the PHP file.
	 *
	 * @since 1.1.0
	 */
	private function load() {
		if ( $this->loaded ) {
			return;
		}

		$this->settings = self::get_defaults();
		$file           = $this->get_settings_file_path();

		if ( file_exists( $file ) && is_readable( $file ) ) {
			$grabwp_tenancy_settings = array();
			ob_start();
			include $file;
			ob_end_clean();

			if ( is_array( $grabwp_tenancy_settings ) ) {
				$this->settings = wp_parse_args( $grabwp_tenancy_settings, $this->settings );
			}
		}

		$this->loaded = true;
	}

	/**
	 * Get a single setting value.
	 *
	 * @since  1.1.0
	 * @param  string $key     Setting key.
	 * @param  mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}

		$defaults = self::get_defaults();
		if ( null === $default && isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Get all settings.
	 *
	 * @since  1.1.0
	 * @return array All current settings merged with defaults.
	 */
	public function get_all() {
		return $this->settings;
	}

	/**
	 * Sanitize settings values.
	 *
	 * @since  1.1.0
	 * @param  array $raw_settings Raw input from form submission.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $raw_settings ) {
		$defaults  = self::get_defaults();
		$types     = self::get_setting_types();
		$sanitized = array();

		foreach ( array_keys( $defaults ) as $key ) {
			$type = isset( $types[ $key ] ) ? $types[ $key ] : 'bool';

			if ( 'int' === $type ) {
				$sanitized[ $key ] = isset( $raw_settings[ $key ] ) ? absint( $raw_settings[ $key ] ) : $defaults[ $key ];
			} elseif ( 'string' === $type ) {
				$sanitized[ $key ] = isset( $raw_settings[ $key ] ) ? sanitize_text_field( wp_unslash( $raw_settings[ $key ] ) ) : $defaults[ $key ];
			} else {
				$sanitized[ $key ] = ! empty( $raw_settings[ $key ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Save settings to the PHP file.
	 *
	 * @since  1.1.0
	 * @param  array $settings Settings array to save.
	 * @return bool  True on success, false on failure.
	 */
	public function save( $settings ) {
		$sanitized = $this->sanitize_settings( $settings );
		$file      = $this->get_settings_file_path();

		$content  = "<?php\n";
		$content .= "// GrabWP Tenancy Settings - Auto-generated.\n\n";
		$content .= '$grabwp_tenancy_settings = array(' . "\n";

		foreach ( $sanitized as $key => $value ) {
			if ( is_bool( $value ) ) {
				$export = $value ? 'true' : 'false';
			} elseif ( is_int( $value ) ) {
				$export = (string) $value;
			} else {
				$export = "'" . addslashes( $value ) . "'";
			}
			$content .= "\t'" . $key . "' => " . $export . ",\n";
		}

		$content .= ");\n";

		$result = GrabWP_Tenancy_Path_Manager::atomic_put_php_file( $file, $content );

		if ( $result ) {
			$this->settings = $sanitized;
		}

		return $result;
	}
}
