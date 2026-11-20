<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<!-- Google Tag Manager -->
	<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
	new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
	j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
	'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
	})(window,document,'script','dataLayer','GTM-K86X6RF7');</script>
	<!-- End Google Tag Manager -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K86X6RF7"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<?php
$flxlm_groups   = flxlm_nav_groups();
$flxlm_stations = flxlm_get_stations();
$flxlm_caret    = '<svg class="fx-nav__caret" viewBox="0 0 10 6" width="10" height="6" aria-hidden="true" focusable="false"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<?php
/*
 * The glass bar. Dark or light is chosen by the body class flxlm-dark-hero
 * (see inc/site-frame.php). The glass itself is the .fx-header__glass layer, not the
 * header, so the phone drawer (position: fixed) is not trapped inside a filtered parent.
 */
$flxlm_brand = get_template_directory_uri() . '/assets/images/brand/';
?>
<header class="site-header fx-header" id="site-header">
	<div class="fx-header__glass" aria-hidden="true"></div>
	<div class="container site-header__inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-header__logo fx-logo" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>: Home">
			<img class="fx-logo__img fx-logo__img--white" src="<?php echo esc_url( $flxlm_brand . 'FLX-Logo-Horizontal-White.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="221" height="30">
			<img class="fx-logo__img fx-logo__img--navy" src="<?php echo esc_url( $flxlm_brand . 'FLX-Logo-Horizontal-Navy.png' ); ?>" alt="" width="221" height="30">
		</a>

		<button type="button" class="fx-burger" id="fx-burger" aria-expanded="false" aria-controls="fx-nav" aria-label="Menu">
			<span class="fx-burger__bars" aria-hidden="true"></span>
		</button>

		<?php
		/*
		 * Primary navigation. Printed from flxlm_nav_groups() and flxlm_get_stations() (inc/products.php),
		 * not from the 'primary' menu location, which stays registered in functions.php.
		 * Behaviour: assets/js/site-nav.js. Styles: assets/css/site-bold.css.
		 */
		?>
		<nav class="fx-nav" id="fx-nav" aria-label="Primary navigation">
			<ul class="fx-nav__list">
				<?php foreach ( $flxlm_groups as $group ) : ?>
					<?php
					$gkey   = isset( $group['key'] ) ? sanitize_html_class( $group['key'] ) : '';
					$glabel = isset( $group['label'] ) ? (string) $group['label'] : '';
					if ( '' === $gkey || '' === $glabel ) {
						continue;
					}
					$gtype   = isset( $group['type'] ) && 'stations' === $group['type'] ? 'stations' : 'links';
					$panel   = 'fx-panel-' . $gkey;
					$current = false;
					if ( 'links' === $gtype && ! empty( $group['items'] ) ) {
						foreach ( $group['items'] as $it ) {
							if ( flxlm_nav_is_current( flxlm_nav_url( $it ) ) ) {
								$current = true;
								break;
							}
						}
					} elseif ( 'stations' === $gtype ) {
						$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
						$current = false !== strpos( $request, '/solutions/radio/' );
					}
					?>
					<li class="fx-item fx-item--<?php echo esc_attr( $gkey ); ?><?php echo 'stations' === $gtype ? ' fx-item--mega' : ''; ?><?php echo $current ? ' is-current' : ''; ?>" data-fx-item>
						<button type="button" class="fx-nav__btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel ); ?>"<?php echo $current ? ' data-current="true"' : ''; ?>>
							<span class="fx-nav__label"><?php echo esc_html( $glabel ); ?></span>
							<?php echo $flxlm_caret; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup above. ?>
						</button>

						<?php if ( 'stations' === $gtype ) : ?>
							<div class="fx-panel fx-panel--mega" id="<?php echo esc_attr( $panel ); ?>">
								<div class="container fx-mega">
									<div class="fx-mega__stations">
										<p class="fx-panel__title" aria-hidden="true">Our stations</p>
										<ul class="fx-stations">
											<?php foreach ( $flxlm_stations as $st ) : ?>
												<?php
												if ( empty( $st['slug'] ) || empty( $st['name'] ) ) {
													continue;
												}
												$logo = flxlm_nav_station_logo( $st['slug'] );
												$aud  = isset( $st['demo'] ) ? (string) $st['demo'] : '';
												?>
												<li>
													<a class="fx-station<?php echo $logo ? '' : ' fx-station--text'; ?>" href="<?php echo esc_url( home_url( '/solutions/radio/' . $st['slug'] . '/' ) ); ?>">
														<?php if ( $logo ) : ?>
															<img src="<?php echo esc_url( $logo ); ?>" alt="" loading="lazy" decoding="async">
														<?php endif; ?>
														<b><?php echo esc_html( $st['name'] ); ?></b>
														<?php if ( '' !== $aud ) : ?>
															<small><?php echo esc_html( $aud ); ?></small>
														<?php endif; ?>
													</a>
												</li>
											<?php endforeach; ?>
											<li>
												<a class="fx-station fx-station--all" href="<?php echo esc_url( flxlm_nav_url( isset( $group['url'] ) ? $group['url'] : '/solutions/radio/' ) ); ?>">
													<b><?php echo esc_html( isset( $group['all_label'] ) ? $group['all_label'] : 'All stations' ); ?></b>
													<?php if ( ! empty( $group['all_blurb'] ) ) : ?>
														<small><?php echo esc_html( $group['all_blurb'] ); ?> &rarr;</small>
													<?php endif; ?>
												</a>
											</li>
										</ul>
									</div>
									<?php if ( ! empty( $group['feature']['title'] ) ) : ?>
										<?php $feat = $group['feature']; ?>
										<div class="fx-mega__feature">
											<div class="fx-feature">
												<b><?php echo esc_html( $feat['title'] ); ?></b>
												<?php if ( ! empty( $feat['text'] ) ) : ?>
													<span><?php echo esc_html( $feat['text'] ); ?></span>
												<?php endif; ?>
												<?php if ( ! empty( $feat['label'] ) ) : ?>
													<a href="<?php echo esc_url( flxlm_nav_url( isset( $feat['url'] ) ? $feat['url'] : '/contact/' ) ); ?>"><?php echo esc_html( $feat['label'] ); ?> &rarr;</a>
												<?php endif; ?>
											</div>
										</div>
									<?php endif; ?>
								</div>
							</div>
						<?php else : ?>
							<div class="fx-panel fx-panel--links" id="<?php echo esc_attr( $panel ); ?>">
								<ul class="fx-links">
									<?php foreach ( (array) ( isset( $group['items'] ) ? $group['items'] : array() ) as $item ) : ?>
										<?php
										$iurl = flxlm_nav_url( $item );
										if ( '' === $iurl || empty( $item['label'] ) ) {
											continue;
										}
										$icon = 'media' === $gkey && ! empty( $item['slug'] ) ? flxlm_nav_item_icon( $item['slug'] ) : '';
										?>
										<li>
											<a<?php echo $icon ? ' class="fx-links__has-icon"' : ''; ?> href="<?php echo esc_url( $iurl ); ?>"<?php echo flxlm_nav_is_current( $iurl ) ? ' aria-current="page"' : ''; ?>>
												<?php if ( $icon ) : ?>
													<img class="fx-links__icon" src="<?php echo esc_url( $icon ); ?>" alt="" width="24" height="24" loading="lazy" decoding="async">
												<?php endif; ?>
												<span class="fx-links__label"><?php echo esc_html( $item['label'] ); ?></span>
												<?php if ( ! empty( $item['blurb'] ) ) : ?>
													<small><?php echo esc_html( $item['blurb'] ); ?></small>
												<?php endif; ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn--primary fx-nav__cta">Get a free plan</a>
		</nav>
	</div>
</header>

<main class="site-main" id="main-content">
