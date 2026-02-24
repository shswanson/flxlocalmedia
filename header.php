<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

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
		<li><a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>">Client Stories</a></li>
		<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
		<li><a href="<?php echo esc_url( home_url( '/resources/' ) ); ?>">Resources</a></li>
		<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
	</ul>
	<?php
}
