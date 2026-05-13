<?php
/**
 * Single job template.
 *
 * Auto-resolved by WP for the `flxlm_job` CPT. Title + meta in the page-header,
 * narrative content in the body, then standard responsibilities / qualifications
 * / offer / apply / EEO sections.
 *
 * @package flxlm
 */

get_header();

while ( have_posts() ) : the_post();
	$job_location         = get_post_meta( get_the_ID(), 'job_location', true );
	$job_type             = get_post_meta( get_the_ID(), 'job_type', true );
	$job_email            = get_post_meta( get_the_ID(), 'job_email', true );
	$job_responsibilities = get_post_meta( get_the_ID(), 'job_responsibilities', true );
	$job_qualifications   = get_post_meta( get_the_ID(), 'job_qualifications', true );
	$job_offer            = get_post_meta( get_the_ID(), 'job_offer', true );
?>

<div class="page-header">
	<div class="container">
		<p class="page-header__eyebrow"><a href="<?php echo esc_url( home_url( '/careers/' ) ); ?>">Careers</a></p>
		<h1 class="page-header__title"><?php the_title(); ?></h1>
		<?php if ( $job_location || $job_type ) : ?>
			<p class="page-header__subtitle job-meta">
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
	</div>
</div>

<section class="section">
	<div class="container container--narrow">
		<div class="post-content">

			<?php the_content(); ?>

			<?php if ( $job_responsibilities ) : ?>
				<div class="job-section">
					<h2>What You'll Do</h2>
					<?php echo wp_kses_post( $job_responsibilities ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $job_qualifications ) : ?>
				<div class="job-section">
					<h2>What We're Looking For</h2>
					<?php echo wp_kses_post( $job_qualifications ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $job_offer ) : ?>
				<div class="job-section">
					<h2>What We Offer</h2>
					<?php echo wp_kses_post( $job_offer ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $job_email ) :
				$emails = array_values( array_filter( array_map( 'trim', explode( ',', $job_email ) ) ) );
				$links  = array();
				foreach ( $emails as $em ) {
					$links[] = '<a href="mailto:' . esc_attr( $em ) . '">' . esc_html( $em ) . '</a>';
				}
				$count = count( $links );
				if ( $count === 1 ) {
					$linked = $links[0];
				} elseif ( $count === 2 ) {
					$linked = $links[0] . ' and ' . $links[1];
				} else {
					$last   = array_pop( $links );
					$linked = implode( ', ', $links ) . ', and ' . $last;
				}
				?>
				<div class="job-section">
					<h2>How to Apply</h2>
					<p>Please submit your resume and cover letter to <?php echo wp_kses( $linked, array( 'a' => array( 'href' => array() ) ) ); ?>.</p>
				</div>
			<?php endif; ?>

			<div class="job-section job-section--eeo">
				<p><em>FLX Local Media is an equal opportunity employer. All qualified applicants will receive consideration without regard to race, color, religion, sex, sexual orientation, gender identity, national origin, disability, veteran status, or any other protected characteristic.</em></p>
			</div>

		</div>
	</div>
</section>

<?php endwhile; ?>

<?php get_footer(); ?>
