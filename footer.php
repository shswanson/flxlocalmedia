<?php
/**
 * Site footer.
 *
 * @package flxlm
 */
?>
</main>

<?php get_template_part( 'template-parts/cta-primary' ); ?>

<footer class="site-footer">
	<div class="container site-footer__inner">
		<div class="site-footer__brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__logo" aria-label="<?php bloginfo( 'name' ); ?> — Home">
				<?php bloginfo( 'name' ); ?>
			</a>
			<p class="site-footer__tagline">Radio. Digital. Events. Content. One team, total market reach.</p>
		</div>

		<div class="site-footer__nav">
			<div class="site-footer__col">
				<h4 class="site-footer__heading">Solutions</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/solutions/radio/' ) ); ?>">Radio Advertising</a></li>
					<li><a href="<?php echo esc_url( home_url( '/solutions/digital/' ) ); ?>">Digital Marketing</a></li>
					<li><a href="<?php echo esc_url( home_url( '/solutions/events/' ) ); ?>">Event Marketing</a></li>
					<li><a href="<?php echo esc_url( home_url( '/solutions/content-marketing/' ) ); ?>">Content Marketing</a></li>
				</ul>
			</div>

			<div class="site-footer__col">
				<h4 class="site-footer__heading">Company</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About Us</a></li>
					<li><a href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>">Client Stories</a></li>
					<li><a href="<?php echo esc_url( home_url( '/resources/' ) ); ?>">Resources</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
				</ul>
			</div>

			<div class="site-footer__col">
				<h4 class="site-footer__heading">Contact</h4>
				<ul>
					<li>Geneva, NY 14456</li>
					<li>Auburn, NY 13021</li>
					<li>Penn Yan, NY 14527</li>
				</ul>
			</div>
		</div>
	</div>

	<div class="site-footer__bottom">
		<div class="container">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
