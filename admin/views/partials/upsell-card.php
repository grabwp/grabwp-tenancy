<?php
/**
 * Shared upsell card partial.
 *
 * Expected variables (set by GrabWP_Tenancy_Admin::render_upsell_card):
 * @var string $upsell_url   Upgrade URL with UTM params.
 * @var string $upsell_title Optional heading (empty = badge inline with message).
 * @var string $upsell_message Body copy.
 * @var string $upsell_cta   Button label.
 * @var string $upsell_margin Margin utility class.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.1
 */

if ( empty( $upsell_url ) || empty( $upsell_message ) ) {
	return;
}

$upsell_cta    = ! empty( $upsell_cta ) ? $upsell_cta : __( 'Learn about Pro', 'grabwp-tenancy' );
$upsell_margin = ! empty( $upsell_margin ) ? $upsell_margin : 'grabwp-mt-sm';
?>
<div class="grabwp-admin-card <?php echo esc_attr( $upsell_margin ); ?>">
	<?php if ( ! empty( $upsell_title ) ) : ?>
		<h3>
			<?php echo esc_html( $upsell_title ); ?>
			<span class="grabwp-badge grabwp-badge-info"><?php esc_html_e( 'Pro', 'grabwp-tenancy' ); ?></span>
		</h3>
		<p><?php echo esc_html( $upsell_message ); ?></p>
	<?php else : ?>
		<p>
			<span class="grabwp-badge grabwp-badge-info"><?php esc_html_e( 'Pro', 'grabwp-tenancy' ); ?></span>
			<?php echo esc_html( $upsell_message ); ?>
		</p>
	<?php endif; ?>
	<p class="grabwp-mt-sm">
		<a href="<?php echo esc_url( $upsell_url ); ?>" class="button button-primary grabwp-cta-gradient" target="_blank" rel="noopener noreferrer">
			<?php echo esc_html( $upsell_cta ); ?>
		</a>
	</p>
</div>
