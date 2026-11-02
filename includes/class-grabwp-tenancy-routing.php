<?php
/**
 * GrabWP Tenancy Routing Mode
 *
 * Site-wide routing mode (path, domain or both), stored in the routing block
 * of tenants.php. A valid GRABWP_TENANCY_ROUTING_MODE constant in
 * wp-config.php overrides it and makes the setting read-only.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.13
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Routing {

	/**
	 * Effective mode.
	 *
	 * @return string
	 */
	public static function get_mode() {
		return grabwp_tenancy_get_routing_mode();
	}

	/**
	 * Mode stored in tenants.php, or '' when none is stored.
	 *
	 * @return string
	 */
	public static function get_stored_mode() {
		$config = grabwp_tenancy_routing_config();
		return ( isset( $config['mode'] ) && self::is_valid( $config['mode'] ) ) ? $config['mode'] : '';
	}

	/**
	 * @param string $mode Mode to check.
	 * @return bool
	 */
	public static function is_valid( $mode ) {
		return is_string( $mode ) && in_array( $mode, grabwp_tenancy_routing_modes(), true );
	}

	/**
	 * The mode is set in wp-config.php (valid or not).
	 *
	 * @return bool
	 */
	public static function is_constant_defined() {
		return defined( 'GRABWP_TENANCY_ROUTING_MODE' );
	}

	/**
	 * A valid constant in wp-config.php sets the mode; the setting is read-only.
	 *
	 * @return bool
	 */
	public static function is_locked() {
		return self::is_constant_defined() && self::is_valid( GRABWP_TENANCY_ROUTING_MODE );
	}

	/**
	 * The constant is defined but not a valid mode, so it is ignored.
	 *
	 * @return bool
	 */
	public static function lock_is_invalid() {
		return self::is_constant_defined() && ! self::is_valid( GRABWP_TENANCY_ROUTING_MODE );
	}

	/**
	 * @param string $mode Mode.
	 * @return string Translated label.
	 */
	public static function label( $mode ) {
		switch ( $mode ) {
			case 'path':
				return __( 'Path', 'grabwp-tenancy' );
			case 'domain':
				return __( 'Domain', 'grabwp-tenancy' );
			default:
				return __( 'Path and domain', 'grabwp-tenancy' );
		}
	}

	/**
	 * Store a routing mode in tenants.php.
	 *
	 * Re-reads the file and writes it back with the new mode. A missing file
	 * is created with no sites. Aborts when the file cannot be read, so a bad
	 * read never empties the mappings.
	 *
	 * @param string $mode Mode.
	 * @return bool
	 */
	public static function save_mode( $mode ) {
		if ( ! self::is_valid( $mode ) ) {
			return false;
		}

		$current = GrabWP_Tenancy_Tenant_Crud::read_tenants_file();
		if ( false === $current ) {
			return false;
		}

		$routing         = is_array( $current['routing'] ) ? $current['routing'] : array();
		$routing['mode'] = $mode;

		if ( ! GrabWP_Tenancy_Tenant_Crud::write_tenants_file( $current['mappings'], $routing ) ) {
			return false;
		}

		grabwp_tenancy_routing_config( $routing );
		return true;
	}

	/**
	 * Whether tenants.php can be written, by cause.
	 *
	 * @param string|null $file Path; null for the configured tenants.php.
	 * @return string 'ok', 'missing' (created on save), 'dir_not_writable',
	 *                'unreadable' or 'not_writable' (the rename would fail).
	 */
	public static function file_status( $file = null ) {
		$file = null === $file ? GrabWP_Tenancy_Path_Manager::get_tenants_file_path() : $file;
		clearstatcache();

		// The writer renames a temp file into the folder, so the folder must be
		// writable; a missing folder is created inside its nearest parent.
		$dir = dirname( $file );
		while ( ! file_exists( $dir ) && dirname( $dir ) !== $dir ) {
			$dir = dirname( $dir );
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return 'dir_not_writable';
		}
		if ( ! file_exists( $file ) ) {
			return 'missing';
		}
		if ( ! is_readable( $file ) ) {
			return 'unreadable';
		}
		// A read-only file is replaced by the rename all the same, unless the
		// folder is sticky and PHP owns neither the folder nor the file.
		if ( is_writable( $file ) || ! ( fileperms( $dir ) & 01000 ) ) {
			return 'ok';
		}
		$uid = function_exists( 'posix_geteuid' ) ? posix_geteuid() : getmyuid();
		return in_array( $uid, array( fileowner( $dir ), fileowner( $file ) ), true ) ? 'ok' : 'not_writable';
	}

	/**
	 * Message for a tenants.php status; '' for 'ok'.
	 *
	 * @param string      $status See file_status().
	 * @param string|null $file   Path; null for the configured tenants.php.
	 * @return string
	 */
	public static function file_message( $status, $file = null ) {
		$file = null === $file ? GrabWP_Tenancy_Path_Manager::get_tenants_file_path() : $file;
		switch ( $status ) {
			case 'ok':
				return '';
			case 'missing':
				/* translators: %s: path of tenants.php. */
				return sprintf( __( '%s does not exist yet. It is created when you save.', 'grabwp-tenancy' ), $file );
			case 'dir_not_writable':
				/* translators: %s: folder of tenants.php. */
				return sprintf( __( 'The folder %s is not writable by the web server, so tenants.php cannot be saved. Make the folder writable.', 'grabwp-tenancy' ), dirname( $file ) );
			case 'unreadable':
				/* translators: %s: path of tenants.php. */
				return sprintf( __( '%s cannot be read by the web server, so it cannot be updated safely. Make it readable and writable.', 'grabwp-tenancy' ), $file );
			case 'not_writable':
				/* translators: %s: path of tenants.php. */
				return sprintf( __( '%s is not writable by the web server. Make the file writable.', 'grabwp-tenancy' ), $file );
		}
		return __( 'Could not write tenants.php. Check file permissions.', 'grabwp-tenancy' );
	}

	/**
	 * Error after a failed save, by its real cause when one is found.
	 *
	 * @return string
	 */
	public static function write_error_message() {
		$status = self::file_status();
		return self::file_message( in_array( $status, array( 'ok', 'missing' ), true ) ? 'failed' : $status );
	}

	/**
	 * Apply a mode picked in a form (Settings > Routing and the setup wizard).
	 *
	 * The mode is written only while there is no site yet. With sites, the
	 * switch has to go through the confirm screen of Settings > Routing,
	 * which lists every site whose address changes.
	 *
	 * @param string $mode Posted mode.
	 * @return string 'unchanged' (locked or same mode), 'invalid', 'saved',
	 *                'confirm' (sites exist) or 'failed' (tenants.php not written).
	 */
	public static function apply_posted_mode( $mode ) {
		if ( self::is_locked() || ( self::is_valid( $mode ) && $mode === self::get_mode() ) ) {
			return 'unchanged';
		}
		if ( ! self::is_valid( $mode ) ) {
			return 'invalid';
		}
		if ( ! empty( GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() ) ) {
			return 'confirm';
		}
		return self::save_mode( $mode ) ? 'saved' : 'failed';
	}

	/**
	 * What a switch to another mode changes.
	 *
	 * @param string $to Target mode.
	 * @return array {
	 *     @type string $from    Current mode.
	 *     @type string $to      Target mode.
	 *     @type array  $blocked Tenant IDs without a domain (only when $to is 'domain').
	 *     @type array  $changes Tenant ID => array( old URL, new URL ).
	 * }
	 */
	public static function plan_transition( $to ) {
		$from = self::get_mode();
		$plan = array(
			'from'    => $from,
			'to'      => $to,
			'blocked' => array(),
			'changes' => array(),
		);

		foreach ( GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() as $tenant_id => $domains ) {
			$tenant = new GrabWP_Tenancy_Tenant( (string) $tenant_id, array( 'domains' => (array) $domains ) );

			if ( 'domain' === $to && ! $tenant->get_real_domains() ) {
				$plan['blocked'][] = (string) $tenant_id;
				continue;
			}

			$old = $tenant->get_site_url( $from );
			$new = $tenant->get_site_url( $to );
			if ( $old !== $new ) {
				$plan['changes'][ (string) $tenant_id ] = array( $old, $new );
			}
		}

		return $plan;
	}
}
