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

<header class="site-header" id="site-header">
	<div class="container site-header__inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-header__logo" aria-label="<?php bloginfo( 'name' ); ?> — Home">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="site-header__wordmark"><?php bloginfo( 'name' ); ?></span>
			<?php endif; ?>
		</a>

		<button class="site-header__toggle" id="nav-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="primary-nav">
			<span class="site-header__hamburger"></span>
		</button>

		<nav class="site-nav" id="primary-nav" aria-label="Primary navigation">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'site-nav__list',
				'fallback_cb'    => 'flxlm_fallback_menu',
				'depth'          => 2,
			) );
			?>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn--primary site-nav__cta">Request Pricing</a>
		</nav>
	</div>
</header>

<main class="site-main" id="main-content">
<?php

/**
 * Fallback menu when no menu is assigned.
 */
function flxlm_fallback_menu() {
	?>
	<ul class="site-nav__list">
		<li><a href="<?php echo esc_url( home_url( '/solutions/' ) ); ?>">Solutions</a></li>
		<?php if ( wp_count_posts( 'flxlm_testimonial' )->publish > 0 ) : ?>
			<li><a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>">Client Stories</a></li>
		<?php endif; ?>
		<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
		<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
	</ul>
	<?php
}
