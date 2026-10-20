<?php
/**
 * Setup wizard shell: progress list, notices, current step, Back / Skip / primary.
 *
 * Variables available from GrabWP_Tenancy_Setup_Wizard::render_page():
 * @var array $grabwp_setup { steps, step_id, step, flow, state, flash, result, prev_url, sub, sub_of }
 *
 * @package GrabWP_Tenancy
 * @since 1.1.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_setup_step     = $grabwp_setup['step'];
$grabwp_setup_is_done  = 'done' === $grabwp_setup['step_id'];
$grabwp_setup_primary  = ! empty( $grabwp_setup_step['primary_label'] ) ? $grabwp_setup_step['primary_label'] : __( 'Save and continue', 'grabwp-tenancy' );
$grabwp_setup_disabled = ! empty( $grabwp_setup_step['primary_disabled_cb'] ) && is_callable( $grabwp_setup_step['primary_disabled_cb'] ) && call_user_func( $grabwp_setup_step['primary_disabled_cb'] );
$grabwp_setup_index    = 0;
$grabwp_setup_position = array_search( $grabwp_setup['step_id'], array_keys( $grabwp_setup['steps'] ), true ) + 1;
?>
<div class="wrap grabwp-setup">
	<div class="grabwp-setup-header">
		<h1><?php esc_html_e( 'GrabWP setup', 'grabwp-tenancy' ); ?></h1>
		<a class="grabwp-setup-exit" href="<?php echo esc_url( GrabWP_Tenancy_Setup_Wizard::get_exit_url( $grabwp_setup['step_id'], $grabwp_setup['sub'] ) ); ?>"><?php esc_html_e( 'Exit setup', 'grabwp-tenancy' ); ?> <span aria-hidden="true">&times;</span></a>
	</div>

	<ol class="grabwp-setup-progress">
		<?php foreach ( $grabwp_setup['steps'] as $grabwp_setup_id => $grabwp_setup_item ) : ?>
			<?php
			++$grabwp_setup_index;
			$grabwp_setup_item_status = isset( $grabwp_setup['state']['steps'][ $grabwp_setup_id ] ) ? $grabwp_setup['state']['steps'][ $grabwp_setup_id ] : '';
			// A passed step (e.g. First site once a site exists) is done and never opened.
			if ( $grabwp_setup_item['passed'] ) {
				$grabwp_setup_item_status = 'done';
			}
			$grabwp_setup_classes     = array( 'grabwp-setup-progress-item' );
			if ( $grabwp_setup_id === $grabwp_setup['step_id'] ) {
				$grabwp_setup_classes[] = 'is-current';
			}
			if ( 'done' === $grabwp_setup_item_status ) {
				$grabwp_setup_classes[] = 'is-done';
			} elseif ( 'skipped' === $grabwp_setup_item_status ) {
				$grabwp_setup_classes[] = 'is-skipped';
			}
			?>
			<li class="<?php echo esc_attr( implode( ' ', $grabwp_setup_classes ) ); ?>" data-step="<?php echo esc_attr( $grabwp_setup_id ); ?>">
				<?php if ( $grabwp_setup_item['passed'] ) : ?>
					<span class="grabwp-setup-progress-link">
				<?php else : ?>
					<a href="<?php echo esc_url( GrabWP_Tenancy_Setup_Wizard::get_step_url( $grabwp_setup_id, $grabwp_setup['flow'] ) ); ?>" <?php echo $grabwp_setup_id === $grabwp_setup['step_id'] ? 'aria-current="step"' : ''; ?>>
				<?php endif; ?>
					<span class="grabwp-setup-progress-mark" aria-hidden="true"><?php echo 'done' === $grabwp_setup_item_status ? '&#10003;' : esc_html( (string) $grabwp_setup_index ); ?></span>
					<span class="grabwp-setup-progress-label"><?php echo esc_html( $grabwp_setup_item['title'] ); ?></span>
					<?php if ( 'done' === $grabwp_setup_item_status ) : ?>
						<span class="screen-reader-text"><?php esc_html_e( '(done)', 'grabwp-tenancy' ); ?></span>
					<?php endif; ?>
				<?php echo $grabwp_setup_item['passed'] ? '</span>' : '</a>'; ?>
			</li>
		<?php endforeach; ?>
	</ol>
	<p class="grabwp-setup-progress-count">
		<?php
		$grabwp_setup_title = $grabwp_setup_step['title'];
		if ( $grabwp_setup['sub_of'] ) {
			$grabwp_setup_title = sprintf(
				/* translators: 1: step title, 2: current part of the step, 3: number of parts. */
				__( '%1$s (%2$d of %3$d)', 'grabwp-tenancy' ),
				$grabwp_setup_title,
				(int) $grabwp_setup['sub_of'][0],
				(int) $grabwp_setup['sub_of'][1]
			);
		}
		printf(
			/* translators: 1: current step number, 2: number of steps, 3: step title. */
			esc_html__( 'Step %1$d of %2$d: %3$s', 'grabwp-tenancy' ),
			(int) $grabwp_setup_position,
			count( $grabwp_setup['steps'] ),
			esc_html( $grabwp_setup_title )
		);
		?>
	</p>
	<?php // WordPress moves admin notices after this marker instead of into the header row. ?>
	<hr class="wp-header-end">

	<?php
	if ( ! empty( $grabwp_setup['result'] ) ) {
		$grabwp_site_result = $grabwp_setup['result'];
		include GRABWP_TENANCY_PLUGIN_DIR . 'admin/views/partials/site-result.php';
	}
	?>

	<?php if ( ! empty( $grabwp_setup['flash']['message'] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $grabwp_setup['flash']['type'] ? 'error' : 'success' ); ?> grabwp-setup-flash">
			<p><?php echo esc_html( $grabwp_setup['flash']['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grabwp-setup-form" data-step="<?php echo esc_attr( $grabwp_setup['step_id'] ); ?>" data-sub="<?php echo esc_attr( $grabwp_setup['sub'] ); ?>">
		<input type="hidden" name="action" value="grabwp_tenancy_setup_save" />
		<input type="hidden" name="step" value="<?php echo esc_attr( $grabwp_setup['step_id'] ); ?>" />
		<input type="hidden" name="flow" value="<?php echo esc_attr( $grabwp_setup['flow'] ); ?>" />
		<?php if ( '' !== $grabwp_setup['sub'] ) : ?>
			<input type="hidden" name="sub" value="<?php echo esc_attr( $grabwp_setup['sub'] ); ?>" />
		<?php endif; ?>
		<?php wp_nonce_field( 'grabwp_tenancy_setup_' . $grabwp_setup['step_id'] ); ?>
		<?php if ( ! $grabwp_setup_is_done ) : ?>
			<?php // Enter in a field saves (the first submit button in the form is the default). ?>
			<button type="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true" <?php disabled( $grabwp_setup_disabled ); ?>><?php echo esc_html( $grabwp_setup_primary ); ?></button>
		<?php endif; ?>

		<div class="grabwp-setup-body">
			<?php if ( ! empty( $grabwp_setup_step['context'] ) ) : ?>
				<p class="grabwp-setup-context"><?php echo esc_html( $grabwp_setup_step['context'] ); ?></p>
			<?php endif; ?>
			<?php call_user_func( $grabwp_setup_step['render_cb'] ); ?>
			<?php GrabWP_Tenancy_Setup_Wizard::render_later( isset( $grabwp_setup_step['later'] ) ? (array) $grabwp_setup_step['later'] : array() ); ?>
		</div>

		<?php if ( ! $grabwp_setup_is_done ) : ?>
			<div class="grabwp-setup-footer">
				<div class="grabwp-setup-footer-start">
					<?php if ( '' !== $grabwp_setup['prev_url'] ) : ?>
						<a class="button grabwp-setup-back" href="<?php echo esc_url( $grabwp_setup['prev_url'] ); ?>"><?php esc_html_e( 'Back', 'grabwp-tenancy' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="grabwp-setup-footer-end">
					<button type="submit" name="wizard_action" value="skip" class="button-link grabwp-setup-skip" formnovalidate><?php esc_html_e( 'Skip this step', 'grabwp-tenancy' ); ?></button>
					<span class="spinner grabwp-setup-spinner"></span>
					<button type="submit" class="button button-primary grabwp-setup-primary" <?php disabled( $grabwp_setup_disabled ); ?>><?php echo esc_html( $grabwp_setup_primary ); ?></button>
				</div>
			</div>
		<?php endif; ?>
	</form>
</div>
