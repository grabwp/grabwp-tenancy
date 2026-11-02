<?php
/**
 * GrabWP Tenancy Integrity Watchdog
 *
 * Keeps one known-good snapshot of each shared root file (wp-config.php,
 * .htaccess, .user.ini, web.config). At the end of tenant write requests it
 * compares each live file with its snapshot (stat first, content only on stat
 * mismatch). Only GrabWP's own `# BEGIN/END GrabWP ...` blocks are guarded:
 * a block that is missing or altered is put back from the snapshot (same
 * markers, same place the installer uses), every other byte is kept. Any
 * other edit is allowed and adopted as the new snapshot without a notice.
 * A deleted file holding a GrabWP block is recreated from its snapshot.
 * An empty, truncated or broken file is never written or adopted: it is
 * reported once and the snapshot stays as the last good copy. Repairs are
 * written atomically (temp file + rename).
 * A missing MU-plugin or data-dir protection file is reinstalled. Repairs
 * are logged (hashes only) and leave a marker for the Status page notice.
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
	const BLOCK_PATTERN  = '/^# BEGIN (GrabWP [^\r\n]*?)\r?\n.*?^# END \1(?=\r?$)/ms'; // Each END matches its own BEGIN.

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
		if ( ! self::ensure_snapshot_dir() ) {
			return false;
		}

		foreach ( GrabWP_Tenancy_Installer::get_protected_files() as $file ) {
			self::snapshot_file( $file );
		}
		return true;
	}

	/**
	 * Create the snapshot directory and deny web access to it.
	 *
	 * @since 1.1.14
	 * @return bool Whether the directory exists.
	 */
	public static function ensure_snapshot_dir() {
		$dir = self::get_snapshot_dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		GrabWP_Tenancy_Installer::create_index_protection_for_directory( $dir );
		return true;
	}

	/**
	 * Keep a copy of a file's content in the snapshot directory, behind the
	 * exit header, so it is never served. Never a .bak next to the file.
	 *
	 * @since 1.1.14
	 * @param string $name    Backup file name, ending in .snap.php.
	 * @param string $content Content to keep.
	 * @return string Backup path, '' on failure.
	 */
	public static function write_backup( $name, $content ) {
		if ( ! self::ensure_snapshot_dir() ) {
			return '';
		}
		$path = self::get_snapshot_dir() . '/' . $name;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false === @file_put_contents( $path, self::HEADER . $content, LOCK_EX ) ? '' : $path;
	}

	/**
	 * After a legitimate change of a protected file, make it the known-good
	 * state right away, so a tenant request finishing meanwhile never restores
	 * the old content. No-op while the watchdog is off.
	 *
	 * @since 1.1.14
	 * @param string $path Absolute path of the changed file.
	 */
	public static function adopt_change( $path ) {
		$basename = basename( $path );
		if ( ABSPATH . $basename === $path && is_file( self::snapshot_path( $basename ) ) ) {
			self::snapshot_file( $basename );
		}
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$original = is_file( $snap ) ? substr( (string) @file_get_contents( $snap ), strlen( self::HEADER ) ) : '';
		if ( false === $content || ( '' !== $original && ! self::is_valid( $basename, $content, $original ) ) ) {
			return false; // Never replace the last good copy with an empty or broken file.
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === @file_put_contents( $snap, self::HEADER . $content, LOCK_EX ) ) {
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

	/**
	 * Shutdown check. Stat first; content is read only when a file's stat
	 * differs from its snapshot. Only GrabWP's own blocks are put back, every
	 * other edit is kept and adopted as the new snapshot.
	 */
	public static function check() {
		if ( ! is_dir( self::get_snapshot_dir() ) ) {
			return;
		}

		$changed  = array();
		$watching = false;
		foreach ( GrabWP_Tenancy_Installer::get_protected_files() as $file ) {
			$snap = self::snapshot_path( $file );
			if ( ! is_file( $snap ) ) {
				continue;
			}
			$watching = true;
			$live     = ABSPATH . $file;
			clearstatcache( true, $live );
			clearstatcache( true, $snap );
			$size  = @filesize( $live );
			$mtime = @filemtime( $live );
			if ( false !== $size && $size + strlen( self::HEADER ) === filesize( $snap ) && $mtime === filemtime( $snap ) ) {
				continue;
			}

			// Stat differs: compare content. A file that exists but cannot be read is skipped, never overwritten,
			// and so is a dangling symlink (never replaced by a regular file).
			if ( is_link( $live ) && false === realpath( $live ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$original = substr( (string) @file_get_contents( $snap ), strlen( self::HEADER ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$current = file_exists( $live ) ? @file_get_contents( $live ) : false;
			if ( false === $current && file_exists( $live ) ) {
				continue;
			}
			if ( false !== $current && $current === $original ) {
				@touch( $snap, $mtime );
				continue;
			}

			$blocks = self::find_blocks( $original );
			if ( false === $current && ! $blocks ) {
				wp_delete_file( $snap ); // Deleted file with no GrabWP block: allowed.
				continue;
			}
			// A deleted file has no other edits to keep: the snapshot is the smallest working file.
			// An empty, truncated or broken file is never adopted or written to: the snapshot stays as the recovery copy.
			$fixed = false;
			if ( false === $current ) {
				$fixed = $original;
			} elseif ( self::is_valid( $file, $current, $original ) ) {
				$fixed = self::put_back_blocks( $file, $current, $blocks );
			}
			if ( $fixed === $current ) {
				self::snapshot_file( $file ); // GrabWP blocks intact: adopt silently.
				self::relock_if_locked( $live );
				continue;
			}
			if ( false !== $fixed && ! self::is_valid( $file, $fixed, $original ) ) {
				$fixed = false;
			}
			clearstatcache( true, $snap );
			if ( ! is_file( $snap ) ) {
				continue; // Watchdog turned off meanwhile (deactivation): write nothing.
			}

			$changed[ $file ] = array(
				'blocks'   => false === $current ? array_keys( $blocks ) : array_keys( array_diff_assoc( $blocks, self::find_blocks( $current ) ) ),
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
				'write'    => false === $fixed ? 'skipped' : self::write_fixed( $file, $fixed, $current ),
			);
			$changed[ $file ]['restored'] = in_array( $changed[ $file ]['write'], array( 'atomic', 'direct' ), true );
		}

		if ( $watching ) {
			$changed = array_merge( $changed, self::check_support_files() );
		}
		if ( ! empty( $changed ) ) {
			self::report( $changed );
		}
	}

	/**
	 * GrabWP marker blocks (`# BEGIN GrabWP ...` to the matching `# END`).
	 *
	 * @param string $content File content.
	 * @return array Block text by name, in file order.
	 */
	private static function find_blocks( $content ) {
		preg_match_all( self::BLOCK_PATTERN, $content, $matches, PREG_SET_ORDER );
		$blocks = array();
		foreach ( $matches as $match ) {
			$blocks[ $match[1] ] = $match[0];
		}
		return $blocks;
	}

	/** Offset of the line that requires wp-settings.php (a real statement, not a comment), false if none. */
	private static function settings_require_pos( $content ) {
		return preg_match( '/^[ \t]*require(?:_once)?\b[^;]*wp-settings\.php/m', $content, $match, PREG_OFFSET_CAPTURE ) ? $match[0][1] : false;
	}

	/**
	 * Cheap sanity check before a file is adopted or written: never on the hot path.
	 * Every GrabWP BEGIN marker needs its END. wp-config.php: PHP open tag, the
	 * wp-settings.php require when the snapshot had it, and the code must parse
	 * (the installer's own check, never executed). .htaccess: non-empty and the
	 * WordPress block complete when the snapshot had one. Other files: non-empty
	 * when the snapshot was.
	 *
	 * @param string $file     Protected file basename.
	 * @param string $content  Content to check.
	 * @param string $original Snapshot content.
	 * @return bool
	 */
	private static function is_valid( $file, $content, $original ) {
		$markers = preg_match_all( '/^# (?:BEGIN|END) GrabWP /m', $content );
		if ( $markers !== 2 * preg_match_all( self::BLOCK_PATTERN, $content ) || ( '' === trim( $content ) && '' !== trim( $original ) ) ) {
			return false;
		}
		if ( '.htaccess' === substr( $file, -9 ) ) {
			$wp = '/^# BEGIN WordPress\r?$.*^# END WordPress\r?$/ms';
			return ! preg_match( $wp, $original ) || preg_match( $wp, $content );
		}
		if ( '.php' !== substr( $file, -4 ) ) {
			return true;
		}
		if ( false === stripos( $content, '<?php' ) || ( false !== self::settings_require_pos( $original ) && false === self::settings_require_pos( $content ) ) ) {
			return false;
		}
		return GrabWP_Tenancy_Installer::php_parses( $content );
	}

	/**
	 * Replace altered blocks in place and insert missing ones where the
	 * installer puts them. Every other byte stays as it is.
	 *
	 * @param string $file    Protected file basename.
	 * @param string $content Live content.
	 * @param array  $blocks  Known-good blocks from the snapshot.
	 * @return string|false New content, false when a block has no safe place to go.
	 */
	private static function put_back_blocks( $file, $content, $blocks ) {
		$php = '.php' === substr( $file, -4 );
		foreach ( $blocks as $name => $block ) {
			$quoted = preg_quote( $name, '/' );
			if ( preg_match( '/^# BEGIN ' . $quoted . '\r?\n.*?^# END ' . $quoted . '(?=\r?$)/ms', $content, $match, PREG_OFFSET_CAPTURE ) ) {
				$content = substr_replace( $content, $block, $match[0][1], strlen( $match[0][0] ) );
				continue;
			}
			// HTTPS proxy goes before the loader; the loader before the stop-editing
			// line or the wp-settings.php require; .htaccess rules before WordPress.
			$require = $php ? self::settings_require_pos( $content ) : false;
			$anchors = $php ? array( "/* That's all, stop editing!" ) : array( '# BEGIN WordPress' );
			if ( $php && 'GrabWP Tenancy' !== $name ) {
				array_unshift( $anchors, '# BEGIN GrabWP Tenancy', 'grabwp-tenancy/load.php' );
			}
			$pos = false;
			foreach ( $anchors as $anchor ) {
				$pos = strpos( $content, $anchor );
				if ( false !== $pos ) {
					break;
				}
			}
			if ( $php && ( false === $pos || ( false !== $require && $pos > $require ) ) ) {
				$pos = $require;
			}
			if ( false === $pos ) {
				if ( $php ) {
					return false; // After the wp-settings.php require would be too late.
				}
				$content = rtrim( $content ) . "\n\n" . $block . "\n";
				continue;
			}
			$line_start = strrpos( substr( $content, 0, $pos ), "\n" );
			$pos        = false === $line_start ? 0 : $line_start + 1;
			if ( $php && 'GrabWP Tenancy' === $name && preg_match( '/^[ \t]*\$table_prefix\s*=/m', $content, $match, 0, $pos ) ) {
				return false; // The loader must run after the main $table_prefix assignment.
			}
			$content = substr( $content, 0, $pos ) . $block . "\n\n" . substr( $content, $pos );
		}
		return $content;
	}

	/**
	 * Write the repaired content in one step (temp file + rename next to the
	 * real file, so a symlinked file stays a symlink), set the mode (0444,
	 * the Shared Files Lock mode, while the lock is on; otherwise keep the
	 * file's own mode, 0644 for a recreated file), check what was written,
	 * refresh the snapshot. A direct write is used only when the directory is
	 * not writable; if that write does not land intact the previous content is
	 * written back.
	 *
	 * @param string       $basename Protected file basename.
	 * @param string       $content  Repaired content.
	 * @param string|false $previous Live content before the repair, false if missing.
	 * @return string 'atomic', 'direct' or 'failed'.
	 */
	private static function write_fixed( $basename, $content, $previous ) {
		$live   = ABSPATH . $basename;
		$target = realpath( $live );
		$target = false === $target ? $live : $target;
		$mode   = GrabWP_Tenancy_Installer::shared_files_locked() ? 0444 : ( is_file( $target ) ? fileperms( $target ) & 0777 : 0644 );
		$how    = GrabWP_Tenancy_Path_Manager::atomic_write( $target, $content, $mode ) ? 'atomic' : 'failed';
		if ( 'failed' === $how && false !== $previous && ! is_writable( dirname( $target ) ) ) {
			@chmod( $target, 0644 );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$how = strlen( $content ) === @file_put_contents( $target, $content, LOCK_EX ) ? 'direct' : 'failed';
			if ( 'failed' === $how ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				@file_put_contents( $target, $previous, LOCK_EX );
			}
			@chmod( $target, $mode );
			if ( function_exists( 'opcache_invalidate' ) ) {
				@opcache_invalidate( $target, true );
			}
		}
		clearstatcache( true, $target );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		if ( 'failed' !== $how && @file_get_contents( $target ) === $content ) {
			self::snapshot_file( $basename );
			return $how;
		}
		return 'failed';
	}

	/** Keep the shared-file lock on an adopted file. */
	private static function relock_if_locked( $live ) {
		if ( 0444 !== ( fileperms( $live ) & 0777 ) && GrabWP_Tenancy_Installer::shared_files_locked() ) {
			@chmod( $live, 0444 );
		}
	}

	/** Stat-only: put back a missing MU-plugin or data-dir protection file. */
	private static function check_support_files() {
		$changed = array();
		$data    = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$files   = array(
			GrabWP_Tenancy_Installer::get_mu_plugin_path() => function () {
				$result = GrabWP_Tenancy_Installer::install_mu_plugin();
				return $result['success'];
			},
			$data . '/.htaccess'                           => function () use ( $data ) {
				return GrabWP_Tenancy_Installer::create_htaccess_for_directory( $data );
			},
			$data . '/index.php'                           => function () use ( $data ) {
				return GrabWP_Tenancy_Installer::create_index_protection_for_directory( $data );
			},
		);
		foreach ( $files as $path => $put_back ) {
			if ( ! is_file( $path ) ) {
				// Path relative to ABSPATH, or the last two parts: never a full server path.
				$key             = 0 === strpos( $path, ABSPATH ) ? substr( $path, strlen( ABSPATH ) ) : basename( dirname( $path ) ) . '/' . basename( $path );
				$changed[ $key ] = array(
					'after'    => 'missing',
					'restored' => $put_back(),
				);
			}
		}
		return $changed;
	}

	/** Latest violation marker, empty array when there is none. */
	private static function read_marker() {
		$file = self::get_snapshot_dir() . '/' . self::VIOLATION_FILE;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$marker = is_file( $file ) ? json_decode( substr( (string) @file_get_contents( $file ), strlen( self::HEADER ) ), true ) : null;
		return is_array( $marker ) ? $marker : array();
	}

	private static function write_marker( array $marker ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( self::get_snapshot_dir() . '/' . self::VIOLATION_FILE, self::HEADER . wp_json_encode( $marker ), LOCK_EX );
	}

	/**
	 * Log the violation, leave the notice marker and notify extensions.
	 *
	 * Only stat data and hashes are logged, never file contents. A repair that
	 * failed is reported once: the marker keeps a hash of each failed file's
	 * state, and the same failure is not reported again until that state
	 * changes, even after the notice is dismissed.
	 *
	 * @param array $changed Per-file before/after data.
	 */
	private static function report( array $changed ) {
		$previous = self::read_marker();
		$seen     = isset( $previous['failed'] ) ? (array) $previous['failed'] : array();
		$failed   = array();
		$restored = array();
		foreach ( $changed as $file => $info ) {
			if ( ! empty( $info['restored'] ) ) {
				$restored[] = $file;
			} else {
				$failed[ $file ] = hash( 'sha256', wp_json_encode( array( isset( $info['before'] ) ? $info['before'] : null, $info['after'] ) ) );
				if ( isset( $seen[ $file ] ) && $seen[ $file ] === $failed[ $file ] ) {
					unset( $changed[ $file ] );
				}
			}
		}
		if ( empty( $changed ) ) {
			if ( $failed !== $seen ) {
				$previous['failed'] = $failed;
				self::write_marker( $previous );
			}
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ), 2 ) : array( '' );
		$context = array(
			'files'          => $changed,
			'tenant_id'      => defined( 'GRABWP_TENANCY_TENANT_ID' ) ? GRABWP_TENANCY_TENANT_ID : '',
			'user_id'        => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
			'method'         => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'cli',
			'request_uri'    => esc_url_raw( $uri[0] ), // Path only: the query string can carry tokens.
			'action'         => isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '',
			'active_plugins' => (array) get_option( 'active_plugins', array() ),
		);
		// phpcs:enable

		GrabWP_Tenancy_Logger::log(
			count( $restored ) === count( $changed )
				? 'Integrity watchdog: GrabWP block or file lost, put back; other edits kept.'
				: 'Integrity watchdog: GrabWP could not put back every lost block or file (see "restored"); files it could not repair were left as they are and the last good snapshot was kept.',
			$context
		);

		self::write_marker(
			array(
				'time'       => time(),
				'tenant_id'  => $context['tenant_id'],
				'files'      => $restored,
				'unrepaired' => array_keys( $failed ),
				'failed'     => $failed,
			)
		);

		do_action( 'grabwp_tenancy_integrity_violation', array_keys( $changed ), $context );
	}

	/** Status page notice for the latest violation, with nonce-checked dismiss. */
	public static function show_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'grabwp-tenancy-status' !== $page || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$marker = self::read_marker();
		if ( ! $marker || ! empty( $marker['dismissed'] ) ) {
			return;
		}

		if ( isset( $_GET['grabwp_dismiss_integrity'], $_GET['_wpnonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'grabwp_dismiss_integrity' ) ) {
			$marker['dismissed'] = true; // Kept so a known failure is not reported again.
			self::write_marker( $marker );
			return;
		}

		$lines = array();
		if ( ! empty( $marker['files'] ) ) {
			$lines[] = esc_html__( 'GrabWP lost some of its own code in these files and put it back. Other edits were kept:', 'grabwp-tenancy' ) . ' <code>' . esc_html( implode( ', ', (array) $marker['files'] ) ) . '</code>';
		}
		if ( ! empty( $marker['unrepaired'] ) ) {
			$lines[] = esc_html__( 'GrabWP could not put back its own code in these files because they are empty, broken or not writable, so it left them as they are and kept the last good copy in the integrity snapshots folder. Check them now:', 'grabwp-tenancy' ) . ' <code>' . esc_html( implode( ', ', (array) $marker['unrepaired'] ) ) . '</code>';
		}
		if ( ! $lines ) {
			return;
		}
		$dismiss_url = wp_nonce_url( add_query_arg( 'grabwp_dismiss_integrity', '1' ), 'grabwp_dismiss_integrity' );
		printf(
			'<div class="notice notice-warning grabwp-integrity-notice"><p><strong>GrabWP Tenancy:</strong> %s (%s). %s <a href="%s">%s</a></p></div>',
			implode( ' ', $lines ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			sprintf( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped parts.
				/* translators: 1: tenant ID, 2: date and time of the change (UTC). */
				esc_html__( 'tenant %1$s, %2$s', 'grabwp-tenancy' ),
				'<code>' . esc_html( (string) $marker['tenant_id'] ) . '</code>',
				esc_html( gmdate( 'Y-m-d H:i:s', (int) $marker['time'] ) . ' UTC' )
			),
			esc_html__( 'See grabwp_debug.log for details. To remove a GrabWP block on purpose, turn protection off on the Status page first.', 'grabwp-tenancy' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'grabwp-tenancy' )
		);
	}
}
