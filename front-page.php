<?php
/**
 * Homepage template.
 *
 * Bold hero with one claim and the "Get a free plan" button, a strip of the
 * seven station tiles (live now playing), the newest client video, then the
 * audience numbers, the four problems we solve and more client stories.
 *
 * @package flxlm
 */

$stations = flxlm_hub_stations();

// The newest published client video (hidden testimonials never appear).
$newest    = flxlm_get_testimonials( array( 'posts_per_page' => 1 ) );
$newest_id = 0;
if ( $newest->have_posts() ) {
	$newest_id = (int) $newest->posts[0]->ID;
}

// More stories below: the next three, never the one already shown above.
$more_stories = flxlm_get_testimonials( array(
	'posts_per_page' => 3,
	'post__not_in'   => flxlm_exclude_hidden( $newest_id ? array( $newest_id ) : array() ),
) );

get_header();
?>

<div class="flxlm-hub flxlm-hub--home">

	<!-- 1. Hero -->
	<section class="hb-hero hb-hero--home" aria-labelledby="hb-title">
		<div class="hb-wrap hb-hero__inner">
			<p class="hb-eyebrow">Local Solutions for Local Businesses</p>
			<h1 class="hb-title" id="hb-title">Your customers are on the road across the Finger&nbsp;Lakes every day. <span>What are they hearing about&nbsp;you?</span></h1>
			<p class="hb-lead">Most local businesses advertise in one place and hope for the best. We put you where your customers already are: on 7 radio stations, across digital, at community events, and in the local news.</p>
			<div class="hb-actions">
				<a class="hb-btn hb-btn--primary" href="<?php echo esc_url( flxlm_hub_contact_url() ); ?>">Get a free plan</a>
				<a class="hb-btn hb-btn--ghost" href="<?php echo esc_url( home_url( '/solutions/' ) ); ?>">Our Solutions</a>
			</div>
		</div>
	</section>

	<?php if ( $stations ) : ?>
		<!-- 2. The seven stations -->
		<section class="hb-section hb-section--lift hb-strip-section" aria-label="Our seven radio stations">
			<div class="hb-wrap hb-wrap--wide">
				<div class="hb-strip">
					<?php foreach ( $stations as $t ) : ?>
						<?php flxlm_hub_station_tile( $t, true, false ); ?>
					<?php endforeach; ?>
				</div>
				<p class="hb-more hb-more--center"><a href="<?php echo esc_url( home_url( '/solutions/radio/' ) ); ?>">See all seven stations and where you can hear them <span aria-hidden="true">&rarr;</span></a></p>
			</div>
		</section>
	<?php endif; ?>

	<!-- 3. The newest client video -->
	<?php if ( $newest_id ) : ?>
		<?php
		$n_person   = (string) get_post_meta( $newest_id, 'person_name', true );
		$n_business = (string) get_post_meta( $newest_id, 'business_name', true );
		$n_quote    = trim( (string) get_post_meta( $newest_id, 'quote_short', true ) );
		$n_poster   = get_post_meta( $newest_id, 'poster_webp', true );
		$n_poster   = $n_poster ? $n_poster : get_post_meta( $newest_id, 'poster_jpg', true );
		$n_has_v    = '' !== flxlm_get_video_url( $newest_id );
		$n_secs     = $n_has_v ? flxlm_hub_video_seconds( $newest_id ) : 0;
		?>
		<section class="hb-section hb-feature" aria-labelledby="hb-feature-title">
			<div class="hb-wrap">
				<h2 class="sr-only" id="hb-feature-title">A client in their own words</h2>
				<div class="hb-feature__grid">
					<div class="hb-feature__media">
						<?php if ( $n_has_v ) : ?>
							<?php flxlm_video_facade( $newest_id, array( 'class' => 'hb-facade', 'len' => $n_secs ? flxlm_hub_clock( $n_secs ) : '' ) ); ?>
						<?php elseif ( $n_poster ) : ?>
							<a class="hb-feature__still" href="<?php echo esc_url( get_permalink( $newest_id ) ); ?>">
								<img src="<?php echo esc_url( $n_poster ); ?>" alt="<?php echo esc_attr( trim( $n_person . ( '' !== $n_business ? ' of ' . $n_business : '' ) ) ); ?>" width="960" height="540" loading="lazy">
							</a>
						<?php endif; ?>
					</div>
					<div class="hb-feature__text">
						<p class="hb-eyebrow hb-eyebrow--dark">In their own words</p>
						<?php if ( '' !== $n_quote ) : ?>
							<blockquote class="hb-feature__quote">&ldquo;<?php echo esc_html( $n_quote ); ?>&rdquo;</blockquote>
						<?php endif; ?>
						<p class="hb-feature__who">
							<?php if ( '' !== $n_person ) : ?><b><?php echo esc_html( $n_person ); ?></b><?php endif; ?>
							<?php if ( '' !== $n_business ) : ?><span><?php echo esc_html( $n_business ); ?></span><?php endif; ?>
						</p>
						<p class="hb-more"><a href="<?php echo esc_url( get_permalink( $newest_id ) ); ?>">Read the full story <span aria-hidden="true">&rarr;</span></a></p>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- 4. Audience numbers (each number once, with its source) -->
	<?php get_template_part( 'template-parts/stats-row' ); ?>

	<!-- 5. Problems we solve -->
	<section class="hb-section" aria-labelledby="hb-problems-title">
		<div class="hb-wrap">
			<div class="hb-head">
				<p class="hb-eyebrow hb-eyebrow--dark">Sound familiar?</p>
				<h2 id="hb-problems-title">The Challenges Local Businesses Tell Us About</h2>
				<p>If you've said any of these out loud, you're not alone, and we can help.</p>
			</div>

			<div class="hb-problems">
				<a href="<?php echo esc_url( home_url( '/solutions/radio/' ) ); ?>" class="hb-problem">
					<h3>"They're listening to the radio every day. But they're not hearing about me."</h3>
					<p>7 stations across the Finger Lakes. Country fans in the morning, rock listeners at lunch, news and talk on the drive home. We build your schedule across the stations that reach your customers.</p>
					<span class="hb-problem__tag">Radio Advertising</span>
					<span class="hb-problem__go">How radio works <span aria-hidden="true">&rarr;</span></span>
				</a>

				<a href="<?php echo esc_url( home_url( '/solutions/digital/' ) ); ?>" class="hb-problem">
					<h3>"People search for what I sell, but they find my competitor first."</h3>
					<p>SEO, Google Ads, social media, and a review link and QR card that make it easy for customers to review you. We handle the work so you show up when and where it matters.</p>
					<span class="hb-problem__tag">Digital Marketing</span>
					<span class="hb-problem__go">How digital works <span aria-hidden="true">&rarr;</span></span>
				</a>

				<a href="<?php echo esc_url( home_url( '/solutions/events/' ) ); ?>" class="hb-problem">
					<h3>"I sponsor things but never feel like it moves the needle."</h3>
					<p>Live remotes, community events, and sponsorships that put your brand in front of your local customers, with real follow-through, not just a logo on a banner.</p>
					<span class="hb-problem__tag">Event Marketing</span>
					<span class="hb-problem__go">How events work <span aria-hidden="true">&rarr;</span></span>
				</a>

				<a href="<?php echo esc_url( home_url( '/solutions/content-marketing/' ) ); ?>" class="hb-problem">
					<h3>"I know I need content but I don't have time to create it."</h3>
					<p>Sponsored articles and native content on Finger Lakes Daily News. We write it, publish it, and put it in front of the readers who matter.</p>
					<span class="hb-problem__tag">Content Marketing</span>
					<span class="hb-problem__go">How content works <span aria-hidden="true">&rarr;</span></span>
				</a>
			</div>
		</div>
	</section>

	<!-- 6. More client stories -->
	<?php if ( $more_stories->have_posts() ) : ?>
		<section class="hb-section hb-section--tint" aria-labelledby="hb-more-title">
			<div class="hb-wrap">
				<div class="hb-head">
					<p class="hb-eyebrow hb-eyebrow--dark">Local businesses, real results</p>
					<h2 id="hb-more-title">Your Neighbors Trust Us With Their Marketing</h2>
					<p>Auto dealers, restaurants, insurance agents, flooring companies: local businesses across the Finger Lakes.</p>
				</div>
				<div class="hb-vgrid hb-vgrid--three">
					<?php while ( $more_stories->have_posts() ) : $more_stories->the_post(); ?>
						<?php flxlm_hub_video_card(); ?>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
				<p class="hb-more hb-more--center"><a class="hb-btn hb-btn--solid" href="<?php echo esc_url( home_url( '/testimonials/' ) ); ?>">See All Client Stories</a></p>
			</div>
		</section>
	<?php endif; ?>

</div><!-- .flxlm-hub -->

<?php
get_footer();
