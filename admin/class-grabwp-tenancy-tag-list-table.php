<?php
/**
 * GrabWP Tenancy Tag List Table
 *
 * Lists the tenant tag vocabulary on the hidden Tags page.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.17
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Tenant tags list: Name, Slug, Tenants and a visible Actions column.
 *
 * @since 1.1.17
 */
class GrabWP_Tenancy_Tag_List_Table extends WP_List_Table {

	/**
	 * Form id of the standalone delete form the row buttons submit.
	 */
	const DELETE_FORM_ID = 'grabwp-tag-delete-form';

	/**
	 * Tenants per tag.
	 *
	 * @var array
	 */
	private $counts = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'tag',
				'plural'   => 'tags',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'name'    => __( 'Name', 'grabwp-tenancy' ),
			'slug'    => __( 'Slug', 'grabwp-tenancy' ),
			'tenants' => __( 'Tenants', 'grabwp-tenancy' ),
			'actions' => __( 'Actions', 'grabwp-tenancy' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name' => array( 'name', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array( 'grabwp_tenancy_tag_bulk_delete' => __( 'Delete', 'grabwp-tenancy' ) );
	}

	/**
	 * Prepare items: search by name or slug, sort by name, 20 per page.
	 */
	public function prepare_items() {
		$this->counts = GrabWP_Tenancy_Management::tenant_counts();
		$items        = array();
		foreach ( GrabWP_Tenancy_Management::get_tags() as $slug => $tag ) {
			$items[] = array(
				'slug' => (string) $slug,
				'name' => $tag['name'],
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search is read-only operation
		$search = isset( $_REQUEST['s'] ) && is_string( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( '' !== $search ) {
			$items = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $search ) {
						$in_name = function_exists( 'mb_stripos' ) ? mb_stripos( $item['name'], $search ) : stripos( $item['name'], $search );
						return false !== $in_name || false !== stripos( $item['slug'], $search );
					}
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sorting is read-only operation
		$order = isset( $_REQUEST['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'desc' : 'asc';
		if ( 'desc' === $order ) {
			$items = array_reverse( $items );
		}

		$per_page     = 20;
		$total_items  = count( $items );
		$current_page = $this->get_pagenum();

		$this->items = array_slice( $items, ( $current_page - 1 ) * $per_page, $per_page );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	/**
	 * Column headers whose sort links drop the success notice args, so
	 * sorting after "Tag saved." does not show the notice again.
	 *
	 * @param bool $with_id Whether to set the id attribute.
	 */
	public function print_column_headers( $with_id = true ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Restored unchanged below; WP_List_Table escapes the links it builds from it.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

		$_SERVER['REQUEST_URI'] = remove_query_arg( GrabWP_Tenancy_Tenant_Tags_Admin::NOTICE_ARGS, $request_uri );
		parent::print_column_headers( $with_id );
		$_SERVER['REQUEST_URI'] = $request_uri;
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Tag row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return '<label class="screen-reader-text" for="grabwp-tag-cb-' . esc_attr( $item['slug'] ) . '">'
			/* translators: %s: tag name. */
			. esc_html( sprintf( __( 'Select %s', 'grabwp-tenancy' ), $item['name'] ) ) . '</label>'
			. '<input type="checkbox" id="grabwp-tag-cb-' . esc_attr( $item['slug'] ) . '" name="tag_slugs[]" value="' . esc_attr( $item['slug'] ) . '" />';
	}

	/**
	 * Name column: links to the editor.
	 *
	 * @param array $item Tag row.
	 * @return string
	 */
	public function column_name( $item ) {
		return '<strong><a href="' . esc_url( self::edit_url( $item['slug'] ) ) . '">' . esc_html( $item['name'] ) . '</a></strong>';
	}

	/**
	 * Slug column.
	 *
	 * @param array $item Tag row.
	 * @return string
	 */
	public function column_slug( $item ) {
		return '<code dir="ltr">' . esc_html( $item['slug'] ) . '</code>';
	}

	/**
	 * Tenants column: count, linking to the tenant list filtered by the tag.
	 *
	 * @param array $item Tag row.
	 * @return string
	 */
	public function column_tenants( $item ) {
		$count = isset( $this->counts[ $item['slug'] ] ) ? (int) $this->counts[ $item['slug'] ] : 0;
		if ( 0 === $count ) {
			return '0';
		}
		$url = admin_url( 'admin.php?page=grabwp-tenancy&tenant_tag=' . rawurlencode( $item['slug'] ) );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( number_format_i18n( $count ) ) . '</a>';
	}

	/**
	 * Actions column: Edit link and a Delete button that submits the standalone delete form.
	 *
	 * @param array $item Tag row.
	 * @return string
	 */
	public function column_actions( $item ) {
		$count = isset( $this->counts[ $item['slug'] ] ) ? (int) $this->counts[ $item['slug'] ] : 0;
		/* translators: %s: tag name. */
		$edit_label = sprintf( __( 'Edit %s', 'grabwp-tenancy' ), $item['name'] );
		/* translators: %s: tag name. */
		$delete_label = sprintf( __( 'Delete %s', 'grabwp-tenancy' ), $item['name'] );
		$confirm      = 0 === $count
			? __( 'Delete this tag?', 'grabwp-tenancy' )
			: sprintf(
				/* translators: %d: number of tenants that carry the tag. */
				_n( 'Delete this tag? It is removed from %d tenant.', 'Delete this tag? It is removed from %d tenants.', $count, 'grabwp-tenancy' ),
				$count
			);

		$actions   = array();
		$actions[] = '<a href="' . esc_url( self::edit_url( $item['slug'] ) ) . '" title="' . esc_attr( $edit_label ) . '" aria-label="' . esc_attr( $edit_label ) . '">'
			. '<span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $edit_label ) . '</span></a>';
		$actions[] = '<button type="submit" form="' . esc_attr( self::DELETE_FORM_ID ) . '" name="tag" value="' . esc_attr( $item['slug'] ) . '" class="button-link button-link-delete" title="' . esc_attr( $delete_label ) . '" aria-label="' . esc_attr( $delete_label ) . '" onclick="return confirm(\'' . esc_js( $confirm ) . '\');">'
			. '<span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $delete_label ) . '</span></button>';

		return implode( ' ', $actions );
	}

	/**
	 * Empty state with an add link.
	 */
	public function no_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search is read-only operation
		if ( ! empty( $_REQUEST['s'] ) ) {
			esc_html_e( 'No tags found.', 'grabwp-tenancy' );
			return;
		}
		esc_html_e( 'No tags yet.', 'grabwp-tenancy' );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=' . GrabWP_Tenancy_Tenant_Tags_Admin::PAGE . '&action=add' ) ) . '">' . esc_html__( 'Add New Tag', 'grabwp-tenancy' ) . '</a>';
	}

	/**
	 * Editor URL for a tag.
	 *
	 * @param string $slug Tag slug.
	 * @return string
	 */
	public static function edit_url( $slug ) {
		return admin_url( 'admin.php?page=' . GrabWP_Tenancy_Tenant_Tags_Admin::PAGE . '&action=edit&tag=' . rawurlencode( (string) $slug ) );
	}
}
