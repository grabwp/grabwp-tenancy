<?php
/**
 * GrabWP Tenancy Installer
 *
 * Single source of truth for all install, fix, activate, and deactivate operations.
 * Handles: directories, htaccess, tenant mappings, MU-plugin, wp-config loader.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Installer {

	/**
	 * MU-plugin filename.
	 */
	const MU_PLUGIN_FILE = 'mu-grabwp-tenancy.php';

	// =========================================================================
	// Activation / Deactivation
	// =========================================================================

	/**
	 * Full plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Data directory & security files.
		self::create_directories();
		self::create_htaccess();
		self::create_index_protection();
		self::create_tenant_mappings_file();

		// Root .htaccess rewrite rules for /site/[tenant-id].
		self::add_site_path_rewrite_rules();

		// MU-plugin and wp-config loader (silent — failures shown on status page).
		self::install_mu_plugin();
		self::install_loader();

		// Remove legacy file-based token artifacts (replaced by HMAC stateless tokens).
		self::cleanup_legacy_token_files();
	}

	/**
	 * Full plugin deactivation.
	 *
	 * @since 1.1.0
	 */
	public static function deactivate() {
		// Never leave the site read-only: unlock and drop snapshots first.
		self::unprotect_shared_files( false );

		// Root .htaccess rewrite rules.
		self::remove_site_path_rewrite_rules();

		// wp-config loader and MU-plugin.
		self::remove_loader();
		self::remove_mu_plugin();
	}

	// =========================================================================
	// MU-Plugin Install / Remove
	// =========================================================================

	/**
	 * Install the MU-plugin file.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function install_mu_plugin() {
		$mu_dir  = self::get_mu_plugins_dir();
		$mu_path = $mu_dir . '/' . self::MU_PLUGIN_FILE;

		if ( file_exists( $mu_path ) ) {
			return array( 'success' => true, 'message' => 'MU-Plugin is already installed.' );
		}

		if ( ! is_dir( $mu_dir ) ) {
			if ( ! wp_mkdir_p( $mu_dir ) ) {
				return array( 'success' => false, 'message' => 'Could not create mu-plugins directory. Please check file permissions.' );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $mu_path, self::get_mu_plugin_content() );

		if ( false === $result ) {
			return array( 'success' => false, 'message' => 'Could not write MU-Plugin file. Please check file permissions.' );
		}

		return array( 'success' => true, 'message' => 'MU-Plugin installed successfully.' );
	}

	/**
	 * Remove the MU-plugin file.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function remove_mu_plugin() {
		$mu_path = self::get_mu_plugin_path();

		if ( ! file_exists( $mu_path ) ) {
			return array( 'success' => true, 'message' => 'MU-Plugin was not installed.' );
		}

		// Verify it's our file before deleting.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $mu_path );
		if ( false !== $content && strpos( $content, 'grabwp-tenancy' ) === false ) {
			return array( 'success' => false, 'message' => 'MU-Plugin file exists but does not appear to be ours. Skipping removal.' );
		}

		wp_delete_file( $mu_path );
		if ( file_exists( $mu_path ) ) {
			return array( 'success' => false, 'message' => 'Could not delete MU-Plugin file. Please check file permissions.' );
		}

		return array( 'success' => true, 'message' => 'MU-Plugin removed successfully.' );
	}

	// =========================================================================
	// wp-config.php Loader Install / Remove
	// =========================================================================

	/**
	 * Inject load.php require into wp-config.php using marker comments.
	 *
	 * Uses `# BEGIN/END GrabWP Tenancy` markers (valid PHP comments) for clean
	 * install/removal. Places the block before the stop-editing marker on first
	 * install. The injected code uses file_exists() to prevent white-screen if
	 * the plugin is deleted without deactivation.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function install_loader() {
		$wp_config_path = ABSPATH . 'wp-config.php';
		self::begin_shared_write();

		if ( ! self::filesystem_is_writable( $wp_config_path ) ) {
			return array( 'success' => false, 'message' => 'wp-config.php is not writable. Please check file permissions.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $wp_config_path );

		if ( false === $content ) {
			return array( 'success' => false, 'message' => 'Could not read wp-config.php.' );
		}

		$marker_start = '# BEGIN GrabWP Tenancy';

		// Already installed with markers — idempotent.
		if ( strpos( $content, $marker_start ) !== false ) {
			return array( 'success' => true, 'message' => 'Loader is already present in wp-config.php.' );
		}

		// Legacy check: raw require without markers (from older versions).
		if ( strpos( $content, 'grabwp-tenancy/load.php' ) !== false ) {
			return array( 'success' => true, 'message' => 'Loader is already present in wp-config.php (legacy format).' );
		}

		// Build the marker block — safe with file_exists() guard.
		$block = $marker_start . "\n"
			. '( $_grabwpl = __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php" ) && file_exists( $_grabwpl ) && require_once $_grabwpl;' . "\n"
			. '# END GrabWP Tenancy' . "\n\n";

		// Find the stop-editing marker for placement.
		$pos = strpos( $content, "/* That's all, stop editing! Happy publishing. */" );
		if ( false === $pos ) {
			$pos = strpos( $content, "/* That's all, stop editing! */" );
		}

		if ( false === $pos ) {
			return array( 'success' => false, 'message' => 'Could not find the stop-editing marker in wp-config.php. Please add the line manually.' );
		}

		// Insert before the stop-editing marker.
		$new_content = substr( $content, 0, $pos ) . $block . substr( $content, $pos );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $wp_config_path, $new_content );

		if ( false === $result ) {
			return array( 'success' => false, 'message' => 'Could not write to wp-config.php. Please check file permissions.' );
		}
		if ( class_exists( 'GrabWP_Tenancy_Integrity_Watchdog' ) ) {
			GrabWP_Tenancy_Integrity_Watchdog::adopt_change( $wp_config_path ); // The snapshot guards the new loader right away.
		}

		return array( 'success' => true, 'message' => 'Loader installed to wp-config.php successfully.' );
	}

	/**
	 * Remove the GrabWP Tenancy loader block from wp-config.php.
	 *
	 * Removes everything between `# BEGIN GrabWP Tenancy` and `# END GrabWP Tenancy`
	 * markers (inclusive). Also handles legacy single-line format.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function remove_loader() {
		$wp_config_path = ABSPATH . 'wp-config.php';
		self::begin_shared_write();

		if ( ! self::filesystem_is_writable( $wp_config_path ) ) {
			return array( 'success' => false, 'message' => 'wp-config.php is not writable.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $wp_config_path );

		if ( false === $content ) {
			return array( 'success' => false, 'message' => 'Could not read wp-config.php.' );
		}

		$changed      = false;
		$marker_start = '# BEGIN GrabWP Tenancy';
		$marker_end   = '# END GrabWP Tenancy';
		$start_pos    = strpos( $content, $marker_start );

		// Remove marker block.
		if ( false !== $start_pos ) {
			$end_pos = strpos( $content, $marker_end, $start_pos );
			if ( false !== $end_pos ) {
				$block_end = $end_pos + strlen( $marker_end );
				$before    = rtrim( substr( $content, 0, $start_pos ) ) . "\n";
				$after     = ltrim( substr( $content, $block_end ) );
				$content   = $before . "\n" . $after;
				$changed   = true;
			}
		}

		// Also remove legacy single-line format (no markers).
		if ( ! $changed ) {
			$legacy_line = 'require_once __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php";';
			if ( strpos( $content, $legacy_line ) !== false ) {
				$content = str_replace( $legacy_line . "\n", '', $content );
				$content = str_replace( $legacy_line, '', $content );
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return array( 'success' => true, 'message' => 'Loader was not present in wp-config.php.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $wp_config_path, $content );

		if ( false === $result ) {
			return array( 'success' => false, 'message' => 'Could not write to wp-config.php.' );
		}

		return array( 'success' => true, 'message' => 'Loader removed from wp-config.php.' );
	}

	// =========================================================================
	// HTTPS behind a proxy (wp-config.php block) and https site addresses
	// =========================================================================

	/** Markers of the HTTPS proxy block in wp-config.php. */
	const PROXY_MARKER_START = '# BEGIN GrabWP HTTPS proxy';
	const PROXY_MARKER_END   = '# END GrabWP HTTPS proxy';

	/** @var callable|null Whether the site still loads; null asks over a loopback. Tests replace it. */
	private static $site_check = null;

	/**
	 * The HTTPS proxy block: turns HTTPS on when a proxy or CDN reports that
	 * the visitor used https (X-Forwarded-Proto, or Cloudflare's CF-Visitor).
	 * Plain PHP that needs nothing from GrabWP, so it stays valid without it.
	 *
	 * @since 1.1.14
	 * @return string
	 */
	public static function get_proxy_https_block() {
		return self::PROXY_MARKER_START . "\n" . <<<'PHP'
if ( ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( trim( current( explode( ',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) ) )
	|| ( isset( $_SERVER['HTTP_CF_VISITOR'] ) && false !== strpos( strtolower( $_SERVER['HTTP_CF_VISITOR'] ), '"https"' ) ) ) {
	$_SERVER['HTTPS'] = 'on';
}
PHP
			. "\n" . self::PROXY_MARKER_END . "\n";
	}

	/**
	 * Add the HTTPS proxy block to wp-config.php, before the GrabWP loader so
	 * tenant boot sees HTTPS too (otherwise before the stop-editing line).
	 *
	 * The write is atomic and keeps the file permissions. The old content is
	 * kept in the protected snapshot directory first. When the site no longer
	 * loads afterwards, the old content is put back.
	 *
	 * @since 1.1.14
	 * @param string|null $path wp-config.php path; null for the one next to WordPress.
	 * @return array{success: bool, message: string}
	 */
	public static function install_proxy_https( $path = null ) {
		$path = null === $path ? ABSPATH . 'wp-config.php' : $path;
		if ( self::is_tenant_request() ) {
			return self::failure( __( 'Run this from the main site.', 'grabwp-tenancy' ) );
		}
		self::begin_shared_write();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = is_readable( $path ) ? file_get_contents( $path ) : false;
		if ( false === $content ) {
			return self::failure( __( 'Could not read wp-config.php.', 'grabwp-tenancy' ) );
		}
		if ( false !== strpos( $content, self::PROXY_MARKER_START ) ) {
			return array(
				'success' => true,
				'message' => __( 'HTTPS detection is already in wp-config.php.', 'grabwp-tenancy' ),
			);
		}

		$pos = self::proxy_block_position( $content );
		if ( false === $pos ) {
			return self::failure( __( 'Could not find where to add the code in wp-config.php. Add it by hand.', 'grabwp-tenancy' ) );
		}
		$new = substr( $content, 0, $pos ) . self::get_proxy_https_block() . "\n" . substr( $content, $pos );
		$result = self::replace_wp_config( $path, $content, $new, 'pre-proxy' );
		if ( $result['success'] ) {
			$result['message'] = __( 'HTTPS detection added to wp-config.php.', 'grabwp-tenancy' );
		}
		return $result;
	}

	/**
	 * Remove the HTTPS proxy block from wp-config.php (Undo). Takes back exactly
	 * what install_proxy_https() added, with the same safe write.
	 *
	 * @since 1.1.14
	 * @param string|null $path wp-config.php path; null for the one next to WordPress.
	 * @return array{success: bool, message: string}
	 */
	public static function remove_proxy_https( $path = null ) {
		$path = null === $path ? ABSPATH . 'wp-config.php' : $path;
		if ( self::is_tenant_request() ) {
			return self::failure( __( 'Run this from the main site.', 'grabwp-tenancy' ) );
		}
		self::begin_shared_write();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = is_readable( $path ) ? file_get_contents( $path ) : false;
		if ( false === $content ) {
			return self::failure( __( 'Could not read wp-config.php.', 'grabwp-tenancy' ) );
		}

		$start = strpos( $content, self::PROXY_MARKER_START );
		$end   = false === $start ? false : strpos( $content, self::PROXY_MARKER_END, $start );
		if ( false === $end ) {
			return array(
				'success' => true,
				'message' => __( 'HTTPS detection by GrabWP was not in wp-config.php.', 'grabwp-tenancy' ),
			);
		}
		$end += strlen( self::PROXY_MARKER_END );
		// The line break after the end marker and the blank line added after it.
		foreach ( array( "\r\n", "\n" ) as $break ) {
			if ( substr( $content, $end, strlen( $break ) ) === $break ) {
				$end += strlen( $break );
				if ( substr( $content, $end, strlen( $break ) ) === $break ) {
					$end += strlen( $break );
				}
				break;
			}
		}
		$new    = substr( $content, 0, $start ) . substr( $content, $end );
		$result = self::replace_wp_config( $path, $content, $new, 'pre-proxy-undo' );
		if ( $result['success'] ) {
			$result['message'] = __( 'HTTPS detection removed from wp-config.php.', 'grabwp-tenancy' );
		}
		return $result;
	}

	/**
	 * Offset of the line the HTTPS proxy block goes before: the GrabWP loader,
	 * else the stop-editing line.
	 *
	 * @param string $content wp-config.php content.
	 * @return int|false
	 */
	private static function proxy_block_position( $content ) {
		$open = strpos( $content, '<?php' );
		foreach ( array( '# BEGIN GrabWP Tenancy', 'grabwp-tenancy/load.php', "/* That's all, stop editing! Happy publishing. */", "/* That's all, stop editing! */" ) as $anchor ) {
			$pos = strpos( $content, $anchor );
			if ( false === $pos || false === $open || $pos < $open ) {
				continue;
			}
			$line_start = strrpos( substr( $content, 0, $pos ), "\n" );
			$pos        = false === $line_start ? 0 : $line_start + 1;
			return $pos > $open ? $pos : false;
		}
		return false;
	}

	/**
	 * Replace wp-config.php safely. See replace_files().
	 *
	 * Call begin_shared_write() first.
	 *
	 * @param string $path  wp-config.php path.
	 * @param string $old   Current content.
	 * @param string $new   New content.
	 * @param string $label Backup label.
	 * @return array{success: bool, message: string}
	 */
	private static function replace_wp_config( $path, $old, $new, $label ) {
		return self::replace_files( array( compact( 'path', 'old', 'new' ) ), $label );
	}

	/**
	 * Replace shared root files safely, together.
	 *
	 * 1. Every new PHP file must parse.
	 * 2. The old content of each file is kept in the protected snapshot directory.
	 * 3. Atomic writes that keep the permissions; the watchdog adopts them.
	 * 4. Verify: each file reads back as written, and the site still loads when
	 *    it loaded before. Otherwise every file is put back the same way.
	 *
	 * Call begin_shared_write() first.
	 *
	 * @param array[] $changes path, old, new.
	 * @param string  $label   Backup label.
	 * @return array{success: bool, message: string}
	 */
	private static function replace_files( $changes, $label ) {
		$names = implode( ' + ', array_map( 'basename', array_column( $changes, 'path' ) ) );
		foreach ( $changes as $change ) {
			if ( ! self::filesystem_is_writable( $change['path'] ) ) {
				/* translators: %s: file name. */
				return self::failure( sprintf( __( '%s is not writable. Add the code by hand.', 'grabwp-tenancy' ), basename( $change['path'] ) ) );
			}
			if ( '.php' === substr( $change['path'], -4 ) && ! self::php_parses( $change['new'] ) ) {
				/* translators: %s: file name. */
				return self::failure( sprintf( __( '%s would not be valid PHP after the change, so nothing was changed.', 'grabwp-tenancy' ), basename( $change['path'] ) ) );
			}
		}
		foreach ( $changes as $change ) {
			if ( '' === GrabWP_Tenancy_Integrity_Watchdog::write_backup( basename( $change['path'] ) . '.' . $label . '.snap.php', $change['old'] ) ) {
				/* translators: %s: file name. */
				return self::failure( sprintf( __( 'Could not save a backup of %s, so nothing was changed.', 'grabwp-tenancy' ), basename( $change['path'] ) ) );
			}
		}

		$loaded_before = self::site_loads();
		$written       = array();
		$write_failed  = false;
		foreach ( $changes as $index => $change ) {
			clearstatcache( true, $change['path'] );
			$changes[ $index ]['mode'] = fileperms( $change['path'] ) & 0777;
			if ( ! GrabWP_Tenancy_Path_Manager::atomic_write( $change['path'], $change['new'], $changes[ $index ]['mode'] ) ) {
				$write_failed = true;
				break;
			}
			$written[] = $changes[ $index ];
			self::after_wp_config_write( $change['path'] );
		}
		/* translators: %s: file name(s). */
		$write_error = sprintf( __( 'Could not write %s. The folder must be writable. Add the code by hand.', 'grabwp-tenancy' ), $names );
		if ( $write_failed && ! $written ) {
			return self::failure( $write_error );
		}
		$ok = ! $write_failed;

		foreach ( $written as $change ) {
			clearstatcache( true, $change['path'] );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$ok = $ok && file_get_contents( $change['path'] ) === $change['new'];
		}
		if ( $ok && ( ! $loaded_before || self::site_loads() ) ) {
			return array(
				'success' => true,
				'message' => '',
			);
		}

		$restored = true;
		foreach ( $written as $change ) {
			$restored = GrabWP_Tenancy_Path_Manager::atomic_write( $change['path'], $change['old'], $change['mode'] ) && $restored;
			self::after_wp_config_write( $change['path'] );
		}
		if ( $write_failed && $restored ) {
			return self::failure( $write_error );
		}
		return self::failure(
			$restored
				/* translators: %s: file name(s). */
				? sprintf( __( 'The site did not load after the change, so %s was put back as it was.', 'grabwp-tenancy' ), $names )
				/* translators: %s: file name(s). */
				: sprintf( __( 'The site did not load after the change and %s could not be put back. Restore it from the backup in the GrabWP data folder.', 'grabwp-tenancy' ), $names )
		);
	}

	/** Watchdog adopts the change; the next request compiles the new file. */
	private static function after_wp_config_write( $path ) {
		GrabWP_Tenancy_Integrity_Watchdog::adopt_change( $path );
		if ( ! class_exists( 'GrabWP_Tenancy_Status_Checker' ) ) {
			require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		}
		GrabWP_Tenancy_Status_Checker::refresh_boot_files();
	}

	/**
	 * Whether PHP code parses (PHP 7+; older versions are not checked).
	 *
	 * @param string $code PHP file content.
	 * @return bool
	 */
	public static function php_parses( $code ) {
		if ( ! defined( 'TOKEN_PARSE' ) ) {
			return true;
		}
		try {
			token_get_all( $code, TOKEN_PARSE );
			return true;
		} catch ( \ParseError $e ) {
			return false;
		}
	}

	/** Whether the site answers its own loopback without a server error. */
	private static function site_loads() {
		return null === self::$site_check
			? GrabWP_Tenancy_Tenant_Installer::can_loopback()
			: (bool) call_user_func( self::$site_check );
	}

	/**
	 * Switch home and siteurl to https.
	 *
	 * Only when GrabWP_Tenancy_Status_Checker::get_https_url_status() allows
	 * it: valid https answers, this request is https, no WP_HOME / WP_SITEURL
	 * constants, main site. The old values are kept for Undo. Post content is
	 * not changed.
	 *
	 * @since 1.1.14
	 * @return array{success: bool, message: string}
	 */
	public static function fix_https_urls() {
		if ( ! class_exists( 'GrabWP_Tenancy_Status_Checker' ) ) {
			require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		}
		if ( self::is_tenant_request() ) {
			return self::failure( __( 'Run this from the main site.', 'grabwp-tenancy' ) );
		}
		$status = GrabWP_Tenancy_Status_Checker::get_https_url_status();
		if ( 'ok' === $status['state'] ) {
			return array(
				'success' => true,
				'message' => __( 'The site address already uses https.', 'grabwp-tenancy' ),
			);
		}
		if ( ! $status['fixable'] ) {
			return self::failure( __( 'The site address cannot be switched here yet. Check again and follow the steps in the card.', 'grabwp-tenancy' ) );
		}

		$old = array(
			'home'    => (string) get_option( 'home' ),
			'siteurl' => (string) get_option( 'siteurl' ),
		);
		update_option( GrabWP_Tenancy_Status_Checker::HTTPS_URL_UNDO_OPTION, $old + array( 'time' => time() ), false );
		update_option( 'home', set_url_scheme( $old['home'], 'https' ) );
		update_option( 'siteurl', set_url_scheme( $old['siteurl'], 'https' ) );
		delete_transient( GrabWP_Tenancy_Status_Checker::HOSTING_TRANSIENT );

		if ( 0 !== stripos( (string) get_option( 'home' ), 'https://' ) || 0 !== stripos( (string) get_option( 'siteurl' ), 'https://' ) ) {
			update_option( 'home', $old['home'] );
			update_option( 'siteurl', $old['siteurl'] );
			delete_option( GrabWP_Tenancy_Status_Checker::HTTPS_URL_UNDO_OPTION );
			return self::failure( __( 'Could not save the https address. Nothing was changed.', 'grabwp-tenancy' ) );
		}
		return array(
			'success' => true,
			'message' => __( 'The site address now uses https.', 'grabwp-tenancy' ),
		);
	}

	/**
	 * Undo fix_https_urls(): put back the saved home and siteurl, unless they
	 * were changed since.
	 *
	 * @since 1.1.14
	 * @return array{success: bool, message: string}
	 */
	public static function revert_https_urls() {
		if ( ! class_exists( 'GrabWP_Tenancy_Status_Checker' ) ) {
			require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		}
		if ( self::is_tenant_request() ) {
			return self::failure( __( 'Run this from the main site.', 'grabwp-tenancy' ) );
		}
		$old = get_option( GrabWP_Tenancy_Status_Checker::HTTPS_URL_UNDO_OPTION );
		if ( ! is_array( $old ) || ! isset( $old['home'], $old['siteurl'] ) ) {
			return self::failure( __( 'There is no address change to undo.', 'grabwp-tenancy' ) );
		}
		if ( get_option( 'home' ) !== set_url_scheme( $old['home'], 'https' ) || get_option( 'siteurl' ) !== set_url_scheme( $old['siteurl'], 'https' ) ) {
			delete_option( GrabWP_Tenancy_Status_Checker::HTTPS_URL_UNDO_OPTION );
			return self::failure( __( 'The site address was changed since, so it was not undone. Edit it in Settings, General.', 'grabwp-tenancy' ) );
		}
		update_option( 'home', $old['home'] );
		update_option( 'siteurl', $old['siteurl'] );
		delete_option( GrabWP_Tenancy_Status_Checker::HTTPS_URL_UNDO_OPTION );
		delete_transient( GrabWP_Tenancy_Status_Checker::HOSTING_TRANSIENT );
		return array(
			'success' => true,
			'message' => __( 'The site address is back to what it was.', 'grabwp-tenancy' ),
		);
	}

	// =========================================================================
	// Multisite settings left by a managed host in wp-config.php
	// =========================================================================

	/** Option that keeps what the Multisite cleanup wrote, for Undo. */
	const MULTISITE_UNDO_OPTION = 'grabwp_tenancy_multisite_undo';

	/**
	 * Plan the removal of the wp-config.php settings that stop GrabWP:
	 * GrabWP_Tenancy_Status_Checker::CONFLICT_CONSTANTS and CONFLICT_VARIABLES,
	 * whatever their value. Writes nothing.
	 *
	 * Only a whole top-level statement is removed. One inside a condition,
	 * or a removed name still used by other code, goes to 'manual'. A WP_HOME
	 * or WP_SITEURL set to a plain quoted URL goes to 'urls', so the main site
	 * keeps that address in its home / siteurl option.
	 *
	 * .htaccess is never changed: Multisite rules there do not affect GrabWP,
	 * which routes tenants through its own "GrabWP Tenancy" block.
	 *
	 * @since 1.1.14
	 * @param string $config wp-config.php content.
	 * @return array {
	 *     @type string   $config  New wp-config.php content.
	 *     @type bool     $changed Settings were found to remove.
	 *     @type string[] $removed Removed wp-config.php code.
	 *     @type string[] $urls    Option name ('home', 'siteurl') => URL the removed constant set.
	 *     @type array[]  $manual  To remove by hand: file, name, line, code.
	 * }
	 */
	public static function plan_multisite_cleanup( $config ) {
		if ( ! class_exists( 'GrabWP_Tenancy_Status_Checker' ) ) {
			require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		}
		$plan    = array(
			'config'  => (string) $config,
			'changed' => false,
			'removed' => array(),
			'urls'    => array(),
			'manual'  => array(),
		);
		$options = array(
			'WP_HOME'    => 'home',
			'WP_SITEURL' => 'siteurl',
		);
		$edits   = array();
		$gone    = array();
		foreach ( GrabWP_Tenancy_Status_Checker::scan_wp_config( $plan['config'] )['defines'] as $define ) {
			if ( ! $define['statement'] || $define['conditional'] ) {
				$plan['manual'][] = array(
					'file' => 'wp-config.php',
					'name' => $define['name'],
					'line' => $define['line'],
					'code' => $define['code'],
				);
				continue;
			}
			if ( isset( $options[ $define['name'] ] ) && preg_match( '#^([\'"])(https?://[^\'"$\\\\]+)\1$#i', $define['value'], $match ) ) {
				$plan['urls'][ $options[ $define['name'] ] ] = rtrim( $match[2], '/' );
			}
			$plan['changed']   = true;
			$gone[]            = $define['name'];
			$edits[]           = self::define_removal( $plan['config'], $define['start'], $define['end'] );
			$plan['removed'][] = $define['code'];
		}

		// Last first, so earlier byte offsets stay valid. $check keeps the line numbers.
		$check = $plan['config'];
		foreach ( array_reverse( $edits ) as $edit ) {
			$check          = substr_replace( $check, str_repeat( "\n", substr_count( substr( $check, $edit[0], $edit[1] ), "\n" ) ), $edit[0], $edit[1] );
			$plan['config'] = substr_replace( $plan['config'], $edit[2], $edit[0], $edit[1] );
		}
		// A removed name still used elsewhere would stop the site: leave it to the admin.
		$lines = explode( "\n", (string) $config );
		foreach ( self::constants_used( $check, $gone ) as $line => $name ) {
			$plan['manual'][] = array(
				'file' => 'wp-config.php',
				'name' => $name,
				'line' => $line,
				'code' => trim( $lines[ $line - 1 ] ),
			);
		}
		return $plan;
	}

	/**
	 * Byte range that removes a define() statement: the whole line when it
	 * holds nothing else (a trailing // or # comment goes with it).
	 *
	 * @param string $content Content.
	 * @param int    $start   Statement start.
	 * @param int    $end     Byte after the ;.
	 * @return array start, length, replacement.
	 */
	private static function define_removal( $content, $start, $end ) {
		$line_start = strrpos( substr( $content, 0, $start ), "\n" );
		$line_start = false === $line_start ? 0 : $line_start + 1;
		if ( '' === trim( substr( $content, $line_start, $start - $line_start ) )
			&& preg_match( '/\G[ \t]*(?:(?:\/\/|#)(?:(?!\?>)[^\r\n])*)?(?:\r\n|\n|\r|$)/', $content, $match, 0, $end ) ) {
			return array( $line_start, $end + strlen( $match[0] ) - $line_start, '' );
		}
		return array( $start, $end - $start, '' );
	}

	/**
	 * Lines where code still uses one of the given constants or '$name' variables.
	 *
	 * @param string   $content PHP code.
	 * @param string[] $names   Constant names and '$name' variable names.
	 * @return string[] Line => name.
	 */
	private static function constants_used( $content, $names ) {
		$used = array();
		if ( ! $names || ! function_exists( 'token_get_all' ) ) {
			return $used;
		}
		foreach ( @token_get_all( $content ) as $token ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! is_array( $token ) ) {
				continue;
			}
			// PHP 8 reads \NAME as one token.
			$name = ltrim( $token[1], '\\' );
			if ( ( T_STRING === $token[0] || T_VARIABLE === $token[0] || ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $token[0] ) )
				&& in_array( $name, $names, true ) ) {
				$used[ $token[2] ] = $name;
			}
		}
		return $used;
	}

	/**
	 * Remove the settings that stop GrabWP from wp-config.php, on an explicit
	 * confirmed click (never by Fix automatically). .htaccess is never
	 * changed, see plan_multisite_cleanup().
	 *
	 * Refused for a network with more than one site, on tenants, and when a
	 * setting can only be removed by hand. A removed WP_HOME / WP_SITEURL URL
	 * is saved to the home / siteurl option first; Undo puts the old values back. The file is written with
	 * the safe write of replace_files() and backed up first ("pre-multisite").
	 * Running it again on a clean site changes nothing.
	 *
	 * @since 1.1.14
	 * @param string|null $config_path wp-config.php path; null for the one next to WordPress.
	 * @return array{success: bool, message: string}
	 */
	public static function remove_multisite_settings( $config_path = null ) {
		$config_path = null === $config_path ? ABSPATH . 'wp-config.php' : $config_path;
		if ( ! class_exists( 'GrabWP_Tenancy_Status_Checker' ) ) {
			require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		}
		if ( self::is_tenant_request() ) {
			return self::failure( __( 'Run this from the main site.', 'grabwp-tenancy' ) );
		}
		self::begin_shared_write();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$config = is_readable( $config_path ) ? file_get_contents( $config_path ) : false;
		if ( false === $config ) {
			return self::failure( __( 'Could not read wp-config.php.', 'grabwp-tenancy' ) );
		}

		$status = GrabWP_Tenancy_Status_Checker::get_multisite_status( $config );
		if ( 'network' === $status['state'] ) {
			return self::failure(
				sprintf(
					/* translators: %d: number of sites in the Multisite network. */
					__( 'This is a Multisite network with %d sites, so nothing was changed. Back up or move those sites first.', 'grabwp-tenancy' ),
					(int) $status['sites']
				)
			);
		}

		$plan = self::plan_multisite_cleanup( $config );
		if ( $plan['manual'] ) {
			return self::failure(
				sprintf(
					/* translators: %s: list of file, line and code to edit by hand. */
					__( 'Some wp-config.php settings must be removed by hand, so nothing was changed: %s', 'grabwp-tenancy' ),
					self::describe_lines( $plan['manual'] )
				)
			);
		}
		if ( ! $plan['changed'] ) {
			return array(
				'success' => true,
				'message' => __( 'No settings that stop GrabWP were left in wp-config.php.', 'grabwp-tenancy' ),
			);
		}

		// The main site keeps the address the removed WP_HOME / WP_SITEURL gave it.
		$options = self::save_url_options( $plan['urls'] );
		if ( false === $options ) {
			return self::failure( __( 'Could not save the site address. Nothing was changed.', 'grabwp-tenancy' ) );
		}
		$result = self::replace_wp_config( $config_path, $config, $plan['config'], 'pre-multisite' );
		if ( ! $result['success'] ) {
			self::update_options( $options );
			return $result;
		}

		update_option(
			self::MULTISITE_UNDO_OPTION,
			array(
				'files'   => array(
					array(
						'path' => $config_path,
						'old'  => sha1( $config ),
						'new'  => sha1( $plan['config'] ),
					),
				),
				'options' => $options,
				'time'    => time(),
			),
			false
		);
		delete_transient( GrabWP_Tenancy_Status_Checker::HOSTING_TRANSIENT );

		return array(
			'success' => true,
			'message' => __( 'Settings that stop GrabWP were removed from wp-config.php. Log in again if asked, then activate plugins that were network activated.', 'grabwp-tenancy' ),
		);
	}

	/**
	 * Undo remove_multisite_settings(): put back the exact bytes of
	 * wp-config.php, unless it was changed since, and the home / siteurl
	 * options it changed.
	 *
	 * @since 1.1.14
	 * @return array{success: bool, message: string}
	 */
	public static function undo_multisite_settings() {
		if ( ! class_exists( 'GrabWP_Tenancy_Status_Checker' ) ) {
			require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		}
		if ( self::is_tenant_request() ) {
			return self::failure( __( 'Run this from the main site.', 'grabwp-tenancy' ) );
		}
		$undo = get_option( self::MULTISITE_UNDO_OPTION );
		if ( ! is_array( $undo ) || empty( $undo['files'] ) || ! is_array( $undo['files'] ) ) {
			return self::failure( __( 'There is no Multisite cleanup to undo.', 'grabwp-tenancy' ) );
		}
		self::begin_shared_write();

		$changes = array();
		foreach ( $undo['files'] as $file ) {
			$name   = basename( $file['path'] );
			$backup = GrabWP_Tenancy_Integrity_Watchdog::get_snapshot_dir() . '/' . $name . '.pre-multisite.snap.php';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$current = is_readable( $file['path'] ) ? file_get_contents( $file['path'] ) : false;
			if ( false === $current || sha1( $current ) !== $file['new'] ) {
				return self::failure(
					sprintf(
						/* translators: %s: file name. */
						__( '%s was changed since the Multisite cleanup, so nothing was undone. The old copy is kept in the GrabWP data folder.', 'grabwp-tenancy' ),
						$name
					)
				);
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$old    = is_readable( $backup ) ? (string) file_get_contents( $backup ) : '';
			$header = GrabWP_Tenancy_Integrity_Watchdog::HEADER;
			$old    = 0 === strpos( $old, $header ) ? substr( $old, strlen( $header ) ) : '';
			if ( sha1( $old ) !== $file['old'] ) {
				return self::failure(
					sprintf(
						/* translators: %s: file name. */
						__( 'The backup of %s is missing or changed, so nothing was undone.', 'grabwp-tenancy' ),
						$name
					)
				);
			}
			$changes[] = array(
				'path' => $file['path'],
				'old'  => $current,
				'new'  => $old,
			);
		}

		$result = self::replace_files( $changes, 'pre-multisite-undo' );
		if ( ! $result['success'] ) {
			return $result;
		}
		self::update_options( isset( $undo['options'] ) ? (array) $undo['options'] : array() );
		delete_option( self::MULTISITE_UNDO_OPTION );
		delete_transient( GrabWP_Tenancy_Status_Checker::HOSTING_TRANSIENT );
		return array(
			'success' => true,
			'message' => __( 'The removed settings are back in wp-config.php.', 'grabwp-tenancy' ),
		);
	}

	/**
	 * Save the home / siteurl options in the database. Core filters both with
	 * WP_HOME / WP_SITEURL, which hides the saved value from get_option() and
	 * makes update_option() skip the write, so those filters are off meanwhile.
	 *
	 * @param string[] $urls option => URL.
	 * @return string[]|false option => previous value, or false (nothing changed).
	 */
	private static function save_url_options( $urls ) {
		$filters = array(
			'option_home'    => '_config_wp_home',
			'option_siteurl' => '_config_wp_siteurl',
		);
		$removed = array();
		foreach ( $filters as $hook => $callback ) {
			if ( remove_filter( $hook, $callback ) ) {
				$removed[ $hook ] = $callback;
			}
		}
		$options = array();
		foreach ( $urls as $option => $url ) {
			$current = (string) get_option( $option );
			if ( $current === $url ) {
				continue;
			}
			$options[ $option ] = $current;
			update_option( $option, $url );
			if ( (string) get_option( $option ) !== $url ) {
				self::update_options( $options );
				$options = false;
				break;
			}
		}
		foreach ( $removed as $hook => $callback ) {
			add_filter( $hook, $callback );
		}
		return $options;
	}

	/**
	 * @param array $options Option name => value.
	 */
	private static function update_options( $options ) {
		foreach ( $options as $option => $value ) {
			update_option( $option, $value );
		}
	}

	/**
	 * "file line N: code" list.
	 *
	 * @param array[] $lines file, line, code.
	 * @return string
	 */
	private static function describe_lines( $lines ) {
		$parts = array();
		foreach ( $lines as $line ) {
			$parts[] = sprintf(
				/* translators: 1: file name, 2: line number, 3: code on that line. */
				__( '%1$s line %2$d: %3$s', 'grabwp-tenancy' ),
				$line['file'],
				(int) $line['line'],
				$line['code']
			);
		}
		return implode( '; ', $parts );
	}

	/** Whether this request runs for a tenant. */
	private static function is_tenant_request() {
		return defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT;
	}

	/**
	 * @param string $message Message.
	 * @return array{success: bool, message: string}
	 */
	private static function failure( $message ) {
		return array(
			'success' => false,
			'message' => $message,
		);
	}

	// =========================================================================
	// Fix Helpers (for status page "Fix Now" buttons)
	// =========================================================================

	/**
	 * Fix root .htaccess by adding/repositioning the GrabWP Tenancy rewrite block.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function fix_root_htaccess() {
		self::add_site_path_rewrite_rules();

		// Verify it was written.
		$htaccess_file = ABSPATH . '.htaccess';
		if ( is_readable( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$content = file_get_contents( $htaccess_file );
			if ( false !== $content && strpos( $content, '# BEGIN GrabWP Tenancy' ) !== false ) {
				return array( 'success' => true, 'message' => 'Root .htaccess GrabWP Tenancy block installed successfully.' );
			}
		}

		return array( 'success' => false, 'message' => 'Could not write to .htaccess. Please check file permissions.' );
	}

	/**
	 * Fix data directory .htaccess security protection.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function fix_data_htaccess() {
		$base_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$result   = self::create_htaccess_for_directory( $base_dir, 'GrabWP Tenancy Security Protection' );

		if ( $result ) {
			return array( 'success' => true, 'message' => 'Data directory .htaccess created successfully.' );
		}

		return array( 'success' => false, 'message' => 'Could not create data directory .htaccess. Please check file permissions.' );
	}

	/**
	 * Fix index.php protection in data directory.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function fix_index_protection() {
		$base_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$result   = self::create_index_protection_for_directory( $base_dir, 'GrabWP_Tenancy' );

		if ( $result ) {
			return array( 'success' => true, 'message' => 'index.php protection created successfully.' );
		}

		return array( 'success' => false, 'message' => 'Could not create index.php protection. Please check file permissions.' );
	}

	// =========================================================================
	// Shared-file lock (on by default, opt-out from the Status page)
	// =========================================================================

	/** True while protected root files are temporarily writable for a main-site write. */
	private static $shared_write_open = false;

	/**
	 * Protected root files (basenames relative to ABSPATH).
	 *
	 * @since 1.1.9
	 * @return string[]
	 */
	public static function get_protected_files() {
		return (array) apply_filters( 'grabwp_tenancy_protected_files', array( 'wp-config.php', '.htaccess', '.user.ini', 'web.config' ) );
	}

	/**
	 * Whether the shared-file lock is on (any existing protected file is read-only 0444).
	 *
	 * @since 1.1.9
	 * @return bool
	 */
	public static function shared_files_locked() {
		foreach ( self::get_protected_files() as $file ) {
			$path = ABSPATH . $file;
			clearstatcache( true, $path );
			if ( is_file( $path ) && 0444 === ( fileperms( $path ) & 0777 ) ) {
				return true;
			}
		}
		return false;
	}

	/** chmod every existing protected file; returns basenames that failed. */
	private static function chmod_shared_files( $mode ) {
		$failed = array();
		foreach ( self::get_protected_files() as $file ) {
			$path = ABSPATH . $file;
			if ( is_file( $path ) && ! @chmod( $path, $mode ) ) {
				$failed[] = $file;
			}
			clearstatcache( true, $path );
		}
		return $failed;
	}

	/** Lock protected root files read-only (0444), best effort per file. */
	public static function lock_shared_files() {
		self::$shared_write_open = false;
		$failed = self::chmod_shared_files( 0444 );
		if ( $failed ) {
			return array( 'success' => false, 'failed' => $failed, 'message' => 'Watching all files, but could not lock: ' . implode( ', ', $failed ) . '. Check file ownership.' );
		}
		return array( 'success' => true, 'message' => 'Shared files locked.' );
	}

	/**
	 * Turn protection on: snapshot every file (watchdog on), lock what can be
	 * locked, and clear the admin opt-out marker. Never fatal.
	 *
	 * @since 1.1.9
	 */
	public static function protect_shared_files() {
		GrabWP_Tenancy_Integrity_Watchdog::set_opt_out( false );
		GrabWP_Tenancy_Integrity_Watchdog::create_snapshots();
		return self::lock_shared_files();
	}

	/**
	 * Turn protection off: watchdog off, files 0644. With $opt_out the admin
	 * choice is recorded so auto-protect never re-locks; without it (plugin
	 * deactivation) the marker is cleared too.
	 *
	 * @since 1.1.9
	 */
	public static function unprotect_shared_files( $opt_out ) {
		GrabWP_Tenancy_Integrity_Watchdog::delete_snapshots();
		GrabWP_Tenancy_Integrity_Watchdog::set_opt_out( $opt_out );
		self::$shared_write_open = false;
		return self::unlock_shared_files();
	}

	/**
	 * Main-site admin_init: enable protection once on installs that never set
	 * it up (update path). Stat-only: opt-out marker plus first snapshot.
	 *
	 * @since 1.1.9
	 */
	public static function maybe_auto_protect() {
		if ( GrabWP_Tenancy_Integrity_Watchdog::is_opted_out() || GrabWP_Tenancy_Integrity_Watchdog::has_snapshots()
			|| ! apply_filters( 'grabwp_tenancy_auto_protect_shared_files', true ) ) {
			return;
		}
		self::protect_shared_files();
	}

	/** Unlock protected root files (0644). */
	public static function unlock_shared_files() {
		$failed = self::chmod_shared_files( 0644 );
		if ( $failed ) {
			return array( 'success' => false, 'message' => 'Could not unlock: ' . implode( ', ', $failed ) . '. Check file ownership.' );
		}
		return array( 'success' => true, 'message' => 'Shared files unlocked.' );
	}

	/**
	 * Open a write window for a legitimate main-site write while locked.
	 *
	 * No-op when unlocked. Files are re-locked by end_shared_write(), which is
	 * also registered on shutdown so every exit path re-locks.
	 *
	 * @since 1.1.9
	 */
	public static function begin_shared_write() {
		if ( self::$shared_write_open || ! self::shared_files_locked() ) {
			return;
		}
		// Put back lost GrabWP blocks first so end_shared_write() never adopts their loss.
		if ( class_exists( 'GrabWP_Tenancy_Integrity_Watchdog' ) ) {
			GrabWP_Tenancy_Integrity_Watchdog::check();
		}
		self::chmod_shared_files( 0644 );
		self::$shared_write_open = true;
		add_action( 'shutdown', array( __CLASS__, 'end_shared_write' ), 1 );
	}

	/**
	 * Close the write window: refresh watchdog snapshots so the legitimate
	 * change becomes the new known-good state, then re-lock.
	 *
	 * @since 1.1.9
	 */
	public static function end_shared_write() {
		if ( ! self::$shared_write_open ) {
			return;
		}
		if ( class_exists( 'GrabWP_Tenancy_Integrity_Watchdog' ) && GrabWP_Tenancy_Integrity_Watchdog::count_snapshots() > 0 ) {
			GrabWP_Tenancy_Integrity_Watchdog::create_snapshots();
		}
		self::lock_shared_files();
	}

	/**
	 * Main-site hard rewrite flush (permalink save, plugin activation): open the
	 * write window right before WordPress writes .htaccess / web.config.
	 *
	 * @since 1.1.9
	 * @param bool $hard Whether to flush hard.
	 * @return bool Unchanged.
	 */
	public static function allow_hard_flush( $hard ) {
		if ( $hard ) {
			self::begin_shared_write();
		}
		return $hard;
	}

	/** Permalinks screen POST: open the write window before core checks is_writable(). */
	public static function on_permalink_screen() {
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			self::begin_shared_write();
		}
	}

	// =========================================================================
	// Root .htaccess Rewrite Rules
	// =========================================================================

	/**
	 * Write /{prefix}/[tenant-id] rewrite rules into the WordPress root .htaccess.
	 *
	 * CRITICAL: These rules MUST appear BEFORE the WordPress rewrite block,
	 * otherwise WordPress's catch-all `RewriteRule . /index.php [L]` matches
	 * /site/{id}/* first (the path doesn't exist on disk) and the tenant
	 * rewrite rules never fire — causing a redirect loop.
	 *
	 * insert_with_markers() appends new blocks at the end of the file, so we
	 * manually reposition the block before "# BEGIN WordPress" after writing.
	 *
	 * @since 1.1.0
	 */
	public static function add_site_path_rewrite_rules( $prefix = '' ) {
		$htaccess_file = ABSPATH . '.htaccess';
		$prefix        = '' !== $prefix ? $prefix : grabwp_tenancy_get_path_prefix();
		self::begin_shared_write();

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		insert_with_markers( $htaccess_file, 'GrabWP Tenancy', self::get_site_path_rewrite_rules( $prefix ) );
		self::reposition_htaccess_block( $htaccess_file );
		// New rules (prefix change) are the known-good block, also when the lock could not be set.
		if ( class_exists( 'GrabWP_Tenancy_Integrity_Watchdog' ) ) {
			GrabWP_Tenancy_Integrity_Watchdog::adopt_change( $htaccess_file );
		}
	}

	/**
	 * Lines of the "GrabWP Tenancy" .htaccess block for a path prefix.
	 *
	 * A tenant path that is not a file or directory (pretty permalink, REST,
	 * feed) goes straight to /index.php. Apache reaches the same result by
	 * running .htaccess again on the stripped path, where the WordPress block
	 * sends it to index.php; OpenLiteSpeed (xCloud) runs .htaccess once, so the
	 * strip rule alone ends in a server 404. .php files (also with a path
	 * after them, like index.php/2026/hello/) and the wp-admin, wp-content and
	 * wp-includes paths always keep the strip rule, so they work as before
	 * even where DOCUMENT_ROOT is not the WordPress root.
	 *
	 * @since 1.1.13
	 * @param string $prefix Path prefix, for example "site".
	 * @return string[]
	 */
	public static function get_site_path_rewrite_rules( $prefix ) {
		$slug = '([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)';

		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'# Tenant homepage: /' . $prefix . '/{tenant-id-or-alias}[/] → WordPress front-end with site param',
			'RewriteRule ^' . $prefix . '/' . $slug . '/?$ /index.php?site=$1 [QSA,L]',
			'# Tenant pages, REST, feeds (not a file or directory): WordPress front-end with site param',
			'RewriteCond %{REQUEST_URI} !\.php(/|$)',
			'RewriteCond %{REQUEST_URI} !/wp-(admin|content|includes)(/|$)',
			'RewriteCond %{DOCUMENT_ROOT}/$2 !-f',
			'RewriteCond %{DOCUMENT_ROOT}/$2 !-d',
			'RewriteRule ^' . $prefix . '/' . $slug . '/(.+)$ /index.php?site=$1 [QSA,L]',
			'# Tenant files and directories (wp-admin, wp-login, assets): strip prefix, pass site param',
			'RewriteRule ^' . $prefix . '/' . $slug . '/(.+)$ /$2?site=$1 [QSA,L,NE]',
			'</IfModule>',
		);
	}

	/**
	 * Whether .htaccess content holds the current GrabWP Tenancy rules.
	 *
	 * Path and "both" modes need every RewriteCond and RewriteRule line of
	 * get_site_path_rewrite_rules(). A block written before single-pass
	 * routing only has the homepage and strip rules; it is reported as
	 * outdated so the Status "Fix" button rewrites it. Domain mode never uses
	 * the path rules and keeps the homepage-rule check (current or legacy
	 * 6-char tenant ID).
	 *
	 * @since 1.1.13
	 * @param string $content .htaccess content.
	 * @param string $prefix  Path prefix, for example "site".
	 * @param string $mode    Routing mode: path, domain or both.
	 * @return bool
	 */
	public static function htaccess_has_site_path_rules( $content, $prefix, $mode ) {
		$rules = self::get_site_path_rewrite_rules( $prefix );

		if ( 'domain' === $mode ) {
			// $rules[3] is the tenant homepage RewriteRule.
			return false !== strpos( $content, $rules[3] )
				|| false !== strpos( $content, 'RewriteRule ^' . $prefix . '/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]' );
		}

		foreach ( preg_grep( '/^Rewrite(Cond|Rule) /', $rules ) as $line ) {
			if ( false === strpos( $content, $line ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Remove path routing rewrite rules from .htaccess on deactivation.
	 *
	 * @since 1.1.0
	 */
	public static function remove_site_path_rewrite_rules() {
		$htaccess_file = ABSPATH . '.htaccess';
		self::begin_shared_write();

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		insert_with_markers( $htaccess_file, 'GrabWP Tenancy', array() );
	}

	// =========================================================================
	// Data Directory Security Files (reusable utilities)
	// =========================================================================

	/**
	 * Create .htaccess file for any directory (reusable utility).
	 *
	 * @since 1.0.0
	 * @param string $directory      Directory path where to create .htaccess.
	 * @param string $comment_header Header comment for the .htaccess file.
	 * @return bool Success status.
	 */
	public static function create_htaccess_for_directory( $directory, $comment_header = 'GrabWP Tenancy Security Protection' ) {
		if ( empty( $directory ) || ! is_string( $directory ) ) {
			return false;
		}

		$htaccess_file = $directory . '/.htaccess';

		$htaccess_content  = "# {$comment_header}\n";
		$htaccess_content .= "# Prevent directory listing\n";
		$htaccess_content .= "Options -Indexes\n\n";
		$htaccess_content .= "# Deny access to PHP files\n";
		$htaccess_content .= "<FilesMatch \"\\.php$\">\n";
		$htaccess_content .= "    <IfModule mod_authz_core.c>\n";
		$htaccess_content .= "        Require all denied\n";
		$htaccess_content .= "    </IfModule>\n";
		$htaccess_content .= "    <IfModule !mod_authz_core.c>\n";
		$htaccess_content .= "        Order allow,deny\n";
		$htaccess_content .= "        Deny from all\n";
		$htaccess_content .= "    </IfModule>\n";
		$htaccess_content .= "</FilesMatch>\n\n";

		if ( ! file_exists( $directory ) ) {
			if ( ! wp_mkdir_p( $directory ) ) {
				return false;
			}
		}

		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = @file_put_contents( $htaccess_file, $htaccess_content );
			return false !== $result;
		}

		return true;
	}

	/**
	 * Create index.php protection for any directory (reusable utility).
	 *
	 * @since 1.0.0
	 * @param string $directory Directory path where to create index.php.
	 * @param string $package   Package name for the comment.
	 * @return bool Success status.
	 */
	public static function create_index_protection_for_directory( $directory, $package = 'GrabWP_Tenancy' ) {
		if ( empty( $directory ) || ! is_string( $directory ) ) {
			return false;
		}

		$index_file = $directory . '/index.php';

		$index_content  = "<?php\n";
		$index_content .= "/**\n";
		$index_content .= " * {$package} - Directory Protection\n";
		$index_content .= " * \n";
		$index_content .= " * @package {$package}\n";
		$index_content .= " */\n\n";
		$index_content .= "// Silence is golden.\n";

		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = @file_put_contents( $index_file, $index_content );
			return false !== $result;
		}

		return true;
	}

	// =========================================================================
	// MU-Plugin Helpers
	// =========================================================================

	/**
	 * Get the mu-plugins directory path.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_mu_plugins_dir() {
		return defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( ABSPATH . 'wp-content/mu-plugins' );
	}

	/**
	 * Get the expected mu-plugin file path.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_mu_plugin_path() {
		return self::get_mu_plugins_dir() . '/' . self::MU_PLUGIN_FILE;
	}

	/**
	 * Generate the mu-plugin file content.
	 *
	 * Uses __DIR__ for portable paths that work even if wp-content is relocated.
	 *
	 * @since 1.3.0
	 * @return string PHP file content.
	 */
	public static function get_mu_plugin_content() {
		return <<<'PHP'
<?php
// GrabWP Tenancy MU-Plugin — auto-generated.
$mu_grabwp_base = __DIR__ . '/../plugins/grabwp-tenancy/grabwp-tenancy.php';
$mu_grabwp_pro  = __DIR__ . '/../plugins/grabwp-tenancy-pro/grabwp-tenancy-pro.php';
if ( file_exists( $mu_grabwp_base ) ) { require_once $mu_grabwp_base; }
if ( file_exists( $mu_grabwp_pro ) )  { require_once $mu_grabwp_pro; }

PHP;
	}

	/**
	 * Get the loader line used in wp-config.php (for admin notice display).
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_loader_snippet() {
		return '( $_grabwpl = __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php" ) && file_exists( $_grabwpl ) && require_once $_grabwpl;';
	}

	/**
	 * Check if wp-config.php contains the stop-editing marker.
	 *
	 * @since 1.3.0
	 * @param string $wp_config_path Absolute path to wp-config.php.
	 * @return bool True if a marker was found.
	 */
	public static function has_stop_editing_marker( $wp_config_path ) {
		if ( ! is_readable( $wp_config_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $wp_config_path );
		if ( false === $content ) {
			return false;
		}

		return strpos( $content, "/* That's all, stop editing! Happy publishing. */" ) !== false
			|| strpos( $content, "/* That's all, stop editing! */" ) !== false;
	}

	// =========================================================================
	// Private Helpers
	// =========================================================================

	/**
	 * Move the "GrabWP Tenancy" block before the "WordPress" block in .htaccess.
	 *
	 * @since 1.1.0
	 * @param string $htaccess_file Path to .htaccess file.
	 */
	private static function reposition_htaccess_block( $htaccess_file ) {
		if ( ! is_readable( $htaccess_file ) || ! self::filesystem_is_writable( $htaccess_file ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$contents = file_get_contents( $htaccess_file );
		if ( false === $contents ) {
			return;
		}

		$marker_start = '# BEGIN GrabWP Tenancy';
		$marker_end   = '# END GrabWP Tenancy';
		$wp_start     = '# BEGIN WordPress';

		$grabwp_pos = strpos( $contents, $marker_start );
		$wp_pos     = strpos( $contents, $wp_start );

		if ( false === $grabwp_pos || false === $wp_pos || $grabwp_pos < $wp_pos ) {
			return;
		}

		$end_pos = strpos( $contents, $marker_end );
		if ( false === $end_pos ) {
			return;
		}
		$block_end    = $end_pos + strlen( $marker_end );
		$grabwp_block = substr( $contents, $grabwp_pos, $block_end - $grabwp_pos );

		$before_block = rtrim( substr( $contents, 0, $grabwp_pos ) );
		$after_block  = ltrim( substr( $contents, $block_end ) );
		$contents     = $before_block . "\n" . $after_block;

		$wp_pos   = strpos( $contents, $wp_start );
		$contents = substr( $contents, 0, $wp_pos ) . $grabwp_block . "\n\n" . substr( $contents, $wp_pos );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $htaccess_file, $contents );
	}

	/**
	 * Remove legacy tokens.php and .consumed remnants.
	 *
	 * @since 1.0.4
	 */
	private static function cleanup_legacy_token_files() {
		$base_dir   = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$token_file = $base_dir . '/tokens.php';

		if ( file_exists( $token_file ) ) {
			@unlink( $token_file );
		}

		$consumed = glob( $base_dir . '/tokens.php.consumed.*' );
		if ( is_array( $consumed ) ) {
			foreach ( $consumed as $file ) {
				@unlink( $file );
			}
		}
	}

	/**
	 * Create .htaccess file for data directory.
	 *
	 * @since 1.0.0
	 */
	private static function create_htaccess() {
		$grabwp_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		self::create_htaccess_for_directory( $grabwp_dir, 'GrabWP Tenancy Security Protection' );
	}

	/**
	 * Create index.php protection for data directory.
	 *
	 * @since 1.0.0
	 */
	private static function create_index_protection() {
		$grabwp_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		self::create_index_protection_for_directory( $grabwp_dir, 'GrabWP_Tenancy' );
	}

	/**
	 * Create necessary directories.
	 *
	 * @since 1.0.0
	 */
	private static function create_directories() {
		$grabwp_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();

		if ( ! file_exists( $grabwp_dir ) ) {
			wp_mkdir_p( $grabwp_dir );
		}
	}

	/**
	 * Create default tenant mappings file.
	 *
	 * @since 1.0.0
	 */
	private static function create_tenant_mappings_file() {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();

		if ( ! file_exists( $mappings_file ) ) {
			$content  = "<?php\n";
			$content .= "/**\n";
			$content .= " * Tenant Domain Mappings\n";
			$content .= " * Generated by GrabWP Tenancy Plugin\n";
			$content .= " * \n";
			$content .= " * This file contains domain mappings for tenant identification.\n";
			$content .= " * Format: \$tenant_mappings['tenant_id'] = array( 'domain1', 'domain2' );\n";
			$content .= " * \n";
			$content .= " * @package GrabWP_Tenancy\n";
			$content .= " */\n\n";
			$content .= "defined( 'ABSPATH' ) || exit; // Exit if accessed directly\n\n";
			$content .= "\$tenant_mappings = array(\n";
			$content .= "    // Example: 'abc123' => array( 'tenant1.grabwp.local' ),\n";
			$content .= ");\n";

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $mappings_file, $content );
		}
	}

	// =========================================================================
	// Filesystem Helpers
	// =========================================================================

	/**
	 * Check if a path is writable using WP_Filesystem.
	 *
	 * @since 1.3.1
	 * @param string $path Absolute path to check.
	 * @return bool
	 */
	private static function filesystem_is_writable( $path ) {
		global $wp_filesystem;

		// During an unlock window the files were just chmod-ed writable; non-direct
		// WP_Filesystem transports may not see that, so trust the local check.
		if ( self::$shared_write_open ) {
			return is_writable( $path );
		}

		if ( empty( $wp_filesystem ) ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
		}

		return $wp_filesystem ? $wp_filesystem->is_writable( $path ) : false;
	}
}
