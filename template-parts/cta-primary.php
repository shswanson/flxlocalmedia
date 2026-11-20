<?php
/**
 * Template Part: Primary CTA Banner
 *
 * Free local marketing review CTA, links to the contact page review form.
 *
 * @package flxlm
 */
?>
<section class="cta-banner">
	<div class="container">
		<span class="section__eyebrow flx-eyebrow">Free local marketing review</span>
		<h2 class="cta-banner__title">Tell us the one thing <span>holding your business back.</span></h2>
		<p class="cta-banner__subtitle">A short conversation with someone who knows the Finger Lakes market. We'll come back with a plan, not a pitch.</p>
		<div class="cta-banner__actions">
			<a href="<?php echo esc_url( home_url( '/contact/#review' ) ); ?>" class="flx-btn">Get Your Free Review</a>
			<a href="<?php echo esc_url( home_url( '/how-we-work/' ) ); ?>" class="cta-banner__link flx-btn-link">How we build a plan <span aria-hidden="true">&rarr;</span></a>
		</div>
	</div>
</section>
