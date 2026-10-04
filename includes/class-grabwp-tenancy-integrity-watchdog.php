<?php
/**
 * GrabWP Tenancy Integrity Watchdog
 *
 * Keeps known-good snapshots of the shared root files (wp-config.php,
 * .htaccess, .user.ini, web.config) while the shared-file lock is on. At the
 * end of tenant write requests it compares each live file with its snapshot
 * (stat first, content only on stat mismatch), restores any change, logs the
 * request context and leaves a marker for the main-site admin notice.
 *
 * Snapshots are stored as `<basename>.snap.php` with an exit header so they
 * are never served or executed, even on servers that ignore .htaccess.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Integrity_Watchdog {

	const SNAPSHOT_DIR   = '.integrity-snapshots';
	const HEADER         = "<?php exit; ?>\n"; // Prepended to every file in the snapshot dir.
	const VIOLATION_FILE = 'violation.php';   // Marker read by the main-site notice.
	const OPT_OUT_FILE   = 'disabled.php';    // Admin unlocked: auto-protect stays off.

	public static function get_snapshot_dir() {
		return GRABWP_TENANCY_BASE_DIR . '/' . self::SNAPSHOT_DIR;
	}

	private static function snapshot_path( $basename ) {
		return self::get_snapshot_dir() . '/' . $basename . '.snap.php';
	}

	public static function count_snapshots() {
		$count = 0;
		foreach ( GrabWP_Tenancy_Installer::get_protected_files() as $file ) {
			if ( is_file( self::snapshot_path( $file ) ) ) {
				$count++;
			}
		}
		return $count;
	}

	/** Stat-only: stops at the first snapshot found. */
	public static function has_snapshots() {
		foreach ( GrabWP_Tenancy_Installer::get_protected_files() as $file ) {
			if ( is_file( self::snapshot_path( $file ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_opted_out() {
		return is_file( self::get_snapshot_dir() . '/' . self::OPT_OUT_FILE );
	}

	/** Write or remove the admin opt-out marker. */
	public static function set_opt_out( $opt_out ) {
		$marker = self::get_snapshot_dir() . '/' . self::OPT_OUT_FILE;
		if ( ! $opt_out ) {
			if ( is_file( $marker ) ) {
				wp_delete_file( $marker );
			}
			return;
		}
		if ( is_dir( self::get_snapshot_dir() ) || wp_mkdir_p( self::get_snapshot_dir() ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $marker, self::HEADER . time() );
		}
	}

	/**
	 * Whether the current request should be checked at shutdown.
	 *
	 * Front-end GET/HEAD requests never are. Called once at plugin init so
	 * the shutdown hook is not even registered for front-end reads.
	 *
	 * @return bool
	 */
	public static function is_watched_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		return ! in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/** Snapshot every existing protected file and protect the directory. */
	public static function create_snapshots() {
		$dir = self::get_snapshot_dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		GrabWP_Tenancy_Installer::create_index_protection_for_directory( $dir );

		foreach ( GrabWP_Tenancy_Installer::get_protected_files() as $file ) {
			self::snapshot_file( $file );
		}
		return true;
	}

	/** Snapshot one protected file, copying its mtime so later checks are stat-only. */
	public static function snapshot_file( $basename ) {
		$live = ABSPATH . $basename;
		$snap = self::snapshot_path( $basename );
		if ( ! is_file( $live ) || ! is_dir( self::get_snapshot_dir() ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = @file_get_contents( $live );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $content || false === @file_put_contents( $snap, self::HEADER . $content, LOCK_EX ) ) {
			return false;
		}
		clearstatcache( true, $live );
		@touch( $snap, filemtime( $live ) );
		return true;
	}

	/** Remove all snapshots (turns the watchdog off). The violation marker stays. */
	public static function delete_snapshots() {
		foreach ( GrabWP_Tenancy_Installer::get_protected_files() as $file ) {
			$snap = self::snapshot_path( $file );
			if ( is_file( $snap ) ) {
				wp_delete_file( $snap );
			}
		}
	}

	/** Shutdown check: compare live files with snapshots and restore changes. */
	public static function check() {
		if ( ! is_dir( self::get_snapshot_dir() ) ) {
			return;
		}

		$changed = array();
		foreach ( GrabWP_Tenancy_Installer::get_protected_files() as $file ) {
			$snap = self::snapshot_path( $file );
			if ( ! is_file( $snap ) ) {
				continue;
			}
			$live = ABSPATH . $file;
			clearstatcache( true, $live );
			clearstatcache( true, $snap );
			$size  = @filesize( $live );
			$mtime = @filemtime( $live );
			if ( false !== $size && $size + strlen( self::HEADER ) === filesize( $snap ) && $mtime === filemtime( $snap ) ) {
				continue;
			}

			// Stat differs: compare content.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$original = substr( (string) @file_get_contents( $snap ), strlen( self::HEADER ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$current = false === $size ? false : @file_get_contents( $live );
			if ( false !== $current && $current === $original ) {
				@touch( $snap, $mtime );
				continue;
			}

			$changed[ $file ] = array(
				'before'   => array(
					'size'   => strlen( $original ),
					'sha256' => hash( 'sha256', $original ),
				),
				'after'    => false === $current ? 'missing' : array(
					'size'   => $size,
					'mtime'  => $mtime,
					'perms'  => substr( sprintf( '%o', @fileperms( $live ) ), -4 ),
					'sha256' => hash( 'sha256', $current ),
				),
				'restored' => self::restore( $file, $original ),
			);
		}

		if ( ! empty( $changed ) ) {
			self::report( $changed );
		}
	}

	/** Write the snapshot content back and re-lock the file. */
	private static function restore( $basename, $original ) {
		$live = ABSPATH . $basename;
		if ( is_file( $live ) ) {
			@chmod( $live, 0644 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$ok = false !== @file_put_contents( $live, $original, LOCK_EX );
		@chmod( $live, 0444 );
		if ( $ok ) {
			clearstatcache( true, $live );
			@touch( self::snapshot_path( $basename ), filemtime( $live ) );
		}
		return $ok;
	}

	/**
	 * Log the violation, leave the notice marker and notify extensions.
	 *
	 * Only stat data and hashes are logged, never file contents.
	 *
	 * @param array $changed Per-file before/after data.
	 */
	private static function report( array $changed ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$context = array(
			'files'          => $changed,
			'tenant_id'      => defined( 'GRABWP_TENANCY_TENANT_ID' ) ? GRABWP_TENANCY_TENANT_ID : '',
			'user_id'        => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
			'method'         => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'cli',
			'request_uri'    => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			'action'         => isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '',
			'active_plugins' => (array) get_option( 'active_plugins', array() ),
		);
		// phpcs:enable

		GrabWP_Tenancy_Logger::log( 'Integrity watchdog: protected file changed during tenant request, restored from snapshot.', $context );

		$marker = array(
			'time'      => time(),
			'tenant_id' => $context['tenant_id'],
			'files'     => array_keys( $changed ),
		);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( self::get_snapshot_dir() . '/' . self::VIOLATION_FILE, self::HEADER . wp_json_encode( $marker ), LOCK_EX );

		do_action( 'grabwp_tenancy_integrity_violation', array_keys( $changed ), $context );
	}

	/** Main-site admin notice for the latest violation, with nonce-checked dismiss. */
	public static function show_notice() {
		$marker_file = self::get_snapshot_dir() . '/' . self::VIOLATION_FILE;
		if ( ! is_file( $marker_file ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['grabwp_dismiss_integrity'], $_GET['_wpnonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_dismiss_integrity' ) ) {
			wp_delete_file( $marker_file );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$marker = json_decode( substr( (string) file_get_contents( $marker_file ), strlen( self::HEADER ) ), true );
		if ( ! is_array( $marker ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'grabwp_dismiss_integrity', '1' ), 'grabwp_dismiss_integrity' );
		printf(
			'<div class="notice notice-warning grabwp-integrity-notice"><p><strong>GrabWP Tenancy:</strong> %s <code>%s</code> (%s <code>%s</code>, %s). %s <a href="%s">%s</a></p></div>',
			esc_html__( 'A tenant request changed protected files; they were restored from the snapshot:', 'grabwp-tenancy' ),
			esc_html( implode( ', ', (array) $marker['files'] ) ),
			esc_html__( 'tenant', 'grabwp-tenancy' ),
			esc_html( (string) $marker['tenant_id'] ),
			esc_html( gmdate( 'Y-m-d H:i:s', (int) $marker['time'] ) . ' UTC' ),
			esc_html__( 'See grabwp_debug.log for details. If you changed these files yourself, use Unlock then Lock on the Status page to refresh the snapshot.', 'grabwp-tenancy' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'grabwp-tenancy' )
		);
	}
}
