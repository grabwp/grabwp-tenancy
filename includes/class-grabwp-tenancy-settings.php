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
	 * Values of controlled constants defined before GrabWP loaded, keyed by setting.
	 *
	 * @var array|null
	 */
	private static $preset_constants = null;

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
	 * @return array Key => [ label, type, group, description, tech?, constant?, min?, max? ].
	 *                `tech` and `constant` exist only for rows backed by a real WordPress constant.
	 */
	public static function get_setting_fields() {
		// Each label says what "Yes" does, so a ticked box or a Yes choice always means the label.
		return array(
			'disallow_file_mods'     => array(
				'label'       => __( 'Disable Install Plugins & Themes', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Tenant admins cannot install, update or delete plugins and themes.', 'grabwp-tenancy' ),
				'tech'        => 'DISALLOW_FILE_MODS=true',
				'constant'    => 'DISALLOW_FILE_MODS',
			),
			'disallow_file_edit'     => array(
				'label'       => __( 'Disable Plugin & Theme File Editor', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Tenant admins cannot use the built-in code editor for plugin and theme files.', 'grabwp-tenancy' ),
				'tech'        => 'DISALLOW_FILE_EDIT=true',
				'constant'    => 'DISALLOW_FILE_EDIT',
			),
			'hide_plugin_management' => array(
				'label'       => __( 'Hide Plugins Menu', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Removes the Plugins menu from tenant dashboards.', 'grabwp-tenancy' ),
			),
			'hide_theme_management'  => array(
				'label'       => __( 'Hide Appearance Menu', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Removes the Appearance menu (themes) from tenant dashboards.', 'grabwp-tenancy' ),
			),
			'hide_grabwp_plugins'    => array(
				'label'       => __( 'Hide GrabWP Plugins', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'capabilities',
				'description' => __( 'Hides every GrabWP plugin and the SQLite plugin from the tenant Plugins list, so tenants cannot turn them off by mistake.', 'grabwp-tenancy' ),
			),
			'disable_wp_cron'        => array(
				'label'       => __( 'Disable WP-Cron', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'performance',
				'description' => __( 'Turns off the built-in task scheduler on tenant sites. Run a server cron job instead.', 'grabwp-tenancy' ),
				'tech'        => 'DISABLE_WP_CRON=true',
				'constant'    => 'DISABLE_WP_CRON',
			),
			'disable_xmlrpc'         => array(
				'label'       => __( 'Disable XML-RPC', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'performance',
				'description' => __( 'Turns off the old remote publishing API, a common target of password-guessing attacks.', 'grabwp-tenancy' ),
			),
			'wp_post_revisions'      => array(
				'label'       => __( 'Post Revisions to Keep', 'grabwp-tenancy' ),
				'type'        => 'int',
				'group'       => 'performance',
				'description' => __( 'How many older versions of each post to keep. 0 turns revisions off.', 'grabwp-tenancy' ),
				'tech'        => 'WP_POST_REVISIONS',
				'constant'    => 'WP_POST_REVISIONS',
				'min'         => 0,
				'max'         => 100,
			),
			'empty_trash_days'       => array(
				'label'       => __( 'Days Before Trash Is Emptied', 'grabwp-tenancy' ),
				'type'        => 'int',
				'group'       => 'performance',
				'description' => __( 'Deleted items stay in the Trash this many days. 0 deletes them right away.', 'grabwp-tenancy' ),
				'tech'        => 'EMPTY_TRASH_DAYS',
				'constant'    => 'EMPTY_TRASH_DAYS',
				'min'         => 0,
				'max'         => 365,
			),
			'wp_http_block_external' => array(
				'label'       => __( 'Block External Requests', 'grabwp-tenancy' ),
				'type'        => 'bool',
				'group'       => 'performance',
				'description' => __( 'Tenant sites cannot connect to other websites, except the allowed hosts below.', 'grabwp-tenancy' ),
				'tech'        => 'WP_HTTP_BLOCK_EXTERNAL=true',
				'constant'    => 'WP_HTTP_BLOCK_EXTERNAL',
			),
			'wp_accessible_hosts'    => array(
				'label'       => __( 'Allowed Hosts When Blocked', 'grabwp-tenancy' ),
				'type'        => 'string',
				'group'       => 'performance',
				'description' => __( 'Comma-separated host names tenant sites can still reach when external requests are blocked. Wildcards like *.wordpress.org work.', 'grabwp-tenancy' ),
				'tech'        => 'WP_ACCESSIBLE_HOSTS',
				'constant'    => 'WP_ACCESSIBLE_HOSTS',
			),
		);
	}

	/**
	 * Setting key => WordPress constant map, for rows backed by a real constant.
	 *
	 * Must match the `constant` entries of get_setting_fields(). Kept separate and
	 * free of translation calls so it is safe to use while the plugin file is
	 * still loading, before the locale is known.
	 *
	 * @since  1.1.14
	 * @return array
	 */
	public static function get_setting_constants() {
		return array(
			'disallow_file_mods'     => 'DISALLOW_FILE_MODS',
			'disallow_file_edit'     => 'DISALLOW_FILE_EDIT',
			'disable_wp_cron'        => 'DISABLE_WP_CRON',
			'wp_post_revisions'      => 'WP_POST_REVISIONS',
			'empty_trash_days'       => 'EMPTY_TRASH_DAYS',
			'wp_http_block_external' => 'WP_HTTP_BLOCK_EXTERNAL',
			'wp_accessible_hosts'    => 'WP_ACCESSIBLE_HOSTS',
		);
	}

	/**
	 * Capture the constants GrabWP settings control that were defined before GrabWP.
	 *
	 * Called when the Base plugin loads, before any GrabWP define(). GrabWP only
	 * defines these on tenant requests, so on the main site a defined constant comes
	 * from wp-config.php or code loaded earlier (mu-plugins, earlier plugins).
	 *
	 * Uses get_setting_constants(), not get_setting_fields(): calling __() this
	 * early would load the text domain before the locale is set and leave every
	 * GrabWP string untranslated for the rest of the request.
	 *
	 * @since 1.1.13
	 */
	public static function capture_preset_constants() {
		if ( null !== self::$preset_constants ) {
			return;
		}
		self::$preset_constants = array();
		foreach ( self::get_setting_constants() as $key => $constant ) {
			if ( defined( $constant ) ) {
				self::$preset_constants[ $key ] = constant( $constant );
			}
		}
	}

	/**
	 * Get a constant that was defined before GrabWP and overrules a setting value.
	 *
	 * @since  1.1.13
	 * @param  string $key   Setting key.
	 * @param  mixed  $value Value GrabWP would apply (global or per-tenant).
	 * @return array|null [ name, value ] when the preset constant differs from $value, else null.
	 */
	public static function get_preset_constant( $key, $value ) {
		self::capture_preset_constants();
		if ( ! array_key_exists( $key, self::$preset_constants ) ) {
			return null;
		}

		$fields = self::get_setting_fields();
		$preset = self::$preset_constants[ $key ];
		$type   = $fields[ $key ]['type'];

		if ( 'bool' === $type ) {
			$same = (bool) $preset === (bool) $value;
		} elseif ( 'int' === $type ) {
			$same = ! is_bool( $preset ) && is_numeric( $preset ) && (int) $preset === (int) $value;
		} else {
			$same = (string) $preset === (string) $value;
		}

		return $same ? null : array(
			'name'  => $fields[ $key ]['constant'],
			'value' => $preset,
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
