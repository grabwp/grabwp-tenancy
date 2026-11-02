<?php
/**
 * GrabWP Tenancy - Theme screen lock for tenant sites
 *
 * Hides Appearance > Themes and blocks theme browsing, installing and switching
 * while keeping Customize, Menus, Widgets and the Site Editor.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.13
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the hide_theme_switching tenant setting.
 */
class GrabWP_Tenancy_Theme_Screen_Lock {

	/**
	 * Admin screens a tenant cannot open while the lock is on.
	 *
	 * @var string[]
	 */
	const BLOCKED_SCREENS = array( 'themes.php', 'theme-install.php', 'theme-editor.php' );

	/**
	 * Theme capabilities removed from every user on the tenant site.
	 *
	 * Removing switch_themes also hides the Customizer theme switcher, the
	 * toolbar Themes link and theme activation through AJAX and REST.
	 *
	 * @var string[]
	 */
	const DENIED_CAPS = array( 'switch_themes', 'install_themes', 'upload_themes', 'update_themes', 'delete_themes', 'edit_themes' );

	/**
	 * Register the lock hooks when only the lighter setting is on.
	 *
	 * The stronger hide_theme_management setting already removes the whole
	 * Appearance menu, so the lock stays out of its way.
	 *
	 * @param  bool $hide_theme_management Hide the whole Appearance menu.
	 * @param  bool $hide_theme_switching  Hide only the Themes screen.
	 * @return bool Whether hooks were registered.
	 */
	public static function maybe_register( $hide_theme_management, $hide_theme_switching ) {
		if ( $hide_theme_management || ! $hide_theme_switching ) {
			return false;
		}

		add_filter( 'user_has_cap', array( __CLASS__, 'deny_theme_caps' ), 999 );
		add_action( 'admin_menu', array( __CLASS__, 'remove_themes_submenu' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'block_theme_screens' ), 1 );
		add_filter( 'customize_panel_active', array( __CLASS__, 'deactivate_themes_panel' ), 999, 2 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'remove_admin_bar_node' ), 999 );
		return true;
	}

	/**
	 * Drop the theme management capabilities.
	 *
	 * @param  array $allcaps All capabilities of the user.
	 * @return array
	 */
	public static function deny_theme_caps( $allcaps ) {
		foreach ( self::DENIED_CAPS as $cap ) {
			$allcaps[ $cap ] = false;
		}
		return $allcaps;
	}

	/**
	 * Remove Appearance > Themes but keep the Appearance menu.
	 *
	 * Core links the top-level item to its first submenu entry, so Appearance
	 * opens the first remaining entry (Site Editor, Design/Patterns, or
	 * Customize, depending on theme and WP version).
	 */
	public static function remove_themes_submenu() {
		remove_submenu_page( 'themes.php', 'themes.php' );
	}

	/**
	 * Stop direct visits to the theme browser, installer and editor.
	 */
	public static function block_theme_screens() {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only routing check; sanitize_key() drops slashes.
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		if ( ! self::is_blocked_screen( $pagenow, $page ) ) {
			return;
		}

		wp_die(
			esc_html__( 'Themes are managed by your host. You can still customize your current theme, menus and widgets from the Appearance menu.', 'grabwp-tenancy' ),
			esc_html__( 'Themes unavailable', 'grabwp-tenancy' ),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	/**
	 * Whether an admin screen is blocked by the lock.
	 *
	 * Theme option pages added under Appearance with add_theme_page() load
	 * through themes.php?page=<slug>, so they stay reachable.
	 *
	 * @param  string|null $screen Admin file name, as in $pagenow.
	 * @param  string      $page   Sanitized ?page= query value, if any.
	 * @return bool
	 */
	public static function is_blocked_screen( $screen, $page = '' ) {
		if ( 'themes.php' === $screen && '' !== (string) $page ) {
			return false;
		}
		return in_array( (string) $screen, self::BLOCKED_SCREENS, true );
	}

	/**
	 * Deactivate the Customizer theme switcher panel.
	 *
	 * Uses customize_panel_active rather than customize_register, because core
	 * shows Appearance > Customize on block themes whenever customize_register
	 * has a callback.
	 *
	 * @param  bool               $active Whether the panel is active.
	 * @param  WP_Customize_Panel $panel  Customizer panel.
	 * @return bool
	 */
	public static function deactivate_themes_panel( $active, $panel ) {
		return ( is_object( $panel ) && isset( $panel->id ) && 'themes' === $panel->id ) ? false : $active;
	}

	/**
	 * Remove the toolbar Themes link.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function remove_admin_bar_node( $wp_admin_bar ) {
		if ( is_object( $wp_admin_bar ) && method_exists( $wp_admin_bar, 'remove_node' ) ) {
			$wp_admin_bar->remove_node( 'themes' );
		}
	}
}
