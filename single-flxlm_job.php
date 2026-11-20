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

$flxlm_job_id    = get_queried_object_id();
$job_location         = get_post_meta( $flxlm_job_id, 'job_location', true );
$job_type             = get_post_meta( $flxlm_job_id, 'job_type', true );
$job_email            = get_post_meta( $flxlm_job_id, 'job_email', true );
$job_responsibilities = get_post_meta( $flxlm_job_id, 'job_responsibilities', true );
$job_qualifications   = get_post_meta( $flxlm_job_id, 'job_qualifications', true );
$job_offer            = get_post_meta( $flxlm_job_id, 'job_offer', true );

// Location, type and pay range, joined with dots, for the line under the title.
$flxlm_job_bits = array();
if ( $job_location ) {
	$flxlm_job_bits[] = '<span class="job-meta__item">' . esc_html( $job_location ) . '</span>';
}
if ( $job_type ) {
	$flxlm_job_bits[] = '<span class="job-meta__item">' . esc_html( $job_type ) . '</span>';
}
/*
 * The pay range. New York Labor Law 194-b requires a good faith
 * minimum and maximum on any posting for work performed in the
 * state, and this site rendered no figure at all until now.
 */
$flxlm_salary = function_exists( 'flxlm_careers_salary_display' )
	? flxlm_careers_salary_display( $flxlm_job_id )
	: '';
if ( ( $job_location || $job_type ) && $flxlm_salary ) {
	$flxlm_job_bits[] = '<span class="job-meta__item job-meta__item--salary">' . esc_html( $flxlm_salary ) . '</span>';
}
$flxlm_job_lead = implode( ' <span class="job-meta__sep" aria-hidden="true">&middot;</span> ', $flxlm_job_bits );

/*
 * Apply above the fold.
 *
 * Most people who read a job posting decide within the first screenful,
 * and the old page gave them nothing to act on until they had scrolled
 * the entire description. This anchors down to the form rather than
 * duplicating it, so there is still only one form on the page.
 */
$flxlm_job_apply = function_exists( 'flxlm_ats_render_form' )
	? '<a class="flx-btn" href="#apply">Apply for this job</a>'
	: '';

flxlm_pg_begin( array(
	'accent'         => 'navy',
	'eyebrow'        => 'Careers',
	'eyebrow_url'    => home_url( '/careers/' ),
	'title'          => get_the_title( $flxlm_job_id ),
	'lead_html'      => $flxlm_job_lead,
	'hero_extra'     => $flxlm_job_apply,
	'cta'            => false,
	'band'           => false,
	'no_footer_band' => true,
) );
get_header();
flxlm_pg_open();

while ( have_posts() ) : the_post();
?>

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

			<?php
			/*
			 * What We Offer is composed, not typed. The posting supplies the
			 * role specific lines; the standard employee benefits come from one
			 * shared definition so a benefit changed once is changed everywhere.
			 * A posting that retyped them from memory went live on 2026-08-25
			 * promising roughly 50% more vacation than the company offers.
			 */
			$flxlm_standard = ( function_exists( 'flxlm_careers_standard_benefits_items' )
				&& function_exists( 'flxlm_careers_is_benefits_eligible' )
				&& flxlm_careers_is_benefits_eligible( get_the_ID() ) )
					? flxlm_careers_standard_benefits_items()
					: '';
			if ( $job_offer || $flxlm_standard ) :
				?>
				<div class="job-section">
					<h2>What We Offer</h2>
					<ul>
						<?php
						echo wp_kses_post( flxlm_careers_offer_items( $job_offer ) );
						echo wp_kses_post( $flxlm_standard );
						?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( function_exists( 'flxlm_careers_about_html' ) ) : ?>
				<div class="job-section job-section--about">
					<h2>About FLX Local Media</h2>
					<?php echo wp_kses_post( flxlm_careers_about_html() ); ?>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * How to apply.
			 *
			 * The ATS plugin owns this when it is active: an on-page form that
			 * captures the application, stores the resume privately, and records
			 * the recruitment source the FCC EEO report is built from.
			 *
			 * The mailto: fallback below is kept deliberately. If the plugin is
			 * ever deactivated, mid-migration or otherwise, a job posting must
			 * still tell a candidate how to reach us. A careers page that
			 * silently offers no way to apply is worse than an old-fashioned one.
			 */
			if ( function_exists( 'flxlm_ats_render_form' ) ) :
				echo flxlm_ats_render_form( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			elseif ( $job_email ) :
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

			<?php
			/*
			 * Email-in alternative + referral ask.
			 *
			 * Lives in the template, not per-post copy, so every job posting
			 * (present and future) carries it automatically. jobs@flxlocalmedia.com
			 * is a Google Group Dennie/Frank already read; emailed resumes still get
			 * hand-entered into the ATS today (fldn#1994).
			 */
			?>
			<div class="job-section job-section--email-referral">
				<p>Prefer email? Send your resume to <a href="mailto:jobs@flxlocalmedia.com">jobs@flxlocalmedia.com</a>.</p>
				<p>Know someone who would be a great fit? Send them this posting.</p>
			</div>

			<div class="job-section job-section--eeo">
				<p><em>FLX Local Media is an equal opportunity employer. All qualified applicants will receive consideration without regard to race, color, religion, sex, sexual orientation, gender identity, national origin, disability, veteran status, or any other protected characteristic.</em></p>
			</div>

		</div>
	</div>
</section>

<?php endwhile; ?>

<?php
flxlm_pg_close();
get_footer();

