<?php
/**
 * GrabWP Tenancy Setup Wizard
 *
 * One wizard page owned by Base. Pro and WaaS add their screens through the
 * `grabwp_tenancy_setup_steps` filter; every screen saves through its own
 * plugin's existing code. State is one option (no table); one-time results
 * (a password shown once) live in a per-user transient.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Setup_Wizard {

	const PAGE_SLUG          = 'grabwp-tenancy-setup';
	const OPTION_KEY         = 'grabwp_tenancy_setup';
	const REDIRECT_TRANSIENT = 'grabwp_tenancy_setup_redirect';
	const LOOPBACK_TRANSIENT = 'grabwp_tenancy_setup_loopback';
	const CREDENTIALS_OPTION = 'grabwp_tenancy_setup_credentials';
	const EXITED_TRANSIENT   = 'grabwp_tenancy_setup_exited_';

	/**
	 * HTML allowed in option descriptions and link list lines.
	 */
	const KSES = array(
		'code'   => array( 'dir' => true ),
		'strong' => array(),
		'br'     => array(),
		'a'      => array( 'href' => true ),
	);

	/**
	 * Register hooks (main site admin only).
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_grabwp_tenancy_setup_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_grabwp_tenancy_setup_dismiss', array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'admin_post_grabwp_tenancy_setup_dismiss_addons', array( __CLASS__, 'handle_dismiss_addons' ) );
		add_action( 'admin_post_grabwp_tenancy_setup_forget_password', array( __CLASS__, 'handle_forget_password' ) );
		add_action( 'admin_post_grabwp_tenancy_setup_exit', array( __CLASS__, 'handle_exit' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_filter( 'grabwp_tenancy_setup_needs_setup', array( __CLASS__, 'base_needs_setup' ) );
		add_action( 'grabwp_tenancy_after_create_tenant', array( __CLASS__, 'forget_tenant_count' ) );
		add_action( 'grabwp_tenancy_after_delete_tenant', array( __CLASS__, 'forget_tenant_count' ) );
	}

	/**
	 * Whether any tenant exists, cached for the request.
	 *
	 * @var bool|null
	 */
	private static $has_tenant = null;

	/**
	 * Hidden page (no menu entry), same pattern as Edit Tenant.
	 */
	public static function add_page() {
		add_submenu_page(
			'Edit Tenant',
			__( 'GrabWP setup', 'grabwp-tenancy' ),
			__( 'GrabWP setup', 'grabwp-tenancy' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/* ------------------------------------------------------------------
	 * State
	 * ----------------------------------------------------------------*/

	/**
	 * @return array
	 */
	public static function get_state() {
		$state = get_option( self::OPTION_KEY, array() );
		$state = is_array( $state ) ? $state : array();
		$state = wp_parse_args(
			$state,
			array(
				'sections'       => array(),
				'steps'          => array(),
				'dismissed'      => false,
				'starter_tenant' => '',
				'resume'         => array(),
				'updated_at'     => 0,
			)
		);
		$state['sections'] = (array) $state['sections'];
		$state['steps']    = (array) $state['steps'];
		$state['resume']   = (array) $state['resume'];
		return $state;
	}

	/**
	 * @param array $state Full state.
	 */
	public static function save_state( $state ) {
		$state['updated_at'] = time();
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $state, '', false );
		} else {
			update_option( self::OPTION_KEY, $state, false );
		}
	}

	/**
	 * Status of a section: pending, done or skipped.
	 *
	 * @param string $section Section ID.
	 * @return string
	 */
	public static function get_section_status( $section ) {
		$state = self::get_state();
		return isset( $state['sections'][ $section ] ) ? $state['sections'][ $section ] : 'pending';
	}

	/* ------------------------------------------------------------------
	 * Steps
	 * ----------------------------------------------------------------*/

	/**
	 * Available steps sorted by priority, keyed by step ID.
	 *
	 * @return array
	 */
	public static function get_steps() {
		$tenants_url = admin_url( 'admin.php?page=grabwp-tenancy' );
		$steps       = array(
			'check' => array(
				'section'   => 'base',
				'title'     => __( 'Welcome', 'grabwp-tenancy' ),
				'priority'  => 10,
				'render_cb'     => array( __CLASS__, 'render_check_step' ),
				'save_cb'       => '__return_true',
				'primary_label' => __( 'Continue', 'grabwp-tenancy' ),
			),
			'routing' => array(
				'section'       => 'base',
				'title'         => __( 'Routing', 'grabwp-tenancy' ),
				'priority'      => 15,
				'render_cb'     => array( __CLASS__, 'render_routing_step' ),
				'save_cb'       => array( __CLASS__, 'save_routing_step' ),
				'primary_label' => __( 'Continue', 'grabwp-tenancy' ),
				'later'         => array(
					array(
						'label' => __( 'Custom domains', 'grabwp-tenancy' ),
						'desc'  => __( 'Give any site its own domain from Tenancy > All Tenants > Edit.', 'grabwp-tenancy' ),
						'url'   => $tenants_url,
					),
				),
			),
			// With a site already there, "Use an existing site or create one" is
			// not a real choice: the screen shows as done, is never opened, and
			// Done uses that site.
			'site'  => array(
				'section'   => 'base',
				'title'     => __( 'First site', 'grabwp-tenancy' ),
				'priority'  => 40,
				'passed_cb'     => array( __CLASS__, 'has_tenant' ),
				'render_cb'     => array( __CLASS__, 'render_site_step' ),
				'save_cb'       => array( __CLASS__, 'save_site_step' ),
				'primary_label' => __( 'Create site', 'grabwp-tenancy' ),
				'later'         => array(
					array(
						'label' => __( 'More sites', 'grabwp-tenancy' ),
						'desc'  => __( 'Add as many as you need from Tenancy > Add New.', 'grabwp-tenancy' ),
						'url'   => admin_url( 'admin.php?page=grabwp-tenancy-create' ),
					),
					array(
						'label' => __( 'Copies of a site', 'grabwp-tenancy' ),
						'desc'  => __( 'Clone a finished site from its row in Tenancy > All Tenants to start the next one faster.', 'grabwp-tenancy' ),
						'url'   => $tenants_url,
					),
				),
			),
			'done'  => array(
				'section'   => '',
				'title'     => __( 'Done', 'grabwp-tenancy' ),
				'priority'  => 90,
				'render_cb' => array( __CLASS__, 'render_done_step' ),
				'save_cb'   => '__return_true',
			),
		);

		/**
		 * Filter the setup wizard steps.
		 *
		 * Each step: section, title, priority, render_cb, save_cb, optional
		 * available_cb, passed_cb (true: shown as done, never opened),
		 * primary_label, context (one line shown above the screen), later
		 * (items for render_later()) and substeps (screens inside the step,
		 * ID => label, see get_current_sub()).
		 *
		 * @since 1.1.12
		 * @param array $steps Steps keyed by ID.
		 */
		$steps = apply_filters( 'grabwp_tenancy_setup_steps', $steps );

		$available = array();
		foreach ( (array) $steps as $id => $step ) {
			$id = sanitize_key( $id );
			if ( '' === $id || ! is_array( $step ) || empty( $step['render_cb'] ) ) {
				continue;
			}
			if ( ! empty( $step['available_cb'] ) && is_callable( $step['available_cb'] ) && ! call_user_func( $step['available_cb'] ) ) {
				continue;
			}
			$step['id']       = $id;
			$step['passed']   = ! empty( $step['passed_cb'] ) && is_callable( $step['passed_cb'] ) && call_user_func( $step['passed_cb'] );
			$step['priority'] = isset( $step['priority'] ) ? (int) $step['priority'] : 50;
			$step['section']  = isset( $step['section'] ) ? (string) $step['section'] : '';
			$available[ $id ] = $step;
		}

		uasort(
			$available,
			function ( $a, $b ) {
				return $a['priority'] - $b['priority'];
			}
		);

		return $available;
	}

	/**
	 * Sections that have at least one available step.
	 *
	 * @return array Section IDs.
	 */
	public static function get_active_sections() {
		$sections = array();
		foreach ( self::get_steps() as $step ) {
			if ( '' !== $step['section'] && ! in_array( $step['section'], $sections, true ) ) {
				$sections[] = $step['section'];
			}
		}
		return $sections;
	}

	/**
	 * Wizard URL for a step.
	 *
	 * @param string $step Step ID.
	 * @param string $flow 'all' or 'pending' (only pending sections).
	 * @param string $sub  Sub-step ID ('' = the first one).
	 * @return string
	 */
	public static function get_step_url( $step = '', $flow = 'all', $sub = '' ) {
		$args = array( 'page' => self::PAGE_SLUG );
		if ( '' !== $step ) {
			$args['step'] = $step;
		}
		if ( '' !== $sub ) {
			$args['sub'] = $sub;
		}
		if ( 'pending' === $flow ) {
			$args['flow'] = 'pending';
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Whether a step was saved or skipped ('partial': only some of its
	 * screens were saved).
	 *
	 * @param array  $state   Wizard state.
	 * @param string $step_id Step ID.
	 * @return bool
	 */
	private static function is_finished( $state, $step_id ) {
		return isset( $state['steps'][ $step_id ] ) && in_array( $state['steps'][ $step_id ], array( 'done', 'skipped' ), true );
	}

	/**
	 * Where the wizard opens from Status, the notice and the activation
	 * redirect.
	 *
	 * 1. The screen the admin left (saved on every step change and on Exit);
	 *    for the pending flow only while its section is still pending.
	 * 2. Else the first step of a pending section that was neither saved nor
	 *    skipped (e.g. the Pro screens added after Base was set up).
	 * 3. Else the first step: a rerun.
	 *
	 * @param string $flow 'all' or 'pending'.
	 * @return array { step, sub, progress: 'new', 'partial' or 'done' }
	 */
	public static function get_resume_target( $flow = 'all' ) {
		$state = self::get_state();
		$steps = array_filter(
			self::get_steps(),
			function ( $step ) {
				return ! $step['passed'];
			}
		);

		$resume = $state['resume'];
		$section = ! empty( $resume['step'] ) && isset( $steps[ $resume['step'] ] ) ? $steps[ $resume['step'] ]['section'] : '';
		$pending = '' !== $section && ( ! isset( $state['sections'][ $section ] ) || 'pending' === $state['sections'][ $section ] );
		if ( ! empty( $resume['step'] ) && 'done' !== $resume['step'] && isset( $steps[ $resume['step'] ] ) && ( 'pending' !== $flow || $pending ) ) {
			$sub = isset( $resume['sub'] ) ? (string) $resume['sub'] : '';
			return array(
				'step'     => $resume['step'],
				'sub'      => in_array( $sub, self::sub_ids( $steps[ $resume['step'] ] ), true ) ? $sub : '',
				'progress' => 'partial',
			);
		}

		foreach ( $steps as $id => $step ) {
			$status = isset( $state['sections'][ $step['section'] ] ) ? $state['sections'][ $step['section'] ] : 'pending';
			if ( '' !== $step['section'] && 'pending' === $status && ! self::is_finished( $state, $id ) ) {
				return array(
					'step'     => $id,
					'sub'      => '',
					'progress' => $state['steps'] ? 'partial' : 'new',
				);
			}
		}

		return array(
			'step'     => (string) key( $steps ),
			'sub'      => '',
			'progress' => 'done',
		);
	}

	/**
	 * Wizard URL of get_resume_target().
	 *
	 * @param string $flow 'all' or 'pending'.
	 * @return string
	 */
	public static function get_resume_url( $flow = 'all' ) {
		$target = self::get_resume_target( $flow );
		return self::get_step_url( $target['step'], $flow, $target['sub'] );
	}

	/**
	 * The wizard button of the Status page: resume a setup in progress,
	 * else run it from the first step.
	 *
	 * @return array { url, label }
	 */
	public static function get_entry_link() {
		$target = self::get_resume_target();
		return array(
			'url'   => self::get_step_url( $target['step'], 'all', $target['sub'] ),
			'label' => 'partial' === $target['progress'] ? __( 'Resume setup wizard', 'grabwp-tenancy' ) : __( 'Run setup wizard', 'grabwp-tenancy' ),
		);
	}

	/**
	 * Remember the screen the wizard shows next.
	 *
	 * @param string $step_id Step ID ('done' forgets it).
	 * @param string $sub     Sub-step ID.
	 * @param string $partial Step to mark 'partial' (one of its screens saved).
	 */
	private static function remember( $step_id, $sub, $partial = '' ) {
		$state           = self::get_state();
		$state['resume'] = 'done' === $step_id ? array() : array(
			'step' => $step_id,
			'sub'  => $sub,
		);
		if ( '' !== $partial ) {
			$state['steps'][ $partial ] = 'partial';
		}
		self::save_state( $state );
	}

	/**
	 * Step after (or before) the given one.
	 *
	 * @param string $current   Current step ID.
	 * @param string $flow      'all' or 'pending'.
	 * @param int    $direction 1 = next, -1 = previous.
	 * @return string Step ID or '' when there is none.
	 */
	public static function get_adjacent_step( $current, $flow = 'all', $direction = 1 ) {
		$steps = self::get_steps();
		$ids   = array_keys( $steps );
		$state = self::get_state();
		$index = array_search( $current, $ids, true );
		if ( false === $index ) {
			return '';
		}

		for ( $i = $index + $direction; $i >= 0 && $i < count( $ids ); $i += $direction ) {
			$step = $steps[ $ids[ $i ] ];
			if ( $step['passed'] ) {
				continue;
			}
			if ( 'pending' === $flow && '' !== $step['section'] ) {
				$status = isset( $state['sections'][ $step['section'] ] ) ? $state['sections'][ $step['section'] ] : 'pending';
				if ( 'pending' !== $status ) {
					continue;
				}
			}
			return $ids[ $i ];
		}
		return '';
	}

	/**
	 * Record a step result and update its section.
	 *
	 * A section is done when its last step saves, skipped when all of its
	 * steps were skipped.
	 *
	 * @param string $step_id Step ID.
	 * @param string $result  'done' or 'skipped'.
	 */
	public static function record_step( $step_id, $result ) {
		$steps = self::get_steps();
		if ( ! isset( $steps[ $step_id ] ) ) {
			return;
		}
		$state                     = self::get_state();
		$state['steps'][ $step_id ] = $result;
		$section                   = $steps[ $step_id ]['section'];

		if ( '' !== $section ) {
			$section_steps = array();
			foreach ( $steps as $id => $step ) {
				if ( $section === $step['section'] && ( ! $step['passed'] || $id === $step_id ) ) {
					$section_steps[] = $id;
				}
			}

			$all_skipped = true;
			foreach ( $section_steps as $id ) {
				if ( ! isset( $state['steps'][ $id ] ) || 'skipped' !== $state['steps'][ $id ] ) {
					$all_skipped = false;
				}
			}

			if ( 'done' === $result && end( $section_steps ) === $step_id ) {
				$state['sections'][ $section ] = 'done';
			} elseif ( $all_skipped ) {
				$state['sections'][ $section ] = 'skipped';
			}
		}

		self::save_state( $state );
	}

	/* ------------------------------------------------------------------
	 * Notice, redirect, dismiss
	 * ----------------------------------------------------------------*/

	/**
	 * Base needs setup while there is no tenant yet.
	 *
	 * @param array $needs Section => bool.
	 * @return array
	 */
	public static function base_needs_setup( $needs ) {
		$needs['base'] = 0 === count( GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() );
		return $needs;
	}

	/**
	 * Whether no tenant exists yet, cached for the request.
	 *
	 * @return bool
	 */
	public static function has_no_tenant() {
		if ( null === self::$has_tenant ) {
			self::$has_tenant = ! empty( GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() );
		}
		return ! self::$has_tenant;
	}

	/**
	 * Whether a tenant exists (the First site step is then done).
	 *
	 * @return bool
	 */
	public static function has_tenant() {
		return ! self::has_no_tenant();
	}

	/**
	 * Whether a step is available but shown as done and never opened.
	 *
	 * @param string $step_id Step ID.
	 * @return bool
	 */
	public static function is_step_passed( $step_id ) {
		$steps = self::get_steps();
		return isset( $steps[ $step_id ] ) && $steps[ $step_id ]['passed'];
	}

	/**
	 * Tenants were added or removed in this request.
	 */
	public static function forget_tenant_count() {
		self::$has_tenant = null;
	}

	/**
	 * Active sections that are pending and still need setup.
	 *
	 * @return array Section IDs.
	 */
	public static function get_sections_needing_setup() {
		/**
		 * Which sections still need setup (e.g. Pro: license inactive).
		 *
		 * @since 1.1.12
		 * @param array $needs Section => bool.
		 */
		$needs = apply_filters( 'grabwp_tenancy_setup_needs_setup', array() );
		$out   = array();
		foreach ( self::get_active_sections() as $section ) {
			if ( ! empty( $needs[ $section ] ) && 'pending' === self::get_section_status( $section ) ) {
				$out[] = $section;
			}
		}
		return $out;
	}

	/**
	 * Queue the one-time activation redirect into the wizard.
	 *
	 * Called from each plugin's activation hook. Only for a single activation
	 * in wp-admin, while the plugin's section is pending and needs setup.
	 *
	 * @param string $section     Section ID ('base', 'pro', 'waas').
	 * @param bool   $needs_setup Whether that plugin still needs setup.
	 */
	public static function maybe_queue_redirect( $section, $needs_setup ) {
		if ( ! $needs_setup || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( is_network_admin() || ( defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT ) ) {
			return;
		}
		// Another GrabWP plugin still waits to be activated: the notice points
		// to the wizard instead, so it opens with every plugin's screens.
		if ( self::get_inactive_addons( $section ) ) {
			return;
		}
		// Bulk activation from the Plugins screen.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verified the bulk action nonce.
		if ( isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) && count( $_POST['checked'] ) > 1 ) {
			return;
		}
		$state = self::get_state();
		if ( $state['dismissed'] ) {
			return;
		}
		$status = isset( $state['sections'][ $section ] ) ? $state['sections'][ $section ] : 'pending';
		if ( 'pending' !== $status ) {
			return;
		}
		set_transient( self::REDIRECT_TRANSIENT, $section, 60 );
	}

	/**
	 * GrabWP plugins that are installed but not active yet (Pro, WaaS).
	 *
	 * @param string $activating Section of the plugin being activated in this
	 *                           request; WordPress marks it active only after
	 *                           its activation hook.
	 * @return array Section => plugin name.
	 */
	public static function get_inactive_addons( $activating = '' ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$addons   = array(
			'pro'  => 'grabwp-tenancy-pro.php',
			'waas' => 'grabwp-tenancy-waas.php',
		);
		$inactive = array();
		$active   = array();
		foreach ( get_plugins() as $file => $data ) {
			$section = array_search( basename( $file ), $addons, true );
			if ( false === $section || $section === $activating ) {
				continue;
			}
			if ( is_plugin_active( $file ) ) {
				$active[ $section ] = true;
			} else {
				$inactive[ $section ] = $data['Name'];
			}
		}
		// A second, inactive copy of an active plugin does not count.
		return array_diff_key( $inactive, $active );
	}

	/**
	 * Consume the activation redirect once.
	 */
	public static function maybe_redirect() {
		if ( ! get_transient( self::REDIRECT_TRANSIENT ) ) {
			return;
		}
		if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_transient( self::REDIRECT_TRANSIENT );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by core after bulk activation.
		if ( isset( $_GET['activate-multi'] ) || self::get_state()['dismissed'] ) {
			return;
		}

		// Activation wrote wp-config.php and the MU loader: the Check screen
		// must not run an older compiled copy.
		require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		GrabWP_Tenancy_Status_Checker::refresh_boot_files();

		wp_safe_redirect( self::get_resume_url( 'pending' ) );
		exit;
	}

	/**
	 * "Finish setup" notice on Dashboard, Plugins and GrabWP screens.
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// Once, on the Status page after Exit setup.
		$exited = self::EXITED_TRANSIENT . get_current_user_id();
		if ( 'grabwp-tenancy-status' === $page && get_transient( $exited ) ) {
			delete_transient( $exited );
			printf(
				'<div class="notice notice-info is-dismissible grabwp-setup-exit-notice"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: name of the button on the Status page, "Resume setup wizard". */
						__( 'Setup paused. The steps you saved are kept. You can resume any time with "%s" on this Status page.', 'grabwp-tenancy' ),
						__( 'Resume setup wizard', 'grabwp-tenancy' )
					)
				)
			);
		}

		if ( self::get_state()['dismissed'] ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_our = 0 === strpos( $page, 'grabwp' ) && self::PAGE_SLUG !== $page;
		$on_wp  = $screen && in_array( $screen->id, array( 'dashboard', 'plugins' ), true );
		if ( ! $on_our && ! $on_wp ) {
			return;
		}

		if ( ! self::get_sections_needing_setup() ) {
			return;
		}

		$state    = self::get_state();
		$inactive = empty( $state['addons_notice_dismissed'] ) ? self::get_inactive_addons() : array();
		if ( $inactive ) {
			self::render_addons_notice( $inactive );
			return;
		}

		$left  = 0;
		foreach ( self::get_steps() as $id => $step ) {
			if ( '' === $step['section'] || $step['passed'] ) {
				continue;
			}
			$status = isset( $state['sections'][ $step['section'] ] ) ? $state['sections'][ $step['section'] ] : 'pending';
			if ( 'pending' === $status && ! self::is_finished( $state, $id ) ) {
				++$left;
			}
		}
		$left = max( 1, $left );
		?>
		<div class="notice notice-info grabwp-setup-notice">
			<p>
				<?php
				printf(
					/* translators: %d: number of setup steps left. */
					esc_html( _n( 'GrabWP: finish setup to get your first site running (%d step left).', 'GrabWP: finish setup to get your first site running (%d steps left).', $left, 'grabwp-tenancy' ) ),
					(int) $left
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grabwp-setup-notice-actions" style="display:flex;align-items:center;gap:12px;margin:0 0 8px;">
				<a class="button button-primary" href="<?php echo esc_url( self::get_resume_url( 'pending' ) ); ?>"><?php esc_html_e( 'Continue setup', 'grabwp-tenancy' ); ?></a>
				<input type="hidden" name="action" value="grabwp_tenancy_setup_dismiss" />
				<?php wp_nonce_field( 'grabwp_tenancy_setup_dismiss' ); ?>
				<button type="submit" class="button-link grabwp-setup-dismiss"><?php esc_html_e( 'Dismiss', 'grabwp-tenancy' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * "Finish activating" notice while Pro or WaaS is installed but inactive.
	 *
	 * @param array $inactive Section => plugin name.
	 */
	private static function render_addons_notice( $inactive ) {
		?>
		<div class="notice notice-info grabwp-setup-notice grabwp-setup-addons-notice">
			<p>
				<?php
				printf(
					/* translators: %s: plugin names, e.g. "GrabWP Tenancy Pro and GrabWP Tenancy WaaS". */
					esc_html__( 'Finish activating %s, then run the setup wizard.', 'grabwp-tenancy' ),
					esc_html( wp_sprintf_l( '%l', array_values( $inactive ) ) )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grabwp-setup-notice-actions" style="display:flex;align-items:center;gap:12px;margin:0 0 8px;">
				<a class="button" href="<?php echo esc_url( self::get_resume_url( 'pending' ) ); ?>"><?php esc_html_e( 'Run the setup wizard', 'grabwp-tenancy' ); ?></a>
				<input type="hidden" name="action" value="grabwp_tenancy_setup_dismiss_addons" />
				<?php wp_nonce_field( 'grabwp_tenancy_setup_dismiss_addons' ); ?>
				<button type="submit" class="button-link grabwp-setup-addons-dismiss"><?php esc_html_e( 'Dismiss', 'grabwp-tenancy' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Hide the "Finish activating" notice (site-wide). The setup notice and
	 * the activation redirect keep working.
	 */
	public static function handle_dismiss_addons() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'grabwp-tenancy' ) );
		}
		check_admin_referer( 'grabwp_tenancy_setup_dismiss_addons' );

		$state                            = self::get_state();
		$state['addons_notice_dismissed'] = true;
		self::save_state( $state );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	/**
	 * Hide the notice and the redirect for good (site-wide).
	 */
	public static function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'grabwp-tenancy' ) );
		}
		check_admin_referer( 'grabwp_tenancy_setup_dismiss' );

		$state              = self::get_state();
		$state['dismissed'] = true;
		self::save_state( $state );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	/**
	 * Exit setup URL of a screen.
	 *
	 * @param string $step_id Step ID.
	 * @param string $sub     Sub-step ID.
	 * @return string
	 */
	public static function get_exit_url( $step_id, $sub = '' ) {
		$args = array(
			'action' => 'grabwp_tenancy_setup_exit',
			'step'   => $step_id,
		);
		if ( '' !== $sub ) {
			$args['sub'] = $sub;
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'grabwp_tenancy_setup_exit' );
	}

	/**
	 * Exit setup: nothing on the screen is saved. The next visit opens that
	 * screen again; the notice and the activation redirect stop (like
	 * Dismiss). Lands on the Status page, where the wizard can be resumed.
	 */
	public static function handle_exit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'grabwp-tenancy' ) );
		}
		check_admin_referer( 'grabwp_tenancy_setup_exit' );

		$steps = self::get_steps();
		$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		// From Done the setup is finished: nothing to resume.
		if ( isset( $steps[ $step ] ) && 'done' !== $step && ! $steps[ $step ]['passed'] ) {
			$state              = self::get_state();
			$state['dismissed'] = true;
			$state['resume']    = array(
				'step' => $step,
				'sub'  => self::request_sub( $steps[ $step ] ),
			);
			self::save_state( $state );
			set_transient( self::EXITED_TRANSIENT . get_current_user_id(), 1, DAY_IN_SECONDS );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=grabwp-tenancy-status' ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Page + save dispatcher
	 * ----------------------------------------------------------------*/

	/**
	 * Current step from the request, falling back to the first step.
	 *
	 * @return string
	 */
	private static function current_step_id() {
		$steps = self::get_steps();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		if ( ! isset( $steps[ $step ] ) || $steps[ $step ]['passed'] ) {
			$step = (string) key( $steps );
		}
		return $step;
	}

	/**
	 * Sub-step being shown or saved.
	 *
	 * @var string
	 */
	private static $sub = '';

	/**
	 * Current sub-step of the step on screen.
	 *
	 * A step with `substeps` (ID => label, in order) shows one screen per
	 * sub-step; the first one opens by default. A label of '' leaves that
	 * screen out of the "(1 of 2)" count (e.g. a chooser). Its save_cb sees
	 * the sub-step in $post['sub'] and returns true (next sub-step, or the
	 * next step after the last one), a sub-step ID to open next, '' to
	 * finish the step, or a WP_Error.
	 *
	 * @return string Sub-step ID, '' for a step without sub-steps.
	 */
	public static function get_current_sub() {
		return self::$sub;
	}

	/**
	 * Sub-step IDs of a step, in order.
	 *
	 * @param array $step Step.
	 * @return string[]
	 */
	private static function sub_ids( $step ) {
		return empty( $step['substeps'] ) ? array() : array_map( 'strval', array_keys( (array) $step['substeps'] ) );
	}

	/**
	 * Sub-step from the request, falling back to the first one.
	 *
	 * @param array $step Step.
	 * @return string
	 */
	private static function request_sub( $step ) {
		$subs = self::sub_ids( $step );
		// phpcs:ignore WordPress.Security.NonceVerification
		$sub = isset( $_REQUEST['sub'] ) ? sanitize_key( wp_unslash( $_REQUEST['sub'] ) ) : '';
		return in_array( $sub, $subs, true ) ? $sub : (string) reset( $subs );
	}

	/**
	 * Sub-step after (or before) the given one.
	 *
	 * @param array  $step      Step.
	 * @param string $sub       Sub-step ID.
	 * @param int    $direction 1 = next, -1 = previous.
	 * @return string Sub-step ID or '' when there is none.
	 */
	private static function adjacent_sub( $step, $sub, $direction ) {
		$subs  = self::sub_ids( $step );
		$index = array_search( $sub, $subs, true );
		return ( false !== $index && isset( $subs[ $index + $direction ] ) ) ? $subs[ $index + $direction ] : '';
	}

	/**
	 * @return string 'all' or 'pending'.
	 */
	private static function current_flow() {
		// phpcs:ignore WordPress.Security.NonceVerification
		$flow = isset( $_REQUEST['flow'] ) ? sanitize_key( wp_unslash( $_REQUEST['flow'] ) ) : 'all';
		return 'pending' === $flow ? 'pending' : 'all';
	}

	/**
	 * Notice + posted values carried over a failed save (current user only).
	 *
	 * @return array { step, type, message, posted }
	 */
	private static function pull_flash() {
		$key   = 'grabwp_tenancy_setup_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( false === $flash ) {
			return array();
		}
		delete_transient( $key );
		return is_array( $flash ) ? $flash : array();
	}

	/**
	 * @param string $step    Step ID.
	 * @param string $message Message.
	 * @param array  $posted  Posted values to refill.
	 * @param string $type    Notice type.
	 */
	private static function set_flash( $step, $message, $posted = array(), $type = 'error' ) {
		set_transient(
			'grabwp_tenancy_setup_flash_' . get_current_user_id(),
			array(
				'step'    => $step,
				'type'    => $type,
				'message' => $message,
				'posted'  => $posted,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Values posted on the last failed save of this step (for refill).
	 *
	 * @var array
	 */
	private static $posted = array();

	/**
	 * One-time site result shown on the Done screen.
	 *
	 * @var array
	 */
	private static $result = array();

	/**
	 * Posted value from a failed save, else the default.
	 *
	 * @param string $key     Field name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function field_value( $key, $default ) {
		return array_key_exists( $key, self::$posted ) ? self::$posted[ $key ] : $default;
	}

	/**
	 * Whether the page was refilled after a failed save.
	 *
	 * @return bool
	 */
	public static function has_posted_values() {
		return ! empty( self::$posted );
	}

	/**
	 * Render the wizard shell and the current step.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'grabwp-tenancy' ) );
		}

		$steps     = self::get_steps();
		$step_id   = self::current_step_id();
		$step      = $steps[ $step_id ];
		$flow      = self::current_flow();
		$state     = self::get_state();
		$flash     = self::pull_flash();
		self::$sub = self::request_sub( $step );
		if ( ! empty( $flash['step'] ) && $flash['step'] === $step_id && ! empty( $flash['posted'] ) ) {
			self::$posted = (array) $flash['posted'];
		}
		$result = GrabWP_Tenancy_Tenant_Installer::pull_one_time_result();
		// The Done screen shows the site in its own block.
		if ( 'done' === $step_id ) {
			self::$result = $result;
			$result       = array();
		}

		$grabwp_setup = array(
			'steps'    => $steps,
			'step_id'  => $step_id,
			'step'     => $step,
			'flow'     => $flow,
			'state'    => $state,
			'flash'    => ( ! empty( $flash['step'] ) && $flash['step'] === $step_id ) ? $flash : array(),
			'result'   => $result,
			'prev_url' => '',
			'sub'      => self::$sub,
			'sub_of'   => array(),
		);
		// "(1 of 2)" after the title, counting the sub-steps that have a label.
		$counted = array_map( 'strval', array_keys( array_filter( isset( $step['substeps'] ) ? (array) $step['substeps'] : array(), 'strlen' ) ) );
		$index   = array_search( self::$sub, $counted, true );
		if ( false !== $index ) {
			$grabwp_setup['sub_of'] = array( $index + 1, count( $counted ) );
		}
		$prev_sub = self::adjacent_sub( $step, self::$sub, -1 );
		$prev     = self::get_adjacent_step( $step_id, $flow, -1 );
		if ( '' !== $prev_sub ) {
			$grabwp_setup['prev_url'] = self::get_step_url( $step_id, $flow, $prev_sub );
		} elseif ( '' !== $prev ) {
			$grabwp_setup['prev_url'] = self::get_step_url( $prev, $flow );
		}

		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/setup-wizard.php';
	}

	/**
	 * Save one step (POST, redirect, GET).
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) || ( defined( 'GRABWP_TENANCY_IS_TENANT' ) && GRABWP_TENANCY_IS_TENANT ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'grabwp-tenancy' ) );
		}

		$steps   = self::get_steps();
		$step_id = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		if ( ! isset( $steps[ $step_id ] ) ) {
			wp_die( esc_html__( 'Unknown setup step.', 'grabwp-tenancy' ) );
		}
		check_admin_referer( 'grabwp_tenancy_setup_' . $step_id );

		$flow      = self::current_flow();
		$action    = isset( $_POST['wizard_action'] ) ? sanitize_key( wp_unslash( $_POST['wizard_action'] ) ) : 'save';
		self::$sub = self::request_sub( $steps[ $step_id ] );

		if ( 'skip' === $action ) {
			// An earlier screen of this step saved data: the step counts as done.
			$state = self::get_state();
			self::record_step( $step_id, isset( $state['steps'][ $step_id ] ) && 'partial' === $state['steps'][ $step_id ] ? 'done' : 'skipped' );
		} else {
			$posted = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each save_cb sanitizes its own fields.
			$result = call_user_func( $steps[ $step_id ]['save_cb'], $posted );
			if ( is_wp_error( $result ) ) {
				unset( $posted['_wpnonce'], $posted['_wp_http_referer'] );
				// Passwords are never stored for the refill nor printed again.
				foreach ( array_keys( $posted ) as $key ) {
					if ( false !== strpos( (string) $key, 'password' ) ) {
						unset( $posted[ $key ] );
					}
				}
				self::set_flash( $step_id, $result->get_error_message(), $posted );
				wp_safe_redirect( self::get_step_url( $step_id, $flow, self::$sub ) );
				exit;
			}
			$next_sub = is_string( $result ) ? $result : self::adjacent_sub( $steps[ $step_id ], self::$sub, 1 );
			if ( '' !== $next_sub && in_array( $next_sub, self::sub_ids( $steps[ $step_id ] ), true ) ) {
				// A labelled screen saved its part; a chooser ('' label) saves nothing.
				$labelled = '' !== (string) $steps[ $step_id ]['substeps'][ self::$sub ];
				self::remember( $step_id, $next_sub, $labelled ? $step_id : '' );
				wp_safe_redirect( self::get_step_url( $step_id, $flow, $next_sub ) );
				exit;
			}
			self::record_step( $step_id, 'done' );
		}

		$next = self::get_adjacent_step( $step_id, $flow, 1 );
		$next = '' !== $next ? $next : 'done';
		self::remember( $next, '' );
		wp_safe_redirect( self::get_step_url( $next, $flow ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Shared screen parts (Base, Pro and WaaS screens)
	 * ----------------------------------------------------------------*/

	/**
	 * Radio choices as option cards: one real radio per card, title, level
	 * label and one-line description.
	 *
	 * Level 'simple' works with nothing extra to prepare; 'advanced' needs
	 * extra setup, an outside account, DNS, another database or technical
	 * know-how. It tells the effort, never which choice is best.
	 *
	 * @param string $name     Input name.
	 * @param array  $options  Value => array( title, desc (HTML, see KSES), level, disabled,
	 *                          tip (plain text help tip after the level label) ).
	 * @param string $current  Checked value.
	 * @param string $legend   Screen reader legend.
	 * @param string $class    Extra fieldset class.
	 * @param bool   $disabled Disable every choice.
	 */
	public static function render_choices( $name, $options, $current, $legend, $class = '', $disabled = false ) {
		$levels = array(
			'simple'   => __( 'Simple', 'grabwp-tenancy' ),
			'advanced' => __( 'Advanced', 'grabwp-tenancy' ),
		);
		echo '<fieldset class="grabwp-setup-choices ' . esc_attr( $class ) . '"><legend class="screen-reader-text">' . esc_html( $legend ) . '</legend>';
		foreach ( $options as $value => $option ) {
			$option = wp_parse_args( $option, array( 'title' => '', 'desc' => '', 'level' => 'simple', 'disabled' => $disabled, 'tip' => '' ) );
			$level  = isset( $levels[ $option['level'] ] ) ? $option['level'] : 'simple';
			printf(
				'<label class="grabwp-setup-choice%1$s"><input type="radio" name="%2$s" value="%3$s"%4$s%5$s /><span><span class="grabwp-setup-choice-title">%6$s%7$s%9$s</span><span class="grabwp-setup-choice-desc description">%8$s</span></span></label>',
				$option['disabled'] ? ' is-disabled' : '',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( (string) $current, (string) $value, false ),
				disabled( $option['disabled'], true, false ),
				esc_html( $option['title'] ),
				' <span class="grabwp-setup-level is-' . esc_attr( $level ) . '">' . esc_html( $levels[ $level ] ) . '</span>',
				wp_kses( $option['desc'], self::KSES ),
				GrabWP_Tenancy_Admin::get_help_tip( $option['tip'] )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Link list: each item array( 'label', 'desc' (HTML, see KSES), 'url', 'target' ).
	 *
	 * @param array $items Items.
	 */
	public static function render_link_list( $items ) {
		echo '<ul>';
		foreach ( $items as $item ) {
			$item = wp_parse_args( $item, array( 'label' => '', 'desc' => '', 'url' => '', 'target' => '' ) );
			$link = '' !== $item['url']
				? '<a href="' . esc_url( $item['url'] ) . '"' . ( '' !== $item['target'] ? ' target="' . esc_attr( $item['target'] ) . '" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $item['label'] ) . '</a>'
				: '<strong>' . esc_html( $item['label'] ) . '</strong>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link is built from escaped parts.
			echo '<li>' . $link . ( '' !== $item['desc'] ? ' <span class="description">' . wp_kses( $item['desc'], self::KSES ) . '</span>' : '' ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * "Later, you can add" line of a step: nothing here is needed now. One
	 * short line, each item a link to where it is set up.
	 *
	 * @param array $items See render_link_list() (desc is not shown here).
	 */
	public static function render_later( $items ) {
		$links = array();
		foreach ( $items as $item ) {
			$item = wp_parse_args( $item, array( 'label' => '', 'url' => '' ) );
			if ( '' !== $item['label'] ) {
				$links[] = '' !== $item['url'] ? '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>' : esc_html( $item['label'] );
			}
		}
		if ( $links ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $links are built from escaped parts.
			echo '<p class="grabwp-setup-later"><strong>' . esc_html__( 'Later, you can add:', 'grabwp-tenancy' ) . '</strong> ' . implode( ', ', $links ) . '</p>';
		}
	}

	/* ------------------------------------------------------------------
	 * Base steps
	 * ----------------------------------------------------------------*/

	/**
	 * Cached loopback check (also used to default "Install WordPress now").
	 *
	 * @param bool $refresh Run the check again.
	 * @return bool
	 */
	public static function can_loopback( $refresh = false ) {
		$cached = get_transient( self::LOOPBACK_TRANSIENT );
		if ( ! $refresh && false !== $cached ) {
			return 'yes' === $cached;
		}
		$ok = GrabWP_Tenancy_Tenant_Installer::can_loopback();
		set_transient( self::LOOPBACK_TRANSIENT, $ok ? 'yes' : 'no', 10 * MINUTE_IN_SECONDS );
		return $ok;
	}

	/**
	 * Check screen.
	 */
	public static function render_check_step() {
		require_once GRABWP_TENANCY_PLUGIN_DIR . 'includes/class-grabwp-tenancy-status-checker.php';
		$grabwp_check_loopback = self::can_loopback( true );
		// A unique arg, so no page cache answers for the new check.
		$grabwp_check_again = add_query_arg( 'recheck', time(), self::get_step_url( 'check', self::current_flow() ) );
		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/setup-steps/check.php';
	}

	/**
	 * Routing screen: path, domain or both, with the shared Settings radios.
	 */
	public static function render_routing_step() {
		$grabwp_routing_current  = (string) self::field_value( 'routing_mode', GrabWP_Tenancy_Routing::get_mode() );
		$grabwp_routing_disabled = GrabWP_Tenancy_Routing::is_locked();
		$grabwp_routing_has_site = ! empty( GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() );
		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/setup-steps/routing.php';
	}

	/**
	 * Save the Routing screen through the same rules as Settings > Routing.
	 *
	 * Keeping the current mode writes nothing. With sites, a switch is
	 * refused here and left to the confirm screen of Settings > Routing.
	 *
	 * @param array $post Unslashed POST data.
	 * @return true|WP_Error
	 */
	public static function save_routing_step( $post ) {
		$mode = isset( $post['routing_mode'] ) ? sanitize_key( $post['routing_mode'] ) : '';
		switch ( GrabWP_Tenancy_Routing::apply_posted_mode( $mode ) ) {
			case 'invalid':
				return new WP_Error( 'routing', __( 'Choose how visitors reach your sites.', 'grabwp-tenancy' ) );
			case 'confirm':
				return new WP_Error( 'routing', __( 'Sites already exist, so their addresses would change. Change the mode in Tenancy > Settings > Routing, which lists the affected sites.', 'grabwp-tenancy' ) );
			case 'failed':
				return new WP_Error( 'routing', __( 'Could not write tenants.php. Check file permissions.', 'grabwp-tenancy' ) );
		}
		return true;
	}

	/**
	 * Arguments of the First site screen (WaaS turns it into "Starter site").
	 *
	 * @return array
	 */
	public static function get_site_args() {
		$choices = array();
		foreach ( GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() as $tid => $domains ) {
			$choices[ $tid ] = $tid;
		}
		// The address of the site depends on the routing mode.
		$descriptions = array(
			'path'   => __( 'Your first site opens on a path address right away. No DNS needed.', 'grabwp-tenancy' ),
			'domain' => __( 'Your first site opens on its own domain. Point that domain to this server (DNS) first.', 'grabwp-tenancy' ),
			'both'   => __( 'Your first site opens on a path address right away. You can give it its own domain now or later.', 'grabwp-tenancy' ),
		);
		$routing = grabwp_tenancy_get_routing_mode();

		/**
		 * Filter the First site screen.
		 *
		 * @since 1.1.12
		 * @param array $args heading, description, default_title, default_mode, choices (tenant ID => ID).
		 */
		$args = apply_filters(
			'grabwp_tenancy_setup_site_args',
			array(
				'heading'       => __( 'Create your first site', 'grabwp-tenancy' ),
				'description'   => isset( $descriptions[ $routing ] ) ? $descriptions[ $routing ] : $descriptions['both'],
				'default_title' => __( 'My First Site', 'grabwp-tenancy' ),
				'default_mode'  => 'new',
				'choices'       => $choices,
			)
		);

		// Label each choice with its address.
		$mappings = GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings();
		foreach ( $args['choices'] as $tid => $label ) {
			if ( isset( $mappings[ $tid ] ) ) {
				$tenant                  = new GrabWP_Tenancy_Tenant( $tid, array( 'domains' => (array) $mappings[ $tid ] ) );
				$args['choices'][ $tid ] = $tid . ' (' . $tenant->get_site_url() . ')';
			} else {
				unset( $args['choices'][ $tid ] );
			}
		}
		if ( empty( $args['choices'] ) ) {
			$args['default_mode'] = 'new';
		}
		return $args;
	}

	/**
	 * First site screen.
	 */
	public static function render_site_step() {
		$grabwp_site_args    = self::get_site_args();
		$grabwp_can_loop     = self::can_loopback();
		$grabwp_routing_mode = grabwp_tenancy_get_routing_mode();
		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/setup-steps/site.php';
	}

	/**
	 * Save the First site screen: create (and install) a site or pick one.
	 *
	 * @param array $post Unslashed POST data.
	 * @return true|WP_Error
	 */
	public static function save_site_step( $post ) {
		$args  = self::get_site_args();
		$mode  = isset( $post['site_mode'] ) && 'existing' === $post['site_mode'] ? 'existing' : 'new';
		$state = self::get_state();

		if ( 'existing' === $mode ) {
			$tid = isset( $post['existing_tenant'] ) ? sanitize_key( $post['existing_tenant'] ) : '';
			if ( ! isset( $args['choices'][ $tid ] ) ) {
				return new WP_Error( 'tenant', __( 'Pick a site from the list.', 'grabwp-tenancy' ) );
			}
			$state['starter_tenant'] = $tid;
			self::save_state( $state );
			return true;
		}

		$title = isset( $post['site_title'] ) ? sanitize_text_field( $post['site_title'] ) : '';
		if ( '' === $title || strlen( $title ) > 100 ) {
			return new WP_Error( 'title', __( 'Enter a site title (up to 100 characters).', 'grabwp-tenancy' ) );
		}
		$email = isset( $post['admin_email'] ) ? sanitize_email( $post['admin_email'] ) : '';
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'email', __( 'Enter a valid admin email.', 'grabwp-tenancy' ) );
		}

		// The routing mode decides the address; only "both" offers a choice.
		$routing = grabwp_tenancy_get_routing_mode();
		$address = isset( $post['address'] ) && 'domain' === $post['address'] ? 'domain' : 'path';
		if ( 'both' !== $routing ) {
			$address = 'domain' === $routing ? 'domain' : 'path';
		}

		$domains = array();
		if ( 'domain' === $address ) {
			$domain = isset( $post['domain'] ) ? strtolower( trim( sanitize_text_field( $post['domain'] ) ) ) : '';
			if ( '' === $domain ) {
				return new WP_Error(
					'domain',
					'domain' === $routing
						? __( 'Enter the domain of the site. Domain routing needs a domain for every site.', 'grabwp-tenancy' )
						: __( 'Enter the custom domain, or choose Path URL.', 'grabwp-tenancy' )
				);
			}
			$domains = array( $domain );
		}

		$created = GrabWP_Tenancy_Tenant_Installer::create_site(
			array(
				'domains'     => $domains,
				'title'       => $title,
				'admin_email' => $email,
				'install'     => ! empty( $post['install_wp'] ),
			)
		);
		if ( ! $created['ok'] ) {
			return new WP_Error( 'create', $created['error'] );
		}

		$state                   = self::get_state();
		$state['starter_tenant'] = $created['tenant_id'];
		self::save_state( $state );

		GrabWP_Tenancy_Tenant_Installer::set_one_time_result(
			array(
				'tenant_id'     => $created['tenant_id'],
				'password'      => $created['password'],
				'install_error' => $created['install_error'],
			)
		);
		if ( '' !== $created['password'] ) {
			self::store_credentials( $created['tenant_id'], $created['password'] );
		} else {
			delete_option( self::CREDENTIALS_OPTION );
		}
		return true;
	}

	/* ------------------------------------------------------------------
	 * Starter site password (kept until the admin hides it)
	 * ----------------------------------------------------------------*/

	/**
	 * Key for the stored password, derived from this site's salts, so a
	 * database dump alone does not reveal it.
	 *
	 * @return string
	 */
	private static function credentials_key() {
		return sodium_crypto_generichash( wp_salt( 'secure_auth' ) . self::CREDENTIALS_OPTION, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Store the starter site's admin password (autoload off, encrypted).
	 *
	 * @param string $tenant_id Tenant ID (also the admin username).
	 * @param string $password  Plain password.
	 * @return bool False without sodium (WordPress before 5.2 on a PHP build
	 *              without it): Done then shows the password once, from the
	 *              one-time result.
	 */
	private static function store_credentials( $tenant_id, $password ) {
		delete_option( self::CREDENTIALS_OPTION );
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return false;
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$value = array(
			'tenant_id' => $tenant_id,
			'password'  => base64_encode( $nonce . sodium_crypto_secretbox( $password, $nonce, self::credentials_key() ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
		return add_option( self::CREDENTIALS_OPTION, $value, '', false );
	}

	/**
	 * Stored password of a tenant, for administrators only.
	 *
	 * @param string $tenant_id Tenant ID.
	 * @return string Plain password, or '' when none is stored.
	 */
	public static function get_credentials_password( $tenant_id ) {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}
		$value = get_option( self::CREDENTIALS_OPTION, array() );
		if ( ! is_array( $value ) || empty( $value['password'] ) || ! isset( $value['tenant_id'] ) || $value['tenant_id'] !== $tenant_id ) {
			return '';
		}
		$raw = base64_decode( (string) $value['password'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::credentials_key() );
		return false === $plain ? '' : $plain;
	}

	/**
	 * "I saved it, hide this": delete the stored password for good.
	 */
	public static function handle_forget_password() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'grabwp-tenancy' ) );
		}
		check_admin_referer( 'grabwp_tenancy_setup_forget_password' );
		delete_option( self::CREDENTIALS_OPTION );
		wp_safe_redirect( self::get_step_url( 'done', self::current_flow() ) );
		exit;
	}

	/**
	 * Done screen. Marks every active section that was not skipped as done.
	 */
	public static function render_done_step() {
		$state = self::get_state();
		foreach ( self::get_active_sections() as $section ) {
			$status = isset( $state['sections'][ $section ] ) ? $state['sections'][ $section ] : 'pending';
			if ( 'skipped' !== $status ) {
				$state['sections'][ $section ] = 'done';
			}
		}
		$state['steps']['done'] = 'done';
		$state['resume']        = array();
		self::save_state( $state );

		$grabwp_done_tenant = '' !== $state['starter_tenant'] ? GrabWP_Tenancy_Tenant_Installer::get_tenant( $state['starter_tenant'] ) : null;
		// The First site screen was passed because a site exists: use that site.
		if ( ! $grabwp_done_tenant && self::is_step_passed( 'site' ) ) {
			$grabwp_first = array_key_first( GrabWP_Tenancy_Tenant_Installer::get_tenant_mappings() );
			if ( null !== $grabwp_first ) {
				$state['starter_tenant'] = (string) $grabwp_first;
				self::save_state( $state );
				$grabwp_done_tenant = GrabWP_Tenancy_Tenant_Installer::get_tenant( $state['starter_tenant'] );
			}
		}
		$grabwp_done_result   = self::$result;
		$grabwp_done_password = $grabwp_done_tenant ? self::get_credentials_password( $grabwp_done_tenant->get_id() ) : '';
		// Not stored (no sodium): show the password of the site just created once.
		if ( '' === $grabwp_done_password && $grabwp_done_tenant && isset( $grabwp_done_result['tenant_id'], $grabwp_done_result['password'] ) && $grabwp_done_result['tenant_id'] === $grabwp_done_tenant->get_id() ) {
			$grabwp_done_password = (string) $grabwp_done_result['password'];
		}
		if ( ! $grabwp_done_tenant ) {
			delete_option( self::CREDENTIALS_OPTION );
		}
		$grabwp_done_items = array();

		$grabwp_done_items[] = self::done_item( 'check', __( 'Server checked', 'grabwp-tenancy' ), $state );

		/**
		 * Lines of the Done screen from other plugins, inserted after "Server checked".
		 *
		 * @since 1.1.12
		 * @param array $items Each: array( 'ok' => bool, 'label' => string, 'step' => step ID or '' ).
		 * @param array $state Wizard state.
		 */
		$grabwp_done_items = apply_filters( 'grabwp_tenancy_setup_done_items', $grabwp_done_items, $state );

		if ( $grabwp_done_tenant ) {
			$installed           = GrabWP_Tenancy_Tenant_Installer::is_installed( $grabwp_done_tenant->get_id() );
			$grabwp_done_items[] = array(
				'ok'    => $installed,
				'label' => $installed
					/* translators: %s: tenant ID. */
					? sprintf( __( 'Site %s created and installed', 'grabwp-tenancy' ), $grabwp_done_tenant->get_id() )
					/* translators: %s: tenant ID. */
					: sprintf( __( 'Site %s created; WordPress is not installed yet', 'grabwp-tenancy' ), $grabwp_done_tenant->get_id() ),
				'step'  => ( $installed || self::is_step_passed( 'site' ) ) ? '' : 'site',
			);
		} else {
			$grabwp_done_items[] = array(
				'ok'    => false,
				'label' => __( 'First site: Skipped', 'grabwp-tenancy' ),
				'step'  => 'site',
			);
		}

		/**
		 * Extra buttons on the Done screen (e.g. WaaS signup test).
		 *
		 * @since 1.1.12
		 * @param array $links Each: array( 'url', 'label', 'target' ).
		 * @param array $state Wizard state.
		 */
		$grabwp_done_links = apply_filters( 'grabwp_tenancy_setup_done_links', array(), $state );

		$grabwp_done_next = array();
		if ( $grabwp_done_tenant ) {
			$grabwp_done_next[] = array(
				'url'   => admin_url( 'admin.php?page=grabwp-tenancy-edit&tenant_id=' . rawurlencode( $grabwp_done_tenant->get_id() ) . '&_wpnonce=' . rawurlencode( wp_create_nonce( 'grabwp_tenancy_edit' ) ) ),
				'label' => __( 'Map a custom domain', 'grabwp-tenancy' ),
				'desc'  => __( 'Give this site its own domain, like my-shop.com.', 'grabwp-tenancy' ),
			);
		}
		$grabwp_done_next[] = array(
			'url'   => admin_url( 'admin.php?page=grabwp-tenancy-create' ),
			'label' => __( 'Add more sites', 'grabwp-tenancy' ),
			'desc'  => __( 'Each one separate, all from this WordPress.', 'grabwp-tenancy' ),
		);

		/**
		 * "Grow later" links on the Done screen.
		 *
		 * @since 1.1.12
		 * @param array $links Each: array( 'url', 'label', optional 'desc' ).
		 * @param array $state Wizard state.
		 */
		$grabwp_done_next = apply_filters( 'grabwp_tenancy_setup_done_next', $grabwp_done_next, $state );

		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/setup-steps/done.php';
	}

	/**
	 * Done line for a step: ok when saved (also only some of its screens),
	 * "Skipped" link otherwise.
	 *
	 * @param string $step_id Step ID.
	 * @param string $label   Label when done.
	 * @param array  $state   Wizard state.
	 * @return array
	 */
	public static function done_item( $step_id, $label, $state ) {
		$status = isset( $state['steps'][ $step_id ] ) ? $state['steps'][ $step_id ] : '';
		if ( in_array( $status, array( 'done', 'partial' ), true ) ) {
			return array(
				'ok'    => true,
				'label' => $label,
				'step'  => '',
			);
		}
		return array(
			'ok'    => false,
			/* translators: %s: name of a setup item. */
			'label' => sprintf( __( '%s: Skipped', 'grabwp-tenancy' ), $label ),
			'step'  => $step_id,
		);
	}
}
