<?php
/**
 * Template Name: Station
 *
 * Station product page ("option D"): bold hero, spec bar, listener portrait,
 * voices with a live on-air bar, coverage map, ways to advertise, one client
 * story, a floating table of contents and a single call to action.
 *
 * Everything comes from flxlm_station_page_data() (functions.php), which
 * reads the station's 'page' array in flxlm_get_station_data() (inc/helpers.php).
 * A section with no data is skipped, never printed empty.
 *
 * @package flxlm
 */

$page_slug    = get_post_field( 'post_name', get_post() );
$station_data = flxlm_get_station_data( $page_slug );
$p            = $station_data ? flxlm_station_page_data( $page_slug ) : null;

if ( ! $station_data || ! $p ) {
	get_header();
	?>
	<div class="page-header">
		<div class="container">
			<h1 class="page-header__title">Station Not Found</h1>
			<p class="page-header__subtitle">We couldn't find data for this station. Please check the URL and try again.</p>
		</div>
	</div>
	<?php
	get_footer();
	return;
}

$name = $p['name'];
$st   = $p['stats'];

// m:ss from seconds.
$flxlm_clock = function ( $seconds ) {
	$seconds = max( 0, (int) $seconds );
	return floor( $seconds / 60 ) . ':' . str_pad( (string) ( $seconds % 60 ), 2, '0', STR_PAD_LEFT );
};

// Which sections have something to show. Drives both the page and the table of contents.
$has_listener = ! empty( $p['persona'] );
$has_onair    = '' !== $p['stream_url'] || '' !== $p['nowplaying_slug'];
$has_voices   = ! empty( $p['voices'] ) || $has_onair;
$has_market   = '' !== $p['call_sign']; // The map needs only the call sign.
$has_ways     = ! empty( $p['ways'] );
$has_proof    = ! empty( $p['testimonial'] );

$toc = array();
if ( $has_listener ) {
	$toc['listener'] = 'Meet your listener';
}
if ( $has_voices ) {
	$toc['voices'] = ! empty( $p['voices'] ) ? 'Meet the voices' : 'On air';
}
if ( $has_market ) {
	$toc['market'] = 'The market';
}
if ( $has_ways ) {
	$toc['ways'] = 'Ways to advertise';
}
if ( $has_proof ) {
	$toc['proof'] = 'Client story';
}

// Spec bar.
$spec_nums = array();
if ( $st['weekly'] ) {
	$spec_nums[] = array( number_format( $st['weekly'] ), 'Est. weekly listeners' );
}
if ( $st['daily'] ) {
	$spec_nums[] = array( number_format( $st['daily'] ), 'Est. daily listeners' );
}
if ( $st['in_car'] ) {
	$spec_nums[] = array( number_format( $st['in_car'] ), 'Est. daily in the car' );
}
if ( $st['streaming_hours'] ) {
	$spec_nums[] = array( number_format( $st['streaming_hours'] ), 'Streaming hours' . ( '' !== $st['streaming_window'] ? ', ' . $st['streaming_window'] : '' ) );
}
$spec_meta = array();
if ( $p['counties'] ) {
	$spec_meta[] = array( implode( ', ', $p['counties'] ), count( $p['counties'] ) > 1 ? 'Counties covered' : 'County covered', true );
}
if ( '' !== $p['target'] ) {
	$spec_meta[] = array( $p['target'], 'Target audience', false );
}
$has_est       = $st['weekly'] || $st['daily'] || $st['in_car'];
$has_streaming = (bool) $st['streaming_hours'];

$contact_url = add_query_arg( 'station', $page_slug, home_url( '/contact/' ) );
$live_text   = preg_match( '/^[0-9.]+ (FM|AM)$/', $p['frequency_display'] ) ? $p['frequency_display'] : $name;

get_header();
?>

<div class="flxlm-station" style="<?php echo esc_attr( $p['css_vars'] ); ?>">

	<!-- 1. Hero -->
	<section class="st-hero" aria-labelledby="st-title">
		<div class="st-wrap st-hero__grid<?php echo $p['logo_white'] ? '' : ' st-hero__grid--solo'; ?>">
			<?php if ( $p['logo_white'] ) : ?>
				<img class="st-hero__logo" src="<?php echo esc_url( $p['logo_white'] ); ?>" alt="<?php echo esc_attr( $name . ' logo' ); ?>">
			<?php endif; ?>
			<div class="st-hero__text">
				<?php if ( '' !== $p['eyebrow'] ) : ?>
					<div class="st-eyebrow"><?php echo esc_html( $p['eyebrow'] ); ?></div>
				<?php endif; ?>
				<h1 class="st-fm<?php echo strlen( $p['frequency_display'] ) > 14 ? ' st-fm--long' : ''; ?>" id="st-title">
					<span class="sr-only"><?php echo esc_html( $name ); ?>, </span>
					<?php
					$freq_html = preg_replace( '/\b(FM|AM)\b/', '<span>$1</span>', esc_html( '' !== $p['frequency_display'] ? $p['frequency_display'] : $name ) );
					echo wp_kses( $freq_html, array( 'span' => array() ) );
					?>
				</h1>
				<?php if ( '' !== $p['frequency_also'] ) : ?>
					<div class="st-also"><?php echo esc_html( $p['frequency_also'] ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $p['format_label'] && $p['format_label'] !== $p['tagline'] ) : ?>
					<div class="st-format"><?php echo esc_html( $p['format_label'] ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $p['tagline'] ) : ?>
					<div class="st-tag"><?php echo esc_html( $p['tagline'] ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $p['lead'] ) : ?>
					<p class="st-lead"><?php echo esc_html( $p['lead'] ); ?></p>
				<?php endif; ?>

				<?php if ( $p['profile_audio'] ) : ?>
					<!-- Hero CTA: the station's profile clip. -->
					<div class="st-hero-cta">
						<button type="button" class="st-cta-btn" id="st-profile-play" data-profile-play aria-pressed="false">
							<span class="ic" aria-hidden="true">&#9654;</span>
							<span class="tx">
								<b><?php echo esc_html( 'Hear why ' . $name . ' works' ); ?></b>
								<small id="st-profile-time" data-profile-time><?php echo esc_html( $p['profile_seconds'] ? $p['profile_seconds'] . '-second station profile' : 'Station profile' ); ?></small>
							</span>
						</button>
						<audio id="st-profile-audio" preload="none" src="<?php echo esc_url( $p['profile_audio'] ); ?>"></audio>
					</div>
				<?php elseif ( $p['stream_url'] ) : ?>
					<!-- No profile clip for this station: the hero button plays the live stream. -->
					<div class="st-hero-cta">
						<button type="button" class="st-cta-btn" data-live-play aria-pressed="false">
							<span class="ic" aria-hidden="true">&#9654;</span>
							<span class="tx">
								<b>Listen live</b>
								<small><?php echo esc_html( 'Hear ' . $name . ' now' ); ?></small>
							</span>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<div class="st-sheet">
		<div class="st-main">

			<?php if ( $spec_nums || $spec_meta ) : ?>
				<!-- 2. Spec bar: every listener number lives here, once. -->
				<div class="st-specs">
					<?php if ( $spec_nums ) : ?>
						<div class="st-specs__row st-specs__nums" style="--cols:<?php echo (int) count( $spec_nums ); ?>">
							<?php foreach ( $spec_nums as $item ) : ?>
								<div><b><?php echo esc_html( $item[0] ); ?></b><span><?php echo esc_html( $item[1] ); ?></span></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $spec_meta ) : ?>
						<div class="st-specs__row st-specs__meta" style="--cols:<?php echo (int) count( $spec_meta ); ?>">
							<?php foreach ( $spec_meta as $item ) : ?>
								<div><b<?php echo $item[2] ? ' class="names"' : ''; ?>><?php echo esc_html( $item[0] ); ?></b><span><?php echo esc_html( $item[1] ); ?></span></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php if ( $has_est || $has_streaming ) : ?>
					<?php /* Sources kept in code only (Scott, 2026-11-20: no source link on the page). */ ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $has_listener ) : ?>
				<?php
				$pe       = $p['persona'];
				$initial  = '' !== $pe['name'] ? mb_strtoupper( mb_substr( $pe['name'], 0, 1 ) ) : '';
				$heading  = $pe['name'] . ( '' !== $pe['age'] ? ', ' . $pe['age'] : '' );
				$facts_n  = count( $pe['facts'] );
				?>
				<!-- 3. Meet your listener -->
				<section class="st-panel" id="listener">
					<h2>Meet your listener <span>Composite portrait</span></h2>
					<div class="st-pad st-listener<?php echo $pe['story'] || $facts_n ? '' : ' st-listener--solo'; ?>">
						<div class="st-portrait">
							<?php if ( $pe['photo'] ) : ?>
								<img class="st-avatar" src="<?php echo esc_url( $pe['photo'] ); ?>" width="140" height="140" loading="lazy" alt="<?php echo esc_attr( 'Illustrated composite of a typical ' . $name . ' listener' ); ?>">
							<?php elseif ( '' !== $initial ) : ?>
								<div class="st-avatar st-avatar--initial" aria-hidden="true"><?php echo esc_html( $initial ); ?></div>
							<?php endif; ?>
							<?php if ( '' !== trim( $heading ) ) : ?>
								<h3><?php echo esc_html( $heading ); ?></h3>
							<?php endif; ?>
							<?php if ( '' !== $pe['line1'] || '' !== $pe['line2'] ) : ?>
								<p>
									<?php echo esc_html( $pe['line1'] ); ?>
									<?php if ( '' !== $pe['line1'] && '' !== $pe['line2'] ) : ?><br><?php endif; ?>
									<?php echo esc_html( $pe['line2'] ); ?>
								</p>
							<?php endif; ?>
							<span class="st-label">Composite listener</span>
						</div>
						<?php if ( $pe['story'] || $facts_n ) : ?>
							<div class="st-story">
								<?php foreach ( $pe['story'] as $para ) : ?>
									<p><?php echo esc_html( $para ); ?></p>
								<?php endforeach; ?>
								<?php if ( $facts_n ) : ?>
									<div class="st-facts" style="--cols:<?php echo (int) min( 4, $facts_n ); ?>">
										<?php foreach ( $pe['facts'] as $fact ) : ?>
											<div class="st-fact"><b><?php echo esc_html( $fact[0] ); ?></b><span><?php echo esc_html( $fact[1] ); ?></span></div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $has_voices ) : ?>
				<?php $voice_cols = count( $p['voices'] ) <= 4 ? max( 1, count( $p['voices'] ) ) : 3; ?>
				<!-- 4. Meet the voices, with the live on-air bar -->
				<section class="st-panel" id="voices">
					<h2><?php echo esc_html( $toc['voices'] ); ?> <span><?php echo ! empty( $p['voices'] ) ? 'What hosts do for you' : 'Live now'; ?></span></h2>
					<?php if ( $has_onair ) : ?>
						<div class="st-onair-row">
							<div class="st-onair" id="st-onair">
								<span class="dot" aria-hidden="true"></span>
								<span class="np">
									<span class="tag-now">On air now</span>
									<span class="song" id="st-onair-song"><?php echo esc_html( 'Live now on ' . $live_text ); ?></span>
									<span class="artist" id="st-onair-artist"></span>
								</span>
								<?php if ( $p['stream_url'] ) : ?>
									<button type="button" class="listen" data-live-play aria-pressed="false">&#9654; Listen live</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $p['voices'] ) ) : ?>
						<div class="st-voices" style="--cols:<?php echo (int) $voice_cols; ?>">
							<?php foreach ( $p['voices'] as $v ) : ?>
								<?php
								$vi = '';
								foreach ( array_slice( preg_split( '/\s+/', preg_replace( '/[^\p{L}\s]/u', '', $v['name'] ) ), 0, 2 ) as $w ) {
									$vi .= '' !== $w ? mb_strtoupper( mb_substr( $w, 0, 1 ) ) : '';
								}
								?>
								<div class="st-voice<?php echo $v['syndicated'] ? ' st-voice--syn' : ''; ?>">
									<div class="ph">
										<?php if ( $v['photo'] ) : ?>
											<img src="<?php echo esc_url( $v['photo'] ); ?>" alt="<?php echo esc_attr( $v['photo_alt'] ); ?>" loading="lazy"<?php echo '' !== $v['photo_pos'] ? ' style="object-position:' . esc_attr( $v['photo_pos'] ) . '"' : ''; ?>>
										<?php else : ?>
											<span class="ph__initials" aria-hidden="true"><?php echo esc_html( $vi ); ?></span>
										<?php endif; ?>
									</div>
									<div class="tx">
										<h3><?php echo esc_html( $v['name'] ); ?></h3>
										<?php if ( '' !== $v['when'] ) : ?>
											<span class="when"><?php echo esc_html( $v['when'] ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $v['blurb'] ) : ?>
											<p><?php echo esc_html( $v['blurb'] ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $has_market ) : ?>
				<?php
				$mk        = $p['market'];
				$has_left  = '' !== $mk['population'] || $mk['towns'];
				$map_note  = '' !== $p['map_note'] ? $p['map_note'] : 'Shaded area: where ' . $name . ' can typically be heard on all of its signals combined. FM areas are modelled on real terrain to the 54 dBu level (60 dBu for some translators); AM areas use the FCC\'s daytime 0.5 mV/m contour. Reception varies with terrain, buildings and the radio.';
				?>
				<!-- 5. The market, with the coverage map -->
				<section class="st-panel st-market" id="market">
					<h2>The market <span>Coverage</span></h2>
					<div class="st-pad st-market__grid<?php echo $has_left ? '' : ' st-market__grid--solo'; ?>">
						<?php if ( $has_left ) : ?>
							<div>
								<?php if ( '' !== $mk['population'] ) : ?>
									<div class="pop"><?php echo esc_html( $mk['population'] ); ?></div>
									<?php if ( '' !== $mk['label'] ) : ?>
										<p class="pop-label"><?php echo esc_html( $mk['label'] ); ?></p>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( $mk['towns'] ) : ?>
									<h3>Towns</h3>
									<ul class="st-chips">
										<?php foreach ( $mk['towns'] as $town ) : ?>
											<li><?php echo esc_html( $town ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<div class="st-map-box">
							<div id="station-map" class="station-map" data-station-call="<?php echo esc_attr( $p['call_sign'] ); ?>" role="img" aria-label="<?php echo esc_attr( 'Coverage map for ' . $name ); ?>"></div>
							<p class="st-footnote"><?php echo esc_html( $map_note ); ?></p>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $has_ways ) : ?>
				<?php $way_cols = count( $p['ways'] ) <= 5 ? max( 1, count( $p['ways'] ) ) : 3; ?>
				<!-- 6. Ways to advertise -->
				<section class="st-panel" id="ways">
					<h2>Ways to advertise <span>What you can buy</span></h2>
					<div class="st-cards" style="--cols:<?php echo (int) $way_cols; ?>">
						<?php foreach ( $p['ways'] as $way ) : ?>
							<div class="st-way">
								<?php echo flxlm_station_way_icon( $way['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built in functions.php. ?>
								<h3><?php echo esc_html( $way['title'] ); ?></h3>
								<?php if ( '' !== $way['text'] ) : ?>
									<p><?php echo esc_html( $way['text'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $has_proof ) : ?>
				<?php
				$t       = $p['testimonial'];
				$who     = trim( $t['person'] . ( '' !== $t['person'] && '' !== $t['business'] ? ', ' : '' ) . $t['business'] );
				$t_alt   = '' !== $t['person'] ? $t['person'] . ( '' !== $t['business'] ? ' of ' . $t['business'] : '' ) : $t['business'];
				$t_len   = $t['video_seconds'] ? $flxlm_clock( $t['video_seconds'] ) : '';
				$t_thumb = $t['thumb'] ? $t['thumb'] : '';
				?>
				<!-- 7. From an advertiser -->
				<section class="st-panel st-proof" id="proof">
					<h2>From an advertiser <span>Client story</span></h2>
					<div class="st-pad">
						<div class="st-pq<?php echo $t_thumb ? '' : ' st-pq--solo'; ?>">
							<?php if ( $t_thumb ) : ?>
								<?php if ( $t['page_url'] ) : ?>
									<a class="st-pq-video" href="<?php echo esc_url( $t['page_url'] ); ?>" aria-label="<?php echo esc_attr( 'Watch the video' . ( '' !== $t_alt ? ': ' . $t_alt : '' ) ); ?>">
										<img src="<?php echo esc_url( $t_thumb ); ?>" alt="<?php echo esc_attr( $t_alt ); ?>" loading="lazy">
										<span class="st-pq-play" aria-hidden="true">&#9654;</span>
										<?php if ( $t_len ) : ?><span class="st-pq-len"><?php echo esc_html( $t_len ); ?></span><?php endif; ?>
									</a>
								<?php else : ?>
									<span class="st-pq-video">
										<img src="<?php echo esc_url( $t_thumb ); ?>" alt="<?php echo esc_attr( $t_alt ); ?>" loading="lazy">
									</span>
								<?php endif; ?>
							<?php endif; ?>
							<div class="st-pq-text">
								<blockquote class="st-pq-quote">&ldquo;<?php echo esc_html( $t['quote'] ); ?>&rdquo;</blockquote>
								<?php if ( '' !== $who ) : ?>
									<p class="st-pq-who">
										<?php if ( '' !== $t['person'] ) : ?>
											<b><?php echo esc_html( $t['person'] ); ?></b><?php echo '' !== $t['business'] ? esc_html( ', ' . $t['business'] ) : ''; ?>
										<?php else : ?>
											<?php echo esc_html( $t['business'] ); ?>
										<?php endif; ?>
									</p>
								<?php endif; ?>
								<?php if ( $t['page_url'] ) : ?>
									<a class="st-proof-link" href="<?php echo esc_url( $t['page_url'] ); ?>"><?php echo $t['video_seconds'] ? 'Watch the full video' : 'Read the full story'; ?></a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</section>
			<?php endif; ?>

		</div><!-- .st-main -->

		<!-- 8. Floating table of contents and the one call to action. Fixed to the bottom on phones. -->
		<aside class="st-aside" id="st-aside">
			<div class="st-aside-card">
				<h2><?php echo esc_html( $name ); ?></h2>
				<?php if ( $toc ) : ?>
					<p class="sub">Jump to a section</p>
					<nav aria-label="<?php echo esc_attr( $name . ' page sections' ); ?>">
						<?php foreach ( $toc as $anchor => $label ) : ?>
							<a href="#<?php echo esc_attr( $anchor ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
				<a class="st-btn" href="<?php echo esc_url( $contact_url ); ?>"><?php echo esc_html( 'Get a free plan for ' . $name ); ?></a>
				<p class="small">A plan, not a pitch.</p>
			</div>
		</aside>
	</div><!-- .st-sheet -->

</div><!-- .flxlm-station -->

<?php get_footer(); ?>
