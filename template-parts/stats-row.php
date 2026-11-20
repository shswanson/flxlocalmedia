<?php
/**
 * Template Part: Stats Row
 *
 * Audience stats. Long-form sources (the page prints short labels only): U.S. Census Bureau American Community Survey 5-Year Estimates,
 * 2022 (Ontario, Cayuga, Seneca, Wayne, Yates, Schuyler, Steuben, Chemung, Livingston,
 * Tompkins: 697,705, table B01003; the 2020 Census count is 701,769),
 * GA4 BigQuery export for FLDN (Sep 2026). The newsletter figure is the subscriber count on the newsletter product page (inc/products.php), rounded down to the thousand.
 * Figures are rounded down so they hold for every month in Jun to Sep 2026.
 *
 * @package flxlm
 */
$flxlm_subs = function_exists( 'flxlm_newsletter_subscribers' ) ? flxlm_newsletter_subscribers() : 0;
?>
<section class="stats-row">
	<div class="container stats-row__inner">
		<div class="stats-row__item">
			<div class="stats-row__number">697K</div>
			<div class="stats-row__label">People across 10 Finger Lakes counties</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number">730K<span>+</span></div>
			<div class="stats-row__label">Monthly pageviews on Finger Lakes Daily News</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number">210K<span>+</span></div>
			<div class="stats-row__label">Monthly users on Finger Lakes Daily News</div>
		</div>
		<div class="stats-row__item">
			<div class="stats-row__number"><?php echo esc_html( number_format_i18n( (int) ( floor( $flxlm_subs / 1000 ) * 1000 ) ) ); ?><span>+</span></div>
			<div class="stats-row__label">Newsletter subscribers</div>
		</div>
	</div>
	<div class="container">
		<details class="methodology-link st-sources">
			<summary>Sources</summary>
			<ol>
				<li><b>697K</b> U.S. Census, 2022</li>
				<li><b>730K+</b> Google Analytics, Sep 2026</li>
				<li><b>210K+</b> Google Analytics, Sep 2026</li>
				<li><b>5,000+</b> Mailchimp, Nov 2026</li>
			</ol>
		</details>
	</div>
</section>
