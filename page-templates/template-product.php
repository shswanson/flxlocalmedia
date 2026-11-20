<?php
/**
 * Template Name: Product
 *
 * Generic product page in the station page design: bold colour hero, one bar of
 * numbers, who you reach, ways to advertise, more lists, one client story, a
 * floating table of contents and a single call to action.
 *
 * The page slug picks the product: /solutions/<slug>/ reads flxlm_get_product( '<slug>' )
 * from inc/products.php. A section with no data is skipped, never printed empty.
 * Layout and most styles are station.css (loaded for this template in functions.php);
 * only the new rules are in assets/css/site-bold.css.
 *
 * @package flxlm
 */

$page_slug = get_post_field( 'post_name', get_post() );
$p         = flxlm_get_product( $page_slug );

if ( ! $p ) {
	get_header();
	?>
	<div class="page-header">
		<div class="container">
			<h1 class="page-header__title">Page Not Found</h1>
			<p class="page-header__subtitle">We couldn't find this page. Please check the address and try again.</p>
		</div>
	</div>
	<?php
	get_footer();
	return;
}

$name = $p['name'];

// m:ss from seconds.
$flxlm_clock = function ( $seconds ) {
	$seconds = max( 0, (int) $seconds );
	return floor( $seconds / 60 ) . ':' . str_pad( (string) ( $seconds % 60 ), 2, '0', STR_PAD_LEFT );
};

// Which sections have something to show. Drives both the page and the table of contents.
$has_stats = ! empty( $p['stats'] );
$has_who   = ! empty( $p['who']['paragraphs'] ) || '' !== $p['who']['heading'];
$has_ways  = ! empty( $p['offers'] );
$has_proof = ! empty( $p['testimonial'] );

$toc = array();
if ( $has_who ) {
	$toc['who'] = 'Who you reach';
}
if ( $has_ways ) {
	$toc['ways'] = 'Ways to advertise';
}
foreach ( $p['extra'] as $i => $block ) {
	$toc[ 'extra-' . ( $i + 1 ) ] = $block['heading'];
}
if ( $has_proof ) {
	$toc['proof'] = 'From an advertiser';
}

// Sources for the bar of numbers: each stat's own source, then the note.
$sources = array();
foreach ( $p['stats'] as $stat ) {
	if ( '' !== $stat['source'] ) {
		$sources[] = ( '' !== $stat['label'] ? $stat['label'] . ': ' : '' ) . $stat['source'];
	}
}

get_header();
?>

<div class="flxlm-station flxlm-product" style="<?php echo esc_attr( $p['css_vars'] ); ?>">

	<!-- 1. Hero -->
	<section class="st-hero" aria-labelledby="st-title">
		<div class="st-wrap st-hero__grid<?php echo ( $p['hero_mark'] || $p['logo'] ) ? '' : ' st-hero__grid--solo'; ?>">
			<?php if ( $p['hero_mark'] ) : ?>
				<img class="st-hero__mark st-hero__mark--<?php echo esc_attr( $p['hero_mark_style'] ); ?>" src="<?php echo esc_url( $p['hero_mark'] ); ?>" alt="<?php echo esc_attr( $name . ' logo' ); ?>">
			<?php elseif ( $p['logo'] ) : ?>
				<img class="st-hero__logo" src="<?php echo esc_url( $p['logo'] ); ?>" alt="<?php echo esc_attr( $name . ' logo' ); ?>">
			<?php endif; ?>
			<div class="st-hero__text">
				<?php if ( '' !== $p['eyebrow'] ) : ?>
					<div class="st-eyebrow"><?php echo esc_html( $p['eyebrow'] ); ?></div>
				<?php endif; ?>
				<h1 class="st-title" id="st-title"><?php echo esc_html( $p['title'] ); ?></h1>
				<?php if ( '' !== $p['tagline'] ) : ?>
					<div class="st-tag"><?php echo esc_html( $p['tagline'] ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $p['lead'] ) : ?>
					<p class="st-lead st-lead--wide"><?php echo esc_html( $p['lead'] ); ?></p>
				<?php endif; ?>
				<div class="st-hero-cta">
					<a class="st-cta-btn st-cta-btn--link" href="<?php echo esc_url( $p['cta_url'] ); ?>">
						<span class="tx">
							<b><?php echo esc_html( $p['cta_label'] ); ?></b>
							<small>A plan, not a pitch</small>
						</span>
						<span class="ic" aria-hidden="true">&rarr;</span>
					</a>
				</div>
			</div>
		</div>
	</section>

	<?php if ( ! empty( $p['hero_logos'] ) ) : ?>
		<!-- 1b. Logo band: who we are on the air with. -->
		<section class="pr-band" aria-label="On the air with">
			<div class="st-wrap">
				<div class="pr-band__eyebrow">On the air with</div>
				<?php foreach ( array( false, true ) as $small_row ) : ?>
					<?php
					$row = array_filter(
						$p['hero_logos'],
						function ( $lg ) use ( $small_row ) {
							return $small_row === $lg['small'];
						}
					);
					?>
					<?php if ( $row ) : ?>
						<div class="pr-band__row<?php echo $small_row ? ' pr-band__row--small' : ''; ?>">
							<?php foreach ( $row as $lg ) : ?>
								<img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/products/logos/' . $lg['file'] ) ); ?>" alt="<?php echo esc_attr( $lg['alt'] ); ?>" loading="lazy" decoding="async">
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<div class="st-sheet">
		<div class="st-main">

			<?php if ( $has_stats ) : ?>
				<!-- 2. Bar of numbers: each number appears once, with its source. -->
				<div class="st-specs">
					<div class="st-specs__row st-specs__nums" style="--cols:<?php echo (int) count( $p['stats'] ); ?>">
						<?php foreach ( $p['stats'] as $stat ) : ?>
							<div><b><?php echo esc_html( $stat['value'] ); ?></b><?php if ( '' !== $stat['label'] ) : ?><span><?php echo esc_html( $stat['label'] ); ?></span><?php endif; ?></div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php if ( $sources || '' !== $p['stats_note'] ) : ?>
					<p class="st-specs-note">
						<?php if ( $sources ) : ?>
							<?php echo esc_html( 'Sources: ' . implode( '; ', $sources ) . '.' ); ?>
						<?php endif; ?>
						<?php echo '' !== $p['stats_note'] ? esc_html( $p['stats_note'] ) : ''; ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $has_who ) : ?>
				<!-- 3. Who you reach -->
				<section class="st-panel" id="who">
					<h2>Who you reach <span>The audience</span></h2>
					<div class="st-pad pr-who">
						<?php if ( '' !== $p['who']['heading'] ) : ?>
							<h3><?php echo esc_html( $p['who']['heading'] ); ?></h3>
						<?php endif; ?>
						<?php foreach ( $p['who']['paragraphs'] as $para ) : ?>
							<p><?php echo esc_html( $para ); ?></p>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $has_ways ) : ?>
				<!-- 4. Ways to advertise -->
				<section class="st-panel" id="ways">
					<h2>Ways to advertise <span>What you can buy</span></h2>
					<div class="pr-ways">
						<?php foreach ( $p['offers'] as $way ) : ?>
							<div class="pr-way">
								<?php if ( ! empty( $way['logos'] ) ) : ?>
									<div class="pr-logos">
										<?php foreach ( $way['logos'] as $lg ) : ?>
											<?php if ( isset( $lg['text'] ) ) : ?>
												<span class="pr-logo pr-logo--text"><?php echo esc_html( $lg['text'] ); ?></span>
											<?php else : ?>
												<span class="pr-logo<?php echo ! empty( $lg['chip'] ) ? ' pr-logo--chip' : ''; ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/products/logos/' . $lg['file'] ) ); ?>" alt="<?php echo esc_attr( $lg['alt'] ); ?>" loading="lazy" decoding="async"></span>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<?php echo flxlm_product_icon( $way['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built in inc/products.php. ?>
								<?php endif; ?>
								<h3><?php echo esc_html( $way['title'] ); ?></h3>
								<?php if ( '' !== $way['text'] ) : ?>
									<p><?php echo esc_html( $way['text'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php foreach ( $p['extra'] as $i => $block ) : ?>
				<!-- 5. More lists -->
				<section class="st-panel" id="<?php echo esc_attr( 'extra-' . ( $i + 1 ) ); ?>">
					<h2><?php echo esc_html( $block['heading'] ); ?></h2>
					<div class="st-pad">
						<ul class="pr-list">
							<?php foreach ( $block['items'] as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</section>
			<?php endforeach; ?>

			<?php if ( $has_proof ) : ?>
				<?php
				$t       = $p['testimonial'];
				$who     = trim( $t['person'] . ( '' !== $t['person'] && '' !== $t['business'] ? ', ' : '' ) . $t['business'] );
				$t_alt   = '' !== $t['person'] ? $t['person'] . ( '' !== $t['business'] ? ' of ' . $t['business'] : '' ) : $t['business'];
				$t_len   = $t['video_seconds'] ? $flxlm_clock( $t['video_seconds'] ) : '';
				$t_thumb = $t['thumb'] ? $t['thumb'] : '';
				?>
				<!-- 6. From an advertiser -->
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

		<!-- 7. Floating table of contents and the one call to action. Fixed to the bottom on phones. -->
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
				<a class="st-btn" href="<?php echo esc_url( $p['cta_url'] ); ?>"><?php echo esc_html( $p['cta_label'] ); ?></a>
				<p class="small">A plan, not a pitch.</p>
			</div>
		</aside>
	</div><!-- .st-sheet -->

</div><!-- .flxlm-station -->

<?php get_footer(); ?>
