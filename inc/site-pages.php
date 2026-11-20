<?php
/**
 * Site pages: shared frame for the company, proof and plain pages.
 *
 * Every page that uses it prints the same fixed frame: a navy hero with an eyebrow, a big title and a one line lead, then white and mist
 * sections, then a closing "Get a free plan" band. Only the accent changes.
 *
 * Usage in a template:
 *
 *   flxlm_pg_begin( array( 'accent' => 'navy', 'eyebrow' => 'Company', 'title' => '...', 'lead' => '...' ) );
 *   get_header();
 *   flxlm_pg_open();     // wrapper and hero
 *   ... sections ...
 *   flxlm_pg_close();    // closing band and wrapper
 *   get_footer();
 *
 * flxlm_pg_begin() runs before get_header() so the body class and the stylesheet
 * are ready when wp_head and body_class() run.
 *
 * Contract with the frame builder: every page that calls flxlm_pg_begin() has a
 * dark hero, so it gets the body class 'flxlm-dark-hero' (the glass menu turns
 * white on dark).
 *
 * @package flxlm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The colour slot for each section accent. Values come from assets/data/flx-palette.json.
 *
 * p       the colour the hero starts from (page colour)
 * deep    the colour the hero ends on
 * accent  eyebrow and highlight colour on the dark hero
 * glow    rgb triplet for the soft light in the hero corner
 * link    text colour on white that passes 4.5:1
 * mark    graphic colour (rules, quote marks, bullets)
 * tint    pale wash for cards
 *
 * @return array
 */
function flxlm_pg_accents() {
	return array(
		// Company pages: navy primary, gold secondary.
		'navy' => array(
			'p'      => '#003b5b',
			'deep'   => '#00273e',
			'accent' => '#e3a72f',
			'glow'   => '102, 136, 161',
			'link'   => '#003b5b',
			'eyebrow' => '#855e0a',
			'mark'   => '#e3a72f',
			'tint'   => '#eff8ff',
		),
	);
}

/**
 * Settings for the page being built. Set by flxlm_pg_begin().
 *
 * @param array|null $set Settings to store, or null to read.
 * @return array
 */
function flxlm_pg_config( $set = null ) {
	static $config = array();
	if ( is_array( $set ) ) {
		$config = $set;
	}
	return $config;
}

/**
 * Start a page. Call before get_header().
 *
 * @param array $args {
 *     @type string $accent   navy. Generic pages are all navy (gold only for buttons and small highlights); any other value falls back to navy.
 *     @type string $eyebrow  Small label above the title.
 *     @type string $eyebrow_url Optional link for the eyebrow.
 *     @type string $title    Page title (plain text).
 *     @type string $title_html Title with <br> allowed; wins over title.
 *     @type string $lead     One line under the title (plain text).
 *     @type string $lead_html Lead markup, already escaped; wins over lead.
 *     @type bool   $cta      Show the gold button in the hero.
 *     @type bool   $band     Print the closing "Get a free plan" band.
 *     @type string $after    white or mist: colour of the first section, for the hero edge.
 *     @type string $hero_extra Extra markup inside the hero, already escaped.
 *     @type bool   $dark_hero Page prints its own dark hero (hero is false): still add the dark hero body class.
 *     @type bool   $no_footer_band Hide the footer's old band without printing one (the page is the call to action).
 * }
 */
function flxlm_pg_begin( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'accent'     => 'navy',
			'eyebrow'    => '',
			'eyebrow_url' => '',
			'title'      => '',
			'title_html' => '',
			'lead'       => '',
			'lead_html'  => '',
			'cta'        => true,
			'band'       => true,
			'after'      => 'white',
			'hero'       => true,
			'hero_extra' => '',
			'no_footer_band' => false,
			'dark_hero'  => false,
		)
	);

	$accents = flxlm_pg_accents();
	if ( ! isset( $accents[ $args['accent'] ] ) ) {
		$args['accent'] = 'navy';
	}

	flxlm_pg_config( $args );
}

/**
 * Whether a page started with flxlm_pg_begin().
 *
 * @return bool
 */
function flxlm_pg_active() {
	$c = flxlm_pg_config();
	return ! empty( $c );
}

/**
 * Body classes: dark hero contract for the glass menu, and band flag.
 *
 * @param array $classes Body classes.
 * @return array
 */
function flxlm_pg_body_class( $classes ) {
	if ( flxlm_pg_active() ) {
		$c         = flxlm_pg_config();
		$classes[] = 'flxlm-pg-page';
		if ( ! empty( $c['hero'] ) || ! empty( $c['dark_hero'] ) ) {
			$classes[] = 'flxlm-dark-hero';
		}
		if ( ! empty( $c['band'] ) || ! empty( $c['no_footer_band'] ) ) {
			// The page prints its own closing band, so the old one in the footer steps aside.
			$classes[] = 'flxlm-pg-band';
		}
	}
	return $classes;
}
add_filter( 'body_class', 'flxlm_pg_body_class', 20 );

/**
 * Load the stylesheet (and the heavy Poppins weights the hero uses) on these pages only.
 */
function flxlm_pg_enqueue() {
	if ( ! flxlm_pg_active() ) {
		return;
	}
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style( 'flxlm-pg-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@800;900&display=swap', array(), null );
	wp_enqueue_style(
		'flxlm-site-pages',
		$uri . '/assets/css/site-pages.css',
		array( 'flxlm-style' ),
		file_exists( $dir . '/assets/css/site-pages.css' ) ? filemtime( $dir . '/assets/css/site-pages.css' ) : FLXLM_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'flxlm_pg_enqueue', 30 );

/**
 * Inline custom properties for the wrapper.
 *
 * @param string $accent Accent key.
 * @return string
 */
function flxlm_pg_css_vars( $accent ) {
	$all = flxlm_pg_accents();
	$a   = isset( $all[ $accent ] ) ? $all[ $accent ] : $all['navy'];
	return sprintf(
		'--pg-p:%1$s;--pg-deep:%2$s;--pg-accent:%3$s;--pg-glow:%4$s;--pg-link:%5$s;--pg-eyebrow:%6$s;--pg-mark:%7$s;--pg-tint:%8$s;',
		$a['p'],
		$a['deep'],
		$a['accent'],
		$a['glow'],
		$a['link'],
		$a['eyebrow'],
		$a['mark'],
		$a['tint']
	);
}

/**
 * The gold hero button.
 *
 * @param string $label Button label.
 * @param string $note  Small line under the label.
 * @param string $url   Link.
 */
function flxlm_pg_cta_button( $label = 'Get a free plan', $note = 'A plan, not a pitch', $url = '' ) {
	if ( '' === $url ) {
		$url = home_url( '/contact/#review' );
	}
	?>
	<a class="pg-cta" href="<?php echo esc_url( $url ); ?>">
		<span class="pg-cta__tx">
			<b><?php echo esc_html( $label ); ?></b>
			<?php if ( '' !== $note ) : ?>
				<small><?php echo esc_html( $note ); ?></small>
			<?php endif; ?>
		</span>
		<span class="pg-cta__ic" aria-hidden="true">&rarr;</span>
	</a>
	<?php
}

/**
 * Open the wrapper and print the hero. Call right after get_header().
 */
function flxlm_pg_open() {
	$c = flxlm_pg_config();
	if ( empty( $c ) ) {
		return;
	}
	?>
<div class="flxlm-pg flxlm-pg--<?php echo esc_attr( $c['accent'] ); ?>" style="<?php echo esc_attr( flxlm_pg_css_vars( $c['accent'] ) ); ?>">
	<?php if ( ! empty( $c['hero'] ) ) : ?>
	<section class="pg-hero pg-hero--after-<?php echo esc_attr( $c['after'] ); ?>" aria-labelledby="pg-title">
		<div class="pg-wrap pg-hero__inner">
			<?php if ( '' !== $c['eyebrow'] ) : ?>
				<div class="pg-eyebrow">
					<?php if ( '' !== $c['eyebrow_url'] ) : ?>
						<a href="<?php echo esc_url( $c['eyebrow_url'] ); ?>"><?php echo esc_html( $c['eyebrow'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $c['eyebrow'] ); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<h1 class="pg-title" id="pg-title">
				<?php
				if ( '' !== $c['title_html'] ) {
					echo wp_kses( $c['title_html'], array( 'br' => array() ) );
				} else {
					echo esc_html( $c['title'] );
				}
				?>
			</h1>
			<?php if ( '' !== $c['lead_html'] ) : ?>
				<p class="pg-lead"><?php echo wp_kses_post( $c['lead_html'] ); ?></p>
			<?php elseif ( '' !== $c['lead'] ) : ?>
				<p class="pg-lead"><?php echo esc_html( $c['lead'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $c['hero_extra'] ) : ?>
				<div class="pg-hero__extra"><?php echo wp_kses_post( $c['hero_extra'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $c['cta'] ) ) : ?>
				<div class="pg-hero__cta"><?php flxlm_pg_cta_button(); ?></div>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>
	<?php
}

/**
 * Print the closing band and close the wrapper. Call before get_footer().
 */
function flxlm_pg_close() {
	$c = flxlm_pg_config();
	if ( empty( $c ) ) {
		return;
	}
	if ( ! empty( $c['band'] ) ) {
		?>
	<section class="pg-band" aria-labelledby="pg-band-title">
		<div class="pg-wrap pg-band__inner">
			<div class="pg-band__text">
				<div class="pg-eyebrow">Free local marketing plan</div>
				<h2 class="pg-band__title" id="pg-band-title">Tell us the one thing holding your business back.</h2>
				<p class="pg-band__sub">A short conversation with someone who knows the Finger Lakes market. We&rsquo;ll come back with a plan, not a pitch.</p>
			</div>
			<div class="pg-band__btn"><?php flxlm_pg_cta_button(); ?></div>
		</div>
	</section>
		<?php
	}
	echo "</div><!-- .flxlm-pg -->\n";
}

/**
 * The /apps/ page body carries its own <h1> in the database while the theme hero already prints the page's H1.
 * Demote the body one to an <h2> at render time so the page has a single H1 (the stored content is left alone).
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( 'apps' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	return preg_replace( array( '#<h1(\s[^>]*)?>#i', '#</h1>#i' ), array( '<h2$1>', '</h2>' ), $content );
}, 9 );

/**
 * Restyle the /apps/ body at render time: the stored content is a purple inline-styled link button.
 * Rebuild it as a centered card with the App Store badge and the FLX.fm icon. The App Store link and the
 * Android line are read from the stored content; if the stored block does not match, it is left alone.
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( 'apps' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( false === strpos( $content, 'class="flxapps"' )
		|| ! preg_match( '#<a class="cta" href="([^"]+)"#', $content, $href )
		|| ! preg_match( '#<h2[^>]*>(.*?)</h2>#is', $content, $head )
		|| ! preg_match( '#<p class="sub">(.*?)</p>#is', $content, $sub )
		|| ! preg_match( '#<p class="note">(.*?)</p>#is', $content, $note ) ) {
		return $content;
	}
	$uri = get_template_directory_uri();
	return '<div class="fa-card">'
		. '<img class="fa-card__icon" src="' . esc_url( $uri . '/assets/images/products/flx-fm/mark.webp' ) . '" alt="FLX.fm app icon" width="96" height="96">'
		. '<h2>' . $head[1] . '</h2>'
		. '<p class="fa-card__sub">' . $sub[1] . '</p>'
		. '<a class="fa-badge" href="' . esc_url( $href[1] ) . '"><img src="' . esc_url( $uri . '/assets/images/badge-app-store.svg' ) . '" alt="Download FLX.fm on the App Store, free for iPhone and iPad" width="180" height="60"></a>'
		. '<p class="fa-card__note">' . $note[1] . '</p>'
		. '</div>';
}, 10 );
