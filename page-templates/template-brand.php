<?php
/**
 * Template Name: Brand & Organizational Architecture
 *
 * Reference document explaining how FLX Local Media is organized.
 * Three views: consumer brands, B2B sales taxonomy, internal teams.
 *
 * @package flxlm
 */

get_header();
?>

<!-- Hero -->
<div class="page-header">
	<div class="container">
		<h1 class="page-header__title">One region.<br>One newsroom.<br>Many voices.</h1>
		<p class="page-header__subtitle">A reference document for advertisers, partners, journalism organizations, and internal teams &mdash; explaining how FLX Local Media is organized.</p>
	</div>
</div>

<!-- The Portfolio -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow">The Portfolio</span>
			<h2 class="section__title">Eleven consumer brands across radio, digital, and email.</h2>
		</div>

		<div class="brand-grid">
			<article class="brand-card brand-card--umbrella">
				<span class="brand-card__tag">Group / Streaming</span>
				<h3 class="brand-card__name">flx.fm</h3>
				<p class="brand-card__meta">All 7 stations &middot; cross-promotion &middot; streaming</p>
			</article>

			<article class="brand-card">
				<span class="brand-card__tag">Adult Contemporary</span>
				<h3 class="brand-card__name">MIX 98.5</h3>
				<p class="brand-card__meta">WNYR &middot; Geneva</p>
			</article>

			<article class="brand-card">
				<span class="brand-card__tag">News / Talk</span>
				<h3 class="brand-card__name">WGVA</h3>
				<p class="brand-card__meta">Geneva</p>
			</article>

			<article class="brand-card">
				<span class="brand-card__tag">News / Talk</span>
				<h3 class="brand-card__name">WAUB</h3>
				<p class="brand-card__meta">Auburn</p>
			</article>

			<article class="brand-card">
				<span class="brand-card__tag">Country</span>
				<h3 class="brand-card__name">WFLR Country</h3>
				<p class="brand-card__meta">Penn Yan</p>
			</article>

			<article class="brand-card">
				<span class="brand-card__tag">Programming</span>
				<h3 class="brand-card__name">WCGR</h3>
				<p class="brand-card__meta">&mdash;</p>
			</article>

			<article class="brand-card">
				<span class="brand-card__tag">Classic Hits</span>
				<h3 class="brand-card__name">Classic Hits 99.3</h3>
				<p class="brand-card__meta">WFLK &middot; Auburn</p>
			</article>

			<article class="brand-card">
				<span class="brand-card__tag">Classic Rock</span>
				<h3 class="brand-card__name">101.7 The Wall</h3>
				<p class="brand-card__meta">WLLW &middot; Geneva</p>
			</article>

			<article class="brand-card brand-card--digital">
				<span class="brand-card__tag">Digital News</span>
				<h3 class="brand-card__name">Finger Lakes Daily News</h3>
				<p class="brand-card__meta">fingerlakesdailynews.com</p>
			</article>

			<article class="brand-card brand-card--digital">
				<span class="brand-card__tag">Email</span>
				<h3 class="brand-card__name">FLX Newsletter</h3>
				<p class="brand-card__meta">Daily &middot; weekly</p>
			</article>

			<article class="brand-card brand-card--digital">
				<span class="brand-card__tag">Discovery</span>
				<h3 class="brand-card__name">DiscoverFLX</h3>
				<p class="brand-card__meta">Tourism &middot; in development</p>
			</article>
		</div>
	</div>
</section>

<!-- The Cohesion Story -->
<section class="section">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow section__eyebrow--lake">The Cohesion Story</span>
			<h2 class="section__title">Content moves across channels.</h2>
			<p class="section__subtitle">The brands above aren't silos &mdash; they're distribution channels. The same piece of content moves across multiple channels. This is the matrix.</p>
		</div>

		<div class="content-matrix-wrap">
			<table class="content-matrix">
				<thead>
					<tr>
						<th class="content-matrix__corner" scope="col"><span class="content-matrix__corner-label">Content &times; Channel</span></th>
						<th scope="col">Broadcast</th>
						<th scope="col">Streaming</th>
						<th scope="col">Web</th>
						<th scope="col">Email</th>
						<th scope="col">Social</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th scope="row">News</th>
						<td class="content-matrix__cell--filled">News Radio</td>
						<td class="content-matrix__cell--filled">flx.fm</td>
						<td class="content-matrix__cell--filled">FLDN</td>
						<td class="content-matrix__cell--filled">Newsletter</td>
						<td class="content-matrix__cell--filled">All</td>
					</tr>
					<tr>
						<th scope="row">Music &amp; Entertainment</th>
						<td class="content-matrix__cell--filled">7 Stations</td>
						<td class="content-matrix__cell--filled">flx.fm</td>
						<td class="content-matrix__cell--empty">&mdash;</td>
						<td class="content-matrix__cell--empty">&mdash;</td>
						<td class="content-matrix__cell--filled">Stations</td>
					</tr>
					<tr>
						<th scope="row">Sports</th>
						<td class="content-matrix__cell--filled">News Radio</td>
						<td class="content-matrix__cell--filled">flx.fm</td>
						<td class="content-matrix__cell--filled">FLDN Sports</td>
						<td class="content-matrix__cell--empty">&mdash;</td>
						<td class="content-matrix__cell--filled">Stations</td>
					</tr>
					<tr>
						<th scope="row">Weather</th>
						<td class="content-matrix__cell--filled">All Stations</td>
						<td class="content-matrix__cell--filled">flx.fm</td>
						<td class="content-matrix__cell--filled">FLDN Weather</td>
						<td class="content-matrix__cell--filled">Newsletter</td>
						<td class="content-matrix__cell--filled">Alerts</td>
					</tr>
				</tbody>
			</table>
		</div>

		<aside class="story-callout">
			<span class="story-callout__label">A Day in the Newsroom</span>
			<h3 class="story-callout__title">A house fire on Castle Street happens at 2:30 PM.</h3>
			<p class="story-callout__lede">One reporter covers the story. One piece of work. Five touchpoints across the matrix.</p>
			<div class="story-callout__trace">
				<span class="story-callout__item">News Radio top-of-hour</span>
				<span class="story-callout__item">flx.fm stream</span>
				<span class="story-callout__item">FLDN article</span>
				<span class="story-callout__item">Newsletter blurb</span>
				<span class="story-callout__item">Station socials</span>
			</div>
		</aside>
	</div>
</section>

<!-- Three Views -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow section__eyebrow--sage">Three Views, One Company</span>
			<h2 class="section__title">Each audience sees a different lens.</h2>
			<p class="section__subtitle">Consumers see brands. Advertisers see product lines. Internal teams see roles. Same organization, three optimizations.</p>
		</div>

		<div class="views-grid">
			<article class="view-card">
				<span class="view-card__num">01</span>
				<span class="view-card__audience">For Audiences</span>
				<h3 class="view-card__title">Consumer-Facing Brands</h3>
				<p class="view-card__lede">What listeners and readers encounter:</p>
				<ul class="view-card__list">
					<li>Seven independent radio station identities</li>
					<li>Finger Lakes Daily News (digital)</li>
					<li>flx.fm streaming wrapper</li>
					<li>FLX Newsletter (email)</li>
					<li>Cross-channel: News Radio, Weather Center, FLX Sports</li>
				</ul>
			</article>

			<article class="view-card">
				<span class="view-card__num">02</span>
				<span class="view-card__audience">For Advertisers</span>
				<h3 class="view-card__title">B2B Sales Taxonomy</h3>
				<p class="view-card__lede">The product lines on rate cards and proposals:</p>
				<ul class="view-card__list">
					<li><strong>Broadcast</strong> &mdash; 7 stations + streaming</li>
					<li><strong>Digital O&amp;O</strong> &mdash; FLDN, newsletter, DiscoverFLX</li>
					<li><strong>Services &mdash; Paid Media</strong> &mdash; SEM, social, geofencing</li>
					<li><strong>Services &mdash; Professional</strong> &mdash; SEO, web, hosting</li>
				</ul>
			</article>

			<article class="view-card">
				<span class="view-card__num">03</span>
				<span class="view-card__audience">For Internal &amp; Partners</span>
				<h3 class="view-card__title">Functional Teams</h3>
				<p class="view-card__lede">Who actually does the work:</p>
				<ul class="view-card__list">
					<li><strong>Newsroom</strong> &mdash; editorial production</li>
					<li><strong>Programming</strong> &mdash; broadcast formats &amp; on-air talent</li>
					<li><strong>Sales</strong> &mdash; East &amp; West region account teams</li>
					<li><strong>Services</strong> &mdash; managed digital products</li>
					<li><strong>Operations</strong> &mdash; finance, HR, traffic, scheduling</li>
				</ul>
			</article>
		</div>
	</div>
</section>

<!-- Style Guide -->
<section class="section">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow section__eyebrow--terracotta">Style Guide</span>
			<h2 class="section__title">The brand in the wild.</h2>
			<p class="section__subtitle">How "FLX Local Media" appears across the surfaces audiences and partners encounter.</p>
		</div>

		<div class="mockups-grid">

			<figure class="mockup mockup--byline">
				<figcaption class="mockup__label">Digital Article Byline</figcaption>
				<div class="mockup__frame">
					<span class="mockup__tag">News</span>
					<h4 class="mockup__headline">Geneva firefighters respond to overnight Castle Street blaze</h4>
					<div class="mockup__byline-rule"></div>
					<p class="mockup__byline-text">By <strong>Kalysta Donaghy-Robinson</strong><br>
					<span class="mockup__byline-meta">FLX Local Media Newsroom &middot; April 26, 2026</span></p>
				</div>
			</figure>

			<figure class="mockup mockup--brand-bar">
				<figcaption class="mockup__label">Footer / Brand Bar</figcaption>
				<div class="mockup__frame mockup__frame--dark">
					<span class="mockup__lockup">
						<strong class="mockup__lockup-master">FLX Local Media</strong>
						<span class="mockup__lockup-sep">|</span>
						<span class="mockup__lockup-division">Newsroom</span>
					</span>
				</div>
				<div class="mockup__frame mockup__frame--dark">
					<span class="mockup__lockup">
						<strong class="mockup__lockup-master">FLX Local Media</strong>
						<span class="mockup__lockup-sep">|</span>
						<span class="mockup__lockup-division">Digital</span>
					</span>
				</div>
				<div class="mockup__frame mockup__frame--dark">
					<span class="mockup__lockup">
						<strong class="mockup__lockup-master">FLX Local Media</strong>
						<span class="mockup__lockup-sep">|</span>
						<span class="mockup__lockup-division">Broadcast</span>
					</span>
				</div>
			</figure>

			<figure class="mockup mockup--email">
				<figcaption class="mockup__label">Email Signature</figcaption>
				<div class="mockup__frame">
					<p class="mockup__email-greeting">Best,</p>
					<p class="mockup__email-name"><strong>Lucas Day</strong></p>
					<p class="mockup__email-role">News Director</p>
					<p class="mockup__email-division">FLX Local Media <span class="mockup__email-pipe">|</span> Newsroom</p>
					<div class="mockup__email-rule"></div>
					<p class="mockup__email-contact">
						<a href="#" class="mockup__email-link">lucas@flxlocalmedia.com</a><br>
						<span class="mockup__email-meta">flxlocalmedia.com &middot; (315) 555&middot;0100</span>
					</p>
				</div>
			</figure>

			<figure class="mockup mockup--onair">
				<figcaption class="mockup__label">On-Air Script / Sweeper</figcaption>
				<div class="mockup__frame mockup__frame--script">
					<span class="mockup__script-cue">7:00 AM &middot; SWEEPER</span>
					<p class="mockup__script-line">&ldquo;An FLX Local Media report.&rdquo;</p>
					<p class="mockup__script-line">&ldquo;Heard on Finger Lakes News Radio.&rdquo;</p>
					<span class="mockup__script-end">&mdash;END&mdash;</span>
				</div>
			</figure>

		</div>
	</div>
</section>

<!-- Corporate Structure -->
<section class="section">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow section__eyebrow--navy">Corporate Structure</span>
			<h2 class="section__title">The formal record.</h2>
		</div>

		<div class="entity-table">
			<div class="entity-table__row">
				<div class="entity-table__name">FLX Local Media</div>
				<div class="entity-table__purpose">Operating company. Owns and operates the seven radio stations and Finger Lakes Daily News.</div>
			</div>
			<div class="entity-table__row">
				<div class="entity-table__name">FLX Local Media Real Estate LLC</div>
				<div class="entity-table__purpose">Real estate holding entity.</div>
			</div>
			<div class="entity-table__row">
				<div class="entity-table__name">TOTIB Technologies Inc.</div>
				<div class="entity-table__purpose">Separate Delaware C-Corp. Owns the SMB website management platform; licenses it to FLX Local Media as the first operator.</div>
			</div>
		</div>
	</div>
</section>

<!-- Document Scope -->
<section class="section">
	<div class="container">
		<aside class="doc-scope">
			<h3 class="doc-scope__title">Document scope</h3>
			<p><strong>This is not consumer marketing.</strong> Audiences should be directed to the consumer brands directly. This page is for:</p>
			<ul class="doc-scope__list">
				<li>Advertisers and agencies evaluating FLX Local Media as a media partner</li>
				<li>Journalism partners and syndication services</li>
				<li>Vendor and partner organizations</li>
				<li>Internal hiring, onboarding, and operational reference</li>
			</ul>
			<p class="doc-scope__meta">Last updated <?php echo esc_html( get_the_modified_date( 'F j, Y' ) ); ?> &middot; Questions or corrections: <a href="mailto:scott@totib.com">scott@totib.com</a></p>
		</aside>
	</div>
</section>

<?php
get_footer();
