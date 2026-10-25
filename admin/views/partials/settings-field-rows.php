<?php
/**
 * GrabWP Tenancy - Settings rows for one schema group.
 *
 * Labels, tips and technical names come from GrabWP_Tenancy_Settings::get_setting_fields()
 * so the Settings, Status and per-tenant Overrides screens use the same wording.
 *
 * @package GrabWP_Tenancy
 *
 * @var array  $settings               Current settings.
 * @var string $grabwp_settings_group  Schema group to render.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table">
	<?php
	foreach ( GrabWP_Tenancy_Settings::get_setting_fields() as $grabwp_field_key => $grabwp_field ) :
		if ( $grabwp_settings_group !== $grabwp_field['group'] ) {
			continue;
		}
		$grabwp_field_value = isset( $settings[ $grabwp_field_key ] ) ? $settings[ $grabwp_field_key ] : '';
		$grabwp_field_tech  = isset( $grabwp_field['tech'] ) ? $grabwp_field['tech'] : '';
		$grabwp_field_id    = 'grabwp-setting-' . $grabwp_field_key;
		?>
		<tr>
			<th scope="row">
				<?php if ( 'bool' === $grabwp_field['type'] ) : ?>
					<span id="<?php echo esc_attr( $grabwp_field_id . '-title' ); ?>"><?php echo esc_html( $grabwp_field['label'] ); ?></span>
				<?php else : ?>
					<label for="<?php echo esc_attr( $grabwp_field_id ); ?>"><?php echo esc_html( $grabwp_field['label'] ); ?></label>
				<?php endif; ?>
				<?php GrabWP_Tenancy_Admin::help_tip( $grabwp_field['description'] ); ?>
			</th>
			<td>
				<?php if ( 'bool' === $grabwp_field['type'] ) : ?>
					<label id="<?php echo esc_attr( $grabwp_field_id . '-yes' ); ?>">
						<input type="checkbox" name="<?php echo esc_attr( $grabwp_field_key ); ?>" value="1" aria-labelledby="<?php echo esc_attr( $grabwp_field_id . '-title ' . $grabwp_field_id . '-yes' ); ?>" <?php checked( ! empty( $grabwp_field_value ) ); ?> />
						<?php
						if ( '' === $grabwp_field_tech ) {
							esc_html_e( 'Yes', 'grabwp-tenancy' );
						} else {
							printf(
								/* translators: %s: technical constant or setting name, e.g. DISALLOW_FILE_MODS=true */
								esc_html__( 'Yes, %s', 'grabwp-tenancy' ),
								'<code dir="ltr">' . esc_html( $grabwp_field_tech ) . '</code>'
							);
						}
						?>
					</label>
				<?php elseif ( 'int' === $grabwp_field['type'] ) : ?>
					<input type="number" id="<?php echo esc_attr( $grabwp_field_id ); ?>" name="<?php echo esc_attr( $grabwp_field_key ); ?>" value="<?php echo esc_attr( (string) (int) $grabwp_field_value ); ?>" min="<?php echo esc_attr( (string) (int) $grabwp_field['min'] ); ?>" max="<?php echo esc_attr( (string) (int) $grabwp_field['max'] ); ?>" class="small-text" />
					<?php if ( '' !== $grabwp_field_tech ) : ?>
						<code dir="ltr"><?php echo esc_html( $grabwp_field_tech ); ?></code>
					<?php endif; ?>
				<?php else : ?>
					<input type="text" id="<?php echo esc_attr( $grabwp_field_id ); ?>" name="<?php echo esc_attr( $grabwp_field_key ); ?>" value="<?php echo esc_attr( (string) $grabwp_field_value ); ?>" class="regular-text" dir="ltr" />
					<?php if ( '' !== $grabwp_field_tech ) : ?>
						<code dir="ltr"><?php echo esc_html( $grabwp_field_tech ); ?></code>
					<?php endif; ?>
				<?php endif; ?>
				<?php GrabWP_Tenancy_Admin::constant_pin_notice( $grabwp_field_key, $grabwp_field_value ); ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
