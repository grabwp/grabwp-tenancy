<?php
/**
 * GrabWP Tenancy Tenant Tags admin
 *
 * The hidden Tags page (list, add, edit, delete, bulk delete), the tag
 * selector assets for the tenant screens and the inline "Add new tag" AJAX
 * action. Main site admin only; all data goes through GrabWP_Tenancy_Management.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.17
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin glue for tenant tags.
 *
 * @since 1.1.17
 */
class GrabWP_Tenancy_Tenant_Tags_Admin {

	/**
	 * Tags page slug.
	 */
	const PAGE = 'grabwp-tenancy-tags';

	/**
	 * Nonce action for the inline create AJAX call.
	 */
	const AJAX_NONCE = 'grabwp_tenancy_tags';

	/**
	 * Nonce action for the Tags page notices.
	 */
	const NOTICE_NONCE = 'grabwp_tenancy_tag_notice';

	/**
	 * Query args of a success notice; the list strips them from its sort links.
	 */
	const NOTICE_ARGS = array( 'message', 'count', '_notice_nonce' );

	/**
	 * Hook the AJAX action and the asset loader.
	 */
	public static function register() {
		add_action( 'wp_ajax_grabwp_tenancy_create_tag', array( __CLASS__, 'ajax_create_tag' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'grabwp_tenancy_handle_custom_action', array( __CLASS__, 'handle_action' ), 10, 2 );
	}

	/**
	 * Render the Tags page: the list, or the editor for &action=add / &action=edit&tag=slug.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage tags.', 'grabwp-tenancy' ) );
		}

		if ( ! GrabWP_Tenancy_Management::is_enabled() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Tenant Tags', 'grabwp-tenancy' ) . '</h1>';
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Tenant names and tags are turned off.', 'grabwp-tenancy' ) . ' ';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=grabwp-tenancy-settings#tab-management' ) ) . '">' . esc_html__( 'Turn them on in Settings > Management', 'grabwp-tenancy' ) . '</a></p></div></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'add' === $action || 'edit' === $action ) {
			$grabwp_tag_is_edit = 'edit' === $action;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
			$grabwp_tag_slug = $grabwp_tag_is_edit && isset( $_GET['tag'] ) ? sanitize_key( wp_unslash( $_GET['tag'] ) ) : '';
			$tags            = GrabWP_Tenancy_Management::get_tags();
			$grabwp_tag      = isset( $tags[ $grabwp_tag_slug ] ) ? $tags[ $grabwp_tag_slug ] : null;
			include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/tenant-tag-edit.php';
			return;
		}

		require_once GRABWP_TENANCY_PLUGIN_DIR . 'admin/class-grabwp-tenancy-tag-list-table.php';
		$list_table = new GrabWP_Tenancy_Tag_List_Table();
		$list_table->prepare_items();
		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/tenant-tags.php';
	}

	/**
	 * Tags page POST actions, routed by the Base form handler (manage_options
	 * and the page are already checked there). Each one checks its nonce.
	 *
	 * @param string $action Posted action.
	 * @param array  $post   Raw $_POST.
	 */
	public static function handle_action( $action, $post ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only routes to handlers that verify their nonce.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE !== $page || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// The bottom bulk selector posts action2 and leaves action at -1.
		if ( '-1' === $action && isset( $post['action2'] ) && is_string( $post['action2'] ) ) {
			$action = sanitize_key( wp_unslash( $post['action2'] ) );
		}

		$handlers = array(
			'grabwp_tenancy_tag_save'        => array( 'grabwp_tenancy_tag_save', 'handle_save' ),
			'grabwp_tenancy_tag_delete'      => array( 'grabwp_tenancy_tag_delete', 'handle_delete' ),
			'grabwp_tenancy_tag_bulk_delete' => array( 'bulk-tags', 'handle_bulk_delete' ),
		);
		if ( ! isset( $handlers[ $action ] ) ) {
			return;
		}
		$nonce = isset( $post['_wpnonce'] ) && is_string( $post['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $post['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $handlers[ $action ][0] ) ) {
			wp_die( esc_html__( 'Security check failed.', 'grabwp-tenancy' ) );
		}
		if ( ! GrabWP_Tenancy_Management::is_enabled() ) {
			self::redirect( array() );
		}

		call_user_func( array( __CLASS__, $handlers[ $action ][1] ), $post );
	}

	/**
	 * Add or edit a tag. Errors go back to the same editor; success goes to the list.
	 *
	 * @param array $post Raw $_POST.
	 */
	private static function handle_save( $post ) {
		$old_slug = isset( $post['old_slug'] ) && is_string( $post['old_slug'] ) ? sanitize_key( wp_unslash( $post['old_slug'] ) ) : '';
		$slug     = isset( $post['tag_slug'] ) && is_string( $post['tag_slug'] ) ? wp_unslash( $post['tag_slug'] ) : '';
		$name     = isset( $post['tag_name'] ) && is_string( $post['tag_name'] ) ? wp_unslash( $post['tag_name'] ) : '';

		$result = GrabWP_Tenancy_Management::save_tag( $slug, $name, $old_slug );
		if ( is_wp_error( $result ) ) {
			set_transient( 'grabwp_tenancy_error', $result->get_error_message(), 60 );
			self::redirect( '' === $old_slug ? array( 'action' => 'add' ) : array( 'action' => 'edit', 'tag' => $old_slug ), false );
		}

		self::redirect( array( 'message' => 'saved' ) );
	}

	/**
	 * Delete one tag from the vocabulary and every tenant.
	 *
	 * @param array $post Raw $_POST.
	 */
	private static function handle_delete( $post ) {
		$slug = isset( $post['tag'] ) && is_string( $post['tag'] ) ? sanitize_key( wp_unslash( $post['tag'] ) ) : '';
		if ( false === GrabWP_Tenancy_Management::delete_tags( array( $slug ) ) ) {
			set_transient( 'grabwp_tenancy_error', __( 'The tag could not be deleted. Check that the GrabWP data folder is writable.', 'grabwp-tenancy' ), 60 );
			self::redirect( array(), false );
		}
		self::redirect( array( 'message' => 'deleted' ) );
	}

	/**
	 * Delete the ticked tags in one write.
	 *
	 * @param array $post Raw $_POST.
	 */
	private static function handle_bulk_delete( $post ) {
		$slugs   = isset( $post['tag_slugs'] ) && is_array( $post['tag_slugs'] ) ? array_map( 'sanitize_key', wp_unslash( array_filter( $post['tag_slugs'], 'is_string' ) ) ) : array();
		$deleted = GrabWP_Tenancy_Management::delete_tags( $slugs );
		if ( false === $deleted ) {
			set_transient( 'grabwp_tenancy_error', __( 'The tags could not be deleted. Check that the GrabWP data folder is writable.', 'grabwp-tenancy' ), 60 );
			self::redirect( array(), false );
		}
		self::redirect(
			array(
				'message' => 'bulk_deleted',
				'count'   => $deleted,
			)
		);
	}

	/**
	 * Redirect to the Tags page and stop.
	 *
	 * @param array $args   Extra query args.
	 * @param bool  $notice Add the notice nonce.
	 */
	private static function redirect( array $args, $notice = true ) {
		$args = array_merge( array( 'page' => self::PAGE ), $args );
		if ( $notice && isset( $args['message'] ) ) {
			$args['_notice_nonce'] = wp_create_nonce( self::NOTICE_NONCE );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Load the tag styles on the tenant list, forms and tags page, and the
	 * selector script on the forms. Nothing loads when the feature is off.
	 */
	public static function enqueue_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$form = in_array( $page, array( 'grabwp-tenancy-create', 'grabwp-tenancy-edit' ), true );
		if ( ! $form && ! in_array( $page, array( 'grabwp-tenancy', self::PAGE ), true ) ) {
			return;
		}
		if ( ! GrabWP_Tenancy_Management::is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'grabwp-tenant-tags',
			GRABWP_TENANCY_PLUGIN_URL . 'admin/css/grabwp-tenant-tags.css',
			array(),
			self::asset_version( 'admin/css/grabwp-tenant-tags.css' )
		);

		if ( ! $form ) {
			return;
		}

		wp_enqueue_script(
			'grabwp-tenant-tags',
			GRABWP_TENANCY_PLUGIN_URL . 'admin/js/grabwp-tenant-tags.js',
			array(),
			self::asset_version( 'admin/js/grabwp-tenant-tags.js' ),
			true
		);
		wp_localize_script(
			'grabwp-tenant-tags',
			'grabwpTenantTags',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE ),
				'i18n'    => array(
					'search'  => __( 'Search or add tags', 'grabwp-tenancy' ),
					'noTags'  => __( 'No tags found', 'grabwp-tenancy' ),
					/* translators: %s: tag name the user typed. */
					'addNew'  => __( 'Add new tag: %s', 'grabwp-tenancy' ),
					/* translators: %s: tag name. */
					'remove'  => __( 'Remove tag %s', 'grabwp-tenancy' ),
					/* translators: %s: tag name. */
					'added'   => __( 'Tag %s added.', 'grabwp-tenancy' ),
					'error'   => __( 'The tag could not be created.', 'grabwp-tenancy' ),
				),
			)
		);
	}

	/**
	 * Create a tag from the tenant form and return { slug, name }. Only the
	 * vocabulary is written here; the assignment is saved with the form.
	 */
	public static function ajax_create_tag() {
		if ( ! check_ajax_referer( self::AJAX_NONCE, '_ajax_nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed. Reload the page and try again.', 'grabwp-tenancy' ), 403 );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to manage tags.', 'grabwp-tenancy' ), 403 );
			return;
		}
		if ( ! GrabWP_Tenancy_Management::is_enabled() ) {
			wp_send_json_error( __( 'Tenant names and tags are turned off.', 'grabwp-tenancy' ), 403 );
			return;
		}

		$name   = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$result = GrabWP_Tenancy_Management::create_tag_inline( $name );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 400 );
			return;
		}

		wp_send_json_success( $result );
	}

	/**
	 * Asset version: plugin version plus the file time.
	 *
	 * @param string $file Path below the plugin folder.
	 * @return string
	 */
	private static function asset_version( $file ) {
		$mtime = @filemtime( GRABWP_TENANCY_PLUGIN_DIR . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return GRABWP_TENANCY_VERSION . ( $mtime ? '.' . $mtime : '' );
	}
}
