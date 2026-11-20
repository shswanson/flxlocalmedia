<?php
/**
 * Template Name: How We Work
 *
 * "How we build your plan": the five steps from the first conversation to the
 * regular reviews, everything a plan can include (printed from the same menu
 * groups and station registry as /solutions/, so nothing about a product is
 * written here), a few client voices and the plain answers to the questions
 * people ask before they start. It sells the process; it has no prices.
 *
 * @package flxlm
 */

$groups = array();
foreach ( flxlm_nav_groups() as $g ) {
	$groups[ $g['key'] ] = $g;
}
$stations = flxlm_hub_stations();

$steps = array(
	array(
		'title' => 'The conversation',
		'text'  => 'We sit down with the owner or whoever makes the decisions. We ask about your business, your customers, your busy and slow seasons, and the one thing holding you back, in your words. It is a real meeting, not a pitch.',
		'gets'  => array( 'A person who listens before recommending anything', 'Your goal, written down the way you said it' ),
	),
	array(
		'title' => 'Research',
		'text'  => 'We look at your market, your competitors and how customers find you online: your Google listing, your website and your reviews. Then we check which of our audiences match your customers: the listeners of our seven stations, Finger Lakes Daily News readers, the newsletter and our events.',
		'gets'  => array( 'A clear picture of where you stand today', 'The audiences that actually fit your customers' ),
	),
	array(
		'title' => 'The plan',
		'text'  => 'You get a written plan with a mix across radio, digital, sponsorships and events, sized to your goal and your budget. It says what each piece is for and how we will measure it. Often there are two or three options at different budgets, so you choose.',
		'gets'  => array( 'What each piece is for, in plain English', 'Real numbers for each option', 'How we will measure each piece' ),
	),
	array(
		'title' => 'Launch',
		'text'  => 'We write the copy, produce the voice work, build the schedule and set up tracking where we can, such as tracking links and phone numbers, so you can see what is working.',
		'gets'  => array( 'Copywriting and voice production', 'A schedule you can see', 'Tracking set up wherever possible' ),
	),
	array(
		'title' => 'Review',
		'text'  => 'We check in regularly with real numbers: spots aired, clicks, calls and newsletter clicks. We talk about what is working and what is not, and we adjust the plan.',
		'gets'  => array( 'Regular check-ins with real numbers', 'Changes to the plan when something is not working' ),
	),
);

$faqs = array(
	array( 'Do you have a rate card?', 'We have one for our own reference, but prices depend on the stations, the time of year, the length of the campaign and the mix. We would rather show you a plan with real numbers than hand you a price list.' ),
	array( 'What is the smallest budget you work with?', 'We will tell you honestly whether your budget is enough to work. If it is not enough for one thing, we will say so and suggest what could be.' ),
	array( 'How long until I see results?', 'It depends on what the plan is for. Some pieces, like a promotion or an event, show up quickly. Others build over time. The plan says what to expect from each piece, and our regular reviews show you what is happening.' ),
	array( 'Can I change the plan?', 'Yes. The review step exists so we can look at the numbers together and adjust. We will go through how changes work with you in the plan.' ),
	array( 'Do I have to sign a long contract?', 'We will go through the terms with you in the plan, so you see them before you decide anything.' ),
);

// Client voices on the relationship and the plan: found by business name, so a reordered list cannot swap them.
$voices = array();
foreach ( flxlm_get_testimonials( array( 'posts_per_page' => -1 ) )->posts as $t ) {
	$b = (string) get_post_meta( $t->ID, 'business_name', true );
	foreach ( array( 'E & V Energy', 'Main Deck', 'Speno Music' ) as $want ) {
		if ( 0 === strpos( $b, $want ) ) {
			$voices[ $want ] = $t;
		}
	}
}

flxlm_pg_begin( array(
	'accent'  => 'navy',
	'eyebrow' => 'How we work',
	'title'   => "We don't hand you a rate card. We build a plan.",
	'lead'    => 'We start with your business, not our inventory.',
) );
get_header();
flxlm_pg_open();
?>

<section class="section sol-sec" aria-labelledby="hw-steps">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="section__eyebrow">Five steps</span>
			<h2 class="section__title" id="hw-steps">From first conversation to regular check-ins</h2>
			<p class="sol-head__text">Every plan starts the same way and ends up different, because every business is.</p>
		</div>
		<ol class="hw-steps">
			<?php foreach ( $steps as $i => $s ) : ?>
				<li class="hw-step">
					<span class="hw-step__num" aria-hidden="true"><?php echo (int) ( $i + 1 ); ?></span>
					<div class="hw-step__body">
						<h3 class="hw-step__title"><?php echo esc_html( $s['title'] ); ?></h3>
						<p><?php echo esc_html( $s['text'] ); ?></p>
						<p class="hw-step__label">What you get</p>
						<ul class="hw-step__gets">
							<?php foreach ( $s['gets'] as $g ) : ?>
								<li><?php echo esc_html( $g ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>

<section class="section section--gray sol-sec" aria-labelledby="hw-all">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="section__eyebrow">The toolbox</span>
			<h2 class="section__title" id="hw-all">Everything we can put in a plan</h2>
			<p class="sol-head__text">We pick from all of it, and only what fits your goal.</p>
		</div>
		<?php if ( $stations ) : ?>
			<div class="flxlm-hub sol-hub hw-stations">
				<div class="hb-strip">
					<?php foreach ( $stations as $t ) : ?>
						<?php flxlm_hub_station_tile( $t, true, false ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<div class="hw-groups">
			<?php foreach ( array( 'media', 'advertising', 'digital' ) as $key ) : ?>
				<?php
				if ( empty( $groups[ $key ]['items'] ) ) {
					continue;
				}
				?>
				<div class="hw-group hw-group--<?php echo esc_attr( $key ); ?>">
					<h3 class="hw-group__title"><?php echo esc_html( $groups[ $key ]['label'] ); ?></h3>
					<ul>
						<?php foreach ( $groups[ $key ]['items'] as $item ) : ?>
							<li>
								<a href="<?php echo esc_url( flxlm_nav_url( $item ) ); ?>">
									<b><?php echo esc_html( $item['label'] ); ?></b>
									<?php if ( ! empty( $item['blurb'] ) ) : ?><span><?php echo esc_html( $item['blurb'] ); ?></span><?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php if ( $voices ) : ?>
<section class="section sol-sec" aria-labelledby="hw-proof">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="section__eyebrow">In their words</span>
			<h2 class="section__title" id="hw-proof">What working with us looks like</h2>
		</div>
		<div class="testimonial-grid">
			<?php foreach ( $voices as $t ) : ?>
				<?php
				$GLOBALS['post'] = $t; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $t );
				get_template_part( 'template-parts/testimonial-card' );
				?>
			<?php endforeach; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section--gray sol-sec" aria-labelledby="hw-faq">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="section__eyebrow">Straight answers</span>
			<h2 class="section__title" id="hw-faq">Questions people ask first</h2>
		</div>
		<div class="hw-faq">
			<?php foreach ( $faqs as $f ) : ?>
				<details>
					<summary><?php echo esc_html( $f[0] ); ?></summary>
					<p><?php echo esc_html( $f[1] ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php
flxlm_pg_close();
get_footer();
