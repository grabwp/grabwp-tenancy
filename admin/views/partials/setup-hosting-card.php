<?php
/**
 * Hosting setup card (setup wizard Check screen and Status page): Multisite
 * and other wp-config.php settings that stop GrabWP, secure address, HTTPS
 * behind a proxy, and wildcard subdomains.
 *
 * Variables available from the including scope:
 * @var array  $grabwp_hosting         Result of GrabWP_Tenancy_Status_Checker::get_hosting_card_data().
 * @var string $grabwp_hosting_recheck URL that runs the checks again.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grabwp_hosting_rows  = $grabwp_hosting['rows'];
$grabwp_hosting_ms    = $grabwp_hosting_rows['multisite'];
$grabwp_hosting_https = $grabwp_hosting_rows['https_urls'];
$grabwp_hosting_proxy = $grabwp_hosting_rows['proxy'];
$grabwp_hosting_wild  = $grabwp_hosting_rows['wildcard'];
$grabwp_hosting_host  = (string) wp_parse_url( $grabwp_hosting_https['home'], PHP_URL_HOST );
$grabwp_hosting_nonce = wp_create_nonce( 'grabwp_fix_component' );
$grabwp_hosting_titles = array(
	'multisite'  => __( 'Multisite and wp-config.php', 'grabwp-tenancy' ),
	'https_urls' => __( 'Secure address', 'grabwp-tenancy' ),
	'proxy'      => __( 'HTTPS behind a proxy', 'grabwp-tenancy' ),
	'wildcard'   => __( 'Subdomains (wildcard)', 'grabwp-tenancy' ),
);

/**
 * One fix or undo button of the card.
 *
 * @param string $action  AJAX action.
 * @param string $label   Button label.
 * @param string $confirm Confirmation question.
 * @param string $nonce   Nonce.
 * @param bool   $to_https Send the browser to the https address after success.
 */
$grabwp_hosting_button = static function ( $action, $label, $confirm, $nonce, $to_https = false ) {
	printf(
		'<button type="button" class="button button-small grabwp-hosting-btn" data-hosting-action="%1$s" data-nonce="%2$s" data-confirm="%3$s"%4$s>%5$s</button>',
		esc_attr( $action ),
		esc_attr( $nonce ),
		esc_attr( $confirm ),
		$to_https ? ' data-to-https="1"' : '',
		esc_html( $label )
	);
};

$grabwp_hosting_mark = static function ( $row ) {
	if ( isset( $row['state'] ) && 'not_needed' === $row['state'] ) {
		return '<span class="grabwp-hosting-mark is-skip" aria-hidden="true">&#9675;</span>';
	}
	return $row['ok']
		? '<span class="grabwp-hosting-mark is-ok" aria-hidden="true">&#10003;</span>'
		: '<span class="grabwp-hosting-mark is-fail" aria-hidden="true">&#10007;</span>';
};

$grabwp_hosting_yes_no = static function ( $label, $ok ) {
	return esc_html( $label ) . ' ' . ( $ok ? '&#10003;' : '&#10007;' );
};
?>
<div class="grabwp-env-card grabwp-hosting-card"
	data-issues="<?php echo (int) $grabwp_hosting['issues']; ?>"
	data-fixable="<?php echo (int) $grabwp_hosting['fixable']; ?>"
	data-busy="<?php esc_attr_e( 'Working...', 'grabwp-tenancy' ); ?>"
	data-network-error="<?php esc_attr_e( 'Network error. Check again.', 'grabwp-tenancy' ); ?>">
	<div class="grabwp-env-card-header">
		<strong><?php esc_html_e( 'Hosting setup', 'grabwp-tenancy' ); ?></strong>
		<?php if ( 0 === $grabwp_hosting['issues'] ) : ?>
			<span class="grabwp-hosting-summary" style="color: #46b450; font-size: 13px;"><?php esc_html_e( '✓ Ready', 'grabwp-tenancy' ); ?></span>
		<?php else : ?>
			<span class="grabwp-hosting-summary" style="color: #dc3232; font-size: 13px;">
				<?php
				printf(
					/* translators: 1: rows that need attention, 2: number of rows. */
					esc_html( _n( '%1$d of %2$d needs attention', '%1$d of %2$d need attention', $grabwp_hosting['issues'], 'grabwp-tenancy' ) ),
					(int) $grabwp_hosting['issues'],
					count( $grabwp_hosting_rows )
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<ul class="grabwp-hosting-rows">
		<?php // Multisite and wp-config.php. ?>
		<li class="grabwp-hosting-row" data-row="multisite" data-ok="<?php echo $grabwp_hosting_ms['ok'] ? '1' : '0'; ?>" data-state="<?php echo esc_attr( $grabwp_hosting_ms['state'] ); ?>">
			<?php echo $grabwp_hosting_mark( $grabwp_hosting_ms ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?>
			<strong><?php echo esc_html( $grabwp_hosting_titles['multisite'] ); ?></strong>
			<span class="grabwp-hosting-text">
				<?php
				switch ( $grabwp_hosting_ms['state'] ) {
					case 'network':
						printf(
							/* translators: %d: number of sites in the Multisite network. */
							esc_html__( 'This is a Multisite network with %d sites. Do not convert it: back up or move those sites first.', 'grabwp-tenancy' ),
							(int) $grabwp_hosting_ms['sites']
						);
						break;
					case 'active':
						esc_html_e( 'This site still runs as WordPress Multisite. GrabWP needs regular WordPress. Remove every line below.', 'grabwp-tenancy' );
						break;
					case 'residue':
						esc_html_e( 'wp-config.php has settings that stop GrabWP. Remove every line below.', 'grabwp-tenancy' );
						break;
					case 'cleanup':
						esc_html_e( 'Regular WordPress. WP_ALLOW_MULTISITE is optional cleanup.', 'grabwp-tenancy' );
						break;
					default:
						esc_html_e( 'This site runs as regular WordPress.', 'grabwp-tenancy' );
				}
				?>
			</span>
			<?php if ( 'network' !== $grabwp_hosting_ms['state'] && ( $grabwp_hosting_ms['defines'] || $grabwp_hosting_ms['external'] ) ) : ?>
				<details class="grabwp-env-code grabwp-hosting-lines"<?php echo $grabwp_hosting_ms['ok'] ? '' : ' open'; ?>>
					<summary><?php esc_html_e( 'Lines to remove by hand', 'grabwp-tenancy' ); ?></summary>
					<?php if ( $grabwp_hosting_ms['defines'] ) : ?>
						<p class="grabwp-env-meta">
							<?php
							printf(
								/* translators: %s: path of wp-config.php. */
								esc_html__( 'In %s:', 'grabwp-tenancy' ),
								'<code dir="ltr">' . esc_html( $grabwp_hosting_ms['file'] ) . '</code>'
							);
							?>
						</p>
						<pre class="grabwp-env-pre" dir="ltr"><?php
						foreach ( $grabwp_hosting_ms['defines'] as $grabwp_hosting_line ) {
							if ( $grabwp_hosting_line['conditional'] ) {
								printf(
									/* translators: 1: line number, 2: PHP code on that line. */
									esc_html__( 'Line %1$d: %2$s (inside a condition, check it)', 'grabwp-tenancy' ),
									(int) $grabwp_hosting_line['line'],
									esc_html( $grabwp_hosting_line['code'] )
								);
							} else {
								printf(
									/* translators: 1: line number, 2: PHP code on that line. */
									esc_html__( 'Line %1$d: %2$s', 'grabwp-tenancy' ),
									(int) $grabwp_hosting_line['line'],
									esc_html( $grabwp_hosting_line['code'] )
								);
							}
							echo "\n";
						}
						?></pre>
					<?php endif; ?>
					<?php if ( $grabwp_hosting_ms['external'] ) : ?>
						<p class="grabwp-env-meta">
							<?php
							printf(
								/* translators: %s: list of constant names. */
								esc_html__( 'Set outside wp-config.php (for example by a host must-use plugin): %s. Ask your host to remove them.', 'grabwp-tenancy' ),
								'<code dir="ltr">' . esc_html( implode( ', ', $grabwp_hosting_ms['external'] ) ) . '</code>'
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $grabwp_hosting_ms['manual'] ) : ?>
						<p class="grabwp-env-meta"><?php esc_html_e( 'Some of these lines are inside a condition or still used by other code, so GrabWP cannot remove them for you.', 'grabwp-tenancy' ); ?></p>
					<?php endif; ?>
					<?php if ( ! $grabwp_hosting_ms['ok'] && ! $grabwp_hosting_ms['fixable'] ) : ?>
						<p class="grabwp-env-meta"><?php esc_html_e( 'GrabWP never removes these lines for you. After removing them, log in again and activate plugins that were network activated, then Check again.', 'grabwp-tenancy' ); ?></p>
					<?php endif; ?>
				</details>
			<?php endif; ?>
			<?php
			if ( $grabwp_hosting_ms['fixable'] ) {
				$grabwp_hosting_button(
					'grabwp_hosting_multisite_cleanup',
					__( 'Remove these settings', 'grabwp-tenancy' ),
					__( 'Remove the settings listed here from wp-config.php? A backup is kept in the GrabWP data folder, and you can undo this here. You may need to log in again.', 'grabwp-tenancy' ),
					$grabwp_hosting_nonce
				);
			} elseif ( 'network' !== $grabwp_hosting_ms['state'] && ! empty( $grabwp_hosting['multisite_undo'] ) ) {
				$grabwp_hosting_button(
					'grabwp_hosting_multisite_cleanup_undo',
					__( 'Undo settings removal', 'grabwp-tenancy' ),
					__( 'Put the removed settings back into wp-config.php? They may stop GrabWP again.', 'grabwp-tenancy' ),
					$grabwp_hosting_nonce
				);
			}
			?>
		</li>

		<?php // Secure address (home and siteurl). ?>
		<li class="grabwp-hosting-row" data-row="https_urls" data-ok="<?php echo $grabwp_hosting_https['ok'] ? '1' : '0'; ?>" data-state="<?php echo esc_attr( $grabwp_hosting_https['state'] ); ?>">
			<?php echo $grabwp_hosting_mark( $grabwp_hosting_https ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?>
			<strong><?php echo esc_html( $grabwp_hosting_titles['https_urls'] ); ?></strong>
			<span class="grabwp-hosting-text">
				<?php
				switch ( $grabwp_hosting_https['state'] ) {
					case 'fixable':
						esc_html_e( 'Your site works on https, but WordPress still uses http.', 'grabwp-tenancy' );
						break;
					case 'open_https':
						esc_html_e( 'Your site works on https, but WordPress still uses http. Fix HTTPS behind a proxy first, then open this page over https.', 'grabwp-tenancy' );
						break;
					case 'constant':
						esc_html_e( 'WP_HOME or WP_SITEURL sets the address. Remove it from wp-config.php above, then Check again.', 'grabwp-tenancy' );
						break;
					case 'cert':
						printf(
							/* translators: %s: host name. */
							esc_html__( 'The https certificate of %s is not valid. Ask your host or CDN for one.', 'grabwp-tenancy' ),
							'<code dir="ltr">' . esc_html( $grabwp_hosting_host ) . '</code>'
						);
						break;
					case 'no_https':
						printf(
							/* translators: %s: host name. */
							esc_html__( '%s does not answer on https yet. Turn on SSL at your host or CDN.', 'grabwp-tenancy' ),
							'<code dir="ltr">' . esc_html( $grabwp_hosting_host ) . '</code>'
						);
						break;
					default:
						esc_html_e( 'WordPress uses https.', 'grabwp-tenancy' );
				}
				?>
			</span>
			<?php
			if ( $grabwp_hosting_https['fixable'] ) {
				$grabwp_hosting_button(
					'grabwp_hosting_https_urls',
					__( 'Switch to https', 'grabwp-tenancy' ),
					__( 'Switch the WordPress Address and Site Address to https? Links inside posts are not changed. You can undo this here.', 'grabwp-tenancy' ),
					$grabwp_hosting_nonce,
					true
				);
			} elseif ( $grabwp_hosting_https['ok'] && $grabwp_hosting['https_undo'] ) {
				$grabwp_hosting_button(
					'grabwp_hosting_https_urls_undo',
					__( 'Undo', 'grabwp-tenancy' ),
					__( 'Put the WordPress Address and Site Address back to http?', 'grabwp-tenancy' ),
					$grabwp_hosting_nonce
				);
			}
			?>
		</li>

		<?php // HTTPS behind a proxy. ?>
		<li class="grabwp-hosting-row" data-row="proxy" data-ok="<?php echo $grabwp_hosting_proxy['ok'] ? '1' : '0'; ?>" data-state="<?php echo esc_attr( $grabwp_hosting_proxy['state'] ); ?>">
			<?php echo $grabwp_hosting_mark( $grabwp_hosting_proxy ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?>
			<strong><?php echo esc_html( $grabwp_hosting_titles['proxy'] ); ?></strong>
			<span class="grabwp-hosting-text">
				<?php
				switch ( $grabwp_hosting_proxy['state'] ) {
					case 'missing':
						esc_html_e( 'Your proxy or CDN serves https, but WordPress is not told.', 'grabwp-tenancy' );
						break;
					case 'ineffective':
						printf(
							/* translators: %d: line number in wp-config.php. */
							esc_html__( 'wp-config.php has HTTPS code at line %d, but WordPress still sees http.', 'grabwp-tenancy' ),
							(int) $grabwp_hosting_proxy['line']
						);
						break;
					case 'after_loader':
						printf(
							/* translators: %d: line number in wp-config.php. */
							esc_html__( 'The HTTPS code at line %d of wp-config.php runs after the GrabWP loader, so new sites can miss https.', 'grabwp-tenancy' ),
							(int) $grabwp_hosting_proxy['line']
						);
						break;
					case 'no_proxy':
						esc_html_e( 'No proxy or CDN reports https for this visit.', 'grabwp-tenancy' );
						break;
					default:
						esc_html_e( 'WordPress sees https from your proxy or CDN.', 'grabwp-tenancy' );
				}
				?>
			</span>
			<?php
			if ( $grabwp_hosting_proxy['fixable'] ) {
				$grabwp_hosting_button(
					'grabwp_hosting_proxy_https',
					__( 'Add HTTPS detection', 'grabwp-tenancy' ),
					__( 'Add HTTPS detection to wp-config.php? A backup is kept in the GrabWP data folder, and you can undo this here.', 'grabwp-tenancy' ),
					$grabwp_hosting_nonce
				);
			} elseif ( $grabwp_hosting_proxy['ours'] ) {
				$grabwp_hosting_button(
					'grabwp_hosting_proxy_https_undo',
					__( 'Remove HTTPS detection', 'grabwp-tenancy' ),
					__( 'Remove the HTTPS detection that GrabWP added to wp-config.php? Behind a proxy, WordPress then sees http again.', 'grabwp-tenancy' ),
					$grabwp_hosting_nonce
				);
			}
			?>
			<?php if ( ! $grabwp_hosting_proxy['ok'] ) : ?>
				<details class="grabwp-env-code">
					<summary><?php esc_html_e( 'Code to add by hand', 'grabwp-tenancy' ); ?></summary>
					<p class="grabwp-env-meta">
						<?php
						printf(
							/* translators: %s: the GrabWP loader marker line. */
							esc_html__( 'Add to wp-config.php, before %s:', 'grabwp-tenancy' ),
							'<code dir="ltr"># BEGIN GrabWP Tenancy</code>'
						);
						?>
					</p>
					<div class="grabwp-manual-code">
						<pre class="grabwp-env-pre" dir="ltr"><?php echo esc_html( GrabWP_Tenancy_Installer::get_proxy_https_block() ); ?></pre>
						<div class="grabwp-env-actions">
							<button type="button" class="button button-small grabwp-copy-code-btn"><?php esc_html_e( 'Copy code', 'grabwp-tenancy' ); ?></button>
						</div>
					</div>
				</details>
			<?php endif; ?>
		</li>

		<?php // Subdomains (wildcard DNS, route, certificate). ?>
		<li class="grabwp-hosting-row" data-row="wildcard" data-ok="<?php echo $grabwp_hosting_wild['ok'] ? '1' : '0'; ?>" data-state="<?php echo esc_attr( $grabwp_hosting_wild['state'] ); ?>">
			<?php echo $grabwp_hosting_mark( $grabwp_hosting_wild ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup. ?>
			<strong><?php echo esc_html( $grabwp_hosting_titles['wildcard'] ); ?></strong>
			<span class="grabwp-hosting-text">
				<?php
				$grabwp_hosting_pattern = '<code dir="ltr">*.' . esc_html( $grabwp_hosting_wild['domain'] ) . '</code>';
				switch ( $grabwp_hosting_wild['state'] ) {
					case 'not_needed':
						esc_html_e( 'Not needed: sites use path addresses.', 'grabwp-tenancy' );
						break;
					case 'no_domain':
						esc_html_e( 'This site has no domain name, so sites cannot get subdomains.', 'grabwp-tenancy' );
						break;
					case 'dns':
						/* translators: %s: wildcard pattern such as *.example.com */
						printf( esc_html__( 'No DNS record for %s yet. Add a wildcard record at your DNS host.', 'grabwp-tenancy' ), $grabwp_hosting_pattern ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
						break;
					case 'unreachable':
						/* translators: %s: wildcard pattern such as *.example.com */
						printf( esc_html__( '%s has DNS, but nothing answers on https.', 'grabwp-tenancy' ), $grabwp_hosting_pattern ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
						break;
					case 'not_ours':
						/* translators: %s: wildcard pattern such as *.example.com */
						printf( esc_html__( '%s reaches a different website. Point it at this site at your host.', 'grabwp-tenancy' ), $grabwp_hosting_pattern ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
						break;
					case 'cert':
						/* translators: %s: wildcard pattern such as *.example.com */
						printf( esc_html__( '%s reaches this site, but the certificate does not cover it.', 'grabwp-tenancy' ), $grabwp_hosting_pattern ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
						break;
					default:
						/* translators: %s: wildcard pattern such as *.example.com */
						printf( esc_html__( 'New sites at %s work.', 'grabwp-tenancy' ), $grabwp_hosting_pattern ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				}
				?>
			</span>
			<?php if ( ! in_array( $grabwp_hosting_wild['state'], array( 'not_needed', 'no_domain' ), true ) ) : ?>
				<span class="grabwp-hosting-steps">
					<?php
					echo $grabwp_hosting_yes_no( __( 'DNS', 'grabwp-tenancy' ), $grabwp_hosting_wild['dns'] ) . ' &middot; ' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper.
						. $grabwp_hosting_yes_no( __( 'address', 'grabwp-tenancy' ), $grabwp_hosting_wild['route'] ) . ' &middot; ' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						. $grabwp_hosting_yes_no( __( 'certificate', 'grabwp-tenancy' ), 'ok' === $grabwp_hosting_wild['tls'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</span>
			<?php endif; ?>
		</li>
	</ul>

	<p class="grabwp-hosting-footer">
		<a class="button-link grabwp-hosting-recheck" href="<?php echo esc_url( $grabwp_hosting_recheck ); ?>"><?php esc_html_e( 'Check again', 'grabwp-tenancy' ); ?></a>
	</p>

	<?php if ( ! $grabwp_hosting_ms['ok'] || ! $grabwp_hosting_wild['ok'] ) : ?>
		<details class="grabwp-env-code grabwp-hosting-xcloud"<?php echo $grabwp_hosting['xspeed'] ? ' open' : ''; ?>>
			<summary><?php esc_html_e( 'Using xCloud?', 'grabwp-tenancy' ); ?></summary>
			<ol class="grabwp-hosting-xcloud-steps">
				<li><?php esc_html_e( 'In xCloud, create the WordPress site as Multisite with subdomains. xCloud sets up wildcard subdomain hosting only for such sites.', 'grabwp-tenancy' ); ?></li>
				<li><?php esc_html_e( 'Wait until the Subdomains row here passes: DNS, address and certificate.', 'grabwp-tenancy' ); ?></li>
				<li><?php esc_html_e( 'Then remove every native Multisite line from wp-config.php. GrabWP runs only on regular WordPress (single site). Old Multisite rules in .htaccess can stay.', 'grabwp-tenancy' ); ?></li>
				<li><?php esc_html_e( 'If the network already has more than one site, do not convert it. Back up or move those sites first.', 'grabwp-tenancy' ); ?></li>
			</ol>
		</details>
	<?php endif; ?>
</div>
