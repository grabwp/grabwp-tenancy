<?php
/**
 * GrabWP Tenancy Management store
 *
 * Optional admin-only tenant names and tags, kept in one file in the GrabWP
 * data folder ({base}/management.php) so tenants.php, settings.php and
 * early-boot mapping stay untouched and nothing is stored in the database.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.17
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only reader and writer of management.php.
 *
 * Every read-modify-write holds an exclusive lock on .management.lock, re-reads
 * the file under the lock, and replaces it atomically. Readers take no lock.
 *
 * @since 1.1.17
 */
class GrabWP_Tenancy_Management {

	/**
	 * Data file name in the GrabWP data folder.
	 */
	const FILE = 'management.php';

	/**
	 * Lock file name. It is never deleted: the data file inode changes on each
	 * write, so the lock must live on a separate, stable file.
	 */
	const LOCK_FILE = '.management.lock';

	/**
	 * Data format version.
	 */
	const VERSION = 1;

	/**
	 * Maximum tenant name length in characters.
	 */
	const NAME_MAX = 100;

	/**
	 * Maximum tag name length in characters.
	 */
	const TAG_NAME_MAX = 50;

	/**
	 * Maximum tag slug length in characters.
	 */
	const SLUG_MAX = 50;

	/**
	 * Seconds to wait for the lock before a write gives up.
	 */
	const LOCK_TIMEOUT = 5;

	/**
	 * Hidden field the forms post so an empty tag list can still clear tags.
	 */
	const MARKER = 'grabwp_management_present';

	/**
	 * Per-request cache: array( 'data' => array, 'exists' => bool, 'corrupt' => bool ).
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Remove a tenant's entry when the tenant is deleted. Main site only.
	 */
	public static function register() {
		add_action( 'grabwp_tenancy_after_delete_tenant', array( __CLASS__, 'delete_tenant' ) );
	}

	/**
	 * Path of the data file.
	 *
	 * @return string
	 */
	public static function path() {
		return GrabWP_Tenancy_Path_Manager::get_config_file_path( self::FILE );
	}

	/**
	 * Path of the lock file.
	 *
	 * @return string
	 */
	public static function lock_path() {
		return GrabWP_Tenancy_Path_Manager::get_config_file_path( self::LOCK_FILE );
	}

	/**
	 * Stored data, read once per request. A missing or broken file reads as the defaults.
	 *
	 * @return array { version: int, enabled: bool, tags: array, tenants: array }
	 */
	public static function read() {
		if ( null === self::$cache ) {
			self::$cache = self::load_file( false );
		}
		return self::$cache['data'];
	}

	/**
	 * Forget the per-request cache so the next read loads the file again.
	 */
	public static function reset_cache() {
		self::$cache = null;
	}

	/**
	 * Whether the file exists but does not hold valid data. Writes are refused then.
	 *
	 * @return bool
	 */
	public static function is_corrupt() {
		self::read();
		return self::$cache['corrupt'];
	}

	/**
	 * Whether tenant names and tags are shown. A missing file or key means yes.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$data = self::read();
		return $data['enabled'];
	}

	/**
	 * Turn tenant names and tags on or off. Stored data is kept either way.
	 *
	 * @param bool $enabled On or off.
	 * @return bool
	 */
	public static function set_enabled( $enabled ) {
		$enabled = (bool) $enabled;
		return true === self::mutate(
			function ( $data ) use ( $enabled ) {
				$data['enabled'] = $enabled;
				return array( $data, true );
			}
		);
	}

	/**
	 * Tag vocabulary sorted by name (natural, case-insensitive).
	 *
	 * PHP turns an all-digit slug key such as "2024" into an int, so cast keys
	 * to string before a strict comparison with the string slugs in
	 * get_tenant()['tags'].
	 *
	 * @return array slug => array( 'name' => string )
	 */
	public static function get_tags() {
		$data = self::read();
		return self::sort_tags( $data['tags'] );
	}

	/**
	 * All tenant entries keyed by tenant ID.
	 *
	 * @return array tenant_id => array( 'name' => string, 'tags' => string[] )
	 */
	public static function get_tenants() {
		$data = self::read();
		return $data['tenants'];
	}

	/**
	 * Name and tag slugs for one tenant; empty values when none are stored.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return array { name: string, tags: string[] }
	 */
	public static function get_tenant( $tenant_id ) {
		$tenants = self::get_tenants();
		$tenant_id = (string) $tenant_id;
		if ( isset( $tenants[ $tenant_id ] ) ) {
			return $tenants[ $tenant_id ];
		}
		return array(
			'name' => '',
			'tags' => array(),
		);
	}

	/**
	 * Number of tenants that carry each tag.
	 *
	 * @return array slug => int, for every tag in the vocabulary.
	 */
	public static function tenant_counts() {
		$data   = self::read();
		$counts = array_fill_keys( array_keys( $data['tags'] ), 0 );
		foreach ( $data['tenants'] as $entry ) {
			foreach ( $entry['tags'] as $slug ) {
				if ( isset( $counts[ $slug ] ) ) {
					++$counts[ $slug ];
				}
			}
		}
		return $counts;
	}

	/**
	 * Add a tag, or edit one when $old_slug is given. A slug change moves the
	 * tag on every tenant in the same write.
	 *
	 * @param string $slug     Raw slug; empty keeps $old_slug, or derives it from the name for a new tag.
	 * @param string $name     Raw (unslashed) tag name.
	 * @param string $old_slug Slug of the tag being edited; empty adds a new tag.
	 * @return true|WP_Error
	 */
	public static function save_tag( $slug, $name, $old_slug = '' ) {
		$name = self::limit( sanitize_text_field( (string) $name ), self::TAG_NAME_MAX );
		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Enter a tag name.', 'grabwp-tenancy' ) );
		}

		$old_slug = (string) $old_slug;
		if ( '' !== trim( (string) $slug ) ) {
			$slug = self::clean_slug( $slug );
		} elseif ( '' !== $old_slug ) {
			$slug = $old_slug;
		} else {
			$slug = self::slug_for_name( $name );
		}
		if ( '' === $slug ) {
			return new WP_Error( 'invalid_slug', __( 'The slug can use lowercase letters, numbers and hyphens only.', 'grabwp-tenancy' ) );
		}

		$result = self::mutate(
			function ( $data ) use ( $slug, $name, $old_slug ) {
				if ( '' !== $old_slug && ! isset( $data['tags'][ $old_slug ] ) ) {
					return array( null, new WP_Error( 'not_found', __( 'That tag no longer exists.', 'grabwp-tenancy' ) ) );
				}
				if ( $slug !== $old_slug && isset( $data['tags'][ $slug ] ) ) {
					return array( null, new WP_Error( 'duplicate_slug', __( 'A tag with that slug already exists.', 'grabwp-tenancy' ) ) );
				}

				if ( '' !== $old_slug && $slug !== $old_slug ) {
					unset( $data['tags'][ $old_slug ] );
					foreach ( $data['tenants'] as $tenant_id => $entry ) {
						$data['tenants'][ $tenant_id ]['tags'] = array_values(
							array_unique(
								array_map(
									function ( $tag ) use ( $old_slug, $slug ) {
										return $tag === $old_slug ? $slug : $tag;
									},
									$entry['tags']
								)
							)
						);
					}
				}
				$data['tags'][ $slug ] = array( 'name' => $name );

				return array( $data, true );
			}
		);

		return false === $result ? self::write_error() : $result;
	}

	/**
	 * Delete tags from the vocabulary and from every tenant in one write.
	 *
	 * @param string[] $slugs Tag slugs.
	 * @return int|false Number of tags deleted, or false when the write failed.
	 */
	public static function delete_tags( array $slugs ) {
		$slugs = array_values( array_unique( array_filter( array_map( 'strval', $slugs ), 'strlen' ) ) );

		return self::mutate(
			function ( $data ) use ( $slugs ) {
				$deleted = array_values( array_intersect( $slugs, array_keys( $data['tags'] ) ) );
				if ( empty( $deleted ) ) {
					return array( null, 0 );
				}
				foreach ( $deleted as $slug ) {
					unset( $data['tags'][ $slug ] );
				}
				foreach ( $data['tenants'] as $tenant_id => $entry ) {
					$data['tenants'][ $tenant_id ]['tags'] = array_values( array_diff( $entry['tags'], $deleted ) );
				}
				return array( $data, count( $deleted ) );
			}
		);
	}

	/**
	 * Create a tag from a name typed in a tenant form. A tag with the same slug
	 * is returned as it is, so typing a name twice picks the same tag.
	 *
	 * @param string $name Raw (unslashed) tag name.
	 * @return array|WP_Error { slug: string, name: string }
	 */
	public static function create_tag_inline( $name ) {
		$name = self::limit( sanitize_text_field( (string) $name ), self::TAG_NAME_MAX );
		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Enter a tag name.', 'grabwp-tenancy' ) );
		}
		$slug = self::slug_for_name( $name );

		$result = self::mutate(
			function ( $data ) use ( $slug, $name ) {
				if ( isset( $data['tags'][ $slug ] ) ) {
					return array(
						null,
						array(
							'slug' => $slug,
							'name' => $data['tags'][ $slug ]['name'],
						),
					);
				}
				$data['tags'][ $slug ] = array( 'name' => $name );
				return array(
					$data,
					array(
						'slug' => $slug,
						'name' => $name,
					),
				);
			}
		);

		return false === $result ? self::write_error() : $result;
	}

	/**
	 * Save a tenant's name and tags. An empty name with no tags removes the entry.
	 * Slugs that are not in the vocabulary are dropped; $new_tag_names are
	 * created (or matched by slug) in the same write.
	 *
	 * @param string   $tenant_id     Tenant ID.
	 * @param string   $name          Raw (unslashed) name.
	 * @param string[] $slugs         Tag slugs.
	 * @param string[] $new_tag_names Raw (unslashed) names of tags to create and assign.
	 * @return bool False for an invalid tenant ID or a failed write.
	 */
	public static function save_tenant( $tenant_id, $name, array $slugs, array $new_tag_names = array() ) {
		$tenant_id = (string) $tenant_id;
		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return false;
		}

		$name  = self::limit( sanitize_text_field( (string) $name ), self::NAME_MAX );
		$slugs = array_map( 'strval', $slugs );
		$new   = array();
		foreach ( $new_tag_names as $new_name ) {
			$new_name = self::limit( sanitize_text_field( (string) $new_name ), self::TAG_NAME_MAX );
			if ( '' !== $new_name ) {
				$new[ self::slug_for_name( $new_name ) ] = $new_name;
			}
		}

		return true === self::mutate(
			function ( $data ) use ( $tenant_id, $name, $slugs, $new ) {
				foreach ( $new as $slug => $new_name ) {
					if ( ! isset( $data['tags'][ $slug ] ) ) {
						$data['tags'][ $slug ] = array( 'name' => $new_name );
					}
					// An all-digit slug comes back from the array key as an int.
					$slugs[] = (string) $slug;
				}
				$tags = array_values( array_unique( array_intersect( $slugs, array_keys( $data['tags'] ) ) ) );

				$data['tenants'][ $tenant_id ] = array(
					'name' => $name,
					'tags' => $tags,
				);
				return array( $data, true );
			}
		);
	}

	/**
	 * Save the Name and Tags rows of the Add New or Edit tenant form. Skipped
	 * when the feature is off or the form did not post the rows (no marker).
	 *
	 * @param string $tenant_id Tenant ID.
	 * @param array  $post      Raw (slashed) $_POST; the caller verified the nonce.
	 * @return bool|null Null when skipped, otherwise whether the write succeeded.
	 */
	public static function save_from_post( $tenant_id, array $post ) {
		if ( ! self::is_enabled() || empty( $post[ self::MARKER ] ) ) {
			return null;
		}

		$name  = isset( $post['tenant_name'] ) && is_string( $post['tenant_name'] ) ? wp_unslash( $post['tenant_name'] ) : '';
		$slugs = isset( $post['tenant_tags'] ) && is_array( $post['tenant_tags'] ) ? array_filter( $post['tenant_tags'], 'is_string' ) : array();
		$slugs = array_map( 'sanitize_key', wp_unslash( $slugs ) );
		$new   = isset( $post['tenant_new_tags'] ) && is_string( $post['tenant_new_tags'] ) ? explode( ',', wp_unslash( $post['tenant_new_tags'] ) ) : array();

		return self::save_tenant( $tenant_id, $name, $slugs, $new );
	}

	/**
	 * Remove a tenant's entry. With no file, or no entry for the tenant, it
	 * returns at once: no lock and no write.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return bool False for an invalid tenant ID or a failed write.
	 */
	public static function delete_tenant( $tenant_id ) {
		$tenant_id = (string) $tenant_id;
		if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) ) {
			return false;
		}

		self::$cache = self::load_file( true );
		if ( ! self::$cache['exists'] ) {
			return true;
		}
		if ( ! self::$cache['corrupt'] && ! isset( self::$cache['data']['tenants'][ $tenant_id ] ) ) {
			return true;
		}

		return true === self::mutate(
			function ( $data ) use ( $tenant_id ) {
				if ( ! isset( $data['tenants'][ $tenant_id ] ) ) {
					return array( null, true );
				}
				unset( $data['tenants'][ $tenant_id ] );
				return array( $data, true );
			}
		);
	}

	/**
	 * Whether a tenant's name or one of its tag names contains the term
	 * (case-insensitive). Slugs missing from the vocabulary never match.
	 *
	 * @param array  $entry Tenant entry from get_tenants().
	 * @param array  $tags  Tag vocabulary from get_tags().
	 * @param string $term  Search term.
	 * @return bool
	 */
	public static function matches( $entry, $tags, $term ) {
		$term = (string) $term;
		if ( '' === $term || ! is_array( $entry ) ) {
			return false;
		}

		$texts = array( isset( $entry['name'] ) ? (string) $entry['name'] : '' );
		foreach ( isset( $entry['tags'] ) ? (array) $entry['tags'] : array() as $slug ) {
			if ( isset( $tags[ $slug ]['name'] ) ) {
				$texts[] = (string) $tags[ $slug ]['name'];
			}
		}

		foreach ( $texts as $text ) {
			$found = function_exists( 'mb_stripos' ) ? mb_stripos( $text, $term ) : stripos( $text, $term );
			if ( false !== $found ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sort tags by name, natural and case-insensitive.
	 *
	 * @param array $tags slug => array( 'name' => string ).
	 * @return array
	 */
	public static function sort_tags( array $tags ) {
		uasort(
			$tags,
			function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);
		return $tags;
	}

	/**
	 * Slug for a tag name; names with no Latin letters or digits get a short hash.
	 *
	 * @param string $name Sanitized tag name.
	 * @return string
	 */
	public static function slug_for_name( $name ) {
		$slug = self::clean_slug( $name );
		return '' === $slug ? 'tag-' . substr( md5( (string) $name ), 0, 8 ) : $slug;
	}

	/**
	 * Lock, re-read, change, verify, write atomically, unlock.
	 *
	 * @param callable $change Receives the fresh data and returns array( $new_data|null, $result ).
	 *                         Null data means nothing to write.
	 * @return mixed The callback's result, or false when the lock, the read or the write failed.
	 */
	private static function mutate( $change ) {
		$dir = dirname( self::path() );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$lock = self::acquire_lock();
		if ( ! $lock ) {
			return false;
		}

		try {
			$state = self::load_file( true );
			if ( $state['corrupt'] ) {
				self::$cache = $state;
				return false;
			}

			$outcome = call_user_func( $change, $state['data'] );
			$data    = $outcome[0];
			$result  = $outcome[1];
			if ( null === $data ) {
				self::$cache = $state;
				return $result;
			}

			$data = self::normalize( $data );
			if ( ! self::write( $data ) ) {
				self::$cache = $state;
				return false;
			}

			self::$cache = array(
				'data'    => $data,
				'exists'  => true,
				'corrupt' => false,
			);
			return $result;
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/**
	 * Take the exclusive lock, polling until the timeout (flock has no timeout of its own).
	 *
	 * @return resource|false
	 */
	private static function acquire_lock() {
		$handle = @fopen( self::lock_path(), 'c' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return false;
		}

		$timeout  = defined( 'GRABWP_TENANCY_MANAGEMENT_LOCK_TIMEOUT' ) ? (float) GRABWP_TENANCY_MANAGEMENT_LOCK_TIMEOUT : self::LOCK_TIMEOUT;
		$deadline = microtime( true ) + $timeout;
		while ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			if ( microtime( true ) >= $deadline ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return false;
			}
			usleep( 50000 );
		}

		return $handle;
	}

	/**
	 * Read the data file.
	 *
	 * @param bool $fresh Drop stat and OPcache copies first (another PHP pool may hold a stale one).
	 * @return array { data: array, exists: bool, corrupt: bool }
	 */
	private static function load_file( $fresh ) {
		$path = self::path();
		clearstatcache( true, $path );

		if ( ! file_exists( $path ) ) {
			return array(
				'data'    => self::defaults(),
				'exists'  => false,
				'corrupt' => false,
			);
		}

		if ( $fresh && function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $path, true );
		}

		$raw = self::include_file( $path );
		if ( ! is_array( $raw ) ) {
			return array(
				'data'    => self::defaults(),
				'exists'  => true,
				'corrupt' => true,
			);
		}

		return array(
			'data'    => self::normalize( $raw ),
			'exists'  => true,
			'corrupt' => false,
		);
	}

	/**
	 * Include a data file and return what it returns; null when it fails to parse or run.
	 *
	 * @param string $path File path.
	 * @return mixed
	 */
	private static function include_file( $path ) {
		$level = ob_get_level();
		ob_start();
		try {
			$raw = include $path;
		} catch ( \Throwable $e ) {
			$raw = null;
		}
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
		return $raw;
	}

	/**
	 * Write the data file atomically; the temp file must include as an array.
	 *
	 * @param array $data Normalized data.
	 * @return bool
	 */
	private static function write( array $data ) {
		$content = "<?php\n// GrabWP Tenancy management data (admin only). Auto-generated.\nreturn " . var_export( $data, true ) . ";\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

		return GrabWP_Tenancy_Path_Manager::atomic_put_php_file(
			self::path(),
			$content,
			function ( $tmp_file ) {
				$valid = is_array( self::include_file( $tmp_file ) );
				if ( function_exists( 'opcache_invalidate' ) ) {
					opcache_invalidate( $tmp_file, true );
				}
				return $valid;
			}
		);
	}

	/**
	 * Data for a missing file.
	 *
	 * @return array
	 */
	private static function defaults() {
		return array(
			'version' => self::VERSION,
			'enabled' => true,
			'tags'    => array(),
			'tenants' => array(),
		);
	}

	/**
	 * Clean stored data: valid keys only, assignments limited to known tags,
	 * and entries with no name and no tags dropped.
	 *
	 * @param array $raw Raw data.
	 * @return array
	 */
	private static function normalize( array $raw ) {
		$data            = self::defaults();
		$data['enabled'] = array_key_exists( 'enabled', $raw ) ? (bool) $raw['enabled'] : true;

		foreach ( isset( $raw['tags'] ) && is_array( $raw['tags'] ) ? $raw['tags'] : array() as $slug => $tag ) {
			$slug = (string) $slug;
			if ( ! GrabWP_Tenancy_Sanitize::is_valid_slug( $slug ) || ! is_array( $tag ) ) {
				continue;
			}
			$name                  = isset( $tag['name'] ) && is_scalar( $tag['name'] ) ? (string) $tag['name'] : '';
			$data['tags'][ $slug ] = array( 'name' => '' === $name ? $slug : $name );
		}

		foreach ( isset( $raw['tenants'] ) && is_array( $raw['tenants'] ) ? $raw['tenants'] : array() as $tenant_id => $entry ) {
			$tenant_id = (string) $tenant_id;
			if ( ! GrabWP_Tenancy_Tenant::validate_id( $tenant_id ) || ! is_array( $entry ) ) {
				continue;
			}
			$name = isset( $entry['name'] ) && is_scalar( $entry['name'] ) ? (string) $entry['name'] : '';
			$tags = array();
			foreach ( isset( $entry['tags'] ) && is_array( $entry['tags'] ) ? $entry['tags'] : array() as $slug ) {
				if ( is_string( $slug ) && isset( $data['tags'][ $slug ] ) && ! in_array( $slug, $tags, true ) ) {
					$tags[] = $slug;
				}
			}
			if ( '' === $name && empty( $tags ) ) {
				continue;
			}
			$data['tenants'][ $tenant_id ] = array(
				'name' => $name,
				'tags' => $tags,
			);
		}

		return $data;
	}

	/**
	 * Sanitized slug cut to the maximum length.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function clean_slug( $value ) {
		return trim( substr( grabwp_sanitize_slug( $value ), 0, self::SLUG_MAX ), '-' );
	}

	/**
	 * Error for a refused or failed write.
	 *
	 * @return WP_Error
	 */
	private static function write_error() {
		return new WP_Error( 'write_failed', __( 'Names and tags could not be written. Check that the GrabWP data folder is writable.', 'grabwp-tenancy' ) );
	}

	/**
	 * Cut text to a maximum number of characters.
	 *
	 * @param string $text Text.
	 * @param int    $max  Maximum characters.
	 * @return string
	 */
	private static function limit( $text, $max ) {
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		return trim( $cut );
	}
}
