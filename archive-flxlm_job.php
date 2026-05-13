<?php
/**
 * Careers archive template — /careers/ on FLXLM.
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Careers</h1>
		<p class="page-header__subtitle">Join our team at FLX Local Media — locally owned multimedia connecting the Finger Lakes through seven radio stations and Finger Lakes Daily News.</p>
	</div>
</div>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="archive-grid">
				<?php while ( have_posts() ) : the_post();
					$job_location = get_post_meta( get_the_ID(), 'job_location', true );
					$job_type     = get_post_meta( get_the_ID(), 'job_type', true );
				?>
					<article class="archive-card">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="archive-card__image">
								<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
							</div>
						<?php endif; ?>
						<div class="archive-card__body">
							<h2 class="archive-card__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<?php if ( $job_location || $job_type ) : ?>
								<p class="archive-card__meta job-meta">
									<?php if ( $job_location ) : ?>
										<span class="job-meta__item"><?php echo esc_html( $job_location ); ?></span>
									<?php endif; ?>
									<?php if ( $job_location && $job_type ) : ?>
										<span class="job-meta__sep" aria-hidden="true">&middot;</span>
									<?php endif; ?>
									<?php if ( $job_type ) : ?>
										<span class="job-meta__item"><?php echo esc_html( $job_type ); ?></span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<p class="archive-card__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 24 ) ); ?></p>
							<p><a class="btn btn--outline" href="<?php the_permalink(); ?>">View Job</a></p>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
		<?php else : ?>
			<p class="text-center" style="color: var(--color-gray-500);">No open positions at this time. Check back soon!</p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
