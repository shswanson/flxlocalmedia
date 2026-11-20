<?php
/**
 * Site footer.
 *
 * Navy, the same on every page. Columns follow the menu groups (printed from
 * flxlm_nav_groups() and flxlm_get_stations(), inc/products.php), then Company.
 * The SBA badge is the official image, shown as supplied.
 *
 * @package flxlm
 */

$flxlm_groups   = function_exists( 'flxlm_nav_groups' ) ? flxlm_nav_groups() : array();
$flxlm_stations = function_exists( 'flxlm_get_stations' ) ? flxlm_get_stations() : array();

// SBA badge: the filter in inc/site-frame.php sets the verification link and alt text.
// If the helper is missing, fall back to the same values here.
$flxlm_badge = function_exists( 'flxlm_sdvosb_badge' ) ? flxlm_sdvosb_badge() : null;
if ( ! $flxlm_badge && is_readable( get_template_directory() . '/assets/images/brand/sba-sdvosb-400.png' ) ) {
	$flxlm_badge = array(
		'src' => flxlm_asset_url( 'assets/images/brand/sba-sdvosb-400.png' ),
		'url' => 'https://search.certifications.sba.gov/profile/W1J2NFT4ZTH9',
		'alt' => 'TOTIB Media Inc, doing business as FLX Local Media, is an SBA-certified Service-Disabled Veteran-Owned Small Business.',
	);
}
?>
</main>

<?php get_template_part( 'template-parts/cta-primary' ); ?>

<footer class="site-footer">
	<div class="container site-footer__inner">
		<div class="site-footer__brand">
			<div class="site-footer__brandline">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__logo" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>, Home">
					<img
						src="<?php echo esc_url( flxlm_asset_url( 'assets/images/brand/FLX-Logo-Horizontal-White.svg' ) ); ?>"
						alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
						width="221"
						height="30"
						loading="lazy"
					/>
				</a>
				<p class="site-footer__owned">FLX Local Media &middot; Veteran Owned</p>
			</div>
			<p class="site-footer__tagline">Local marketing for Finger Lakes businesses.</p>
			<?php if ( $flxlm_badge ) : ?>
				<div class="site-footer__sba">
					<a class="site-footer__badge" href="<?php echo esc_url( $flxlm_badge['url'] ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $flxlm_badge['alt'] ); ?>">
						<img src="<?php echo esc_url( $flxlm_badge['src'] ); ?>" alt="<?php echo esc_attr( $flxlm_badge['alt'] ); ?>" width="90" height="112" loading="lazy">
					</a>
				</div>
			<?php endif; ?>
		</div>

		<nav class="site-footer__nav" aria-label="Footer">
			<?php foreach ( $flxlm_groups as $group ) : ?>
				<?php
				$gkey   = isset( $group['key'] ) ? sanitize_html_class( $group['key'] ) : '';
				$glabel = isset( $group['label'] ) ? (string) $group['label'] : '';
				if ( '' === $gkey || '' === $glabel ) {
					continue;
				}
				$is_stations = isset( $group['type'] ) && 'stations' === $group['type'];
				?>
				<div class="site-footer__col site-footer__col--<?php echo esc_attr( $gkey ); ?>">
					<h4 class="site-footer__heading"><?php echo esc_html( $glabel ); ?></h4>
					<ul>
						<?php if ( $is_stations ) : ?>
							<?php foreach ( $flxlm_stations as $st ) : ?>
								<?php if ( empty( $st['slug'] ) || empty( $st['name'] ) ) { continue; } ?>
								<li><a href="<?php echo esc_url( home_url( '/solutions/radio/' . $st['slug'] . '/' ) ); ?>"><?php echo esc_html( $st['name'] ); ?></a></li>
							<?php endforeach; ?>
							<li><a href="<?php echo esc_url( flxlm_nav_url( isset( $group['url'] ) ? $group['url'] : '/solutions/radio/' ) ); ?>"><?php echo esc_html( isset( $group['all_label'] ) ? $group['all_label'] : 'All stations' ); ?></a></li>
						<?php else : ?>
							<?php foreach ( (array) ( isset( $group['items'] ) ? $group['items'] : array() ) as $item ) : ?>
								<?php
								$iurl = flxlm_nav_url( $item );
								if ( '' === $iurl || empty( $item['label'] ) ) {
									continue;
								}
								?>
								<li><a href="<?php echo esc_url( $iurl ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>
				</div>
			<?php endforeach; ?>

			<div class="site-footer__col site-footer__col--company">
				<h4 class="site-footer__heading">Company</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
					<li><a href="<?php echo esc_url( home_url( '/careers/' ) ); ?>">Careers</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
				</ul>
				<h5 class="site-footer__subheading">Our offices</h5>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/contact/geneva/' ) ); ?>">Geneva</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/auburn/' ) ); ?>">Auburn</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/penn-yan/' ) ); ?>">Penn Yan</a></li>
				</ul>
			</div>
		</nav>
	</div>

	<div class="site-footer__bottom">
		<div class="container">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. All rights reserved. <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy</a> &middot; <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms</a></p>
			<p class="site-footer__legal">FLX Local Media is a d/b/a of TOTIB Media, Inc., 3568 Lenox Road, Geneva, NY 14456.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
