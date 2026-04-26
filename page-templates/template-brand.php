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

			<article class="brand-card brand-card--featured">
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
			<span class="section__eyebrow">The Cohesion Story</span>
			<h2 class="section__title">How content flows from teams to audiences.</h2>
			<p class="section__subtitle">The brands above are <strong>distribution channels, not silos.</strong> Two production teams &mdash; one editorial, one entertainment &mdash; create content that flows across all of them.</p>
		</div>

		<div class="flow-diagram">
			<div class="flow-diagram__row">
				<div class="flow-diagram__team">
					<span class="flow-diagram__team-label">Team</span>
					<span class="flow-diagram__team-name">Newsroom</span>
				</div>
				<div class="flow-diagram__arrow" aria-hidden="true">&rarr;</div>
				<div class="flow-diagram__channels">
					<span class="flow-diagram__pill">Finger Lakes Daily News<span class="flow-diagram__pill-type">digital</span></span>
					<span class="flow-diagram__pill">Finger Lakes News Radio<span class="flow-diagram__pill-type">broadcast</span></span>
					<span class="flow-diagram__pill">FLX Newsletter<span class="flow-diagram__pill-type">email</span></span>
					<span class="flow-diagram__pill">Weather page<span class="flow-diagram__pill-type">digital</span></span>
					<span class="flow-diagram__pill">Social<span class="flow-diagram__pill-type">all</span></span>
				</div>
			</div>

			<div class="flow-diagram__row">
				<div class="flow-diagram__team">
					<span class="flow-diagram__team-label">Team</span>
					<span class="flow-diagram__team-name">Programming</span>
				</div>
				<div class="flow-diagram__arrow" aria-hidden="true">&rarr;</div>
				<div class="flow-diagram__channels">
					<span class="flow-diagram__pill">7 Broadcast Stations<span class="flow-diagram__pill-type">terrestrial</span></span>
					<span class="flow-diagram__pill">flx.fm<span class="flow-diagram__pill-type">streaming</span></span>
					<span class="flow-diagram__pill">Station Apps &amp; Sites<span class="flow-diagram__pill-type">digital</span></span>
					<span class="flow-diagram__pill">Station Socials<span class="flow-diagram__pill-type">social</span></span>
				</div>
			</div>

			<div class="flow-diagram__row">
				<div class="flow-diagram__team flow-diagram__team--cross">
					<span class="flow-diagram__team-label">Cross-Functional</span>
					<span class="flow-diagram__team-name">FLX Sports</span>
				</div>
				<div class="flow-diagram__arrow" aria-hidden="true">&rarr;</div>
				<div class="flow-diagram__channels">
					<span class="flow-diagram__pill">Broadcast Play-by-Play<span class="flow-diagram__pill-type">terrestrial</span></span>
					<span class="flow-diagram__pill">Streaming<span class="flow-diagram__pill-type">audio</span></span>
					<span class="flow-diagram__pill">FLDN Sports Section<span class="flow-diagram__pill-type">digital</span></span>
				</div>
			</div>
		</div>

		<aside class="story-callout">
			<span class="story-callout__label">A Day in the Newsroom</span>
			<h3 class="story-callout__title">A house fire on Castle Street happens at 2:30 PM.</h3>
			<p class="story-callout__lede">One reporter covers the story. One piece of work. Multiple touchpoints.</p>
			<div class="story-callout__trace">
				<span class="story-callout__item">Newsroom <span class="story-callout__arrow">&rarr;</span> FLDN article</span>
				<span class="story-callout__item">Newsroom <span class="story-callout__arrow">&rarr;</span> News Radio top-of-hour</span>
				<span class="story-callout__item">Newsroom <span class="story-callout__arrow">&rarr;</span> Newsletter blurb</span>
				<span class="story-callout__item">Newsroom <span class="story-callout__arrow">&rarr;</span> Social posts</span>
			</div>
		</aside>
	</div>
</section>

<!-- Three Views -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow">Three Views, One Company</span>
			<h2 class="section__title">Each audience sees a different lens.</h2>
			<p class="section__subtitle">Consumers see brands. Advertisers see product lines. Internal teams see roles. Same organization, three optimizations.</p>
		</div>

		<div class="views-grid">
			<article class="view-card view-card--consumer">
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

			<article class="view-card view-card--b2b">
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

			<article class="view-card view-card--internal">
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
			<span class="section__eyebrow">Style Guide</span>
			<h2 class="section__title">Crediting the work.</h2>
			<p class="section__subtitle">The team that produced earns the byline; the channel where it appears earns the dateline.</p>
		</div>

		<div class="examples-grid">
			<div class="example-card">
				<span class="example-card__label">Digital Article Byline</span>
				<div class="example-card__demo">By [Name]<br>FLX Local Media Newsroom</div>
			</div>
			<div class="example-card">
				<span class="example-card__label">Footer / Brand Bar</span>
				<div class="example-card__demo example-card__demo--bar"><strong>FLX Local Media</strong> <span class="example-card__div">|</span> Newsroom</div>
			</div>
			<div class="example-card">
				<span class="example-card__label">On-Air Credit</span>
				<div class="example-card__demo example-card__demo--quote">"An FLX Local Media report"<br>"Heard on Finger Lakes News Radio"</div>
			</div>
			<div class="example-card">
				<span class="example-card__label">Sales Materials</span>
				<div class="example-card__demo"><strong>FLX Local Media</strong> &mdash; Broadcast<br><strong>FLX Local Media</strong> &mdash; Digital O&amp;O</div>
			</div>
		</div>
	</div>
</section>

<!-- Corporate Structure -->
<section class="section section--gray">
	<div class="container">
		<div class="section__header section__header--center">
			<span class="section__eyebrow">Corporate Structure</span>
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
