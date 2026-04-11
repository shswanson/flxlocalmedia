<?php
/**
 * Template Name: Methodology
 *
 * How we measure our audience — linked from station pages and Why Radio,
 * but not in the main navigation. For people who want to verify the math.
 *
 * @package flxlm
 */

get_header();
?>

<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">Our Methodology</h1>
		<p class="page-header__subtitle">How we measure our audience and market reach</p>
	</div>
</div>

<section class="section">
	<div class="container">
		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<p>Every audience number on this site is sourced, computed, and verifiable. The Finger Lakes is not a Nielsen-rated radio market, which means no station here has "official" listener counts. What we do have is better: <strong>real data from authoritative sources</strong>, combined transparently.</p>
			<p>Here is exactly where our numbers come from.</p>
		</div>
	</div>
</section>

<!-- Data Sources -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Data Sources</h2>
		</div>

		<div class="methodology-sources">
			<div class="methodology-source">
				<h3>US Census Bureau</h3>
				<p class="methodology-source__detail">American Community Survey, 5-Year Estimates (2022)</p>
				<p>County-level population, household income, homeownership rates, education, commute patterns. This is the gold standard for demographic data — used by every government agency, bank, and research institution in the country.</p>
			</div>
			<div class="methodology-source">
				<h3>Nielsen Audio Today</h3>
				<p class="methodology-source__detail">March 2026 (Q3 2025 measurement period)</p>
				<p>National radio reach (93% of adults monthly), format audience share data, in-car listening patterns, and ROI measurement. Nielsen is the industry standard for media measurement.</p>
			</div>
			<div class="methodology-source">
				<h3>Edison Research</h3>
				<p class="methodology-source__detail">Share of Ear, Q4 2025</p>
				<p>How Americans divide their audio time across platforms. Confirms AM/FM radio commands 61% of all ad-supported audio and 80%+ of in-car audio.</p>
			</div>
			<div class="methodology-source">
				<h3>FCC Broadcast Database</h3>
				<p class="methodology-source__detail">Licensed coverage contours, updated on license changes</p>
				<p>Official predicted coverage areas for every radio transmitter, based on power, antenna height, and terrain. We use these to define which counties each station serves.</p>
			</div>
			<div class="methodology-source">
				<h3>Google Analytics 4</h3>
				<p class="methodology-source__detail">First-party measurement, live</p>
				<p>Direct measurement of Finger Lakes Daily News website traffic — sessions, pageviews, geographic distribution, engagement. Not estimated. Not sampled. Measured.</p>
			</div>
			<div class="methodology-source">
				<h3>Mailchimp Analytics</h3>
				<p class="methodology-source__detail">Campaign-level tracking, live</p>
				<p>Newsletter subscriber counts, open rates, and click rates tracked at the individual email level. Our 49%+ open rate is measured, not estimated.</p>
			</div>
		</div>
	</div>
</section>

<!-- How We Estimate Radio Audience -->
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">How We Estimate Radio Audience</h2>
			<p class="section__subtitle">A five-layer model that starts with Census population data</p>
		</div>

		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<h3>Step 1: Define the Market</h3>
			<p>We use FCC-licensed coverage contours to determine which New York counties each station's signal reaches. Then we pull the Census population for those counties. This gives us the total addressable market — the number of people who <em>can</em> hear the station.</p>

			<h3>Step 2: Apply Radio Penetration</h3>
			<p>Nielsen reports that 93% of American adults listen to radio every month. We apply this rate to the adult population (18+) in each station's coverage area. This gives us the total number of radio listeners in the market.</p>

			<h3>Step 3: Allocate by Format</h3>
			<p>Nielsen publishes national audience share by format: Country holds 13.8% of all listening, News/Talk holds 12.3%, Adult Contemporary 7.5%, Classic Rock 8.1%. We apply these shares to each station based on its format, with adjustments for small-market dynamics (fewer stations means each one captures a larger share).</p>

			<h3>Step 4: Validate with Streaming Data</h3>
			<p>We measure streaming listeners directly — exact counts, every hour, every day. Industry research shows streaming represents 2-5% of total radio listening. We use our measured streaming data as a floor check: if the streaming-implied audience is higher than our format share estimate, we know we may be underestimating.</p>

			<h3>Step 5: Cross-Validate</h3>
			<p>The final estimate for each station is the highest defensible value across all layers. No station exceeds 30% of adults in its coverage area. Every number traces back to a credible source.</p>
		</div>
	</div>
</section>

<!-- Digital is Measured -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Digital Audience is Measured, Not Estimated</h2>
		</div>

		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<p>Our website and newsletter numbers are not estimates. They are direct measurements.</p>
			<p><strong>Finger Lakes Daily News</strong> traffic is measured by Google Analytics 4 using first-party data. Monthly users, pageviews, geographic distribution, and engagement — all directly observed, not modeled.</p>
			<p><strong>Newsletter</strong> performance is tracked by Mailchimp at the individual email level. When we say our open rate is 49%, that is the actual percentage of delivered emails that were opened, measured across every send.</p>
		</div>
	</div>
</section>

<!-- CTA -->
<section class="section">
	<div class="container" style="text-align: center;">
		<div class="post-content" style="max-width: 720px; margin: 0 auto;">
			<p>Questions about our methodology? We are happy to walk through the data in detail.</p>
			<p><a href="<?php echo home_url( '/contact/' ); ?>" class="btn btn--primary">Get in Touch</a></p>
		</div>
	</div>
</section>

<?php get_footer(); ?>
